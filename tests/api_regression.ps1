param(
    [string]$BaseUrl = 'http://localhost/ksspm/backend/public',
    [string]$AdminEmail = 'admin@example.com',
    [string]$AdminPassword = 'admin123456'
)

$ErrorActionPreference = 'Stop'
$script:Assertions = 0
$script:AdminHeaders = @{}

function Assert-True([bool]$Condition, [string]$Message) {
    $script:Assertions++
    if (-not $Condition) { throw "Assertion failed: $Message" }
}

function Assert-Money($Actual, [double]$Expected, [string]$Message) {
    Assert-True ([Math]::Abs([double]$Actual - $Expected) -lt 0.005) "$Message (expected $Expected, got $Actual)"
}

function Read-JwtPayload([string]$Token) {
    $part = $Token.Split('.')[1].Replace('-', '+').Replace('_', '/')
    while ($part.Length % 4) { $part += '=' }
    [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($part)) | ConvertFrom-Json
}

function Invoke-Envelope([string]$Method, [string]$Path, $Body = $null, $Headers = $script:AdminHeaders) {
    $params = @{ Method = $Method; Uri = "$BaseUrl$Path"; Headers = $Headers }
    if ($null -ne $Body) {
        $params.ContentType = 'application/json'
        $params.Body = $Body | ConvertTo-Json -Depth 12 -Compress
    }
    Invoke-RestMethod @params
}

function Invoke-Api([string]$Method, [string]$Path, $Body = $null, $Headers = $script:AdminHeaders) {
    (Invoke-Envelope $Method $Path $Body $Headers).data
}

function Expect-Status([int]$Status, [string]$Method, [string]$Path, $Body = $null, $Headers = $script:AdminHeaders) {
    $script:Assertions++
    try {
        Invoke-Envelope $Method $Path $Body $Headers | Out-Null
        throw "Expected HTTP $Status from $Method $Path"
    } catch {
        if (-not $_.Exception.Response) { throw }
        $actual = [int]$_.Exception.Response.StatusCode
        if ($actual -ne $Status) { throw "Expected HTTP $Status from $Method $Path, got $actual" }
    }
}

$login = Invoke-Envelope 'POST' '/auth/login' @{ email = $AdminEmail; password = $AdminPassword } @{}
$script:AdminHeaders = @{ Authorization = "Bearer $($login.data.token)" }
$sessionPayload = Read-JwtPayload $login.data.token
Assert-True (-not [bool]$sessionPayload.remember_me) 'Normal login token is session-only'
Assert-True (([long]$sessionPayload.exp - [long]$sessionPayload.iat) -le 86400) 'Normal login token lasts no more than one day'
$rememberedLogin = Invoke-Envelope 'POST' '/auth/login' @{ email = $AdminEmail; password = $AdminPassword; remember_me = $true } @{}
$rememberedPayload = Read-JwtPayload $rememberedLogin.data.token
Assert-True ([bool]$rememberedPayload.remember_me) 'Remembered login token is marked persistent'
Assert-True (([long]$rememberedPayload.exp - [long]$rememberedPayload.iat) -ge 2592000) 'Remembered login token receives the long lifetime'
Expect-Status 422 'POST' '/auth/login' @{ email = $AdminEmail; password = $AdminPassword; remember_me = 'invalid' } @{}
$stamp = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()
$today = Get-Date -Format 'yyyy-MM-dd'
$yesterday = (Get-Date).AddDays(-1).ToString('yyyy-MM-dd')
$futureDue = (Get-Date).AddYears(1).ToString('yyyy-MM-dd')
$projectIds = [System.Collections.Generic.List[int]]::new()
$accountIds = [System.Collections.Generic.List[int]]::new()
$transactionIds = [System.Collections.Generic.List[int]]::new()
$userIds = [System.Collections.Generic.List[int]]::new()

