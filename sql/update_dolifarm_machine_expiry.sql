-- Macchine: expiry dates (revision, insurance, functional check of the sprayer).
-- For installations created before these columns existed. Dolibarr ignores
-- "column already exists" errors, so the script can run at every activation.

ALTER TABLE llx_dolifarm_machine ADD COLUMN date_revision_next date;
ALTER TABLE llx_dolifarm_machine ADD COLUMN date_insurance_end date;
ALTER TABLE llx_dolifarm_machine ADD COLUMN date_sprayer_check_end date;
