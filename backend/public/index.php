<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/validation.php';

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Yangon');

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptDir = PHP_SAPI === 'cli-server' ? '' : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($scriptDir && str_starts_with($uri, $scriptDir)) {
    $uri = substr($uri, strlen($scriptDir));
}
$path = trim(preg_replace('#^/api/?#', '', $uri), '/');
$parts = $path === '' ? [] : explode('/', $path);

function select_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function select_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function execute_sql(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function record_exists(string $table, int $id): bool
{
    $allowed = ['projects','payments','expenses','recurring_fees','invoices','receipts','users','financial_accounts','financial_transactions','domain_billing_periods'];
    if (!in_array($table, $allowed, true)) return false;
    return select_one("SELECT id FROM {$table} WHERE id = ?", [$id]) !== null;
}

function financial_accounts(): array
{
    return select_all('SELECT a.*, ROUND(a.opening_balance + COALESCE(t.money_in,0) - COALESCE(t.money_out,0),2) balance, COALESCE(t.received,0) total_received, COALESCE(t.used_amount,0) total_used, COALESCE(t.transfer_in,0) transfer_in, COALESCE(t.transfer_out,0) transfer_out FROM financial_accounts a LEFT JOIN (SELECT account_id, SUM(money_in) money_in, SUM(money_out) money_out, SUM(received) received, SUM(used_amount) used_amount, SUM(transfer_in) transfer_in, SUM(transfer_out) transfer_out FROM (SELECT to_account_id account_id, amount money_in, 0 money_out, IF(transaction_type="Receive",amount,0) received, 0 used_amount, IF(transaction_type="Transfer",amount,0) transfer_in, 0 transfer_out FROM financial_transactions WHERE to_account_id IS NOT NULL UNION ALL SELECT from_account_id, 0, amount, 0, IF(transaction_type="Use",amount,0), 0, IF(transaction_type="Transfer",amount,0) FROM financial_transactions WHERE from_account_id IS NOT NULL) movements GROUP BY account_id) t ON t.account_id=a.id ORDER BY a.id');
}

function audit_log(?int $userId, string $action, string $module, ?int $recordId, string $description): void
{
    execute_sql('INSERT INTO activity_logs (user_id, action, module, record_id, description) VALUES (?, ?, ?, ?, ?)', [$userId, $action, $module, $recordId, $description]);
}

function paginate_rows(array $rows): array
{
    if (!isset($_GET['page']) && !isset($_GET['limit'])) return $rows;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
    $total = count($rows);
    return [
        'rows' => array_values(array_slice($rows, ($page - 1) * $limit, $limit)),
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => max(1, (int)ceil($total / $limit))],
    ];
}

function paginated_select(string $baseSql, array $where = [], array $params = [], string $orderBy = 'id DESC'): array
{
    if (!preg_match('/^[a-zA-Z0-9_., ]+$/', $orderBy)) {
        throw new InvalidArgumentException('Invalid list ordering');
    }
    $filterSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $wrapped = "({$baseSql}) list_rows";
    if (!isset($_GET['page']) && !isset($_GET['limit'])) {
        return select_all("SELECT * FROM {$wrapped}{$filterSql} ORDER BY {$orderBy}", $params);
    }
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
    $total = (int)select_one("SELECT COUNT(*) total FROM {$wrapped}{$filterSql}", $params)['total'];
    $pages = max(1, (int)ceil($total / $limit));
    $page = min($page, $pages);
    $offset = ($page - 1) * $limit;
    return [
        'rows' => select_all("SELECT * FROM {$wrapped}{$filterSql} ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}", $params),
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => $pages],
    ];
}

function query_list_filters(array $filterMap, array $searchColumns = [], ?string $dateColumn = null): array
{
    $where = [];
    $params = [];
    foreach ($filterMap as $query => $column) {
        if (($_GET[$query] ?? '') !== '') {
            $where[] = "list_rows.{$column} = ?";
            $params[] = $_GET[$query];
        }
    }
    if (trim((string)($_GET['search'] ?? '')) !== '' && $searchColumns) {
        $parts = array_map(fn($column) => "COALESCE(list_rows.{$column}, '')", $searchColumns);
        $where[] = 'CONCAT_WS(" ", ' . implode(', ', $parts) . ') LIKE ?';
        $params[] = '%' . trim((string)$_GET['search']) . '%';
    }
    if ($dateColumn && ($_GET['date_from'] ?? '') !== '') {
        $where[] = "list_rows.{$dateColumn} >= ?";
        $params[] = $_GET['date_from'];
    }
    if ($dateColumn && ($_GET['date_to'] ?? '') !== '') {
        $where[] = "list_rows.{$dateColumn} <= ?";
        $params[] = $_GET['date_to'];
    }
    return [$where, $params];
}

function setting_value(string $key, string $fallback = ''): string
{
    $row = select_one('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);
    return (string)($row['setting_value'] ?? $fallback);
}

function money(mixed $value): float
{
    return round((float)($value ?? 0), 2);
}

function project_select_sql(): string
{
    return "SELECT p.*, COALESCE(pay.total_paid,0) total_paid, COALESCE(pay.total_received,0) total_received,
        COALESCE(pay.domain_paid,0) domain_paid, COALESCE(dom.domain_customer_price,0) domain_customer_price,
        COALESCE(exp.total_expenses,0) total_expenses,
        (p.contract_amount - p.discount_amount + p.tax_amount) total_payable
        FROM projects p
        LEFT JOIN (SELECT project_id, SUM(CASE WHEN payment_scope = 'Project' THEN amount ELSE 0 END) total_paid, SUM(amount) total_received, SUM(CASE WHEN payment_scope = 'Domain' THEN amount ELSE 0 END) domain_paid FROM payments GROUP BY project_id) pay ON pay.project_id = p.id
        LEFT JOIN (SELECT project_id, SUM(customer_price) domain_customer_price FROM domain_billing_periods WHERE purchase_status <> 'Cancelled' GROUP BY project_id) dom ON dom.project_id = p.id
        LEFT JOIN (SELECT project_id, SUM(amount) total_expenses FROM expenses GROUP BY project_id) exp ON exp.project_id = p.id";
}

function project_finance_sql(): string
{
    return 'SELECT q.*, ROUND(q.total_payable - q.total_paid, 2) remaining_balance,
        ROUND(q.total_received - q.total_expenses, 2) profit,
        CASE
            WHEN q.total_payable - q.total_paid > 0 AND q.payment_due_date IS NOT NULL AND q.payment_due_date < CURDATE() THEN "Overdue"
            WHEN q.total_payable > 0 AND q.total_paid >= q.total_payable THEN "Fully Paid"
            WHEN q.total_paid > 0 THEN "Partially Paid"
            ELSE "Unpaid"
        END payment_status
        FROM (' . project_select_sql() . ') q';
}

function with_project_finance(array $row): array
{
    $payable = money($row['total_payable']);
    $paid = money($row['total_paid']);
    $remaining = money($payable - $paid);
    $paymentStatus = 'Unpaid';
    if ($remaining > 0 && !empty($row['payment_due_date']) && $row['payment_due_date'] < date('Y-m-d')) {
        $paymentStatus = 'Overdue';
    } elseif ($payable > 0 && $paid >= $payable) {
        $paymentStatus = 'Fully Paid';
    } elseif ($paid > 0) {
        $paymentStatus = 'Partially Paid';
    }
    $row['total_payable'] = $payable;
    $row['total_paid'] = $paid;
    $row['total_received'] = money($row['total_received'] ?? $paid);
    $row['domain_paid'] = money($row['domain_paid'] ?? 0);
    $row['domain_customer_price'] = money($row['domain_customer_price'] ?? 0);
    $row['remaining_balance'] = $remaining;
    $row['payment_status'] = $paymentStatus;
    $row['profit'] = money($row['total_received'] - $row['total_expenses']);
    return $row;
}

function project_summary(int $projectId): array
{
    $project = select_one('SELECT * FROM projects WHERE id = ?', [$projectId]);
    if (!$project) {
        json_response(false, 'Project not found', null, [], 404);
    }
    return with_project_finance(select_one(project_select_sql() . ' WHERE p.id = ?', [$projectId])) + [
        'payment_percentage' => 0,
        'expected_profit' => 0,
    ];
}

function summary_only(int $projectId): array
{
    $summary = project_summary($projectId);
    $summary['payment_percentage'] = $summary['total_payable'] > 0 ? round(($summary['total_paid'] / $summary['total_payable']) * 100, 2) : 0;
    $summary['expected_profit'] = money($summary['total_payable'] + $summary['domain_customer_price'] - $summary['total_expenses']);
    return [
        'contract_amount' => money($summary['contract_amount']),
        'discount_amount' => money($summary['discount_amount']),
        'tax_amount' => money($summary['tax_amount']),
        'total_payable' => $summary['total_payable'],
        'total_paid' => $summary['total_paid'],
        'total_received' => $summary['total_received'],
        'domain_customer_price' => $summary['domain_customer_price'],
        'domain_paid' => $summary['domain_paid'],
        'remaining_balance' => $summary['remaining_balance'],
        'payment_percentage' => $summary['payment_percentage'],
        'payment_status' => $summary['payment_status'],
        'total_expenses' => money($summary['total_expenses']),
        'profit' => $summary['profit'],
        'expected_profit' => $summary['expected_profit'],
    ];
}

function insert_or_update(string $table, array $data, array $fields, ?int $id = null): int
{
    $payload = [];
    foreach ($fields as $field) {
        if (array_key_exists($field, $data)) {
            $payload[$field] = $data[$field] === '' ? null : $data[$field];
        }
    }
    if (!$payload) {
        return $id ?? 0;
    }
    if ($id) {
        $sets = implode(', ', array_map(fn($field) => "{$field} = ?", array_keys($payload)));
        execute_sql("UPDATE {$table} SET {$sets} WHERE id = ?", [...array_values($payload), $id]);
        return $id;
    }
    $cols = implode(', ', array_keys($payload));
    $marks = implode(', ', array_fill(0, count($payload), '?'));
    execute_sql("INSERT INTO {$table} ({$cols}) VALUES ({$marks})", array_values($payload));
    return (int)db()->lastInsertId();
}

function recurring_status(array $fee): string
{
    if (in_array($fee['status'], ['Paid', 'Cancelled'], true)) {
        return $fee['status'];
    }
    $today = new DateTimeImmutable(date('Y-m-d'));
    $due = new DateTimeImmutable($fee['next_due_date']);
    if ($due < $today) return 'Overdue';
    if ($due == $today) return 'Due Today';
    return $due <= $today->modify('+' . (int)$fee['reminder_days_before_due'] . ' days') ? 'Due Soon' : 'Not Due';
}

function next_due_date(string $current, string $cycle): string
{
    $date = new DateTimeImmutable($current);
    $months = match ($cycle) { 'Monthly' => 1, 'Quarterly' => 3, 'Half Yearly' => 6, 'Yearly' => 12, default => 0 };
    if ($months === 0) return $date->format('Y-m-d');
    $targetMonth = $date->modify('first day of this month')->modify("+{$months} months");
    $day = min((int)$date->format('d'), (int)$targetMonth->format('t'));
    return $targetMonth->setDate((int)$targetMonth->format('Y'), (int)$targetMonth->format('m'), $day)->format('Y-m-d');
}

function domain_billing_select_sql(): string
{
    return 'SELECT d.*, p.project_code, p.project_name, p.customer_company_name, p.currency,
        fa.name paid_from_account_name,
        COALESCE(pay.customer_paid_amount, 0) customer_paid_amount,
        ROUND(d.customer_price - COALESCE(pay.customer_paid_amount, 0), 2) customer_balance_amount,
        ROUND(COALESCE(pay.customer_paid_amount, 0) - d.actual_registrar_cost, 2) realized_domain_profit,
        ROUND(d.customer_price - d.actual_registrar_cost, 2) expected_domain_profit,
        CASE
            WHEN d.customer_price <= 0 THEN "Not Priced"
            WHEN COALESCE(pay.customer_paid_amount, 0) >= d.customer_price THEN "Paid"
            WHEN COALESCE(pay.customer_paid_amount, 0) > 0 THEN "Partially Paid"
            ELSE "Unpaid"
        END customer_payment_status,
        CASE
            WHEN d.purchase_status = "Cancelled" THEN "Cancelled"
            WHEN d.purchase_status = "Quoted" OR d.purchase_date IS NULL THEN "Not Purchased"
            WHEN d.coverage_end_date < CURDATE() THEN "Expired"
            ELSE "Active"
        END effective_purchase_status,
        exp.id linked_expense_id, ft.id linked_transaction_id
        FROM domain_billing_periods d
        JOIN projects p ON p.id = d.project_id
        LEFT JOIN financial_accounts fa ON fa.id = d.paid_from_account_id
        LEFT JOIN (SELECT domain_billing_period_id, SUM(amount) customer_paid_amount FROM payments WHERE payment_scope = "Domain" AND domain_billing_period_id IS NOT NULL GROUP BY domain_billing_period_id) pay ON pay.domain_billing_period_id = d.id
        LEFT JOIN expenses exp ON exp.domain_billing_period_id = d.id
        LEFT JOIN financial_transactions ft ON ft.domain_billing_period_id = d.id';
}

function domain_billing_detail(int $id): array
{
    $row = select_one(domain_billing_select_sql() . ' WHERE d.id = ?', [$id]);
    if (!$row) json_response(false, 'Domain billing period not found', null, [], 404);
    $row['payments'] = select_all('SELECT pay.*, fa.name financial_account_name, u.name recorded_by_name FROM payments pay LEFT JOIN financial_accounts fa ON fa.id=pay.financial_account_id LEFT JOIN users u ON u.id=pay.received_by WHERE pay.domain_billing_period_id=? ORDER BY pay.payment_date DESC, pay.id DESC', [$id]);
    $row['purchase_expense'] = select_one('SELECT * FROM expenses WHERE domain_billing_period_id=?', [$id]);
    $row['purchase_transaction'] = select_one('SELECT ft.*, fa.name from_account_name FROM financial_transactions ft LEFT JOIN financial_accounts fa ON fa.id=ft.from_account_id WHERE ft.domain_billing_period_id=?', [$id]);
    return $row;
}

function recurring_fee_list_sql(): string
{
    $manual = 'SELECT r.id, r.project_id, r.fee_name, r.fee_type, r.amount, r.billing_cycle,
        r.last_paid_date, r.next_due_date, r.reminder_days_before_due, r.status,
        r.auto_create_reminder, r.notes, r.source_type, r.source_key, r.created_at, r.updated_at,
        r.id source_id, 0 is_read_only, p.project_code, p.project_name, p.customer_company_name,
        CASE
            WHEN r.status IN ("Paid","Cancelled") THEN r.status
            WHEN r.next_due_date < CURDATE() THEN "Overdue"
            WHEN r.next_due_date = CURDATE() THEN "Due Today"
            WHEN r.next_due_date <= DATE_ADD(CURDATE(), INTERVAL r.reminder_days_before_due DAY) THEN "Due Soon"
            ELSE "Not Due"
        END effective_status
        FROM recurring_fees r JOIN projects p ON p.id=r.project_id';
    $domains = 'SELECT -domain_rows.source_id id, domain_rows.project_id, domain_rows.fee_name, "Server" fee_type,
        domain_rows.amount, "Yearly" billing_cycle, NULL last_paid_date, domain_rows.next_due_date,
        domain_rows.reminder_days_before_due, domain_rows.effective_status status, 1 auto_create_reminder,
        domain_rows.notes, "Domain Billing" source_type, CONCAT("domain:", domain_rows.source_id) source_key,
        domain_rows.created_at, domain_rows.updated_at, domain_rows.source_id, 1 is_read_only,
        domain_rows.project_code, domain_rows.project_name, domain_rows.customer_company_name,
        domain_rows.effective_status
        FROM (
            SELECT d.id source_id, d.project_id,
                CONCAT(COALESCE(NULLIF(d.domain_name,""), NULLIF(d.period_label,""), "Domain"), " server fee") fee_name,
                d.customer_price amount,
                CASE WHEN d.customer_balance_amount > 0
                    THEN COALESCE(d.customer_due_date, d.customer_renewal_date, d.quote_date)
                    ELSE COALESCE(d.customer_renewal_date, d.customer_due_date, d.quote_date)
                END next_due_date,
                d.reminder_days_before_due, d.notes, d.created_at, d.updated_at,
                d.project_code, d.project_name, d.customer_company_name,
                CASE
                    WHEN d.purchase_status="Cancelled" THEN "Cancelled"
                    WHEN d.customer_payment_status="Paid" AND EXISTS (
                        SELECT 1 FROM domain_billing_periods next_period
                        WHERE next_period.id<>d.id AND next_period.project_id=d.project_id
                          AND COALESCE(next_period.domain_name,"")=COALESCE(d.domain_name,"")
                          AND next_period.quote_date>=d.customer_renewal_date
                    ) THEN "Paid"
                    WHEN (CASE WHEN d.customer_balance_amount > 0 THEN COALESCE(d.customer_due_date,d.customer_renewal_date,d.quote_date) ELSE COALESCE(d.customer_renewal_date,d.customer_due_date,d.quote_date) END) < CURDATE() THEN "Overdue"
                    WHEN (CASE WHEN d.customer_balance_amount > 0 THEN COALESCE(d.customer_due_date,d.customer_renewal_date,d.quote_date) ELSE COALESCE(d.customer_renewal_date,d.customer_due_date,d.quote_date) END) = CURDATE() THEN "Due Today"
                    WHEN (CASE WHEN d.customer_balance_amount > 0 THEN COALESCE(d.customer_due_date,d.customer_renewal_date,d.quote_date) ELSE COALESCE(d.customer_renewal_date,d.customer_due_date,d.quote_date) END) <= DATE_ADD(CURDATE(), INTERVAL d.reminder_days_before_due DAY) THEN "Due Soon"
                    ELSE "Not Due"
                END effective_status
            FROM (' . domain_billing_select_sql() . ') d
            WHERE d.customer_price > 0
        ) domain_rows';
    return $manual . ' UNION ALL ' . $domains;
}

function validate_domain_billing(array $data, ?array $existing = null, bool $validateProject = true): array
{
    $requiredFields = ['quote_date','customer_price','purchase_status'];
    if ($validateProject) array_unshift($requiredFields, 'project_id');
    $errors = required($data, $requiredFields);
    if ($validateProject && !empty($data['project_id']) && !record_exists('projects', (int)$data['project_id'])) $errors['project_id'] = 'Choose an existing project.';
    valid_decimal($errors, $data, 'customer_price', true);
    valid_date($errors, $data, 'quote_date');
    valid_date($errors, $data, 'customer_due_date');
    one_of($errors, $data, 'purchase_status', ['Quoted','Cancelled']);
    max_length($errors, $data, 'domain_name', 255);
    max_length($errors, $data, 'period_label', 50);
    if (isset($data['reminder_days_before_due']) && (filter_var($data['reminder_days_before_due'], FILTER_VALIDATE_INT) === false || (int)$data['reminder_days_before_due'] < 0 || (int)$data['reminder_days_before_due'] > 365)) $errors['reminder_days_before_due'] = 'Enter a whole number from 0 to 365.';
    if (!empty($data['customer_due_date']) && !empty($data['quote_date']) && $data['customer_due_date'] < $data['quote_date']) $errors['customer_due_date'] = 'Due date cannot be earlier than the quote date.';
    if ($existing) {
        $paid = money(select_one('SELECT COALESCE(SUM(amount),0) total FROM payments WHERE domain_billing_period_id=?', [(int)$existing['id']])['total']);
        if (!isset($errors['customer_price']) && (float)($data['customer_price'] ?? 0) < $paid) $errors['customer_price'] = 'Customer price cannot be less than the amount already paid.';
        $hasHistory = $paid > 0 || !empty($existing['purchase_date']);
        if ($hasHistory && (int)($data['project_id'] ?? 0) !== (int)$existing['project_id']) $errors['project_id'] = 'A domain period with financial history cannot be moved to another project.';
    }
    return $errors;
}

function initial_server_billing_payload(array $data, int $projectId = 0, int $userId = 0): ?array
{
    if (!in_array($data['initial_server_billing_enabled'] ?? 0, [1,'1',true], true)) return null;
    return [
        'project_id' => $projectId,
        'domain_name' => trim((string)($data['initial_server_domain_name'] ?? '')) ?: null,
        'period_label' => trim((string)($data['initial_server_period_label'] ?? '')) ?: 'First Year',
        'quote_date' => $data['initial_server_quote_date'] ?? '',
        'customer_price' => $data['initial_server_customer_price'] ?? '',
        'customer_due_date' => !empty($data['initial_server_customer_due_date']) ? $data['initial_server_customer_due_date'] : null,
        'purchase_status' => 'Quoted',
        'reminder_days_before_due' => $data['initial_server_reminder_days'] ?? 30,
        'notes' => trim((string)($data['initial_server_notes'] ?? '')) ?: null,
        'created_by' => $userId,
    ];
}

function validate_initial_server_billing(array $data): array
{
    $enabled = $data['initial_server_billing_enabled'] ?? 0;
    if (!in_array($enabled, [0,'0',false,1,'1',true], true)) return ['initial_server_billing_enabled' => 'Choose whether to add initial server billing.'];
    $billing = initial_server_billing_payload($data);
    if (!$billing) return [];
    $errors = validate_domain_billing($billing, null, false);
    $fieldMap = [
        'domain_name' => 'initial_server_domain_name',
        'period_label' => 'initial_server_period_label',
        'quote_date' => 'initial_server_quote_date',
        'customer_price' => 'initial_server_customer_price',
        'customer_due_date' => 'initial_server_customer_due_date',
        'reminder_days_before_due' => 'initial_server_reminder_days',
    ];
    $mapped = [];
    foreach ($errors as $field => $message) $mapped[$fieldMap[$field] ?? $field] = $message;
    return $mapped;
}

function sync_payment_receive(int $paymentId, array $payment, int $userId, string $notes = 'Project payment'): void
{
    if (!empty($payment['is_historical'])) {
        execute_sql('DELETE FROM financial_transactions WHERE project_payment_id = ?', [$paymentId]);
        return;
    }
    select_one('SELECT id FROM financial_accounts WHERE id = ? FOR UPDATE', [(int)$payment['financial_account_id']]);
    execute_sql('INSERT INTO financial_transactions (transaction_date, transaction_type, to_account_id, amount, notes, project_payment_id, created_by) VALUES (?, "Receive", ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE transaction_date=VALUES(transaction_date), to_account_id=VALUES(to_account_id), amount=VALUES(amount), notes=VALUES(notes), updated_at=CURRENT_TIMESTAMP', [$payment['payment_date'], $payment['financial_account_id'], $payment['amount'], $notes, $paymentId, $userId]);
}

function normalize_project_dates(array $data): array
{
    if (!empty($data['domain_purchase_date']) && empty($data['domain_reminder_date'])) {
        $purchase = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$data['domain_purchase_date']);
        if ($purchase && $purchase->format('Y-m-d') === $data['domain_purchase_date']) {
            $data['domain_reminder_date'] = next_due_date($data['domain_purchase_date'], 'Yearly');
        }
    }
    return $data;
}

