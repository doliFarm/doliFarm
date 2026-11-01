-- Machines: certificate and date of the last functional check of the sprayer (AGEA field notebook, sheets 3, 4 and 10).
-- For installations created before these columns existed. Dolibarr ignores
-- "column already exists" errors, so the script can run at every activation.

ALTER TABLE llx_dolifarm_machine ADD COLUMN sprayer_cert_ref varchar(64);
ALTER TABLE llx_dolifarm_machine ADD COLUMN date_sprayer_check date;
