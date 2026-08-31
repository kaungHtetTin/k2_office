-- KSSPM Version 1.10: installment-aware invoices and editable payment header notes.
-- Safe to run more than once.

USE ksspm;

ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS previously_paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER balance_amount,
    ADD COLUMN IF NOT EXISTS remaining_project_amount DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER previously_paid_amount,
    ADD COLUMN IF NOT EXISTS header_note VARCHAR(500) NULL AFTER status;

-- Preserve the value shown as "Already Paid" on invoices made before this migration.
UPDATE invoices
SET previously_paid_amount = paid_amount
WHERE previously_paid_amount = 0 AND paid_amount > 0;

UPDATE invoices i
JOIN projects p ON p.id = i.project_id
SET i.remaining_project_amount = GREATEST(
    p.contract_amount - p.discount_amount + p.tax_amount
    - i.previously_paid_amount - i.total_amount,
    0
)
WHERE i.remaining_project_amount = 0;