try {
    $baseline = Invoke-Api 'GET' '/dashboard/summary'

    $alphaId = [int](Invoke-Api 'POST' '/financial-accounts' @{ name = "Regression Alpha $stamp"; opening_balance = 100; status = 'Active' }).id
    $betaId = [int](Invoke-Api 'POST' '/financial-accounts' @{ name = "Regression Beta $stamp"; opening_balance = 0; status = 'Active' }).id
    $accountIds.Add($alphaId); $accountIds.Add($betaId)
    Expect-Status 422 'POST' '/financial-accounts' @{ name = "Regression Huge $stamp"; opening_balance = '999999999999999999999'; status = 'Active' }

    Expect-Status 422 'POST' '/projects' @{ project_code = "BAD-NUM-$stamp"; project_name = 'Bad'; status = 'New'; contract_amount = 'abc' }
    Expect-Status 422 'POST' '/projects' @{ project_code = "BAD-DISCOUNT-$stamp"; project_name = 'Bad'; status = 'New'; contract_amount = 100; discount_amount = 101; tax_amount = 0 }
    Expect-Status 422 'POST' '/projects' @{ project_code = "BAD-DATE-$stamp"; project_name = 'Bad'; status = 'New'; contract_amount = 100; domain_purchase_date = 'not-a-date' }
    Expect-Status 422 'POST' '/projects' @{ project_code = "BAD-SERVER-$stamp"; project_name = 'Bad Initial Server'; status = 'New'; contract_amount = 0; initial_server_billing_enabled = 1; initial_server_quote_date = $today; initial_server_period_label = 'First Year' }
    $badInitialServerProject = Invoke-Api 'GET' "/projects?search=BAD-SERVER-$stamp&page=1&limit=10"
    Assert-True ($badInitialServerProject.pagination.total -eq 0) 'Invalid initial server billing does not create a project'

    $projectAId = [int](Invoke-Api 'POST' '/projects' @{
        project_code = "REG-A-$stamp"; project_name = 'Regression Project A'; status = 'New'; priority = 'Medium'
        contract_amount = 1000; discount_amount = 100; tax_amount = 50; upfront_required_amount = 200
        payment_due_date = $yesterday; currency = 'MMK'; domain_purchase_date = '2024-02-29'; domain_server_price = 25
        notes = 'Regression project invoice note'
    }).id
    $projectBCreate = Invoke-Api 'POST' '/projects' @{
        project_code = "REG-B-$stamp"; project_name = 'Regression Project B'; status = 'New'; priority = 'Medium'
        contract_amount = 100; discount_amount = 0; tax_amount = 0; upfront_required_amount = 0; currency = 'MMK'
        initial_server_billing_enabled = 1; initial_server_domain_name = 'project-b.example'; initial_server_period_label = 'First Year'
        initial_server_quote_date = $today; initial_server_customer_price = 60; initial_server_customer_due_date = $futureDue; initial_server_reminder_days = 21
    }
    $projectBId = [int]$projectBCreate.id
    $initialServerBillingId = [int]$projectBCreate.domain_billing_id
    $projectIds.Add($projectAId); $projectIds.Add($projectBId)

    $initialServerBilling = Invoke-Api 'GET' "/domain-billings/$initialServerBillingId"
    Assert-True ([int]$initialServerBilling.project_id -eq $projectBId -and $initialServerBilling.purchase_status -eq 'Quoted') 'Creating a project atomically creates its initial quoted server billing'
    Assert-True ($initialServerBilling.domain_name -eq 'project-b.example' -and $initialServerBilling.period_label -eq 'First Year') 'Initial server billing fields persist'
    Assert-Money $initialServerBilling.customer_price 60 'Initial combined domain and hosting server price persists'

    Invoke-Api 'PUT' "/projects/$projectBId" @{ project_code = "REG-B-$stamp"; project_name = 'Regression Project B Updated'; status = 'On Hold'; priority = 'High'; contract_amount = 100; discount_amount = 0; tax_amount = 0; upfront_required_amount = 0; currency = 'MMK' } | Out-Null
    $updatedProjectB = Invoke-Api 'GET' "/projects/$projectBId"
    Assert-True ($updatedProjectB.project_name -eq 'Regression Project B Updated' -and $updatedProjectB.status -eq 'On Hold' -and $updatedProjectB.priority -eq 'High') 'Project update persists'
    Invoke-Api 'PUT' "/projects/$projectBId" @{ project_code = "REG-B-$stamp"; project_name = 'Regression Project B'; status = 'New'; priority = 'Medium'; contract_amount = 100; discount_amount = 0; tax_amount = 0; upfront_required_amount = 0; currency = 'MMK' } | Out-Null
    $projectBRecurring = Invoke-Api 'GET' "/projects/$projectBId/recurring-fees?page=1&limit=25"
    Assert-True ($projectBRecurring.pagination.total -eq 1) 'Editing a project does not duplicate its initial server billing'
    Assert-True ($projectBRecurring.rows[0].source_type -eq 'Domain Billing' -and $projectBRecurring.rows[0].fee_type -eq 'Server') 'Initial Domain Billing appears as one read-only Server fee'
    $deleteProjectId = [int](Invoke-Api 'POST' '/projects' @{ project_code = "REG-DELETE-$stamp"; project_name = 'Delete Project'; status = 'New'; contract_amount = 0 }).id
    Invoke-Api 'DELETE' "/projects/$deleteProjectId" | Out-Null
    Expect-Status 404 'GET' "/projects/$deleteProjectId"

    $projectA = Invoke-Api 'GET' "/projects/$projectAId"
    Assert-True ($projectA.domain_reminder_date -eq '2025-02-28') 'Leap-day domain renewal must clamp to February 28'
    $compactProjects = Invoke-Api 'GET' '/projects?compact=1&search=Regression%20Project%20A'
    Assert-True (($compactProjects | Where-Object id -eq $projectAId).notes -eq 'Regression project invoice note') 'Compact project data includes invoice notes'
    Assert-True (@($compactProjects).Count -le 50) 'Compact project data is bounded for long-term selector performance'

    Expect-Status 422 'POST' '/payments' @{ project_id = $projectAId; payment_date = $today; amount = 'abc'; payment_type = 'Other'; payment_method = 'Cash'; financial_account_id = $alphaId }
    Expect-Status 422 'POST' '/payments' @{ project_id = $projectAId; payment_date = $today; amount = 10; payment_type = 'Upfront'; payment_method = 'Cash'; is_historical = 0 }
    Expect-Status 422 'POST' '/payments' @{ project_id = $projectAId; payment_date = $today; amount = 10; payment_type = 'Upfront'; payment_method = 'Cash'; is_historical = 2 }
    $paymentAId = [int](Invoke-Api 'POST' '/payments' @{ project_id = $projectAId; payment_date = $today; amount = 200; payment_type = 'Other'; payment_method = 'Cash'; financial_account_id = $alphaId }).id
    $paymentBId = [int](Invoke-Api 'POST' '/payments' @{ project_id = $projectBId; payment_date = $today; amount = 150; payment_type = 'Other'; payment_method = 'Cash'; financial_account_id = $betaId }).id
    $categories = Invoke-Api 'GET' '/expense-categories?all=1'
    $developerCategoryId = [int]($categories | Where-Object name -eq 'Developer Cost' | Select-Object -First 1).id
    $aiCategoryId = [int]($categories | Where-Object name -eq 'AI Tools/Agents' | Select-Object -First 1).id
    Assert-True ($developerCategoryId -gt 0 -and $aiCategoryId -gt 0) 'Managed expense categories are seeded'
    $expenseId = [int](Invoke-Api 'POST' '/expenses' @{ project_id = $projectAId; expense_date = $today; expense_scope = 'Project'; expense_category_id = $developerCategoryId; amount = 40; paid_to = 'Regression Developer'; payment_method = 'Bank Transfer'; financial_account_id = $alphaId; expense_status = 'Paid'; expense_frequency = 'One Time'; is_historical = 0; reference_number = 'EXP-REF'; notes = 'Regression expense note' }).id
    $expenseRecord = Invoke-Api 'GET' "/expenses/$expenseId"
    Assert-True ($expenseRecord.paid_to -eq 'Regression Developer' -and $expenseRecord.payment_method -eq 'Bank Transfer' -and $expenseRecord.reference_number -eq 'EXP-REF' -and $expenseRecord.notes -eq 'Regression expense note') 'Expense optional details persist'
    $expenseMovements = Invoke-Api 'GET' '/financial-transactions?page=1&limit=500&transaction_type=Use'
    Assert-True (@($expenseMovements.rows | Where-Object { [int]$_.expense_id -eq $expenseId }).Count -eq 1) 'Paid expense creates one linked account movement'
    Expect-Status 422 'DELETE' "/financial-transactions/$((@($expenseMovements.rows | Where-Object { [int]$_.expense_id -eq $expenseId }) | Select-Object -First 1).id)"

    $overheadId = [int](Invoke-Api 'POST' '/expenses' @{ expense_date = $today; expense_scope = 'Company'; expense_category_id = $aiCategoryId; amount = 25; paid_to = 'AI Vendor'; payment_method = 'Bank Transfer'; financial_account_id = $betaId; expense_status = 'Paid'; expense_frequency = 'Recurring'; billing_cycle = 'Monthly'; billing_period = (Get-Date -Format 'yyyy-MM'); is_historical = 0 }).id
    $overhead = Invoke-Api 'GET' "/expenses/$overheadId"
    Assert-True ($null -eq $overhead.project_id -and $overhead.expense_scope -eq 'Company') 'Company overhead does not require a project'
    Invoke-Api 'PUT' "/expenses/$overheadId" @{ expense_date = $today; expense_scope = 'Company'; expense_category_id = $aiCategoryId; amount = 30; paid_to = 'AI Vendor'; payment_method = 'Bank Transfer'; expense_status = 'Unpaid'; expense_frequency = 'Recurring'; billing_cycle = 'Monthly'; billing_period = (Get-Date -Format 'yyyy-MM'); is_historical = 0 } | Out-Null
    $usesAfterUnpaid = Invoke-Api 'GET' '/financial-transactions?page=1&limit=500&transaction_type=Use'
    Assert-True (@($usesAfterUnpaid.rows | Where-Object { [int]$_.expense_id -eq $overheadId }).Count -eq 0) 'Changing a paid expense to unpaid reverses its account movement'
    $expenseAnalytics = Invoke-Api 'GET' "/reports/expense-analytics?date_from=$today&date_to=$today"
    Assert-True ([double]$expenseAnalytics.summary.this_month_expenses -ge 70) 'Expense analytics exposes the current-month total'
    Assert-True ([double]$expenseAnalytics.summary.project_expenses -ge 40 -and [double]$expenseAnalytics.summary.overhead_expenses -ge 30) 'Expense analytics separates project costs and overhead'
    Assert-True ([double]$expenseAnalytics.summary.staff_costs -ge 40 -and [double]$expenseAnalytics.summary.software_costs -ge 30) 'Expense analytics separates staff and software costs'
    Invoke-Api 'DELETE' "/expenses/$overheadId" | Out-Null

    $summaryA = Invoke-Api 'GET' "/projects/$projectAId/summary"
    Assert-Money $summaryA.total_payable 950 'Project total payable'
    Assert-Money $summaryA.total_paid 200 'Project total paid'
    Assert-Money $summaryA.remaining_balance 750 'Project remaining balance'
    Assert-Money $summaryA.total_expenses 40 'Project expenses'
    Assert-Money $summaryA.profit 160 'Realized project profit'
    Assert-Money $summaryA.expected_profit 910 'Expected project profit'
    Assert-Money $summaryA.payment_percentage 21.05 'Project payment percentage'
    Assert-True ($summaryA.payment_status -eq 'Overdue') 'Past-due unpaid project must be Overdue'

    $summaryB = Invoke-Api 'GET' "/projects/$projectBId/summary"
    Assert-Money $summaryB.remaining_balance -50 'Overpayment must remain visible'
    Assert-True ($summaryB.payment_status -eq 'Fully Paid') 'Overpaid project must be Fully Paid'

    $dashboard = Invoke-Api 'GET' '/dashboard/summary'
    Assert-Money ([double]$dashboard.total_contract_value - [double]$baseline.total_contract_value) 1050 'Dashboard payable delta'
    Assert-Money ([double]$dashboard.total_received - [double]$baseline.total_received) 350 'Dashboard received delta'
    Assert-Money ([double]$dashboard.total_outstanding - [double]$baseline.total_outstanding) 810 'Dashboard outstanding includes the initial server quote without netting project overpayments'
    Assert-Money ([double]$dashboard.net_profit - [double]$baseline.net_profit) 310 'Dashboard net profit delta'

    $linked = Invoke-Api 'GET' "/financial-transactions?page=1&limit=25&transaction_type=Receive"
    Assert-True (@($linked.rows | Where-Object { [int]$_.project_payment_id -in @($paymentAId, $paymentBId) }).Count -eq 2) 'Each project payment must have exactly one linked Receive'
    $temporaryPaymentId = [int](Invoke-Api 'POST' '/payments' @{ project_id = $projectAId; payment_date = $today; amount = 10; payment_type = 'Other'; payment_method = 'Other'; financial_account_id = $alphaId; notes = 'Delete payment test' }).id
    Invoke-Api 'DELETE' "/payments/$temporaryPaymentId" | Out-Null
    Expect-Status 404 'GET' "/payments/$temporaryPaymentId"
    $receivesAfterPaymentDelete = Invoke-Api 'GET' '/financial-transactions?page=1&limit=500&transaction_type=Receive'
    Assert-True (@($receivesAfterPaymentDelete.rows | Where-Object { [int]$_.project_payment_id -eq $temporaryPaymentId }).Count -eq 0) 'Deleting a payment deletes its Receive transaction'
    Expect-Status 422 'POST' '/financial-transactions' @{ transaction_date = $today; transaction_type = 'Use'; from_account_id = $alphaId; amount = 'abc'; notes = 'Bad amount' }
    Expect-Status 422 'POST' '/financial-transactions' @{ transaction_date = $today; transaction_type = 'Use'; from_account_id = $alphaId; amount = 1; notes = ('x' * 501) }

    $transferId = [int](Invoke-Api 'POST' '/financial-transactions' @{ transaction_date = $today; transaction_type = 'Transfer'; from_account_id = $alphaId; to_account_id = $betaId; amount = 50; notes = 'Regression transfer' }).id
    $useId = [int](Invoke-Api 'POST' '/financial-transactions' @{ transaction_date = $today; transaction_type = 'Use'; from_account_id = $betaId; amount = 20; manual_use_type = 'Owner Withdrawal'; notes = 'Regression use' }).id
    $transactionIds.Add($transferId); $transactionIds.Add($useId)
    $accounts = Invoke-Api 'GET' '/financial-accounts'
    Assert-Money ($accounts | Where-Object id -eq $alphaId).balance 210 'Source account balance includes linked expense'
    Assert-Money ($accounts | Where-Object id -eq $betaId).balance 180 'Destination account balance'
    Assert-Money (($accounts | Where-Object { [int]$_.id -in @($alphaId,$betaId) } | Measure-Object balance -Sum).Sum) 390 'Combined account balance'

    $receiptId = [int](Invoke-Api 'POST' '/receipts' @{ project_id = $projectAId; payment_id = $paymentAId; receipt_date = $today; amount = 120; payment_method = 'Cash'; received_from = 'Regression Customer'; notes = 'Internal acknowledgement' }).id
    Expect-Status 422 'POST' '/receipts' @{ project_id = $projectAId; payment_id = $paymentAId; receipt_date = $today; amount = 81; payment_method = 'Cash' }
    Expect-Status 422 'PUT' "/payments/$paymentAId" @{ project_id = $projectAId; payment_date = $today; amount = 100; payment_type = 'Other'; payment_method = 'Cash'; financial_account_id = $alphaId }
    Expect-Status 422 'PUT' "/payments/$paymentAId" @{ project_id = $projectBId; payment_date = $today; amount = 200; payment_type = 'Other'; payment_method = 'Cash'; financial_account_id = $alphaId }
    $receiptRecord = Invoke-Api 'GET' "/receipts/$receiptId"
    Assert-True ($receiptRecord.payment_id -eq $paymentAId) 'Receipt must remain linked after rejected payment edits'
    Assert-True ($receiptRecord.received_from -eq 'Regression Customer' -and $receiptRecord.notes -eq 'Internal acknowledgement') 'Receipt internal details persist'
    $temporaryReceiptId = [int](Invoke-Api 'POST' '/receipts' @{ project_id = $projectAId; payment_id = $paymentAId; receipt_date = $today; amount = 10; payment_method = 'Cash' }).id
    Invoke-Api 'DELETE' "/receipts/$temporaryReceiptId" | Out-Null
    Expect-Status 404 'GET' "/receipts/$temporaryReceiptId"

    Expect-Status 422 'POST' '/invoices' @{ project_id = $projectAId; invoice_date = $today; invoice_type = 'Project Invoice'; items = @(@{ description = 'Bad'; quantity = 1; unit_price = 'abc' }) }
    Expect-Status 422 'POST' '/invoices' @{ project_id = $projectAId; invoice_date = $today; invoice_type = 'Project Invoice'; items = @(@{ description = ('x' * 256); quantity = 1; unit_price = 1 }) }
    $invoiceId = [int](Invoke-Api 'POST' '/invoices' @{
        project_id = $projectAId; invoice_date = $today; due_date = $yesterday; invoice_type = 'Project Invoice'; status = 'Sent'
        discount_amount = 1.25; tax_amount = 5; paid_amount = 50; project_total_amount = 5000; previously_paid_amount = 123; total_amount = 777; remaining_project_amount = 4000
        header_note = 'Regression payment request'; notes = 'Regression invoice note'
        items = @(@{ description = 'Development'; quantity = 2; unit_price = 100.125 }, @{ description = 'Setup'; quantity = 0.333; unit_price = 3 })
    }).id
    $invoice = Invoke-Api 'GET' "/invoices/$invoiceId"
    $invoiceYear = $today.Substring(0, 4)
    Assert-True ($invoice.invoice_number -match "^INV-$invoiceYear-\d{4,}$") 'Invoice number uses the invoice issue year'
    $nextInvoiceYear = [string]([int]$invoiceYear + 1)
    $nextYearInvoicePreview = Invoke-Api 'GET' "/invoices/next-number?invoice_date=$nextInvoiceYear-01-01"
    Assert-True ($nextYearInvoicePreview.invoice_number -eq "INV-$nextInvoiceYear-0001") 'A new invoice year starts from 0001 independently'
    Assert-Money $invoice.subtotal 201.25 'Invoice subtotal uses rounded line totals'
    Assert-Money $invoice.project_total_amount 5000 'Invoice preserves the manually entered project total'
    Assert-Money $invoice.total_amount 777 'Invoice preserves the manually entered current asked amount'
    Assert-Money $invoice.balance_amount 727 'Invoice current request balance'
    Assert-Money $invoice.previously_paid_amount 123 'Invoice preserves the manually entered already-paid amount'
    Assert-Money $invoice.remaining_project_amount 4000 'Invoice preserves the manually entered remaining amount'
    Assert-True ($invoice.header_note -eq 'Regression payment request') 'Invoice header note persists'
    Assert-True ($invoice.status -eq 'Partially Paid') 'Paid amount must derive Partially Paid status'
    Assert-True ($invoice.notes -eq 'Regression invoice note') 'Invoice notes persist for softcopy output'
    Assert-Money (($invoice.items | Measure-Object total_price -Sum).Sum) $invoice.subtotal 'Stored line totals must equal subtotal'
    Invoke-Api 'PUT' "/invoices/$invoiceId" @{ project_id = $projectAId; invoice_date = $today; due_date = $yesterday; invoice_type = 'Project Invoice'; discount_amount = 1.25; tax_amount = 5; paid_amount = 50; project_total_amount = 6000; previously_paid_amount = 222; total_amount = 888; remaining_project_amount = 4444; header_note = 'Updated payment request'; notes = 'Updated invoice note'; items = @(@{ description = 'Development'; quantity = 2; unit_price = 100.125 }, @{ description = 'Setup'; quantity = 0.333; unit_price = 3 }) } | Out-Null
    $updatedInvoice = Invoke-Api 'GET' "/invoices/$invoiceId"
    Assert-True ($updatedInvoice.notes -eq 'Updated invoice note') 'Invoice update persists'
    Assert-True ($updatedInvoice.header_note -eq 'Updated payment request') 'Invoice header note update persists'
    Assert-Money $updatedInvoice.project_total_amount 6000 'Invoice manual project total update persists'
    Assert-Money $updatedInvoice.previously_paid_amount 222 'Invoice manual already-paid update persists'
    Assert-Money $updatedInvoice.total_amount 888 'Invoice manual current asked update persists'
    Assert-Money $updatedInvoice.remaining_project_amount 4444 'Invoice manual remaining update persists'
    $temporaryInvoiceId = [int](Invoke-Api 'POST' '/invoices' @{ project_id = $projectAId; invoice_date = $today; invoice_type = 'Other'; project_total_amount = 1; previously_paid_amount = 0; total_amount = 1; remaining_project_amount = 0; items = @(@{ description = 'Delete invoice'; quantity = 1; unit_price = 1 }) }).id
    Invoke-Api 'DELETE' "/invoices/$temporaryInvoiceId" | Out-Null
    Expect-Status 404 'GET' "/invoices/$temporaryInvoiceId"

    $feeId = [int](Invoke-Api 'POST' '/recurring-fees' @{ project_id = $projectAId; fee_name = 'Month-end server'; fee_type = 'Server'; amount = 100; billing_cycle = 'Monthly'; next_due_date = '2027-01-31'; reminder_days_before_due = 7; status = 'Not Due' }).id
    Assert-True ((Invoke-Api 'GET' "/recurring-fees/$feeId").source_type -eq 'Manual') 'Direct recurring fee creation uses Manual source'
    $silentFeeId = [int](Invoke-Api 'POST' '/recurring-fees' @{ project_id = $projectAId; fee_name = 'Silent renewal'; fee_type = 'Other'; amount = 1; billing_cycle = 'Yearly'; next_due_date = $today; reminder_days_before_due = 7; auto_create_reminder = 0; status = 'Not Due' }).id
    $temporaryFeeId = [int](Invoke-Api 'POST' '/recurring-fees' @{ project_id = $projectAId; fee_name = 'Temporary fee'; fee_type = 'SSL'; amount = 2; billing_cycle = 'One Time'; next_due_date = $today; status = 'Not Due' }).id
    Invoke-Api 'PUT' "/recurring-fees/$temporaryFeeId" @{ project_id = $projectAId; fee_name = 'Temporary fee updated'; fee_type = 'SSL'; amount = 2; billing_cycle = 'One Time'; next_due_date = $today; reminder_days_before_due = 7; auto_create_reminder = 1; status = 'Not Due' } | Out-Null
    Assert-True ((Invoke-Api 'GET' "/recurring-fees/$temporaryFeeId").fee_name -eq 'Temporary fee updated') 'Recurring fee update persists'
    Invoke-Api 'DELETE' "/recurring-fees/$temporaryFeeId" | Out-Null
    Expect-Status 404 'GET' "/recurring-fees/$temporaryFeeId"
    Expect-Status 422 'POST' '/recurring-fees' @{ project_id = $projectAId; fee_name = ''; fee_type = 'Hosting'; amount = 1; billing_cycle = 'Yearly'; next_due_date = $today }
    $feeDashboard = Invoke-Api 'GET' '/dashboard/summary'
    Assert-True ([int]$feeDashboard.server_fees_due_this_month -eq [int]$baseline.server_fees_due_this_month) 'Dashboard server count excludes non-server fee types'
    Assert-True ([int]$feeDashboard.upcoming_renewals -eq [int]$baseline.upcoming_renewals) 'Dashboard renewals exclude recurring fees with reminders disabled'
    Expect-Status 422 'POST' '/recurring-fees' @{ project_id = $projectAId; fee_name = 'Bad reminder flag'; fee_type = 'Other'; amount = 1; billing_cycle = 'Yearly'; next_due_date = $today; auto_create_reminder = 2; status = 'Not Due' }
    Invoke-Api 'POST' "/recurring-fees/$feeId/mark-paid" @{ create_payment = $false } | Out-Null
    $fee = Invoke-Api 'GET' "/recurring-fees/$feeId"
    Assert-True ($fee.next_due_date -eq '2027-02-28') 'Monthly cycle must clamp January 31 to February 28'

    $overview = Invoke-Api 'GET' "/reports/financial-overview?period=lifetime&project_id=$projectAId"
    Assert-Money $overview.summary.received_amount 200 'Lifetime project received report'
    Assert-Money $overview.summary.project_outstanding_amount 750 'Lifetime project outstanding report'
    Assert-Money $overview.summary.recurring_due_amount 101 'Lifetime recurring report includes fees even when reminders are disabled'
    Assert-Money $overview.summary.total_to_collect 851 'Lifetime total-to-collect report'

    $domainId = [int](Invoke-Api 'POST' '/domain-billings' @{
        project_id = $projectAId; domain_name = 'regression.example'; period_label = 'First year'; quote_date = $yesterday
        customer_price = 80; customer_due_date = $yesterday; purchase_status = 'Quoted'; reminder_days_before_due = 30
    }).id
    $quotedDomain = Invoke-Api 'GET' "/domain-billings/$domainId"
    Assert-True ($quotedDomain.effective_purchase_status -eq 'Not Purchased') 'Domain price can be quoted before purchase'
    Assert-True ($quotedDomain.customer_payment_status -eq 'Unpaid') 'New domain quote must start unpaid'
    Assert-Money $quotedDomain.customer_balance_amount 80 'Quoted domain balance'
    Assert-True ($null -eq $quotedDomain.purchase_expense -and $null -eq $quotedDomain.purchase_transaction) 'Quote must not create registrar accounting before purchase'
    $derivedDomainFees = Invoke-Api 'GET' "/recurring-fees?project_id=$projectAId&fee_type=Server&page=1&limit=100"
    $derivedDomainFee = @($derivedDomainFees.rows | Where-Object { [int]$_.source_id -eq $domainId -and $_.source_type -eq 'Domain Billing' }) | Select-Object -First 1
    Assert-True ($null -ne $derivedDomainFee -and [int]$derivedDomainFee.is_read_only -eq 1 -and $derivedDomainFee.fee_type -eq 'Server') 'Domain billing appears automatically as one read-only Server recurring row'
    Expect-Status 404 'PUT' "/recurring-fees/$($derivedDomainFee.id)" @{ amount = 1 }
    $projectRecurringPage = Invoke-Api 'GET' "/projects/$projectAId/recurring-fees?page=1&limit=100"
    Assert-True (@($projectRecurringPage.rows | Where-Object { [int]$_.source_id -eq $domainId -and $_.source_type -eq 'Domain Billing' }).Count -eq 1) 'Project recurring history includes the derived domain row'
    Invoke-Api 'PUT' "/domain-billings/$domainId" @{ project_id = $projectAId; domain_name = 'regression.example'; period_label = 'First year'; quote_date = $yesterday; customer_price = 80; customer_due_date = $yesterday; purchase_status = 'Quoted'; reminder_days_before_due = 30; notes = 'Updated quote note' } | Out-Null
    Assert-True ((Invoke-Api 'GET' "/domain-billings/$domainId").notes -eq 'Updated quote note') 'Domain quote update persists'

    $domainPaymentA = [int](Invoke-Api 'POST' "/domain-billings/$domainId/customer-payment" @{ payment_date = $today; amount = 30; financial_account_id = $alphaId; reference_number = 'DOMAIN-DEPOSIT' }).payment_id
    $partPaidDomain = Invoke-Api 'GET' "/domain-billings/$domainId"
    Assert-True ($partPaidDomain.customer_payment_status -eq 'Partially Paid') 'Domain customer payment supports payment before purchase'
    Assert-Money $partPaidDomain.customer_paid_amount 30 'Domain amount paid before purchase'
    Assert-Money $partPaidDomain.customer_balance_amount 50 'Domain amount remaining before purchase'
    $summaryAfterDomainDeposit = Invoke-Api 'GET' "/projects/$projectAId/summary"
    Assert-Money $summaryAfterDomainDeposit.total_paid 200 'Domain payment must not reduce project contract balance'
    Assert-Money $summaryAfterDomainDeposit.total_received 230 'Project total received includes domain money'
    Assert-Money $summaryAfterDomainDeposit.remaining_balance 750 'Contract remaining balance stays separate from domain billing'

    Expect-Status 422 'POST' "/domain-billings/$domainId/purchase" @{ purchase_date = '2028-02-29'; customer_renewal_date = '2028-02-29'; coverage_end_date = '2031-02-28'; actual_registrar_cost = 45; financial_account_id = $betaId }
    Expect-Status 422 'POST' "/domain-billings/$domainId/purchase" @{ purchase_date = '2028-02-29'; customer_renewal_date = '2029-02-28'; coverage_end_date = '2028-02-29'; actual_registrar_cost = 45; financial_account_id = $betaId }
    Invoke-Api 'POST' "/domain-billings/$domainId/purchase" @{ purchase_date = '2028-02-29'; customer_renewal_date = '2029-02-28'; coverage_end_date = '2031-02-28'; actual_registrar_cost = 45; financial_account_id = $betaId; registrar_provider = 'Regression Registrar'; registrar_reference = 'REG-45' } | Out-Null
    $purchasedDomain = Invoke-Api 'GET' "/domain-billings/$domainId"
    Assert-True ($purchasedDomain.purchase_status -eq 'Purchased' -and $purchasedDomain.effective_purchase_status -eq 'Active') 'Domain purchase status'
    Assert-True ($purchasedDomain.customer_renewal_date -eq '2029-02-28') 'Customer domain renewal remains annual'
    Assert-True ($purchasedDomain.coverage_end_date -eq '2031-02-28') 'Registrar coverage supports a multi-year purchase'
    Assert-True ($purchasedDomain.renewal_reminder_date -eq '2031-01-29') 'Registrar expiry reminder uses actual multi-year coverage'
    Assert-Money $purchasedDomain.actual_registrar_cost 45 'Actual registrar cost'
    Assert-True ($null -ne $purchasedDomain.purchase_expense -and $null -ne $purchasedDomain.purchase_transaction) 'Purchase creates linked expense and financial transaction'
    Assert-True ($purchasedDomain.purchase_expense.expense_category -eq 'Domain Purchase') 'Purchase expense category'
    Assert-True ($purchasedDomain.purchase_transaction.transaction_type -eq 'Use') 'Purchase deducts the selected financial account'
    Expect-Status 422 'PUT' "/expenses/$($purchasedDomain.linked_expense_id)" @{ project_id = $projectAId; expense_date = $today; expense_category = 'Other'; amount = 1 }
    Expect-Status 422 'DELETE' "/financial-transactions/$($purchasedDomain.linked_transaction_id)"

    Invoke-Api 'POST' "/domain-billings/$domainId/purchase" @{ purchase_date = '2028-02-29'; customer_renewal_date = '2029-02-28'; coverage_end_date = '2031-02-28'; actual_registrar_cost = 50; financial_account_id = $alphaId; registrar_provider = 'Regression Registrar'; registrar_reference = 'REG-50' } | Out-Null
    $updatedPurchase = Invoke-Api 'GET' "/domain-billings/$domainId"
    Assert-Money $updatedPurchase.actual_registrar_cost 50 'Registrar purchase can be corrected'
    Assert-True ($updatedPurchase.purchase_expense.id -eq $purchasedDomain.purchase_expense.id) 'Purchase correction must update one expense'
    Assert-True ($updatedPurchase.purchase_transaction.id -eq $purchasedDomain.purchase_transaction.id) 'Purchase correction must update one financial transaction'

    $domainPaymentB = [int](Invoke-Api 'POST' "/domain-billings/$domainId/customer-payment" @{ payment_date = $today; amount = 50; financial_account_id = $alphaId; reference_number = 'DOMAIN-FINAL' }).payment_id
    Expect-Status 422 'POST' "/domain-billings/$domainId/customer-payment" @{ payment_date = $today; amount = 1; financial_account_id = $alphaId }
    $paidDomain = Invoke-Api 'GET' "/domain-billings/$domainId"
    Assert-True ($paidDomain.customer_payment_status -eq 'Paid') 'Full annual domain payment status'
    Assert-Money $paidDomain.customer_paid_amount 80 'Full annual domain amount paid'
    Assert-Money $paidDomain.realized_domain_profit 30 'Realized annual domain profit'
    Assert-Money $paidDomain.expected_domain_profit 30 'Expected annual domain profit'
    Invoke-Api 'DELETE' "/domain-billings/$domainId/customer-payment/$domainPaymentB" | Out-Null
    $reversedDomainPayment = Invoke-Api 'GET' "/domain-billings/$domainId"
    Assert-True ($reversedDomainPayment.customer_payment_status -eq 'Partially Paid') 'Reversing a domain payment restores partial payment status'
    Assert-Money $reversedDomainPayment.customer_paid_amount 30 'Reversing a domain payment restores the customer balance'
    $receivesAfterDomainReverse = Invoke-Api 'GET' '/financial-transactions?page=1&limit=500&transaction_type=Receive'
    Assert-True (@($receivesAfterDomainReverse.rows | Where-Object { [int]$_.project_payment_id -eq $domainPaymentB }).Count -eq 0) 'Reversing a domain payment removes its linked Receive transaction'
    $domainPaymentB = [int](Invoke-Api 'POST' "/domain-billings/$domainId/customer-payment" @{ payment_date = $today; amount = 50; financial_account_id = $alphaId; reference_number = 'DOMAIN-FINAL-RESTORED' }).payment_id
    Assert-True ((Invoke-Api 'GET' "/domain-billings/$domainId").customer_payment_status -eq 'Paid') 'Domain can be paid again after reversal'
    Expect-Status 422 'DELETE' "/domain-billings/$domainId"

    $domainSummary = Invoke-Api 'GET' "/projects/$projectAId/summary"
    Assert-Money $domainSummary.total_paid 200 'Contract paid remains separate after full domain payment'
    Assert-Money $domainSummary.total_received 280 'Project total received includes full domain payment'
    Assert-Money $domainSummary.total_expenses 90 'Project expenses include registrar purchase'
    Assert-Money $domainSummary.profit 190 'Project realized profit includes domain revenue and registrar cost'
    Assert-Money $domainSummary.expected_profit 940 'Project expected profit includes quoted domain revenue and registrar cost'
    $domainAccounts = Invoke-Api 'GET' '/financial-accounts'
    Assert-Money ($domainAccounts | Where-Object id -eq $alphaId).balance 240 'Receiving, expense, and registrar account movements are synchronized'
    Assert-Money ($domainAccounts | Where-Object id -eq $betaId).balance 180 'Correcting registrar account restores the previous account'

    $domainOverview = Invoke-Api 'GET' "/reports/financial-overview?period=lifetime&project_id=$projectAId"
    Assert-Money $domainOverview.summary.received_amount 280 'Financial overview includes domain customer receipts'
    Assert-Money $domainOverview.summary.project_outstanding_amount 750 'Financial overview keeps contract outstanding separate'
    Assert-Money $domainOverview.summary.domain_outstanding_amount 0 'Paid annual domain has no outstanding amount'
    Assert-Money $domainOverview.summary.domain_server_price_total 80 'Financial overview domain customer price'
    Assert-Money $domainOverview.summary.domain_registrar_cost_total 50 'Financial overview actual registrar cost'
    Assert-Money $domainOverview.summary.total_to_collect 851 'Paid domain is not counted again in total to collect'

    $domainScheduleReminders = Invoke-Api 'GET' '/reminders?limit=100'
    $allDomainScheduleReminders = @($domainScheduleReminders.groups.due_today) + @($domainScheduleReminders.groups.due_this_week) + @($domainScheduleReminders.groups.overdue) + @($domainScheduleReminders.groups.upcoming)
    for ($reminderPage = 2; $reminderPage -le [int]$domainScheduleReminders.pagination.upcoming.pages; $reminderPage++) {
        $nextReminderPage = Invoke-Api 'GET' "/reminders?limit=100&upcoming_page=$reminderPage"
        $allDomainScheduleReminders += @($nextReminderPage.groups.upcoming)
    }
    Assert-True (@($allDomainScheduleReminders | Where-Object { $_.source_type -eq 'domain' -and [int]$_.record_id -eq $projectAId }).Count -eq 0) 'Annual Domain Billing suppresses the legacy project-domain reminder'
    Assert-True (@($allDomainScheduleReminders | Where-Object { $_.source_type -eq 'domain-customer-renewal' -and [int]$_.record_id -eq $domainId }).Count -eq 1) 'Customer annual renewal has its own reminder'
    Assert-True (@($allDomainScheduleReminders | Where-Object { $_.source_type -eq 'domain-renewal' -and [int]$_.record_id -eq $domainId }).Count -eq 1) 'Registrar expiry has its own reminder'

    $todayDomainOverview = Invoke-Api 'GET' "/reports/financial-overview?period=today&project_id=$projectAId&fee_type=Server"
    Assert-Money $todayDomainOverview.summary.received_amount 80 'Domain filter includes only domain customer receipts'
    Assert-Money $todayDomainOverview.summary.project_outstanding_amount 0 'Fee filters exclude unrelated project contract balances'
    Assert-Money $todayDomainOverview.summary.domain_server_price_total 0 'Period filter applies to quoted domain customer prices'
    Assert-Money $todayDomainOverview.summary.domain_registrar_cost_total 0 'Period filter applies to registrar purchase costs'
    Assert-Money $todayDomainOverview.summary.total_to_collect 0 'Domain-filtered total excludes unrelated balances'

    $monthlyProject = Invoke-Api 'GET' "/reports/monthly-income-expense?project_id=$projectAId"
    $currentMonth = Get-Date -Format 'yyyy-MM'
    Assert-Money (($monthlyProject.income | Where-Object month -eq $currentMonth).total) 280 'Monthly report project filter income'
    Assert-Money (($monthlyProject.expenses | Where-Object month -eq $currentMonth).total) 40 'Monthly report project filter expenses'

    $nextDomainId = [int](Invoke-Api 'POST' "/domain-billings/$domainId/renew" @{}).id
    $nextDomain = Invoke-Api 'GET' "/domain-billings/$nextDomainId"
    Assert-True ($nextDomain.purchase_status -eq 'Purchased' -and [int]$nextDomain.is_registrar_carryover -eq 1) 'Next customer year carries existing multi-year registrar coverage'
    Assert-Money $nextDomain.customer_price 80 'Renewal carries the customer price for editing'
    Assert-True ($nextDomain.quote_date -eq '2029-02-28' -and $nextDomain.customer_due_date -eq '2029-02-28') 'Next customer period follows annual customer renewal, not registrar expiry'
    Assert-True ($nextDomain.customer_renewal_date -eq '2030-02-28' -and $nextDomain.coverage_end_date -eq '2031-02-28') 'Carried period keeps yearly customer and multi-year registrar dates separate'
    Assert-True ($null -eq $nextDomain.purchase_expense -and $null -eq $nextDomain.purchase_transaction) 'Carrying registrar coverage creates no duplicate cost or account use'
    $renewedRecurringDomains = Invoke-Api 'GET' "/recurring-fees?project_id=$projectAId&fee_type=Server&page=1&limit=100"
    Assert-True ((@($renewedRecurringDomains.rows | Where-Object { [int]$_.source_id -eq $domainId }) | Select-Object -First 1).status -eq 'Paid') 'A completed domain year stays Paid after its next annual period exists'
    Expect-Status 422 'POST' "/domain-billings/$domainId/renew" @{}
    $afterDomainRenew = Invoke-Api 'GET' '/reminders'
    Assert-True (@($afterDomainRenew.groups.upcoming | Where-Object { $_.source_type -eq 'domain-customer-renewal' -and [int]$_.record_id -eq $domainId }).Count -eq 0) 'Creating the next customer period clears the previous annual reminder'

    $accountsBeforeHistory = Invoke-Api 'GET' '/financial-accounts'
    $alphaBeforeHistory = ($accountsBeforeHistory | Where-Object id -eq $alphaId).balance
    $betaBeforeHistory = ($accountsBeforeHistory | Where-Object id -eq $betaId).balance
    $projectCId = [int](Invoke-Api 'POST' '/projects' @{
        project_code = "REG-HISTORY-$stamp"; project_name = 'Historical Project'; status = 'Completed'; priority = 'Medium'
        contract_amount = 500; discount_amount = 0; tax_amount = 0; upfront_required_amount = 100; currency = 'MMK'
    }).id
    $projectIds.Add($projectCId)
    $historicalPaymentId = [int](Invoke-Api 'POST' '/payments' @{
        project_id = $projectCId; payment_date = '2023-06-15'; amount = 500; payment_type = 'Final Payment'; payment_method = 'Other'; is_historical = 1
    }).id
    $historicalPayment = Invoke-Api 'GET' "/payments/$historicalPaymentId"
    Assert-True ([int]$historicalPayment.is_historical -eq 1) 'Old project payment is marked historical'
    Assert-True ($null -eq $historicalPayment.financial_account_id) 'Historical project payment does not require a financial account'
    $historicalSummary = Invoke-Api 'GET' "/projects/$projectCId/summary"
    Assert-Money $historicalSummary.total_paid 500 'Historical payment counts toward project paid total'
    Assert-Money $historicalSummary.remaining_balance 0 'Historical payment settles old project balance'
    Assert-True ($historicalSummary.payment_status -eq 'Fully Paid') 'Old settled project is automatically Fully Paid'

    $historicalDomainId = [int](Invoke-Api 'POST' '/domain-billings' @{
        project_id = $projectCId; domain_name = 'historical.example'; period_label = '2023-2024'; quote_date = '2023-06-15'
        customer_price = 40; customer_due_date = '2023-06-15'; purchase_status = 'Quoted'; reminder_days_before_due = 30
    }).id
    $historicalDomainPaymentId = [int](Invoke-Api 'POST' "/domain-billings/$historicalDomainId/customer-payment" @{
        payment_date = '2023-06-15'; amount = 40; is_historical = 1; reference_number = 'OLD-DOMAIN'
    }).payment_id
    Invoke-Api 'POST' "/domain-billings/$historicalDomainId/purchase" @{
        purchase_date = '2023-06-15'; actual_registrar_cost = 12; is_historical_purchase = 1
        registrar_provider = 'Historical Registrar'; registrar_reference = 'OLD-PURCHASE'
    } | Out-Null
    $historicalDomain = Invoke-Api 'GET' "/domain-billings/$historicalDomainId"
    Assert-True ($historicalDomain.customer_payment_status -eq 'Paid') 'One historical domain payment settles the old annual domain price'
    Assert-True ([int]$historicalDomain.payments[0].is_historical -eq 1 -and $null -eq $historicalDomain.payments[0].financial_account_id) 'Historical domain payment has no financial account movement'
    Assert-True ($historicalDomain.purchase_status -eq 'Purchased' -and [int]$historicalDomain.is_historical_purchase -eq 1) 'Already-bought domain is marked as a historical purchase'
    Assert-True ($null -ne $historicalDomain.purchase_expense -and $null -eq $historicalDomain.purchase_transaction) 'Historical domain purchase records its expense without an account movement'
    Assert-True ($historicalDomain.coverage_end_date -eq '2024-06-15') 'Historical domain purchase derives one-year coverage'
    Assert-True ($historicalDomain.customer_renewal_date -eq '2024-06-15') 'Historical domain purchase derives an annual customer renewal date'
    $allReceives = Invoke-Api 'GET' '/financial-transactions?page=1&limit=500&transaction_type=Receive'
    Assert-True (@($allReceives.rows | Where-Object { [int]$_.project_payment_id -in @($historicalPaymentId,$historicalDomainPaymentId) }).Count -eq 0) 'Historical payments create no Receive transactions'
    $accountsAfterHistory = Invoke-Api 'GET' '/financial-accounts'
    Assert-Money ($accountsAfterHistory | Where-Object id -eq $alphaId).balance $alphaBeforeHistory 'Historical imports do not change the first account balance'
    Assert-Money ($accountsAfterHistory | Where-Object id -eq $betaId).balance $betaBeforeHistory 'Historical imports do not change the second account balance'

    $paymentReportResponse = Invoke-Api 'GET' "/reports/payment-collection?financial_account_id=$betaId"
    $paymentReport = @($paymentReportResponse.rows)
    Assert-True (@($paymentReport | Where-Object { [int]$_.id -eq $paymentBId }).Count -eq 1) 'Payment report account filter'
    Assert-True (@($paymentReport | Where-Object { [int]$_.financial_account_id -ne $betaId }).Count -eq 0) 'Payment report must not leak other accounts'

    $projectPage = Invoke-Api 'GET' '/projects?status=New&page=1&limit=1'
    Assert-True ($projectPage.pagination.total -ge 2) 'Project status filter and pagination total'
    Assert-True ($projectPage.rows.Count -eq 1) 'Project page size'

    $reminders = Invoke-Api 'GET' '/reminders?limit=10'
    Assert-True ([int]$reminders.pagination.overdue.limit -eq 10) 'Reminder groups expose server-side pagination'
    Assert-True (@($reminders.groups.due_today | Where-Object { $_.source_type -eq 'fee' -and [int]$_.record_id -eq $silentFeeId }).Count -eq 0) 'Recurring fees with reminders disabled must stay hidden'
    $projectReminder = @($reminders.groups.overdue | Where-Object { $_.source_type -eq 'project' -and [int]$_.record_id -eq $projectAId }) | Select-Object -First 1
    Assert-True ($null -ne $projectReminder) 'Overdue project reminder must exist'
    Expect-Status 422 'POST' '/reminders/resolve' @{ source_type = 'project'; record_id = 999999999; due_date = $today }
    Invoke-Api 'POST' '/reminders/resolve' $projectReminder | Out-Null
    $afterResolve = Invoke-Api 'GET' '/reminders'
    Assert-True (@($afterResolve.groups.overdue | Where-Object { $_.source_type -eq 'project' -and [int]$_.record_id -eq $projectAId }).Count -eq 0) 'Resolved reminder must be hidden'

    $viewerEmail = "viewer-$stamp@example.com"
    Expect-Status 422 'POST' '/users' @{ name = ('x' * 151); email = "too-long-$stamp@example.com"; password = 'viewer-pass-123'; role = 'Viewer'; status = 'Active' }
    Expect-Status 422 'POST' '/users' @{ name = 'Long Password'; email = "long-password-$stamp@example.com"; password = ('x' * 73); role = 'Viewer'; status = 'Active' }
    $viewerId = [int](Invoke-Api 'POST' '/users' @{ name = 'Regression Viewer'; email = $viewerEmail; password = 'viewer-pass-123'; role = 'Viewer'; status = 'Active' }).id
    $userIds.Add($viewerId)
    $viewerLogin = Invoke-Envelope 'POST' '/auth/login' @{ email = $viewerEmail; password = 'viewer-pass-123' } @{}
    $viewerHeaders = @{ Authorization = "Bearer $($viewerLogin.data.token)" }
    Invoke-Api 'GET' '/projects?page=1&limit=1' $null $viewerHeaders | Out-Null
    Expect-Status 403 'POST' '/financial-accounts' @{ name = "Forbidden $stamp"; opening_balance = 0; status = 'Active' } $viewerHeaders
    Invoke-Api 'POST' '/auth/logout' $null $viewerHeaders | Out-Null
    Expect-Status 401 'GET' '/auth/me' $null $viewerHeaders

    $staffEmail = "staff-$stamp@example.com"
    $staffId = [int](Invoke-Api 'POST' '/users' @{ name = 'Regression Staff'; email = $staffEmail; password = 'staff-pass-123'; role = 'Staff'; status = 'Active' }).id
    $userIds.Add($staffId)
    $staffLogin = Invoke-Envelope 'POST' '/auth/login' @{ email = $staffEmail; password = 'staff-pass-123' } @{}
    $staffHeaders = @{ Authorization = "Bearer $($staffLogin.data.token)" }
    Expect-Status 403 'GET' '/users' $null $staffHeaders
    Expect-Status 403 'POST' '/settings' @{ company_name = 'Forbidden staff update' } $staffHeaders
    $staffTxId = [int](Invoke-Api 'POST' '/financial-transactions' @{ transaction_date = $today; transaction_type = 'Use'; from_account_id = $alphaId; amount = 5; manual_use_type = 'Cash Adjustment'; notes = 'Staff audit reference' } $staffHeaders).id
    $transactionIds.Add($staffTxId)
    Invoke-Api 'DELETE' "/users/$staffId" | Out-Null
    $staffAfterDelete = @(Invoke-Api 'GET' '/users' | Where-Object { [int]$_.id -eq $staffId }) | Select-Object -First 1
    Assert-True ($staffAfterDelete.status -eq 'Inactive') 'Referenced staff user must be deactivated instead of causing an FK failure'
    Expect-Status 401 'GET' '/auth/me' $null $staffHeaders

    Invoke-Api 'PUT' "/financial-accounts/$alphaId" @{ name = "Regression Alpha $stamp"; opening_balance = 100; status = 'Inactive' } | Out-Null
    Expect-Status 422 'POST' '/payments' @{ project_id = $projectAId; payment_date = $today; amount = 1; payment_type = 'Other'; payment_method = 'Cash'; financial_account_id = $alphaId }
    Invoke-Api 'PUT' "/payments/$paymentAId" @{ project_id = $projectAId; payment_date = $today; amount = 200; payment_type = 'Other'; payment_method = 'Cash'; financial_account_id = $alphaId } | Out-Null
    Invoke-Api 'PUT' "/financial-transactions/$staffTxId" @{ transaction_date = $today; transaction_type = 'Use'; from_account_id = $alphaId; amount = 5; manual_use_type = 'Cash Adjustment'; notes = 'Edited inactive-account history' } | Out-Null
    Assert-True ((Invoke-Api 'GET' "/payments/$paymentAId").financial_account_id -eq $alphaId) 'Existing payment history must remain editable after its account is inactive'
    Assert-True ((Invoke-Api 'GET' "/financial-transactions/$staffTxId").from_account_id -eq $alphaId) 'Existing manual history must remain editable after its account is inactive'
    Invoke-Api 'PUT' "/financial-accounts/$alphaId" @{ name = "Regression Alpha $stamp"; opening_balance = 100; status = 'Active' } | Out-Null

    Invoke-Api 'PUT' "/payments/$paymentAId" @{ project_id = $projectAId; payment_date = $today; amount = 200; payment_type = 'Upfront'; payment_method = 'Other'; is_historical = 1 } | Out-Null
    $afterHistoricalConversion = Invoke-Api 'GET' '/financial-transactions?page=1&limit=500&transaction_type=Receive'
    Assert-True (@($afterHistoricalConversion.rows | Where-Object { [int]$_.project_payment_id -eq $paymentAId }).Count -eq 0) 'Converting a payment to historical removes its Receive movement'
    Invoke-Api 'PUT' "/payments/$paymentAId" @{ project_id = $projectAId; payment_date = $today; amount = 200; payment_type = 'Upfront'; payment_method = 'Other'; is_historical = 0; financial_account_id = $alphaId } | Out-Null
    $afterCurrentConversion = Invoke-Api 'GET' '/financial-transactions?page=1&limit=500&transaction_type=Receive'
    Assert-True (@($afterCurrentConversion.rows | Where-Object { [int]$_.project_payment_id -eq $paymentAId }).Count -eq 1) 'Converting back to current recreates one Receive movement'

    Write-Output "PASS: $script:Assertions assertions"
} finally {
    foreach ($id in @($transactionIds)) { try { Invoke-Api 'DELETE' "/financial-transactions/$id" | Out-Null } catch {} }
    foreach ($id in @($projectIds)) { try { Invoke-Api 'DELETE' "/projects/$id" | Out-Null } catch {} }
    foreach ($id in @($accountIds)) { try { Invoke-Api 'DELETE' "/financial-accounts/$id" | Out-Null } catch {} }
    foreach ($id in @($userIds)) { try { Invoke-Api 'DELETE' "/users/$id" | Out-Null } catch {} }
}
