-- KSSPM Version 1.11: invoice amounts are user-entered historical snapshots.
-- Safe to run more than once.

USE ksspm;

ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS project_total_amount DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER balance_amount;

-- Give older invoices a useful project-total snapshot without changing their other saved amounts.
UPDATE invoices i
JOIN projects p ON p.id = i.project_id
SET i.project_total_amount = p.contract_amount - p.discount_amount + p.tax_amount
WHERE i.project_total_amount = 0;
