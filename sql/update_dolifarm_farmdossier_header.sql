-- Fascicolo aziendale: header of the AGEA validation sheet.
-- For installations created before these columns existed. Dolibarr ignores
-- "column already exists" errors, so the script can run at every activation.

ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN paying_agency varchar(32);
ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN act_type integer;
ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN sheet_number varchar(32);
ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN protocol varchar(64);
ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN date_signature date;
ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN date_validity_end date;
ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN date_reference date;
ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN fk_soc_caa integer;
ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN date_mandate date;
ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN ote_code varchar(16);
ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN economic_size double(24,8);
ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN fk_soc_bio_body integer;
ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN date_bio_membership date;

-- year_validity is no longer filled by the form
ALTER TABLE llx_dolifarm_farmdossier MODIFY year_validity integer NULL;

-- Existing dossiers: the old year of validity becomes "valid until" 31/12 of that year
UPDATE llx_dolifarm_farmdossier SET date_validity_end = STR_TO_DATE(CONCAT(year_validity, '-12-31'), '%Y-%m-%d') WHERE date_validity_end IS NULL AND year_validity BETWEEN 1900 AND 2999;
