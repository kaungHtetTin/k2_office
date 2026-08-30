<?php
function required(array $data, array $fields): array
{
    $errors = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $errors[$field] = 'This field is required.';
        }
    }
    return $errors;
}

function positive(array &$errors, array $data, string $field, bool $allowZero = false): void
{
    if (isset($data[$field]) && $data[$field] !== '') {
        $value = (float)$data[$field];
        if ($allowZero ? $value < 0 : $value <= 0) {
            $errors[$field] = $allowZero ? 'Must be zero or greater.' : 'Must be greater than zero.';
        }
    }
}

function valid_decimal(array &$errors, array $data, string $field, bool $allowZero = false, float $maximum = 9999999999999.99): void
{
    if (!array_key_exists($field, $data) || $data[$field] === '') return;
    if (!is_numeric($data[$field])) {
        $errors[$field] = 'Enter a valid number.';
        return;
    }
    $value = (float)$data[$field];
    if (!is_finite($value) || ($allowZero ? $value < 0 : $value <= 0)) {
        $errors[$field] = $allowZero ? 'Must be zero or greater.' : 'Must be greater than zero.';
    } elseif ($value > $maximum) {
        $errors[$field] = 'Amount is too large.';
    }
}

function max_length(array &$errors, array $data, string $field, int $maximum): void
{
    if (isset($data[$field]) && mb_strlen((string)$data[$field]) > $maximum) {
        $errors[$field] = "Must be {$maximum} characters or fewer.";
    }
}

function one_of(array &$errors, array $data, string $field, array $allowed): void
{
    if (isset($data[$field]) && $data[$field] !== '' && !in_array($data[$field], $allowed, true)) {
        $errors[$field] = 'Choose a valid value.';
    }
}

function valid_date(array &$errors, array $data, string $field): void
{
    if (empty($data[$field])) return;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$data[$field]);
    if (!$date || $date->format('Y-m-d') !== $data[$field]) {
        $errors[$field] = 'Enter a valid date.';
    }
}

function valid_password(array &$errors, array $data, string $field = 'password', bool $required = true): void
{
    $password = (string)($data[$field] ?? '');
    if (!$required && $password === '') return;
    $minimum = (getenv('APP_ENV') ?: 'development') === 'production' ? 12 : 8;
    $length = strlen($password);
    if ($length < $minimum) $errors[$field] = "Password must be at least {$minimum} characters.";
    elseif ($length > 72) $errors[$field] = 'Password must be 72 characters or fewer.';
}

function validate_project(array $data): array
{
    $errors = required($data, ['project_code', 'project_name', 'status', 'contract_amount']);
    foreach (['contract_amount', 'discount_amount', 'tax_amount', 'upfront_required_amount', 'domain_server_price'] as $field) {
        valid_decimal($errors, $data, $field, true);
    }
    foreach (['project_code' => 50, 'project_name' => 255, 'project_type' => 100, 'customer_company_name' => 255, 'contact_person' => 150, 'contact_phone' => 100, 'contact_email' => 150, 'currency' => 10, 'domain_name' => 255, 'hosting_provider' => 255, 'server_provider' => 255, 'server_ip' => 100, 'git_repository_url' => 255, 'admin_panel_url' => 255, 'production_url' => 255] as $field => $maximum) {
        max_length($errors, $data, $field, $maximum);
    }
    if (!isset($errors['contract_amount'], $errors['discount_amount'], $errors['tax_amount'])) {
        $totalPayable = (float)($data['contract_amount'] ?? 0) - (float)($data['discount_amount'] ?? 0) + (float)($data['tax_amount'] ?? 0);
        if ($totalPayable < 0) $errors['discount_amount'] = 'Discount cannot make the project total negative.';
        if ((float)($data['upfront_required_amount'] ?? 0) > $totalPayable) $errors['upfront_required_amount'] = 'Upfront amount cannot exceed the total payable.';
    }
    if (!empty($data['start_date']) && !empty($data['delivery_date']) && $data['delivery_date'] < $data['start_date']) {
        $errors['delivery_date'] = 'Delivery date cannot be earlier than start date.';
    }
    if (!empty($data['start_date']) && !empty($data['completion_date']) && $data['completion_date'] < $data['start_date']) {
        $errors['completion_date'] = 'Completion date cannot be earlier than start date.';
    }
    if (!empty($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['contact_email'] = 'Enter a valid email address.';
    }
    if (!empty($data['domain_purchase_date']) && !empty($data['domain_reminder_date']) && $data['domain_reminder_date'] < $data['domain_purchase_date']) {
        $errors['domain_reminder_date'] = 'Reminder date cannot be earlier than the purchase date.';
    }
    one_of($errors, $data, 'status', ['New', 'In Progress', 'Waiting Payment', 'Delivered', 'Completed', 'Cancelled', 'On Hold']);
    one_of($errors, $data, 'priority', ['Low', 'Medium', 'High', 'Urgent']);
    foreach (['start_date', 'delivery_date', 'completion_date', 'payment_due_date', 'domain_purchase_date', 'domain_reminder_date', 'domain_payment_date'] as $field) {
        valid_date($errors, $data, $field);
    }
    return $errors;
}