function next_document_number(string $sequenceTable, string $prefixKey, string $fallbackPrefix, bool $reserve = false, ?int $sequenceYear = null): string
{
    if (!in_array($sequenceTable, ['invoice_sequences', 'receipt_sequences'], true)) {
        throw new InvalidArgumentException('Invalid sequence table');
    }
    $year = $sequenceYear ?: (int)date('Y');
    if ($year < 2000 || $year > 9999) throw new InvalidArgumentException('Invalid document year');
    $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', setting_value($prefixKey, $fallbackPrefix))) ?: $fallbackPrefix;
    $pdo = db();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        execute_sql("INSERT IGNORE INTO {$sequenceTable} (sequence_year, current_value) VALUES (?, 0)", [$year]);
        $row = select_one("SELECT current_value FROM {$sequenceTable} WHERE sequence_year = ? FOR UPDATE", [$year]);
        $number = (int)($row['current_value'] ?? 0) + 1;
        if ($reserve) {
            execute_sql("UPDATE {$sequenceTable} SET current_value = ? WHERE sequence_year = ?", [$number, $year]);
        }
        if ($ownsTransaction) $pdo->commit();
        return sprintf('%s-%d-%04d', $prefix, $year, $number);
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function next_invoice_number(bool $reserve = false, ?string $invoiceDate = null): string
{
    $year = $invoiceDate ? (int)substr($invoiceDate, 0, 4) : (int)date('Y');
    return next_document_number('invoice_sequences', 'invoice_prefix', 'INV', $reserve, $year);
}

function next_receipt_number(bool $reserve = false): string
{
    return next_document_number('receipt_sequences', 'receipt_prefix', 'REC', $reserve);
}

function report_period(string $period): array
{
    $today = new DateTimeImmutable(date('Y-m-d'));
    return match ($period) {
        'today' => [$today->format('Y-m-d'), $today->format('Y-m-d')],
        'week' => [$today->modify('monday this week')->format('Y-m-d'), $today->modify('sunday this week')->format('Y-m-d')],
        'month' => [$today->format('Y-m-01'), $today->format('Y-m-t')],
        default => [null, null],
    };
}

function financial_overview(): array
{
    $period = in_array($_GET['period'] ?? '', ['today','week','month','lifetime'], true) ? $_GET['period'] : 'month';
    [$from, $to] = report_period($period);
    $projectId = !empty($_GET['project_id']) ? (int)$_GET['project_id'] : null;
    $feeType = trim((string)($_GET['fee_type'] ?? ''));
    $rowLimit = min(1000, max(25, (int)($_GET['detail_limit'] ?? 200)));

    $paymentWhere = [];
    $paymentParams = [];
    if ($from) {
        $paymentWhere[] = 'pay.payment_date BETWEEN ? AND ?';
        array_push($paymentParams, $from, $to);
    }
    if ($projectId) {
        $paymentWhere[] = 'pay.project_id = ?';
        $paymentParams[] = $projectId;
    }
    if ($feeType !== '') {
        if ($feeType === 'Server') {
            $paymentWhere[] = 'pay.payment_type IN ("Domain","Hosting","Server")';
        } else {
            $paymentWhere[] = '1=0';
        }
    }
    $paymentFilterSql = $paymentWhere ? ' WHERE ' . implode(' AND ', $paymentWhere) : '';
    $paymentStats = select_one('SELECT COUNT(*) total, COALESCE(SUM(pay.amount),0) amount FROM payments pay' . $paymentFilterSql, $paymentParams);
    $payments = select_all('SELECT pay.*, p.project_code, p.project_name, p.customer_company_name, fa.name financial_account_name FROM payments pay JOIN projects p ON p.id = pay.project_id LEFT JOIN financial_accounts fa ON fa.id = pay.financial_account_id' . $paymentFilterSql . " ORDER BY pay.payment_date DESC, pay.id DESC LIMIT {$rowLimit}", $paymentParams);

    $outstandingWhere = ['report_projects.remaining_balance > 0'];
    $outstandingParams = [];
    if ($projectId) { $outstandingWhere[] = 'report_projects.id = ?'; $outstandingParams[] = $projectId; }
    if ($from) {
        if ($period === 'today') { $outstandingWhere[] = 'report_projects.payment_due_date <= ?'; $outstandingParams[] = $to; }
        else { $outstandingWhere[] = 'report_projects.payment_due_date BETWEEN ? AND ?'; array_push($outstandingParams, $from, $to); }
    }
    if ($feeType !== '') $outstandingWhere[] = '1=0';
    $outstandingFilterSql = ' WHERE ' . implode(' AND ', $outstandingWhere);
    $projectReportBase = '(' . project_finance_sql() . ') report_projects';
    $outstandingStats = select_one('SELECT COUNT(*) total, COALESCE(SUM(remaining_balance),0) amount FROM ' . $projectReportBase . $outstandingFilterSql, $outstandingParams);
    $outstanding = select_all('SELECT * FROM ' . $projectReportBase . $outstandingFilterSql . " ORDER BY payment_due_date, id LIMIT {$rowLimit}", $outstandingParams);

    $feeWhere = ['r.status NOT IN ("Paid", "Cancelled")'];
    $feeParams = [];
    if ($from) {
        $feeWhere[] = $period === 'today' ? 'r.next_due_date <= ?' : 'r.next_due_date BETWEEN ? AND ?';
        $feeParams[] = $period === 'today' ? $to : $from;
        if ($period !== 'today') $feeParams[] = $to;
    }
    if ($projectId) {
        $feeWhere[] = 'r.project_id = ?';
        $feeParams[] = $projectId;
    }
    if ($feeType !== '') {
        $feeWhere[] = 'r.fee_type = ?';
        $feeParams[] = $feeType;
    }
    $feeFilterSql = ' WHERE ' . implode(' AND ', $feeWhere);
    $feeStats = select_one('SELECT COUNT(*) total, COALESCE(SUM(r.amount),0) amount FROM recurring_fees r' . $feeFilterSql, $feeParams);
    $fees = select_all('SELECT r.*, p.project_code, p.project_name, p.customer_company_name FROM recurring_fees r JOIN projects p ON p.id = r.project_id' . $feeFilterSql . " ORDER BY r.next_due_date, r.id LIMIT {$rowLimit}", $feeParams);

    $domainWhere = ['domain_rows.purchase_status<>"Cancelled"', 'domain_rows.customer_balance_amount>0'];
    $domainParams = [];
    if ($projectId) { $domainWhere[] = 'domain_rows.project_id=?'; $domainParams[] = $projectId; }
    if ($from) {
        if ($period === 'today') { $domainWhere[] = 'domain_rows.customer_due_date<=?'; $domainParams[] = $to; }
        else { $domainWhere[] = 'domain_rows.customer_due_date BETWEEN ? AND ?'; array_push($domainParams,$from,$to); }
    }
    if ($feeType !== '' && $feeType !== 'Server') $domainWhere[] = '1=0';
    $domainFilterSql = ' WHERE ' . implode(' AND ', $domainWhere);
    $domainBase = '(' . domain_billing_select_sql() . ') domain_rows';
    $domainStats = select_one('SELECT COUNT(*) total, COALESCE(SUM(customer_balance_amount),0) outstanding, COALESCE(SUM(customer_price),0) customer_price, COALESCE(SUM(actual_registrar_cost),0) registrar_cost FROM ' . $domainBase . $domainFilterSql, $domainParams);
    $domainBillings = select_all('SELECT * FROM ' . $domainBase . $domainFilterSql . " ORDER BY customer_due_date, id LIMIT {$rowLimit}", $domainParams);

    $domainServerPrice = 0;
    $domainRegistrarCost = 0;
    if ($feeType === '' || $feeType === 'Server') {
        $quoteWhere = ['purchase_status<>"Cancelled"'];
        $quoteParams = [];
        $costWhere = ['purchase_status="Purchased"'];
        $costParams = [];
        if ($projectId) {
            $quoteWhere[] = 'project_id=?';
            $quoteParams[] = $projectId;
            $costWhere[] = 'project_id=?';
            $costParams[] = $projectId;
        }
        if ($from) {
            $quoteWhere[] = 'quote_date BETWEEN ? AND ?';
            array_push($quoteParams, $from, $to);
            $costWhere[] = 'purchase_date BETWEEN ? AND ?';
            array_push($costParams, $from, $to);
        }
        $domainServerPrice = money(select_one('SELECT COALESCE(SUM(customer_price),0) total FROM domain_billing_periods WHERE ' . implode(' AND ', $quoteWhere), $quoteParams)['total']);
        $domainRegistrarCost = money(select_one('SELECT COALESCE(SUM(actual_registrar_cost),0) total FROM domain_billing_periods WHERE ' . implode(' AND ', $costWhere), $costParams)['total']);
    }
    if ($feeType === '' || in_array($feeType, ['Domain', 'Server'], true)) {
        $legacyWhere = ['NOT EXISTS (SELECT 1 FROM domain_billing_periods d WHERE d.project_id=p.id)'];
        $legacyParams = [];
        if ($projectId) { $legacyWhere[] = 'p.id=?'; $legacyParams[] = $projectId; }
        if ($from) {
            $legacyWhere[] = 'COALESCE(p.domain_payment_date,p.domain_purchase_date,DATE(p.created_at)) BETWEEN ? AND ?';
            array_push($legacyParams, $from, $to);
        }
        $legacyDomainRow = select_one('SELECT COALESCE(SUM(p.domain_server_price),0) total FROM projects p WHERE ' . implode(' AND ', $legacyWhere), $legacyParams);
        $domainServerPrice = money($domainServerPrice + (float)$legacyDomainRow['total']);
    }
    return [
        'period' => $period,
        'date_from' => $from,
        'date_to' => $to,
        'summary' => [
            'received_amount' => money($paymentStats['amount']),
            'project_outstanding_amount' => money($outstandingStats['amount']),
            'domain_outstanding_amount' => money($domainStats['outstanding']),
            'recurring_due_amount' => money($feeStats['amount']),
            'total_to_collect' => money((float)$outstandingStats['amount'] + (float)$domainStats['outstanding'] + (float)$feeStats['amount']),
            'domain_server_price_total' => $domainServerPrice,
            'domain_registrar_cost_total' => $domainRegistrarCost,
        ],
        'payments' => $payments,
        'outstanding_projects' => $outstanding,
        'recurring_fees' => array_map(fn($row) => array_merge($row, ['status' => recurring_status($row)]), $fees),
        'domain_billings' => $domainBillings,
        'detail_counts' => ['payments' => (int)$paymentStats['total'], 'outstanding_projects' => (int)$outstandingStats['total'], 'recurring_fees' => (int)$feeStats['total'], 'domain_billings' => (int)$domainStats['total']],
        'detail_limit' => $rowLimit,
    ];
}

function project_list(): array
{
    if (($_GET['compact'] ?? '') === '1') {
        $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
        $search = trim((string)($_GET['search'] ?? ''));
        $selectedId = max(0, (int)($_GET['selected_id'] ?? 0));
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(p.project_code LIKE ? OR p.project_name LIKE ? OR p.customer_company_name LIKE ? OR p.contact_person LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term, $term);
        }
        if ($selectedId > 0) {
            $selectedSql = 'p.id = ?';
            $params[] = $selectedId;
            $where = $where ? ['(' . implode(' AND ', $where) . ' OR ' . $selectedSql . ')'] : [$selectedSql];
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        return select_all('SELECT p.id, p.project_code, p.project_name, p.project_type, p.customer_company_name,
            p.contact_person, p.contact_phone, p.currency, p.notes,
            ROUND(p.contract_amount-p.discount_amount+p.tax_amount,2) total_payable,
            ROUND(p.contract_amount-p.discount_amount+p.tax_amount-COALESCE((SELECT SUM(pay.amount) FROM payments pay WHERE pay.project_id=p.id AND pay.payment_scope="Project"),0),2) remaining_balance
            FROM projects p' .
            $whereSql . " ORDER BY p.project_name, p.id LIMIT {$limit}", $params);
    }
    $where = [];
    $params = [];
    if (!empty($_GET['search'])) {
        $where[] = '(list_rows.project_name LIKE ? OR list_rows.customer_company_name LIKE ? OR list_rows.project_code LIKE ?)';
        $term = '%' . $_GET['search'] . '%';
        array_push($params, $term, $term, $term);
    }
    if (!empty($_GET['status'])) {
        $where[] = 'list_rows.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['project_id'])) {
        $where[] = 'list_rows.id = ?';
        $params[] = (int)$_GET['project_id'];
    }
    if (!empty($_GET['date_from'])) {
        $where[] = 'list_rows.created_at >= ?';
        $params[] = $_GET['date_from'] . ' 00:00:00';
    }
    if (!empty($_GET['date_to'])) {
        $where[] = 'list_rows.created_at <= ?';
        $params[] = $_GET['date_to'] . ' 23:59:59';
    }
    if (!empty($_GET['payment_status'])) {
        $where[] = 'list_rows.payment_status = ?';
        $params[] = $_GET['payment_status'];
    }
    if (!empty($_GET['outstanding_only'])) $where[] = 'list_rows.remaining_balance > 0';
    $result = paginated_select(project_finance_sql(), $where, $params, 'created_at DESC, id DESC');
    if (isset($result['rows'])) $result['rows'] = array_map('with_project_finance', $result['rows']);
    else $result = array_map('with_project_finance', $result);
    return $result;
}

function filter_by_query(array $rows, array $map): array
{
    if (!empty($_GET['search'])) {
        $needle = mb_strtolower(trim((string)$_GET['search']));
        $rows = array_values(array_filter($rows, function ($row) use ($needle) {
            $haystack = mb_strtolower(implode(' ', array_map('strval', array_filter($row, fn($value) => is_scalar($value)))));
            return str_contains($haystack, $needle);
        }));
    }
    foreach ($map as $query => $field) {
        if (!empty($_GET[$query])) {
            $rows = array_values(array_filter($rows, fn($row) => (string)($row[$field] ?? '') === (string)$_GET[$query]));
        }
    }
    if (!empty($_GET['date_from'])) {
        $rows = array_values(array_filter($rows, fn($row) => ($row['payment_date'] ?? $row['expense_date'] ?? $row['invoice_date'] ?? $row['receipt_date'] ?? $row['next_due_date'] ?? '') >= $_GET['date_from']));
    }
    if (!empty($_GET['date_to'])) {
        $rows = array_values(array_filter($rows, fn($row) => ($row['payment_date'] ?? $row['expense_date'] ?? $row['invoice_date'] ?? $row['receipt_date'] ?? $row['next_due_date'] ?? '') <= $_GET['date_to']));
    }
    return paginate_rows($rows);
}

function validate_resource_data(string $resource, array $data, array $requiredFields): array
{
    $errors = required($data, $requiredFields);
    $projectResources = ['payments','expenses','recurring-fees','invoices','receipts'];
    if (in_array($resource, $projectResources, true) && !empty($data['project_id']) && !record_exists('projects', (int)$data['project_id'])) {
        $errors['project_id'] = 'Choose an existing project.';
    }
    if (in_array($resource, ['payments','expenses','receipts'], true)) valid_decimal($errors, $data, 'amount');
    if ($resource === 'recurring-fees') {
        valid_decimal($errors, $data, 'amount', true);
        if (isset($data['reminder_days_before_due']) && (filter_var($data['reminder_days_before_due'], FILTER_VALIDATE_INT) === false || (int)$data['reminder_days_before_due'] < 0 || (int)$data['reminder_days_before_due'] > 3650)) $errors['reminder_days_before_due'] = 'Enter a whole number from 0 to 3650.';
        if (isset($data['auto_create_reminder']) && !in_array((string)$data['auto_create_reminder'], ['0','1'], true)) $errors['auto_create_reminder'] = 'Choose whether to create reminders.';
        max_length($errors, $data, 'fee_name', 255);
        one_of($errors, $data, 'fee_type', ['VPS','Server','SSL','Maintenance','API Subscription','Other']);
        one_of($errors, $data, 'billing_cycle', ['Monthly','Quarterly','Half Yearly','Yearly','One Time']);
        one_of($errors, $data, 'status', ['Not Due','Due Soon','Due Today','Overdue','Paid','Cancelled']);
    }
    if ($resource === 'payments') {
        one_of($errors, $data, 'payment_type', ['Upfront','Progress Payment','Final Payment','Maintenance','Hosting','Domain','Server','Other']);
        one_of($errors, $data, 'payment_method', ['Cash','KPay','WavePay','Bank Transfer','AYA Pay','CB Pay','Other']);
        if (!in_array((string)($data['is_historical'] ?? '0'), ['0','1'], true)) $errors['is_historical'] = 'Choose whether this is a historical payment.';
        if (empty($data['is_historical']) && empty($data['financial_account_id'])) $errors['financial_account_id'] = 'Choose the account that received this payment.';
        if (!empty($data['financial_account_id'])) {
            $account = select_one('SELECT id, status FROM financial_accounts WHERE id = ?', [(int)$data['financial_account_id']]);
            $existingAccountId = !empty($data['id']) ? (int)(select_one('SELECT financial_account_id FROM payments WHERE id = ?', [(int)$data['id']])['financial_account_id'] ?? 0) : 0;
            if (!$account || ($account['status'] !== 'Active' && (int)$account['id'] !== $existingAccountId)) $errors['financial_account_id'] = 'Choose an active receiving account.';
        }
        max_length($errors, $data, 'reference_number', 150);
        if (!empty($data['id'])) {
            $existing = select_one('SELECT project_id FROM payments WHERE id = ?', [(int)$data['id']]);
            $receipts = select_one('SELECT COUNT(*) receipt_count, COALESCE(SUM(amount),0) receipted_amount FROM receipts WHERE payment_id = ?', [(int)$data['id']]);
            if ($existing && (int)$receipts['receipt_count'] > 0 && (int)$existing['project_id'] !== (int)($data['project_id'] ?? 0)) $errors['project_id'] = 'A payment with receipts cannot be moved to another project.';
            if ((float)($data['amount'] ?? 0) < (float)$receipts['receipted_amount']) $errors['amount'] = 'Payment amount cannot be less than the total receipted amount.';
        }
    }
    if ($resource === 'expenses') {
        one_of($errors, $data, 'expense_category', ['Domain Purchase','Hosting Purchase','VPS Purchase','Server Cost','SSL Cost','API Cost','SMS Cost','Developer Cost','Design Cost','Transport','Other']);
        one_of($errors, $data, 'payment_method', ['Cash','KPay','WavePay','Bank Transfer','AYA Pay','CB Pay','Other']);
        max_length($errors, $data, 'paid_to', 255);
        max_length($errors, $data, 'reference_number', 150);
    }
    $dateFields = ['payments' => ['payment_date'], 'expenses' => ['expense_date'], 'recurring-fees' => ['last_paid_date','next_due_date'], 'invoices' => ['invoice_date','due_date'], 'receipts' => ['receipt_date']];
    foreach ($dateFields[$resource] ?? [] as $field) valid_date($errors, $data, $field);
    if ($resource === 'receipts' && !empty($data['payment_id']) && !empty($data['project_id'])) {
        $payment = select_one('SELECT project_id, amount, payment_method FROM payments WHERE id = ?', [(int)$data['payment_id']]);
        if (!$payment) {
            $errors['payment_id'] = 'Choose an existing payment.';
        } elseif ((int)$payment['project_id'] !== (int)$data['project_id']) {
            $errors['payment_id'] = 'The payment must belong to the selected project.';
        } elseif ((float)($data['amount'] ?? 0) > (float)$payment['amount']) {
            $errors['amount'] = 'Receipt amount cannot exceed the linked payment.';
        } else {
            $receiptId = !empty($data['id']) ? (int)$data['id'] : 0;
            $alreadyReceipted = money(select_one('SELECT COALESCE(SUM(amount),0) total FROM receipts WHERE payment_id = ? AND id <> ?', [(int)$data['payment_id'], $receiptId])['total']);
            if ($alreadyReceipted + (float)($data['amount'] ?? 0) > (float)$payment['amount']) $errors['amount'] = 'Total receipts cannot exceed the linked payment.';
        }
    }
    if ($resource === 'invoices') {
        one_of($errors, $data, 'invoice_type', ['Project Invoice','Upfront Invoice','Progress Invoice','Final Invoice','Hosting Invoice','Domain Invoice','Maintenance Invoice','Other']);
        one_of($errors, $data, 'status', ['Draft','Sent','Partially Paid','Paid','Overdue','Cancelled']);
        foreach (['discount_amount','tax_amount','paid_amount'] as $field) valid_decimal($errors, $data, $field, true);
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        if (!$items) $errors['items'] = 'At least one invoice item is required.';
        if (count($items) > 100) $errors['items'] = 'An invoice can contain at most 100 items.';
        foreach ($items as $index => $item) {
            if (trim((string)($item['description'] ?? '')) === '') $errors["items.{$index}.description"] = 'Description is required.';
            if (mb_strlen((string)($item['description'] ?? '')) > 255) $errors["items.{$index}.description"] = 'Description must be 255 characters or fewer.';
            if (!is_numeric($item['quantity'] ?? null) || round((float)$item['quantity'], 2) <= 0 || (float)$item['quantity'] > 99999999.99) $errors["items.{$index}.quantity"] = 'Enter a quantity greater than zero within the supported range.';
            if (!is_numeric($item['unit_price'] ?? null) || (float)$item['unit_price'] < 0 || (float)$item['unit_price'] > 9999999999999.99) $errors["items.{$index}.unit_price"] = 'Enter a valid non-negative unit price.';
        }
    }
    return $errors;
}

function reminder_group(string $dueDate): string
{
    $today = new DateTimeImmutable(date('Y-m-d'));
    $due = new DateTimeImmutable($dueDate);
    if ($due < $today) return 'overdue';
    if ($due == $today) return 'due_today';
    if ($due <= $today->modify('sunday this week')) return 'due_this_week';
    return 'upcoming';
}

function reminder_is_resolved(string $type, int $recordId, string $dueDate): bool
{
    return select_one('SELECT id FROM resolved_reminders WHERE reminder_type = ? AND record_id = ? AND due_date = ?', [$type, $recordId, $dueDate]) !== null;
}

function reminder_target_exists(string $type, int $recordId, string $dueDate): bool
{
    return match ($type) {
        'project' => select_one('SELECT p.id FROM (' . project_finance_sql() . ') p WHERE p.id = ? AND p.payment_due_date = ? AND p.remaining_balance > 0', [$recordId, $dueDate]) !== null,
        'domain' => select_one('SELECT id FROM projects WHERE id = ? AND domain_reminder_date = ?', [$recordId, $dueDate]) !== null,
        'domain-payment' => select_one('SELECT id FROM (' . domain_billing_select_sql() . ') domain_due WHERE id=? AND customer_due_date=? AND customer_balance_amount>0 AND purchase_status<>"Cancelled"', [$recordId,$dueDate]) !== null,
        'domain-customer-renewal' => select_one('SELECT d.id FROM domain_billing_periods d WHERE d.id=? AND d.customer_renewal_date=? AND d.purchase_status="Purchased" AND NOT EXISTS (SELECT 1 FROM domain_billing_periods next_period WHERE next_period.id<>d.id AND next_period.project_id=d.project_id AND COALESCE(next_period.domain_name,"")=COALESCE(d.domain_name,"") AND next_period.quote_date>=d.customer_renewal_date)', [$recordId,$dueDate]) !== null,
        'domain-renewal' => select_one('SELECT id FROM domain_billing_periods WHERE id=? AND renewal_reminder_date=? AND purchase_status="Purchased"', [$recordId,$dueDate]) !== null,
        'fee' => select_one('SELECT id FROM recurring_fees WHERE id = ? AND next_due_date = ? AND auto_create_reminder = 1 AND status NOT IN ("Paid","Cancelled")', [$recordId, $dueDate]) !== null,
        'invoice' => select_one('SELECT id FROM invoices WHERE id = ? AND due_date = ? AND balance_amount > 0 AND status NOT IN ("Paid","Cancelled")', [$recordId, $dueDate]) !== null,
        default => false,
    };
}

function cleanup_stale_resolved_reminders(): void
{
    execute_sql('DELETE rr FROM resolved_reminders rr LEFT JOIN projects p ON rr.reminder_type = "project" AND p.id = rr.record_id LEFT JOIN (SELECT project_id, SUM(amount) paid FROM payments WHERE payment_scope="Project" GROUP BY project_id) pay ON pay.project_id = p.id WHERE rr.reminder_type = "project" AND (p.id IS NULL OR p.payment_due_date <> rr.due_date OR (p.contract_amount - p.discount_amount + p.tax_amount - COALESCE(pay.paid,0)) <= 0)');
    execute_sql('DELETE rr FROM resolved_reminders rr LEFT JOIN projects p ON rr.reminder_type = "domain" AND p.id = rr.record_id WHERE rr.reminder_type = "domain" AND (p.id IS NULL OR p.domain_reminder_date <> rr.due_date)');
    execute_sql('DELETE rr FROM resolved_reminders rr LEFT JOIN recurring_fees f ON rr.reminder_type = "fee" AND f.id = rr.record_id WHERE rr.reminder_type = "fee" AND (f.id IS NULL OR f.next_due_date <> rr.due_date OR f.auto_create_reminder = 0 OR f.status IN ("Paid","Cancelled"))');
    execute_sql('DELETE rr FROM resolved_reminders rr LEFT JOIN invoices i ON rr.reminder_type = "invoice" AND i.id = rr.record_id WHERE rr.reminder_type = "invoice" AND (i.id IS NULL OR i.due_date <> rr.due_date OR i.balance_amount <= 0 OR i.status IN ("Paid","Cancelled"))');
    execute_sql('DELETE rr FROM resolved_reminders rr LEFT JOIN domain_billing_periods d ON rr.reminder_type = "domain-renewal" AND d.id=rr.record_id WHERE rr.reminder_type="domain-renewal" AND (d.id IS NULL OR d.renewal_reminder_date<>rr.due_date OR d.purchase_status<>"Purchased")');
    execute_sql('DELETE rr FROM resolved_reminders rr LEFT JOIN domain_billing_periods d ON rr.reminder_type = "domain-customer-renewal" AND d.id=rr.record_id WHERE rr.reminder_type="domain-customer-renewal" AND (d.id IS NULL OR d.customer_renewal_date<>rr.due_date OR d.purchase_status<>"Purchased")');
    execute_sql('DELETE rr FROM resolved_reminders rr LEFT JOIN domain_billing_periods d ON rr.reminder_type = "domain-payment" AND d.id=rr.record_id LEFT JOIN (SELECT domain_billing_period_id, SUM(amount) paid FROM payments WHERE payment_scope="Domain" GROUP BY domain_billing_period_id) pay ON pay.domain_billing_period_id=d.id WHERE rr.reminder_type="domain-payment" AND (d.id IS NULL OR d.customer_due_date<>rr.due_date OR d.purchase_status="Cancelled" OR d.customer_price-COALESCE(pay.paid,0)<=0)');
}

function reminder_data(): array
{
    // Keep reminder reads cheap; stale resolutions never hide a changed due date.
    if (random_int(1, 100) === 1) cleanup_stale_resolved_reminders();
    $groups = ['due_today' => [], 'due_this_week' => [], 'overdue' => [], 'upcoming' => []];
    $resolved = [];
    foreach (select_all('SELECT reminder_type, record_id, due_date FROM resolved_reminders') as $row) $resolved[$row['reminder_type'] . ':' . $row['record_id'] . ':' . $row['due_date']] = true;
    $push = function (array $row) use (&$groups, $resolved): void {
        if (isset($resolved[$row['source_type'] . ':' . (int)$row['record_id'] . ':' . $row['due_date']])) return;
        $group = reminder_group($row['due_date']);
        $row['status'] = match ($group) { 'due_today' => 'Due Today', 'due_this_week' => 'Due This Week', 'overdue' => 'Overdue', default => 'Upcoming' };
        $groups[$group][] = $row;
    };
    foreach (select_all('SELECT * FROM (' . project_finance_sql() . ') reminder_projects WHERE remaining_balance>0 AND payment_due_date IS NOT NULL') as $project) {
        $push(['source_type' => 'project', 'record_id' => $project['id'], 'project_id' => $project['id'], 'reminder_type' => 'Project payment', 'project_name' => $project['project_name'], 'customer_company_name' => $project['customer_company_name'], 'amount' => $project['remaining_balance'], 'due_date' => $project['payment_due_date']]);
    }
    foreach (select_all('SELECT p.* FROM projects p WHERE p.domain_reminder_date IS NOT NULL AND NOT EXISTS (SELECT 1 FROM domain_billing_periods d WHERE d.project_id=p.id)') as $project) {
        $push(['source_type' => 'domain', 'record_id' => $project['id'], 'project_id' => $project['id'], 'reminder_type' => 'Domain renewal', 'project_name' => $project['project_name'], 'customer_company_name' => $project['customer_company_name'], 'amount' => $project['domain_server_price'], 'due_date' => $project['domain_reminder_date']]);
    }
    foreach (select_all('SELECT r.*, p.project_name, p.customer_company_name FROM recurring_fees r JOIN projects p ON p.id = r.project_id WHERE r.auto_create_reminder = 1 AND r.status NOT IN ("Paid", "Cancelled")') as $fee) {
        $push(['source_type' => 'fee', 'record_id' => $fee['id'], 'project_id' => $fee['project_id'], 'reminder_type' => $fee['fee_type'] . ' renewal', 'project_name' => $fee['project_name'], 'customer_company_name' => $fee['customer_company_name'], 'amount' => $fee['amount'], 'due_date' => $fee['next_due_date']]);
    }
    foreach (select_all('SELECT * FROM (' . domain_billing_select_sql() . ') reminder_domains WHERE purchase_status<>"Cancelled" AND ((customer_due_date IS NOT NULL AND customer_balance_amount>0) OR (purchase_status="Purchased" AND (renewal_reminder_date IS NOT NULL OR customer_renewal_date IS NOT NULL)))') as $domainBilling) {
        if (!empty($domainBilling['customer_due_date']) && (float)$domainBilling['customer_balance_amount'] > 0) {
            $push(['source_type'=>'domain-payment','record_id'=>$domainBilling['id'],'project_id'=>$domainBilling['project_id'],'reminder_type'=>'Domain payment: ' . ($domainBilling['domain_name'] ?: $domainBilling['period_label'] ?: 'Quoted domain'),'project_name'=>$domainBilling['project_name'],'customer_company_name'=>$domainBilling['customer_company_name'],'amount'=>$domainBilling['customer_balance_amount'],'due_date'=>$domainBilling['customer_due_date']]);
        }
        if ($domainBilling['purchase_status'] === 'Purchased' && !empty($domainBilling['renewal_reminder_date'])) {
            $push(['source_type'=>'domain-renewal','record_id'=>$domainBilling['id'],'project_id'=>$domainBilling['project_id'],'reminder_type'=>'Registrar expiry: ' . ($domainBilling['domain_name'] ?: $domainBilling['period_label'] ?: 'Domain'),'project_name'=>$domainBilling['project_name'],'customer_company_name'=>$domainBilling['customer_company_name'],'amount'=>$domainBilling['actual_registrar_cost'],'due_date'=>$domainBilling['renewal_reminder_date']]);
        }
        if ($domainBilling['purchase_status'] === 'Purchased' && !empty($domainBilling['customer_renewal_date']) && reminder_target_exists('domain-customer-renewal', (int)$domainBilling['id'], $domainBilling['customer_renewal_date'])) {
            $push(['source_type'=>'domain-customer-renewal','record_id'=>$domainBilling['id'],'project_id'=>$domainBilling['project_id'],'reminder_type'=>'Charge customer for domain: ' . ($domainBilling['domain_name'] ?: $domainBilling['period_label'] ?: 'Domain'),'project_name'=>$domainBilling['project_name'],'customer_company_name'=>$domainBilling['customer_company_name'],'amount'=>$domainBilling['customer_price'],'due_date'=>$domainBilling['customer_renewal_date']]);
        }
    }
    foreach (select_all('SELECT i.*, p.project_name, p.customer_company_name FROM invoices i JOIN projects p ON p.id = i.project_id WHERE i.balance_amount > 0 AND i.status NOT IN ("Paid", "Cancelled") AND i.due_date IS NOT NULL') as $invoice) {
        $push(['source_type' => 'invoice', 'record_id' => $invoice['id'], 'project_id' => $invoice['project_id'], 'reminder_type' => 'Invoice ' . $invoice['invoice_number'], 'project_name' => $invoice['project_name'], 'customer_company_name' => $invoice['customer_company_name'], 'amount' => $invoice['balance_amount'], 'due_date' => $invoice['due_date']]);
    }
    foreach ($groups as &$rows) usort($rows, fn($a, $b) => strcmp($a['due_date'], $b['due_date']));
    unset($rows);
    $counts = array_map('count', $groups);
    $limit = min(100, max(10, (int)($_GET['limit'] ?? 25)));
    $pagination = [];
    foreach ($groups as $key => &$rows) {
        $pages = max(1, (int)ceil($counts[$key] / $limit));
        $page = min($pages, max(1, (int)($_GET[$key . '_page'] ?? 1)));
        $rows = array_slice($rows, ($page - 1) * $limit, $limit);
        $pagination[$key] = ['page' => $page, 'pages' => $pages, 'total' => $counts[$key], 'limit' => $limit];
    }
    unset($rows);
    return ['groups' => $groups, 'counts' => $counts, 'limit' => $limit, 'pagination' => $pagination];
}

try {
    if ($parts === ['auth', 'login'] && $method === 'POST') {
        $data = input();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        if ($email === '' || mb_strlen($email) > 150 || strlen((string)($data['password'] ?? '')) > 1024) {
            json_response(false, 'Invalid email or password', null, [], 401);
        }
        enforce_login_throttle($email);
        $user = select_one('SELECT * FROM users WHERE email = ? AND status = "Active"', [$email]);
        $verificationHash = $user['password_hash'] ?? '$2y$10$LFP/NtrAmMCk5.KuxPcxl.qKTAssKdn1VreMkr.rnlKhrA56uE1zG';
        $passwordMatches = password_verify((string)($data['password'] ?? ''), $verificationHash);
        if (!$user || !$passwordMatches) {
            record_failed_login($email);
            json_response(false, 'Invalid email or password', null, [], 401);
        }
        clear_login_attempts($email);
        audit_log((int)$user['id'], 'login', 'auth', (int)$user['id'], 'User logged in');
        unset($user['password_hash']);
        json_response(true, 'Login successful', ['token' => token_for($user), 'user' => $user]);
    }
    if ($parts === ['auth', 'logout'] && $method === 'POST') {
        $logoutUser = require_user();
        revoke_token($logoutUser);
        audit_log((int)$logoutUser['sub'], 'logout', 'auth', (int)$logoutUser['sub'], 'User logged out');
        json_response(true, 'Logout successful');
    }
    if ($parts === ['auth', 'me'] && $method === 'GET') {
        json_response(true, 'Current user', require_user());
    }

    $user = require_user();
    $resource = $parts[0] ?? '';
    $id = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : null;

    if ($resource === 'domain-billings') {
        $fields = ['project_id','domain_name','period_label','quote_date','customer_price','customer_due_date','purchase_status','reminder_days_before_due','notes','created_by'];
        if ($method === 'GET' && !$id) {
            $where = [];
            $params = [];
            if (($_GET['project_id'] ?? '') !== '') { $where[] = 'list_rows.project_id=?'; $params[] = (int)$_GET['project_id']; }
            if (($_GET['payment_status'] ?? '') !== '') { $where[] = 'list_rows.customer_payment_status=?'; $params[] = $_GET['payment_status']; }
            if (($_GET['purchase_status'] ?? '') !== '') { $where[] = 'list_rows.effective_purchase_status=?'; $params[] = $_GET['purchase_status']; }
            if (($_GET['date_from'] ?? '') !== '') { $where[] = 'list_rows.quote_date>=?'; $params[] = $_GET['date_from']; }
            if (($_GET['date_to'] ?? '') !== '') { $where[] = 'list_rows.quote_date<=?'; $params[] = $_GET['date_to']; }
            if (trim((string)($_GET['search'] ?? '')) !== '') { $where[] = 'CONCAT_WS(" ", list_rows.domain_name, list_rows.period_label, list_rows.project_code, list_rows.project_name, list_rows.customer_company_name, list_rows.registrar_provider) LIKE ?'; $params[] = '%' . trim((string)$_GET['search']) . '%'; }
            json_response(true, 'Domain billing periods', paginated_select(domain_billing_select_sql(), $where, $params, 'quote_date DESC, id DESC'));
        }
        if ($method === 'GET' && $id) json_response(true, 'Domain billing period', domain_billing_detail($id));
        require_write($user);

        if ($id && ($parts[2] ?? '') === 'customer-payment' && $method === 'POST') {
            $data = input();
            $historicalValue = $data['is_historical'] ?? 0;
            $data['is_historical'] = in_array($historicalValue, [1,'1',true], true) ? 1 : (in_array($historicalValue, [0,'0',false], true) ? 0 : $historicalValue);
            if ((string)$data['is_historical'] === '1') $data['financial_account_id'] = null;
            $errors = required($data, ['payment_date','amount']);
            valid_date($errors, $data, 'payment_date');
            valid_decimal($errors, $data, 'amount');
            max_length($errors, $data, 'reference_number', 150);
            if (!in_array((string)$data['is_historical'], ['0','1'], true)) $errors['is_historical'] = 'Choose whether this is a historical payment.';
            if (!$data['is_historical'] && empty($data['financial_account_id'])) $errors['financial_account_id'] = 'Choose the account that received this payment.';
            $account = !empty($data['financial_account_id']) ? select_one('SELECT id FROM financial_accounts WHERE id=? AND status="Active"', [(int)$data['financial_account_id']]) : null;
            if (!empty($data['financial_account_id']) && !$account) $errors['financial_account_id'] = 'Choose an active receiving account.';
            if ($errors) json_response(false, 'Validation failed', null, $errors, 422);
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $billing = select_one('SELECT * FROM domain_billing_periods WHERE id=? FOR UPDATE', [$id]);
                if (!$billing) json_response(false, 'Domain billing period not found', null, [], 404);
                if ($billing['purchase_status'] === 'Cancelled') json_response(false, 'Cancelled domain billing cannot receive payments.', null, [], 422);
                $paid = money(select_one('SELECT COALESCE(SUM(amount),0) total FROM payments WHERE domain_billing_period_id=?', [$id])['total']);
                $remaining = money((float)$billing['customer_price'] - $paid);
                if ($remaining <= 0) json_response(false, 'This domain period is already fully paid.', null, [], 422);
                if ((float)$data['amount'] > $remaining) json_response(false, 'Payment cannot exceed the remaining domain price.', null, ['amount' => 'Maximum payment is ' . $remaining . '.'], 422);
                $payment = ['project_id' => $billing['project_id'], 'payment_date' => $data['payment_date'], 'amount' => money($data['amount']), 'payment_type' => 'Domain', 'payment_method' => 'Other', 'payment_scope' => 'Domain', 'domain_billing_period_id' => $id, 'is_historical' => $data['is_historical'], 'financial_account_id' => $data['financial_account_id'] ? (int)$data['financial_account_id'] : null, 'reference_number' => $data['reference_number'] ?? null, 'received_by' => $user['sub'], 'notes' => $data['notes'] ?? 'Customer domain payment'];
                $paymentId = insert_or_update('payments', $payment, ['project_id','payment_date','amount','payment_type','payment_method','payment_scope','domain_billing_period_id','is_historical','financial_account_id','reference_number','received_by','notes']);
                sync_payment_receive($paymentId, $payment, (int)$user['sub'], 'Customer domain payment');
                audit_log((int)$user['sub'], 'create', 'domain-payments', $paymentId, 'Recorded customer domain payment');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            json_response(true, 'Customer domain payment recorded', ['payment_id' => $paymentId], [], 201);
        }

        if ($id && ($parts[2] ?? '') === 'customer-payment' && isset($parts[3]) && is_numeric($parts[3]) && $method === 'DELETE') {
            $paymentId = (int)$parts[3];
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $billing = select_one('SELECT id, customer_due_date FROM domain_billing_periods WHERE id=? FOR UPDATE', [$id]);
                if (!$billing) json_response(false, 'Domain billing period not found', null, [], 404);
                $payment = select_one('SELECT id, amount FROM payments WHERE id=? AND domain_billing_period_id=? AND payment_scope="Domain" FOR UPDATE', [$paymentId, $id]);
                if (!$payment) json_response(false, 'Customer domain payment not found', null, [], 404);
                execute_sql('DELETE FROM payments WHERE id=?', [$paymentId]);
                execute_sql('DELETE FROM resolved_reminders WHERE reminder_type="domain-payment" AND record_id=?', [$id]);
                audit_log((int)$user['sub'], 'reverse-payment', 'domain-billings', $id, 'Reversed customer domain payment #' . $paymentId . ' for ' . money($payment['amount']));
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            json_response(true, 'Customer domain payment and linked account transaction reversed');
        }

        if ($id && ($parts[2] ?? '') === 'purchase' && $method === 'POST') {
            $data = input();
            $historicalValue = $data['is_historical_purchase'] ?? 0;
            $data['is_historical_purchase'] = in_array($historicalValue, [1,'1',true], true) ? 1 : (in_array($historicalValue, [0,'0',false], true) ? 0 : $historicalValue);
            if ((string)$data['is_historical_purchase'] === '1') $data['financial_account_id'] = null;
            $requiredFields = ['purchase_date','actual_registrar_cost'];
            if (!$data['is_historical_purchase']) $requiredFields[] = 'financial_account_id';
            $errors = required($data, $requiredFields);
            valid_date($errors, $data, 'purchase_date');
            valid_date($errors, $data, 'customer_renewal_date');
            valid_date($errors, $data, 'coverage_end_date');
            valid_decimal($errors, $data, 'actual_registrar_cost');
            max_length($errors, $data, 'registrar_provider', 255);
            max_length($errors, $data, 'registrar_reference', 150);
            if (!in_array((string)$data['is_historical_purchase'], ['0','1'], true)) $errors['is_historical_purchase'] = 'Choose whether this is a historical purchase.';
            if ($errors) json_response(false, 'Validation failed', null, $errors, 422);
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $billing = select_one('SELECT * FROM domain_billing_periods WHERE id=? FOR UPDATE', [$id]);
                if (!$billing) json_response(false, 'Domain billing period not found', null, [], 404);
                if ($billing['purchase_status'] === 'Cancelled') json_response(false, 'Cancelled domain billing cannot be purchased.', null, [], 422);
                if (!$data['is_historical_purchase']) {
                    $account = select_one('SELECT id, status FROM financial_accounts WHERE id=? FOR UPDATE', [(int)$data['financial_account_id']]);
                    if (!$account || ($account['status'] !== 'Active' && (int)$account['id'] !== (int)($billing['paid_from_account_id'] ?? 0))) json_response(false, 'Choose an active account to pay the registrar.', null, ['financial_account_id' => 'Choose an active account.'], 422);
                }
                $coverageStart = $data['purchase_date'];
                $customerRenewal = !empty($data['customer_renewal_date']) ? $data['customer_renewal_date'] : next_due_date($coverageStart, 'Yearly');
                $coverageEnd = !empty($data['coverage_end_date']) ? $data['coverage_end_date'] : next_due_date($coverageStart, 'Yearly');
                if ($customerRenewal <= $coverageStart) json_response(false, 'Customer renewal date must be after the purchase date.', null, ['customer_renewal_date' => 'Choose a later date.'], 422);
                if ($coverageEnd <= $coverageStart) json_response(false, 'Registrar expiry date must be after the purchase date.', null, ['coverage_end_date' => 'Choose a later date.'], 422);
                $reminderDays = (int)$billing['reminder_days_before_due'];
                $renewalReminder = (new DateTimeImmutable($coverageEnd))->modify("-{$reminderDays} days")->format('Y-m-d');
                $periodLabel = trim((string)$billing['period_label']) ?: substr($coverageStart, 0, 4) . '-' . substr($customerRenewal, 0, 4);
                execute_sql('UPDATE domain_billing_periods SET period_label=?, purchase_status="Purchased", purchase_date=?, coverage_start_date=?, coverage_end_date=?, customer_renewal_date=?, renewal_reminder_date=?, actual_registrar_cost=?, is_historical_purchase=?, is_registrar_carryover=0, paid_from_account_id=?, registrar_provider=?, registrar_reference=? WHERE id=?', [$periodLabel,$data['purchase_date'],$coverageStart,$coverageEnd,$customerRenewal,$renewalReminder,money($data['actual_registrar_cost']),$data['is_historical_purchase'],$data['financial_account_id'] ? (int)$data['financial_account_id'] : null,$data['registrar_provider'] ?? null,$data['registrar_reference'] ?? null,$id]);
                execute_sql('INSERT INTO expenses (project_id,expense_date,expense_category,amount,paid_to,payment_method,reference_number,domain_billing_period_id,notes,created_by) VALUES (?, ?, "Domain Purchase", ?, ?, "Other", ?, ?, ?, ?) ON DUPLICATE KEY UPDATE project_id=VALUES(project_id), expense_date=VALUES(expense_date), amount=VALUES(amount), paid_to=VALUES(paid_to), reference_number=VALUES(reference_number), notes=VALUES(notes), updated_at=CURRENT_TIMESTAMP', [$billing['project_id'],$data['purchase_date'],money($data['actual_registrar_cost']),$data['registrar_provider'] ?? 'Domain registrar',$data['registrar_reference'] ?? null,$id,'Registrar purchase for domain billing period',$user['sub']]);
                if ($data['is_historical_purchase']) {
                    execute_sql('DELETE FROM financial_transactions WHERE domain_billing_period_id=?', [$id]);
                } else {
                    execute_sql('INSERT INTO financial_transactions (transaction_date,transaction_type,from_account_id,amount,notes,domain_billing_period_id,created_by) VALUES (?, "Use", ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE transaction_date=VALUES(transaction_date), from_account_id=VALUES(from_account_id), amount=VALUES(amount), notes=VALUES(notes), updated_at=CURRENT_TIMESTAMP', [$data['purchase_date'],(int)$data['financial_account_id'],money($data['actual_registrar_cost']),'Domain registrar purchase',$id,$user['sub']]);
                }
                audit_log((int)$user['sub'], empty($billing['purchase_date']) ? 'purchase' : 'update-purchase', 'domain-billings', $id, 'Recorded domain registrar purchase');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            json_response(true, $data['is_historical_purchase'] ? 'Historical domain purchase saved without an account movement' : 'Domain purchase and linked accounting saved', ['id' => $id]);
        }

        if ($id && ($parts[2] ?? '') === 'purchase' && $method === 'DELETE') {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $billing = select_one('SELECT * FROM domain_billing_periods WHERE id=? FOR UPDATE', [$id]);
                if (!$billing) json_response(false, 'Domain billing period not found', null, [], 404);
                execute_sql('DELETE FROM financial_transactions WHERE domain_billing_period_id=?', [$id]);
                execute_sql('DELETE FROM expenses WHERE domain_billing_period_id=?', [$id]);
                execute_sql('DELETE FROM resolved_reminders WHERE reminder_type IN ("domain-renewal","domain-customer-renewal") AND record_id=?', [$id]);
                execute_sql('UPDATE domain_billing_periods SET purchase_status="Quoted", purchase_date=NULL, coverage_start_date=NULL, coverage_end_date=NULL, customer_renewal_date=NULL, renewal_reminder_date=NULL, actual_registrar_cost=0, is_historical_purchase=0, is_registrar_carryover=0, paid_from_account_id=NULL, registrar_provider=NULL, registrar_reference=NULL WHERE id=?', [$id]);
                audit_log((int)$user['sub'], 'reverse-purchase', 'domain-billings', $id, 'Reversed domain registrar purchase');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            json_response(true, 'Domain purchase reversed');
        }

        if ($id && ($parts[2] ?? '') === 'renew' && $method === 'POST') {
            $existing = select_one('SELECT * FROM domain_billing_periods WHERE id=?', [$id]);
            if (!$existing) json_response(false, 'Domain billing period not found', null, [], 404);
            $quoteDate = $existing['customer_renewal_date'] ?: $existing['coverage_end_date'] ?: date('Y-m-d');
            $duplicate = select_one('SELECT id FROM domain_billing_periods WHERE id<>? AND project_id=? AND COALESCE(domain_name,"")=COALESCE(?,"") AND quote_date>=? LIMIT 1', [$id,$existing['project_id'],$existing['domain_name'],$quoteDate]);
            if ($duplicate) json_response(false, 'The next customer domain period already exists.', null, [], 422);
            $nextCustomerRenewal = next_due_date($quoteDate, 'Yearly');
            $hasRegistrarCoverage = !empty($existing['coverage_end_date']) && $existing['coverage_end_date'] > $quoteDate;
            $renewalData = ['project_id'=>$existing['project_id'],'domain_name'=>$existing['domain_name'],'period_label'=>substr($quoteDate,0,4) . '-' . substr($nextCustomerRenewal,0,4),'quote_date'=>$quoteDate,'customer_price'=>$existing['customer_price'],'customer_due_date'=>$quoteDate,'purchase_status'=>$hasRegistrarCoverage ? 'Purchased' : 'Quoted','reminder_days_before_due'=>$existing['reminder_days_before_due'],'notes'=>'Renewal from domain billing #' . $id,'created_by'=>$user['sub']];
            $renewalFields = $fields;
            if ($hasRegistrarCoverage) {
                $renewalData += ['purchase_date'=>$existing['purchase_date'],'coverage_start_date'=>$quoteDate,'coverage_end_date'=>$existing['coverage_end_date'],'customer_renewal_date'=>$nextCustomerRenewal,'renewal_reminder_date'=>null,'actual_registrar_cost'=>0,'is_historical_purchase'=>0,'is_registrar_carryover'=>1,'paid_from_account_id'=>null,'registrar_provider'=>$existing['registrar_provider'],'registrar_reference'=>$existing['registrar_reference']];
                $renewalFields = array_merge($renewalFields, ['purchase_date','coverage_start_date','coverage_end_date','customer_renewal_date','renewal_reminder_date','actual_registrar_cost','is_historical_purchase','is_registrar_carryover','paid_from_account_id','registrar_provider','registrar_reference']);
            }
            $newId = insert_or_update('domain_billing_periods', $renewalData, $renewalFields);
            audit_log((int)$user['sub'], 'renew', 'domain-billings', $newId, 'Created next annual domain billing period');
            json_response(true, 'Next annual domain period created', ['id'=>$newId], [], 201);
        }

        if ($method === 'POST' || ($method === 'PUT' && $id)) {
            $existing = $method === 'PUT' ? select_one('SELECT * FROM domain_billing_periods WHERE id=?', [$id]) : null;
            if ($method === 'PUT' && !$existing) json_response(false, 'Domain billing period not found', null, [], 404);
            $data = input();
            $data['purchase_status'] = $existing && $existing['purchase_status'] === 'Purchased' ? 'Purchased' : ($data['purchase_status'] ?? 'Quoted');
            $data['reminder_days_before_due'] = $data['reminder_days_before_due'] ?? 30;
            $data['created_by'] = $existing['created_by'] ?? $user['sub'];
            $validationData = $data;
            if ($data['purchase_status'] === 'Purchased') $validationData['purchase_status'] = 'Quoted';
            $errors = validate_domain_billing($validationData, $existing);
            if ($errors) json_response(false, 'Validation failed', null, $errors, 422);
            $newId = insert_or_update('domain_billing_periods', $data, $fields, $method === 'PUT' ? $id : null);
            audit_log((int)$user['sub'], $method === 'POST' ? 'create' : 'update', 'domain-billings', $newId, ($method === 'POST' ? 'Created' : 'Updated') . ' domain billing period');
            json_response(true, $method === 'POST' ? 'Domain billing period created' : 'Domain billing period updated', ['id'=>$newId], [], $method === 'POST' ? 201 : 200);
        }

        if ($method === 'DELETE' && $id) {
            if (!record_exists('domain_billing_periods', $id)) json_response(false, 'Domain billing period not found', null, [], 404);
            if ((int)select_one('SELECT COUNT(*) total FROM payments WHERE domain_billing_period_id=?', [$id])['total'] > 0) json_response(false, 'Delete the linked customer domain payments before deleting this period.', null, [], 422);
            $pdo = db();
            $pdo->beginTransaction();
            try {
                execute_sql('DELETE FROM financial_transactions WHERE domain_billing_period_id=?', [$id]);
                execute_sql('DELETE FROM expenses WHERE domain_billing_period_id=?', [$id]);
                execute_sql('DELETE FROM resolved_reminders WHERE reminder_type IN ("domain-payment","domain-renewal","domain-customer-renewal") AND record_id=?', [$id]);
                execute_sql('DELETE FROM domain_billing_periods WHERE id=?', [$id]);
                audit_log((int)$user['sub'], 'delete', 'domain-billings', $id, 'Deleted domain billing period and linked purchase accounting');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            json_response(true, 'Domain billing period deleted');
        }
        json_response(false, 'Route not found', null, [], 404);
    }

    if ($resource === 'financial-accounts') {
        if ($method === 'GET') json_response(true, 'Financial accounts', financial_accounts());
        require_admin($user);
        if ($method === 'POST' || ($method === 'PUT' && $id)) {
            if ($method === 'PUT' && !record_exists('financial_accounts', $id)) json_response(false, 'Financial account not found', null, [], 404);
            $data = input();
            $data['name'] = trim((string)($data['name'] ?? ''));
            $errors = required($data, ['name','opening_balance','status']);
            valid_decimal($errors, $data, 'opening_balance', true);
            one_of($errors, $data, 'status', ['Active','Inactive']);
            if (mb_strlen($data['name']) > 150) $errors['name'] = 'Name must be 150 characters or fewer.';
            if ($data['name'] !== '' && select_one('SELECT id FROM financial_accounts WHERE name = ? AND id <> ?', [$data['name'], $id ?? 0])) $errors['name'] = 'This financial account name already exists.';
            if ($errors) json_response(false, 'Validation failed', null, $errors, 422);
            $accountId = insert_or_update('financial_accounts', $data, ['name','opening_balance','status'], $method === 'PUT' ? $id : null);
            audit_log((int)$user['sub'], $method === 'POST' ? 'create' : 'update', 'financial-accounts', $accountId, ($method === 'POST' ? 'Created ' : 'Updated ') . 'financial account');
            json_response(true, $method === 'POST' ? 'Financial account created' : 'Financial account updated', ['id' => $accountId], [], $method === 'POST' ? 201 : 200);
        }
        if ($method === 'DELETE' && $id) {
            if (!record_exists('financial_accounts', $id)) json_response(false, 'Financial account not found', null, [], 404);
            $usage = select_one('SELECT (SELECT COUNT(*) FROM payments WHERE financial_account_id = ?) payments, (SELECT COUNT(*) FROM financial_transactions WHERE from_account_id = ? OR to_account_id = ?) transactions', [$id,$id,$id]);
            if ((int)$usage['payments'] > 0 || (int)$usage['transactions'] > 0) json_response(false, 'This account has financial history. Set it to Inactive instead.', null, [], 422);
            execute_sql('DELETE FROM financial_accounts WHERE id = ?', [$id]);
            audit_log((int)$user['sub'], 'delete', 'financial-accounts', $id, 'Deleted unused financial account');
            json_response(true, 'Financial account deleted');
        }
        json_response(false, 'Route not found', null, [], 404);
    }

    if ($resource === 'financial-transactions') {
        $baseSql = 'SELECT ft.*, fa.name from_account_name, ta.name to_account_name, COALESCE(p.project_name, dp.project_name) project_name, COALESCE(pay.project_id, d.project_id) project_id, d.domain_name, d.period_label domain_period_label FROM financial_transactions ft LEFT JOIN financial_accounts fa ON fa.id=ft.from_account_id LEFT JOIN financial_accounts ta ON ta.id=ft.to_account_id LEFT JOIN payments pay ON pay.id=ft.project_payment_id LEFT JOIN projects p ON p.id=pay.project_id LEFT JOIN domain_billing_periods d ON d.id=ft.domain_billing_period_id LEFT JOIN projects dp ON dp.id=d.project_id';
        if ($method === 'GET' && !$id) {
            $where = [];
            $params = [];
            if (($_GET['transaction_type'] ?? '') !== '') { $where[] = 'list_rows.transaction_type = ?'; $params[] = $_GET['transaction_type']; }
            if (($_GET['account_id'] ?? '') !== '') { $where[] = '(list_rows.from_account_id = ? OR list_rows.to_account_id = ?)'; $params[] = (int)$_GET['account_id']; $params[] = (int)$_GET['account_id']; }
            if (($_GET['date_from'] ?? '') !== '') { $where[] = 'list_rows.transaction_date >= ?'; $params[] = $_GET['date_from']; }
            if (($_GET['date_to'] ?? '') !== '') { $where[] = 'list_rows.transaction_date <= ?'; $params[] = $_GET['date_to']; }
            if (trim((string)($_GET['search'] ?? '')) !== '') { $where[] = 'CONCAT_WS(" ", list_rows.notes, list_rows.from_account_name, list_rows.to_account_name, list_rows.project_name) LIKE ?'; $params[] = '%' . trim((string)$_GET['search']) . '%'; }
            json_response(true, 'Financial transactions', paginated_select($baseSql, $where, $params, 'transaction_date DESC, id DESC'));
        }
        if ($method === 'GET' && $id) {
            $row = select_one($baseSql . ' WHERE ft.id = ?', [$id]);
            if (!$row) json_response(false, 'Financial transaction not found', null, [], 404);
            json_response(true, 'Financial transaction', $row);
        }
        require_write($user);
        if ($method === 'POST' || ($method === 'PUT' && $id)) {
            $existingTransaction = $method === 'PUT' ? select_one('SELECT * FROM financial_transactions WHERE id = ?', [$id]) : null;
            if ($method === 'PUT' && !$existingTransaction) json_response(false, 'Financial transaction not found', null, [], 404);
            if (!empty($existingTransaction['domain_billing_period_id'])) json_response(false, 'Registrar purchase transactions are managed from Domain Billing.', null, [], 422);
            $data = input();
            $errors = required($data, ['transaction_date','transaction_type','amount','from_account_id']);
            one_of($errors, $data, 'transaction_type', ['Use','Transfer']);
            valid_date($errors, $data, 'transaction_date');
            valid_decimal($errors, $data, 'amount');
            max_length($errors, $data, 'notes', 500);
            if (($data['transaction_type'] ?? '') === 'Transfer' && empty($data['to_account_id'])) $errors['to_account_id'] = 'Choose the destination account.';
            if (!empty($data['from_account_id'])) {
                $fromAccount = select_one('SELECT id, status FROM financial_accounts WHERE id=?', [(int)$data['from_account_id']]);
                if (!$fromAccount || ($fromAccount['status'] !== 'Active' && (int)$fromAccount['id'] !== (int)($existingTransaction['from_account_id'] ?? 0))) $errors['from_account_id'] = 'Choose an active source account.';
            }
            if (!empty($data['to_account_id'])) {
                $toAccount = select_one('SELECT id, status FROM financial_accounts WHERE id=?', [(int)$data['to_account_id']]);
                if (!$toAccount || ($toAccount['status'] !== 'Active' && (int)$toAccount['id'] !== (int)($existingTransaction['to_account_id'] ?? 0))) $errors['to_account_id'] = 'Choose an active destination account.';
            }
            if (!empty($data['from_account_id']) && (int)$data['from_account_id'] === (int)($data['to_account_id'] ?? 0)) $errors['to_account_id'] = 'Source and destination must be different.';
            if ($errors) json_response(false, 'Validation failed', null, $errors, 422);
            if (($data['transaction_type'] ?? '') === 'Use') $data['to_account_id'] = null;
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $accountIds = array_values(array_unique(array_filter([(int)$data['from_account_id'], (int)($data['to_account_id'] ?? 0)])));
                $marks = implode(',', array_fill(0, count($accountIds), '?'));
                select_all("SELECT id FROM financial_accounts WHERE id IN ({$marks}) ORDER BY id FOR UPDATE", $accountIds);
                $data['created_by'] = $user['sub'];
                $transactionId = insert_or_update('financial_transactions', $data, ['transaction_date','transaction_type','from_account_id','to_account_id','amount','notes','created_by'], $method === 'PUT' ? $id : null);
                audit_log((int)$user['sub'], $method === 'POST' ? 'create' : 'update', 'financial-transactions', $transactionId, ($method === 'POST' ? 'Created ' : 'Updated ') . strtolower($data['transaction_type']) . ' transaction');
                $pdo->commit();
                json_response(true, $method === 'POST' ? 'Transaction created' : 'Transaction updated', ['id' => $transactionId], [], $method === 'POST' ? 201 : 200);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }
        if ($method === 'DELETE' && $id) {
            $row = select_one('SELECT project_payment_id, domain_billing_period_id FROM financial_transactions WHERE id=?', [$id]);
            if (!$row) json_response(false, 'Financial transaction not found', null, [], 404);
            if (!empty($row['project_payment_id'])) json_response(false, 'Delete the linked project payment instead', null, [], 422);
            if (!empty($row['domain_billing_period_id'])) json_response(false, 'Registrar purchase transactions are managed from Domain Billing.', null, [], 422);
            execute_sql('DELETE FROM financial_transactions WHERE id=?', [$id]);
            audit_log((int)$user['sub'], 'delete', 'financial-transactions', $id, 'Deleted financial transaction');
            json_response(true, 'Transaction deleted');
        }
        json_response(false, 'Route not found', null, [], 404);
    }

    if ($resource === 'projects') {
        $fields = ['project_code','project_name','project_type','description','status','priority','start_date','delivery_date','completion_date','customer_company_name','contact_person','contact_phone','contact_email','customer_address','contract_amount','upfront_required_amount','discount_amount','tax_amount','payment_due_date','currency','domain_name','domain_purchase_date','domain_reminder_date','domain_payment_date','domain_server_price','hosting_provider','server_provider','server_ip','git_repository_url','admin_panel_url','production_url','technical_notes','notes','created_by'];
        if ($id && ($parts[2] ?? '') === 'summary') json_response(true, 'Project summary', summary_only($id));
        if ($method === 'GET' && $id && isset($parts[2])) {
            $projectLists = [
                'payments' => ['SELECT pay.*, u.name recorded_by_name, fa.name financial_account_name FROM payments pay LEFT JOIN users u ON u.id = pay.received_by LEFT JOIN financial_accounts fa ON fa.id = pay.financial_account_id', 'payment_date DESC, id DESC', 'Project payments'],
                'expenses' => ['SELECT e.*, u.name created_by_name FROM expenses e LEFT JOIN users u ON u.id = e.created_by', 'expense_date DESC, id DESC', 'Project expenses'],
                'recurring-fees' => [recurring_fee_list_sql(), 'next_due_date DESC, id DESC', 'Project recurring fees'],
                'invoices' => ['SELECT i.* FROM invoices i', 'invoice_date DESC, id DESC', 'Project invoices'],
                'receipts' => ['SELECT r.* FROM receipts r', 'receipt_date DESC, id DESC', 'Project receipts'],
                'domain-billings' => [domain_billing_select_sql(), 'quote_date DESC, id DESC', 'Project domain billing'],
            ];
            $listName = $parts[2];
            if (isset($projectLists[$listName])) {
                [$baseSql, $orderBy, $message] = $projectLists[$listName];
                json_response(true, $message, paginated_select($baseSql, ['list_rows.project_id = ?'], [$id], $orderBy));
            }
        }
        if ($method === 'GET' && !$id) json_response(true, 'Projects', project_list());
        if ($method === 'GET' && $id) {
            $project = select_one(project_select_sql() . ' WHERE p.id = ?', [$id]);
            if (!$project) json_response(false, 'Project not found', null, [], 404);
            $project = with_project_finance($project);
            $project['summary'] = summary_only($id);
            $project['record_counts'] = select_one('SELECT (SELECT COUNT(*) FROM payments WHERE project_id=?) payments, (SELECT COUNT(*) FROM expenses WHERE project_id=?) expenses, ((SELECT COUNT(*) FROM recurring_fees WHERE project_id=?) + (SELECT COUNT(*) FROM domain_billing_periods WHERE project_id=?)) recurring_fees, (SELECT COUNT(*) FROM invoices WHERE project_id=?) invoices, (SELECT COUNT(*) FROM receipts WHERE project_id=?) receipts, (SELECT COUNT(*) FROM domain_billing_periods WHERE project_id=?) domain_billings', [$id,$id,$id,$id,$id,$id,$id]);
            json_response(true, 'Project detail', $project);
        }
        require_write($user);
        if ($method === 'POST') {
            $data = normalize_project_dates(input());
            $data['created_by'] = $user['sub'];
            $errors = array_merge(validate_project($data), validate_initial_server_billing($data));
            if ($errors) json_response(false, 'Validation failed', null, $errors, 422);
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $newId = insert_or_update('projects', $data, $fields);
                $domainBillingId = null;
                $initialBilling = initial_server_billing_payload($data, $newId, (int)$user['sub']);
                if ($initialBilling) {
                    $domainBillingId = insert_or_update('domain_billing_periods', $initialBilling, ['project_id','domain_name','period_label','quote_date','customer_price','customer_due_date','purchase_status','reminder_days_before_due','notes','created_by']);
                    audit_log((int)$user['sub'], 'create', 'domain-billings', $domainBillingId, 'Created initial server billing with project');
                }
                audit_log((int)$user['sub'], 'create', 'projects', $newId, 'Created project ' . $data['project_code']);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            json_response(true, $domainBillingId ? 'Project and initial server billing created' : 'Project created', ['id' => $newId, 'domain_billing_id' => $domainBillingId], [], 201);
        }
        if ($method === 'PUT' && $id) {
            if (!record_exists('projects', $id)) json_response(false, 'Project not found', null, [], 404);
            $data = normalize_project_dates(input());
            $errors = validate_project($data);
            if ($errors) json_response(false, 'Validation failed', null, $errors, 422);
            $pdo = db();
            $pdo->beginTransaction();
            try {
                insert_or_update('projects', $data, $fields, $id);
                audit_log((int)$user['sub'], 'update', 'projects', $id, 'Updated project');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            json_response(true, 'Project updated', ['id' => $id]);
        }
        if ($method === 'DELETE' && $id) {
            if (!record_exists('projects', $id)) json_response(false, 'Project not found', null, [], 404);
            $pdo = db();
            $pdo->beginTransaction();
            try {
                execute_sql('DELETE FROM resolved_reminders WHERE (reminder_type IN ("project","domain") AND record_id = ?) OR (reminder_type = "fee" AND record_id IN (SELECT id FROM recurring_fees WHERE project_id = ?)) OR (reminder_type = "invoice" AND record_id IN (SELECT id FROM invoices WHERE project_id = ?)) OR (reminder_type IN ("domain-payment","domain-renewal","domain-customer-renewal") AND record_id IN (SELECT id FROM domain_billing_periods WHERE project_id=?))', [$id,$id,$id,$id]);
                execute_sql('DELETE FROM projects WHERE id = ?', [$id]);
                audit_log((int)$user['sub'], 'delete', 'projects', $id, 'Deleted project');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            json_response(true, 'Project deleted');
        }
    }

    $crud = [
        'payments' => ['table' => 'payments', 'fields' => ['project_id','payment_date','amount','payment_type','payment_method','payment_scope','domain_billing_period_id','is_historical','financial_account_id','reference_number','received_by','notes'], 'required' => ['project_id','payment_date','amount','payment_type','payment_method'], 'list' => 'SELECT pay.*, p.project_code, p.project_name, p.customer_company_name, u.name recorded_by_name, fa.name financial_account_name, d.domain_name, d.period_label domain_period_label FROM payments pay JOIN projects p ON p.id = pay.project_id LEFT JOIN financial_accounts fa ON fa.id = pay.financial_account_id LEFT JOIN users u ON u.id = pay.received_by LEFT JOIN domain_billing_periods d ON d.id=pay.domain_billing_period_id', 'one' => 'SELECT pay.*, p.project_code, p.project_name, p.customer_company_name, u.name recorded_by_name, fa.name financial_account_name, d.domain_name, d.period_label domain_period_label FROM payments pay JOIN projects p ON p.id = pay.project_id LEFT JOIN financial_accounts fa ON fa.id = pay.financial_account_id LEFT JOIN users u ON u.id = pay.received_by LEFT JOIN domain_billing_periods d ON d.id=pay.domain_billing_period_id WHERE pay.id = ?'],
        'expenses' => ['table' => 'expenses', 'fields' => ['project_id','expense_date','expense_category','amount','paid_to','payment_method','reference_number','domain_billing_period_id','notes','created_by'], 'required' => ['project_id','expense_date','expense_category','amount'], 'list' => 'SELECT e.*, p.project_code, p.project_name, p.customer_company_name, u.name created_by_name, d.domain_name, d.period_label domain_period_label FROM expenses e JOIN projects p ON p.id = e.project_id LEFT JOIN users u ON u.id = e.created_by LEFT JOIN domain_billing_periods d ON d.id=e.domain_billing_period_id', 'one' => 'SELECT e.*, p.project_code, p.project_name, p.customer_company_name, u.name created_by_name, d.domain_name, d.period_label domain_period_label FROM expenses e JOIN projects p ON p.id = e.project_id LEFT JOIN users u ON u.id = e.created_by LEFT JOIN domain_billing_periods d ON d.id=e.domain_billing_period_id WHERE e.id = ?'],
        'recurring-fees' => ['table' => 'recurring_fees', 'fields' => ['project_id','fee_name','fee_type','amount','billing_cycle','last_paid_date','next_due_date','reminder_days_before_due','status','auto_create_reminder','notes'], 'required' => ['project_id','fee_name','fee_type','amount','billing_cycle','next_due_date'], 'list' => recurring_fee_list_sql(), 'one' => 'SELECT r.*, r.id source_id, 0 is_read_only, p.project_code, p.project_name, p.customer_company_name FROM recurring_fees r JOIN projects p ON p.id = r.project_id WHERE r.id = ?'],
        'invoices' => ['table' => 'invoices', 'fields' => ['invoice_number','project_id','invoice_date','due_date','invoice_type','subtotal','discount_amount','tax_amount','total_amount','paid_amount','balance_amount','status','notes','created_by'], 'required' => ['project_id','invoice_date','invoice_type'], 'list' => 'SELECT i.*, p.project_code, p.project_name, p.customer_company_name FROM invoices i JOIN projects p ON p.id = i.project_id ORDER BY i.invoice_date DESC, i.id DESC', 'one' => 'SELECT i.*, p.project_code, p.project_name, p.customer_company_name, p.contact_person, p.contact_phone, p.contact_email, p.customer_address, p.currency FROM invoices i JOIN projects p ON p.id = i.project_id WHERE i.id = ?'],
        'receipts' => ['table' => 'receipts', 'fields' => ['receipt_number','project_id','payment_id','receipt_date','amount','payment_method','received_from','received_by','notes'], 'required' => ['project_id','payment_id','receipt_date','amount'], 'list' => 'SELECT r.*, p.project_code, p.project_name, p.customer_company_name, u.name received_by_name FROM receipts r JOIN projects p ON p.id = r.project_id LEFT JOIN users u ON u.id = r.received_by ORDER BY r.receipt_date DESC, r.id DESC', 'one' => 'SELECT r.*, p.project_code, p.project_name, p.customer_company_name, p.contact_phone, pay.reference_number, u.name received_by_name FROM receipts r JOIN projects p ON p.id = r.project_id JOIN payments pay ON pay.id = r.payment_id LEFT JOIN users u ON u.id = r.received_by WHERE r.id = ?'],
    ];

    if (isset($crud[$resource])) {
        $def = $crud[$resource];
        if ($resource === 'receipts' && ($parts[2] ?? '') === 'print') {
            json_response(false, 'Printable receipts are not supported', null, [], 404);
        }
        if ($resource === 'invoices' && ($parts[1] ?? '') === 'next-number' && $method === 'GET') {
            $invoiceDate = (string)($_GET['invoice_date'] ?? date('Y-m-d'));
            $dateErrors = [];
            valid_date($dateErrors, ['invoice_date' => $invoiceDate], 'invoice_date');
            if ($dateErrors) json_response(false, 'Validation failed', null, $dateErrors, 422);
            json_response(true, 'Next invoice number', ['invoice_number' => next_invoice_number(false, $invoiceDate)]);
        }
        if ($resource === 'receipts' && ($parts[1] ?? '') === 'next-number' && $method === 'GET') {
            json_response(true, 'Next receipt number', ['receipt_number' => next_receipt_number(false)]);
        }
        if ($resource === 'recurring-fees' && $id && ($parts[2] ?? '') === 'mark-paid' && $method === 'POST') {
            require_write($user);
            $fee = select_one('SELECT * FROM recurring_fees WHERE id = ?', [$id]);
            if (!$fee) json_response(false, 'Recurring fee not found', null, [], 404);
            if ($fee['status'] === 'Cancelled' || ($fee['status'] === 'Paid' && $fee['billing_cycle'] === 'One Time')) json_response(false, 'This recurring fee cannot be marked paid again.', null, [], 422);
            $data = input();
            $paymentErrors = [];
            if (!empty($data['create_payment']) && empty($data['financial_account_id'])) $paymentErrors['financial_account_id'] = 'Choose who received the money.';
            if (!empty($data['create_payment']) && !empty($data['financial_account_id']) && !select_one('SELECT id FROM financial_accounts WHERE id = ? AND status = "Active"', [(int)$data['financial_account_id']])) $paymentErrors['financial_account_id'] = 'Choose an active receiving account.';
            if (!empty($paymentErrors)) json_response(false, 'Validation failed', null, $paymentErrors, 422);
            $pdo = db();
            $pdo->beginTransaction();
            try {
                if (!empty($data['create_payment'])) {
                    $recurringPayment = ['project_id' => $fee['project_id'], 'payment_date' => date('Y-m-d'), 'amount' => $fee['amount'], 'payment_type' => in_array($fee['fee_type'], ['Domain','Hosting','Server'], true) ? $fee['fee_type'] : 'Other', 'payment_method' => 'Other', 'payment_scope' => 'Recurring', 'financial_account_id' => $data['financial_account_id'], 'received_by' => $user['sub'], 'notes' => 'Payment from recurring fee: ' . $fee['fee_name']];
                    $paymentId = insert_or_update('payments', $recurringPayment, $crud['payments']['fields']);
                    sync_payment_receive($paymentId, $recurringPayment, (int)$user['sub'], 'Customer recurring-fee payment');
                    audit_log((int)$user['sub'], 'create', 'payments', $paymentId, 'Created payment from recurring fee');
                }
                $nextStatus = $fee['billing_cycle'] === 'One Time' ? 'Paid' : 'Not Due';
                execute_sql('UPDATE recurring_fees SET last_paid_date = ?, next_due_date = ?, status = ? WHERE id = ?', [date('Y-m-d'), next_due_date($fee['next_due_date'], $fee['billing_cycle']), $nextStatus, $id]);
                audit_log((int)$user['sub'], 'mark-paid', 'recurring-fees', $id, 'Marked recurring fee as paid');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            json_response(true, 'Recurring fee marked as paid');
        }
        if ($method === 'GET' && !$id) {
            if (($_GET['compact'] ?? '') === '1' && $resource === 'payments') {
                $compactWhere = ($_GET['project_id'] ?? '') !== '' ? ' WHERE pay.project_id = ?' : '';
                $compactParams = $compactWhere ? [(int)$_GET['project_id']] : [];
                json_response(true, 'Payment options', select_all('SELECT pay.id, pay.project_id, pay.payment_date, pay.amount, pay.payment_type, pay.payment_method, pay.reference_number, GREATEST(pay.amount - COALESCE(r.receipted_amount, 0), 0) available_amount FROM payments pay LEFT JOIN (SELECT payment_id, SUM(amount) receipted_amount FROM receipts GROUP BY payment_id) r ON r.payment_id = pay.id' . $compactWhere . ' ORDER BY pay.payment_date DESC, pay.id DESC LIMIT 500', $compactParams));
            }
            $filterMap = match ($resource) {
                'payments' => ['project_id' => 'project_id', 'financial_account_id' => 'financial_account_id'],
                'expenses' => ['project_id' => 'project_id', 'expense_category' => 'expense_category', 'payment_method' => 'payment_method'],
                'recurring-fees' => ['project_id' => 'project_id', 'fee_type' => 'fee_type', 'source_type' => 'source_type', 'status' => 'effective_status'],
                'invoices' => ['project_id' => 'project_id', 'invoice_type' => 'invoice_type', 'status' => 'status'],
                'receipts' => ['project_id' => 'project_id', 'payment_method' => 'payment_method'],
                default => [],
            };
            $searchColumns = match ($resource) {
                'payments' => ['project_code','project_name','customer_company_name','financial_account_name','reference_number'],
                'expenses' => ['project_code','project_name','customer_company_name','paid_to','reference_number'],
                'recurring-fees' => ['project_code','project_name','customer_company_name','fee_name','fee_type'],
                'invoices' => ['invoice_number','project_code','project_name','customer_company_name'],
                'receipts' => ['receipt_number','project_code','project_name','customer_company_name','received_from'],
                default => [],
            };
            $dateColumn = match ($resource) { 'payments' => 'payment_date', 'expenses' => 'expense_date', 'recurring-fees' => 'next_due_date', 'invoices' => 'invoice_date', 'receipts' => 'receipt_date', default => null };
            $orderBy = match ($resource) { 'payments' => 'payment_date DESC, id DESC', 'expenses' => 'expense_date DESC, id DESC', 'recurring-fees' => 'next_due_date ASC, id DESC', 'invoices' => 'invoice_date DESC, id DESC', 'receipts' => 'receipt_date DESC, id DESC', default => 'id DESC' };
            [$where, $params] = query_list_filters($filterMap, $searchColumns, $dateColumn);
            $result = paginated_select($def['list'], $where, $params, $orderBy);
            if ($resource === 'recurring-fees') {
                $normalizeFee = function ($row) { $row['status'] = $row['effective_status']; unset($row['effective_status']); return $row; };
                if (isset($result['rows'])) $result['rows'] = array_map($normalizeFee, $result['rows']);
                else $result = array_map($normalizeFee, $result);
            }
            json_response(true, 'Records', $result);
        }
        if ($method === 'GET' && $id) {
            $row = select_one($def['one'], [$id]);
            if (!$row) json_response(false, 'Record not found', null, [], 404);
            if ($resource === 'invoices') {
                $row['items'] = select_all('SELECT * FROM invoice_items WHERE invoice_id = ?', [$id]);
                $row['settings'] = array_column(select_all('SELECT setting_value, setting_key FROM settings'), 'setting_value', 'setting_key');
            }
            if ($resource === 'invoices' && ($parts[2] ?? '') === 'print') $row['company_name'] = select_one('SELECT setting_value FROM settings WHERE setting_key = "company_name"')['setting_value'] ?? 'Your Company Name';
            json_response(true, 'Record detail', $row);
        }
        require_write($user);
        if ($method === 'POST' || ($method === 'PUT' && $id)) {
            $data = input();
            $existingRecord = $method === 'PUT' ? select_one('SELECT * FROM ' . $def['table'] . ' WHERE id=?', [$id]) : null;
            if ($method === 'PUT' && !$existingRecord) json_response(false, 'Record not found', null, [], 404);
            if ($resource === 'expenses' && !empty($existingRecord['domain_billing_period_id'])) json_response(false, 'Registrar purchase expenses are managed from Domain Billing.', null, [], 422);
            if ($method === 'PUT') $data['id'] = $id;
            if ($resource === 'payments') {
                $data['received_by'] = $user['sub'];
                $historicalValue = $data['is_historical'] ?? 0;
                $data['is_historical'] = in_array($historicalValue, [1,'1',true], true) ? 1 : (in_array($historicalValue, [0,'0',false], true) ? 0 : $historicalValue);
                if ((string)$data['is_historical'] === '1') $data['financial_account_id'] = null;
                if (!empty($existingRecord['domain_billing_period_id'])) {
                    $billing = select_one('SELECT * FROM domain_billing_periods WHERE id=?', [(int)$existingRecord['domain_billing_period_id']]);
                    if (!$billing) json_response(false, 'Linked domain billing period not found.', null, [], 422);
                    $data['project_id'] = $billing['project_id'];
                    $data['payment_type'] = 'Domain';
                    $data['payment_method'] = 'Other';
                    $data['payment_scope'] = 'Domain';
                    $data['domain_billing_period_id'] = $billing['id'];
                } elseif ($existingRecord && $existingRecord['payment_scope'] === 'Recurring') {
                    $data['payment_scope'] = 'Recurring';
                    $data['domain_billing_period_id'] = null;
                } else {
                    $data['payment_scope'] = 'Project';
                    $data['domain_billing_period_id'] = null;
                }
            }
            if (in_array($resource, ['expenses','invoices'], true)) $data['created_by'] = $user['sub'];
            if ($resource === 'receipts') $data['received_by'] = $user['sub'];
            $errors = validate_resource_data($resource, $data, $def['required']);
            if ($resource === 'payments' && !empty($data['domain_billing_period_id'])) {
                $otherPaid = money(select_one('SELECT COALESCE(SUM(amount),0) total FROM payments WHERE domain_billing_period_id=? AND id<>?', [(int)$data['domain_billing_period_id'], $id ?? 0])['total']);
                if ($otherPaid + (float)($data['amount'] ?? 0) > (float)$billing['customer_price']) $errors['amount'] = 'Total domain payments cannot exceed the customer domain price.';
            }
            if ($resource === 'invoices') {
                $items = array_map(fn($item) => array_merge($item, ['quantity' => round((float)($item['quantity'] ?? 0), 2), 'unit_price' => money($item['unit_price'] ?? 0)]), $data['items'] ?? []);
                $data['items'] = $items;
                foreach (['discount_amount','tax_amount','paid_amount'] as $field) $data[$field] = money($data[$field] ?? 0);
                $subtotal = array_reduce($items, fn($sum, $item) => $sum + money((float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0)), 0.0);
                $data['subtotal'] = money($subtotal);
                $data['total_amount'] = money($subtotal - (float)($data['discount_amount'] ?? 0) + (float)($data['tax_amount'] ?? 0));
                $data['balance_amount'] = money($data['total_amount'] - (float)($data['paid_amount'] ?? 0));
                if ($data['total_amount'] < 0) $errors['discount_amount'] = 'Discount cannot make the invoice total negative.';
                if ((float)($data['paid_amount'] ?? 0) > $data['total_amount']) $errors['paid_amount'] = 'Paid amount cannot exceed the invoice total.';
                if (($data['status'] ?? '') !== 'Cancelled') {
                    if ($data['balance_amount'] <= 0) $data['status'] = 'Paid';
                    elseif ((float)($data['paid_amount'] ?? 0) > 0) $data['status'] = 'Partially Paid';
                    elseif (!empty($data['due_date']) && $data['due_date'] < date('Y-m-d')) $data['status'] = 'Overdue';
                }
            }
            if ($errors) json_response(false, 'Validation failed', null, $errors, 422);
            $pdo = db();
            $pdo->beginTransaction();
            try {
                if ($resource === 'invoices') {
                    if ($method === 'POST') $data['invoice_number'] = next_invoice_number(true, $data['invoice_date']);
                    else unset($data['invoice_number']);
                }
                if ($resource === 'receipts') {
                    if ($method === 'POST') $data['receipt_number'] = next_receipt_number(true);
                    else unset($data['receipt_number']);
                    $payment = select_one('SELECT payment_method FROM payments WHERE id = ?', [(int)$data['payment_id']]);
                    if (empty($data['payment_method'])) $data['payment_method'] = $payment['payment_method'] ?? 'Other';
                }
                $newId = insert_or_update($def['table'], $data, $def['fields'], $method === 'PUT' ? $id : null);
                if ($resource === 'payments') {
                    $receiveNotes = $data['payment_scope'] === 'Domain' ? 'Customer domain payment' : ($data['payment_scope'] === 'Recurring' ? 'Customer recurring-fee payment' : 'Project payment');
                    sync_payment_receive($newId, $data, (int)$user['sub'], $receiveNotes);
                }
                if ($resource === 'invoices') {
                    execute_sql('DELETE FROM invoice_items WHERE invoice_id = ?', [$newId]);
                    foreach (($data['items'] ?? []) as $item) {
                        insert_or_update('invoice_items', ['invoice_id' => $newId, 'description' => trim((string)$item['description']), 'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'total_price' => money((float)$item['quantity'] * (float)$item['unit_price'])], ['invoice_id','description','quantity','unit_price','total_price']);
                    }
                }
                audit_log((int)$user['sub'], $method === 'POST' ? 'create' : 'update', $resource, $newId, ($method === 'POST' ? 'Created ' : 'Updated ') . $resource . ' record');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            json_response(true, $method === 'POST' ? 'Record created' : 'Record updated', ['id' => $newId], [], $method === 'POST' ? 201 : 200);
        }
        if ($method === 'DELETE' && $id) {
            if (!record_exists($def['table'], $id)) json_response(false, 'Record not found', null, [], 404);
            if ($resource === 'expenses' && !empty(select_one('SELECT domain_billing_period_id FROM expenses WHERE id=?', [$id])['domain_billing_period_id'])) json_response(false, 'Registrar purchase expenses are managed from Domain Billing.', null, [], 422);
            $pdo = db();
            $pdo->beginTransaction();
            try {
                if (in_array($resource, ['recurring-fees','invoices'], true)) execute_sql('DELETE FROM resolved_reminders WHERE reminder_type = ? AND record_id = ?', [$resource === 'recurring-fees' ? 'fee' : 'invoice', $id]);
                execute_sql('DELETE FROM ' . $def['table'] . ' WHERE id = ?', [$id]);
                audit_log((int)$user['sub'], 'delete', $resource, $id, 'Deleted record');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            json_response(true, 'Record deleted');
        }
    }

    if ($resource === 'dashboard' && ($parts[1] ?? '') === 'summary') {
        $projectTotals = select_one('SELECT COUNT(*) total_projects, SUM(status IN ("New","In Progress","Waiting Payment","Delivered","On Hold")) active_projects, SUM(status = "Completed") completed_projects, COALESCE(SUM(total_payable),0) total_payable, COALESCE(SUM(total_paid),0) total_paid, COALESCE(SUM(GREATEST(remaining_balance,0)),0) total_outstanding, SUM(payment_status = "Overdue") overdue_payments FROM (' . project_finance_sql() . ') dashboard_projects');
        $allExpenses = money(select_one('SELECT COALESCE(SUM(amount),0) total FROM expenses')['total']);
        $allReceived = money(select_one('SELECT COALESCE(SUM(amount),0) total FROM payments')['total']);
        $domainOutstanding = money(select_one('SELECT COALESCE(SUM(GREATEST(customer_balance_amount,0)),0) total FROM (' . domain_billing_select_sql() . ') dashboard_domains WHERE purchase_status<>"Cancelled"')['total']);
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $income = money(select_one('SELECT COALESCE(SUM(amount),0) total FROM payments WHERE payment_date BETWEEN ? AND ?', [$monthStart, $monthEnd])['total']);
        $expenses = money(select_one('SELECT COALESCE(SUM(amount),0) total FROM expenses WHERE expense_date BETWEEN ? AND ?', [$monthStart, $monthEnd])['total']);
        $upcomingFees = (int)select_one('SELECT COUNT(*) total FROM recurring_fees WHERE auto_create_reminder=1 AND status NOT IN ("Paid","Cancelled") AND next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)')['total'];
        $upcomingDomains = (int)select_one('SELECT (SELECT COUNT(*) FROM domain_billing_periods WHERE purchase_status="Purchased" AND renewal_reminder_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) + (SELECT COUNT(*) FROM domain_billing_periods d WHERE d.purchase_status="Purchased" AND d.customer_renewal_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND NOT EXISTS (SELECT 1 FROM domain_billing_periods next_period WHERE next_period.id<>d.id AND next_period.project_id=d.project_id AND COALESCE(next_period.domain_name,"")=COALESCE(d.domain_name,"") AND next_period.quote_date>=d.customer_renewal_date)) + (SELECT COUNT(*) FROM projects p WHERE p.domain_reminder_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND NOT EXISTS (SELECT 1 FROM domain_billing_periods d WHERE d.project_id=p.id)) total')['total'];
        json_response(true, 'Dashboard summary', ['total_projects' => (int)$projectTotals['total_projects'], 'active_projects' => (int)$projectTotals['active_projects'], 'completed_projects' => (int)$projectTotals['completed_projects'], 'total_contract_value' => money($projectTotals['total_payable']), 'total_received' => $allReceived, 'total_outstanding' => money((float)$projectTotals['total_outstanding'] + $domainOutstanding), 'project_outstanding' => money($projectTotals['total_outstanding']), 'domain_outstanding' => $domainOutstanding, 'this_month_income' => $income, 'this_month_expenses' => $expenses, 'net_profit' => money($allReceived - $allExpenses), 'overdue_payments' => (int)$projectTotals['overdue_payments'], 'server_fees_due_this_month' => (int)select_one('SELECT COUNT(*) total FROM recurring_fees WHERE fee_type IN ("Hosting","VPS","Server") AND status NOT IN ("Paid","Cancelled") AND next_due_date BETWEEN ? AND ?', [$monthStart, $monthEnd])['total'], 'upcoming_renewals' => $upcomingFees + $upcomingDomains]);
    }
    if ($resource === 'dashboard' && ($parts[1] ?? '') === 'charts') {
        $statusRows = select_all('SELECT payment_status, COUNT(*) total FROM (' . project_finance_sql() . ') chart_projects GROUP BY payment_status');
        json_response(true, 'Dashboard charts', ['monthly_income' => select_all("SELECT DATE_FORMAT(payment_date, '%Y-%m') month, SUM(amount) total FROM payments GROUP BY month ORDER BY month DESC LIMIT 12"), 'monthly_expenses' => select_all("SELECT DATE_FORMAT(expense_date, '%Y-%m') month, SUM(amount) total FROM expenses GROUP BY month ORDER BY month DESC LIMIT 12"), 'payment_statuses' => array_column($statusRows, 'total', 'payment_status'), 'recurring_due' => select_all('SELECT fee_type, COUNT(*) total FROM recurring_fees WHERE status NOT IN ("Paid","Cancelled") GROUP BY fee_type')]);
    }
    if ($resource === 'dashboard' && ($parts[1] ?? '') === 'recent-activity') {
        $upcoming = select_all('(SELECT r.id, r.fee_name, r.amount, r.next_due_date, p.project_name, p.customer_company_name FROM recurring_fees r JOIN projects p ON p.id = r.project_id WHERE r.auto_create_reminder = 1 AND r.status NOT IN ("Paid","Cancelled") AND r.next_due_date >= CURDATE()) UNION ALL (SELECT d.id, CONCAT("Registrar expiry: ", COALESCE(d.domain_name,d.period_label,"Domain")), d.actual_registrar_cost, d.renewal_reminder_date, p.project_name, p.customer_company_name FROM domain_billing_periods d JOIN projects p ON p.id=d.project_id WHERE d.purchase_status="Purchased" AND d.renewal_reminder_date>=CURDATE()) UNION ALL (SELECT d.id, CONCAT("Charge customer: ", COALESCE(d.domain_name,d.period_label,"Domain")), d.customer_price, d.customer_renewal_date, p.project_name, p.customer_company_name FROM domain_billing_periods d JOIN projects p ON p.id=d.project_id WHERE d.purchase_status="Purchased" AND d.customer_renewal_date>=CURDATE() AND NOT EXISTS (SELECT 1 FROM domain_billing_periods next_period WHERE next_period.id<>d.id AND next_period.project_id=d.project_id AND COALESCE(next_period.domain_name,"")=COALESCE(d.domain_name,"") AND next_period.quote_date>=d.customer_renewal_date)) UNION ALL (SELECT p.id, CONCAT("Domain: ", COALESCE(p.domain_name,"Renewal")), p.domain_server_price, p.domain_reminder_date, p.project_name, p.customer_company_name FROM projects p WHERE p.domain_reminder_date >= CURDATE() AND NOT EXISTS (SELECT 1 FROM domain_billing_periods d WHERE d.project_id=p.id)) ORDER BY next_due_date ASC LIMIT 8');
        json_response(true, 'Recent activity', ['recent_payments' => select_all('SELECT pay.*, p.project_name, fa.name financial_account_name FROM payments pay JOIN projects p ON p.id = pay.project_id LEFT JOIN financial_accounts fa ON fa.id = pay.financial_account_id ORDER BY pay.created_at DESC LIMIT 5'), 'recent_projects' => select_all('SELECT * FROM projects ORDER BY created_at DESC LIMIT 5'), 'upcoming_renewals' => $upcoming, 'overdue_balances' => select_all('SELECT * FROM (' . project_finance_sql() . ') overdue_projects WHERE payment_status = "Overdue" ORDER BY payment_due_date, id LIMIT 8')]);
    }
    if ($resource === 'reminders') {
        if (($parts[1] ?? '') === 'resolve' && $method === 'POST') {
            require_write($user);
            $data = input();
            $errors = required($data, ['source_type','record_id','due_date']);
            one_of($errors, $data, 'source_type', ['project','domain','domain-payment','domain-customer-renewal','domain-renewal','fee','invoice']);
            valid_date($errors, $data, 'due_date');
            if (!$errors && !reminder_is_resolved($data['source_type'], (int)$data['record_id'], $data['due_date']) && !reminder_target_exists($data['source_type'], (int)$data['record_id'], $data['due_date'])) $errors['record_id'] = 'The reminder target does not exist or is no longer active.';
            if ($errors) json_response(false, 'Validation failed', null, $errors, 422);
            execute_sql('INSERT INTO resolved_reminders (reminder_type, record_id, due_date, resolved_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE resolved_by = VALUES(resolved_by), resolved_at = CURRENT_TIMESTAMP', [$data['source_type'], (int)$data['record_id'], $data['due_date'], $user['sub']]);
            audit_log((int)$user['sub'], 'resolve', 'reminders', (int)$data['record_id'], 'Resolved ' . $data['source_type'] . ' reminder');
            json_response(true, 'Reminder resolved');
        }
        if ($method === 'GET') json_response(true, 'Reminders', reminder_data());
    }
    if ($resource === 'reports') {
        $kind = $parts[1] ?? 'project-financial';
        if ($kind === 'financial-overview') json_response(true, 'Financial overview', financial_overview());
        $allowedReports = ['project-financial','payment-collection','outstanding-balance','expense','profit','recurring-fees','domain-billing','invoice','monthly-income-expense'];
        if (!in_array($kind, $allowedReports, true)) json_response(false, 'Report not found', null, [], 404);
        if ($kind !== 'monthly-income-expense') {
            $_GET['page'] = max(1, (int)($_GET['page'] ?? 1));
            $_GET['limit'] = min(500, max(1, (int)($_GET['limit'] ?? 100)));
        }
        if (in_array($kind, ['project-financial','outstanding-balance','profit'], true)) {
            if ($kind === 'outstanding-balance') $_GET['outstanding_only'] = '1';
            $data = project_list();
        } elseif ($kind === 'monthly-income-expense') {
            $incomeWhere = [];
            $expenseWhere = [];
            $incomeParams = [];
            $expenseParams = [];
            if (!empty($_GET['date_from'])) { $incomeWhere[] = 'payment_date >= ?'; $expenseWhere[] = 'expense_date >= ?'; $incomeParams[] = $_GET['date_from']; $expenseParams[] = $_GET['date_from']; }
            if (!empty($_GET['date_to'])) { $incomeWhere[] = 'payment_date <= ?'; $expenseWhere[] = 'expense_date <= ?'; $incomeParams[] = $_GET['date_to']; $expenseParams[] = $_GET['date_to']; }
            if (!empty($_GET['project_id'])) {
                $incomeWhere[] = 'project_id = ?';
                $expenseWhere[] = 'project_id = ?';
                $incomeParams[] = (int)$_GET['project_id'];
                $expenseParams[] = (int)$_GET['project_id'];
            }
            $data = [
                'income' => select_all("SELECT DATE_FORMAT(payment_date, '%Y-%m') month, SUM(amount) total FROM payments" . ($incomeWhere ? ' WHERE ' . implode(' AND ', $incomeWhere) : '') . ' GROUP BY month ORDER BY month DESC', $incomeParams),
                'expenses' => select_all("SELECT DATE_FORMAT(expense_date, '%Y-%m') month, SUM(amount) total FROM expenses" . ($expenseWhere ? ' WHERE ' . implode(' AND ', $expenseWhere) : '') . ' GROUP BY month ORDER BY month DESC', $expenseParams),
            ];
        } elseif ($kind === 'domain-billing') {
            $where = [];
            $params = [];
            if (($_GET['project_id'] ?? '') !== '') { $where[]='list_rows.project_id=?'; $params[]=(int)$_GET['project_id']; }
            if (in_array($_GET['payment_status'] ?? '', ['Not Priced','Unpaid','Partially Paid','Paid'], true)) { $where[]='list_rows.customer_payment_status=?'; $params[]=$_GET['payment_status']; }
            if (in_array($_GET['purchase_status'] ?? '', ['Not Purchased','Active','Expired','Cancelled'], true)) { $where[]='list_rows.effective_purchase_status=?'; $params[]=$_GET['purchase_status']; }
            if (($_GET['date_from'] ?? '') !== '') { $where[]='list_rows.quote_date>=?'; $params[]=$_GET['date_from']; }
            if (($_GET['date_to'] ?? '') !== '') { $where[]='list_rows.quote_date<=?'; $params[]=$_GET['date_to']; }
            $data = paginated_select(domain_billing_select_sql(), $where, $params, 'quote_date DESC, id DESC');
        } else {
            $source = match ($kind) { 'payment-collection' => 'payments', 'expense' => 'expenses', 'recurring-fees' => 'recurring-fees', default => 'invoices' };
            $reportFilterMap = match ($source) {
                'payments' => ['project_id' => 'project_id', 'financial_account_id' => 'financial_account_id'],
                'expenses' => ['project_id' => 'project_id', 'expense_category' => 'expense_category', 'payment_method' => 'payment_method'],
                'recurring-fees' => ['project_id' => 'project_id', 'fee_type' => 'fee_type', 'status' => 'effective_status'],
                default => ['project_id' => 'project_id', 'invoice_type' => 'invoice_type', 'status' => 'status'],
            };
            $reportDateColumn = match ($source) { 'payments' => 'payment_date', 'expenses' => 'expense_date', 'recurring-fees' => 'next_due_date', default => 'invoice_date' };
            $reportOrder = match ($source) { 'payments' => 'payment_date DESC, id DESC', 'expenses' => 'expense_date DESC, id DESC', 'recurring-fees' => 'next_due_date ASC, id DESC', default => 'invoice_date DESC, id DESC' };
            [$reportWhere, $reportParams] = query_list_filters($reportFilterMap, [], $reportDateColumn);
            $data = paginated_select($crud[$source]['list'], $reportWhere, $reportParams, $reportOrder);
            if ($source === 'recurring-fees') {
                $normalizeReportFee = function ($row) { $row['status'] = $row['effective_status']; unset($row['effective_status']); return $row; };
                if (isset($data['rows'])) $data['rows'] = array_map($normalizeReportFee, $data['rows']);
                else $data = array_map($normalizeReportFee, $data);
            }
        }
        json_response(true, 'Report data', $data);
    }
    if ($resource === 'users') {
        require_admin($user);
        if ($method === 'GET') json_response(true, 'Users', select_all('SELECT id, name, email, role, status, created_at, updated_at FROM users ORDER BY name'));
        $data = input();
        if ($method === 'POST') {
            $errors = required($data, ['name','email','password','role','status']);
            max_length($errors, $data, 'name', 150);
            max_length($errors, $data, 'email', 150);
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
            valid_password($errors, $data);
            one_of($errors, $data, 'role', ['Admin','Staff','Viewer']);
            one_of($errors, $data, 'status', ['Active','Inactive']);
            if ($errors) json_response(false, 'Validation failed', null, $errors, 422);
            execute_sql('INSERT INTO users (name,email,password_hash,role,status) VALUES (?,?,?,?,?)', [$data['name'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT), $data['role'], $data['status']]);
            $newId = (int)db()->lastInsertId();
            audit_log((int)$user['sub'], 'create', 'users', $newId, 'Created user ' . $data['email']);
            json_response(true, 'User created', ['id' => $newId], [], 201);
        }
        if ($method === 'PUT' && $id) {
            $existing = select_one('SELECT * FROM users WHERE id = ?', [$id]);
            if (!$existing) json_response(false, 'User not found', null, [], 404);
            $errors = required($data, ['name','email','role','status']);
            max_length($errors, $data, 'name', 150);
            max_length($errors, $data, 'email', 150);
            if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
            valid_password($errors, $data, 'password', false);
            one_of($errors, $data, 'role', ['Admin','Staff','Viewer']);
            one_of($errors, $data, 'status', ['Active','Inactive']);
            if ($existing['role'] === 'Admin' && $existing['status'] === 'Active' && ($data['role'] !== 'Admin' || $data['status'] !== 'Active')) {
                $activeAdmins = (int)select_one('SELECT COUNT(*) total FROM users WHERE role = "Admin" AND status = "Active"')['total'];
                if ($activeAdmins <= 1) $errors['role'] = 'At least one active Admin is required.';
            }
            if ($errors) json_response(false, 'Validation failed', null, $errors, 422);
            if (!empty($data['password'])) {
                execute_sql('UPDATE users SET name=?, email=?, password_hash=?, role=?, status=? WHERE id=?', [$data['name'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT), $data['role'], $data['status'], $id]);
            } else {
                execute_sql('UPDATE users SET name=?, email=?, role=?, status=? WHERE id=?', [$data['name'], $data['email'], $data['role'], $data['status'], $id]);
            }
            audit_log((int)$user['sub'], 'update', 'users', $id, 'Updated user ' . $data['email']);
            json_response(true, 'User updated');
        }
        if ($method === 'DELETE' && $id) {
            $existing = select_one('SELECT * FROM users WHERE id = ?', [$id]);
            if (!$existing) json_response(false, 'User not found', null, [], 404);
            if ((int)$user['sub'] === $id) json_response(false, 'You cannot delete your own account', null, [], 422);
            if ($existing['role'] === 'Admin' && $existing['status'] === 'Active' && (int)select_one('SELECT COUNT(*) total FROM users WHERE role = "Admin" AND status = "Active"')['total'] <= 1) {
                json_response(false, 'At least one active Admin is required', null, [], 422);
            }
            $references = (int)select_one('SELECT (SELECT COUNT(*) FROM projects WHERE created_by=?) + (SELECT COUNT(*) FROM payments WHERE received_by=?) + (SELECT COUNT(*) FROM expenses WHERE created_by=?) + (SELECT COUNT(*) FROM invoices WHERE created_by=?) + (SELECT COUNT(*) FROM receipts WHERE received_by=?) + (SELECT COUNT(*) FROM financial_transactions WHERE created_by=?) + (SELECT COUNT(*) FROM domain_billing_periods WHERE created_by=?) total', [$id,$id,$id,$id,$id,$id,$id])['total'];
            if ($references > 0) {
                execute_sql('UPDATE users SET status="Inactive" WHERE id=?', [$id]);
                $message = 'User has related records and was deactivated';
            } else {
                execute_sql('DELETE FROM users WHERE id=?', [$id]);
                $message = 'User deleted';
            }
            audit_log((int)$user['sub'], 'delete', 'users', $id, $message);
            json_response(true, $message);
        }
    }
    if ($resource === 'settings') {
        if ($method === 'GET') json_response(true, 'Settings', select_all('SELECT * FROM settings ORDER BY setting_key'));
        require_admin($user);
        $allowedSettings = ['company_name','company_tagline','company_phone','company_telegram','company_email','company_website','payment_method','payment_account','currency','invoice_prefix','receipt_prefix','project_prefix','invoice_design'];
        foreach (input() as $key => $value) {
            if (!in_array($key, $allowedSettings, true)) continue;
            if ($key === 'invoice_design') {
                if (!is_string($value) || strlen($value) > 60000 || json_decode($value, true) === null) json_response(false, 'Invoice design must be valid JSON.', null, ['invoice_design' => 'Invalid invoice design.'], 422);
            } elseif (!is_scalar($value) && $value !== null) {
                json_response(false, 'Settings values must be text.', null, [$key => 'Enter a text value.'], 422);
            } elseif (in_array($key, ['invoice_prefix','receipt_prefix','project_prefix'], true) && strlen((string)$value) > 20) {
                json_response(false, 'Document prefixes must be 20 characters or fewer.', null, [$key => 'Use 20 characters or fewer.'], 422);
            }
            execute_sql('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
        }
        audit_log((int)$user['sub'], 'update', 'settings', null, 'Updated company settings');
        json_response(true, 'Settings saved');
    }

    json_response(false, 'Route not found', null, [], 404);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') json_response(false, 'A record with the same unique value already exists', null, [], 409);
    json_response(false, 'Database operation failed', null, ['detail' => getenv('APP_DEBUG') ? $e->getMessage() : 'Check server logs.'], 500);
} catch (Throwable $e) {
    json_response(false, 'Server error', null, ['detail' => getenv('APP_DEBUG') ? $e->getMessage() : 'Check server logs.'], 500);
}
