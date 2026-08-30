-- KSSPM Version 1.9: Domain Billing is the only automatic recurring-fee source.
-- Removes obsolete project-generated hosting/server rows and labels domain periods as Server fees.
-- Safe to run more than once.

USE ksspm;

DELETE rr
FROM resolved_reminders rr
JOIN recurring_fees rf ON rf.id=rr.record_id
WHERE rr.reminder_type='fee' AND rf.source_type='Project Generated';

DELETE FROM recurring_fees WHERE source_type='Project Generated';

UPDATE recurring_fees
SET fee_type='Server'
WHERE source_type='Manual' AND fee_type IN ('Domain','Hosting');

ALTER TABLE recurring_fees
    MODIFY source_type ENUM('Manual') NOT NULL DEFAULT 'Manual';
