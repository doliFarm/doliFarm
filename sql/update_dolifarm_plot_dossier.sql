-- Appezzamenti: data of the AGEA dossier (conduction, cadastral section, surfaces, constraints).
-- For installations created before these columns existed. Dolibarr ignores
-- "column already exists" errors, so the script can run at every activation.

ALTER TABLE llx_dolifarm_plot ADD COLUMN conduction_protocol varchar(64);
ALTER TABLE llx_dolifarm_plot ADD COLUMN conduction_partial smallint;
ALTER TABLE llx_dolifarm_plot ADD COLUMN date_conduction_start date;
ALTER TABLE llx_dolifarm_plot ADD COLUMN date_conduction_end date;
ALTER TABLE llx_dolifarm_plot ADD COLUMN cadastral_section varchar(10);
ALTER TABLE llx_dolifarm_plot ADD COLUMN cadastral_reference varchar(32);
ALTER TABLE llx_dolifarm_plot ADD COLUMN size_cadastral double(24,8);
ALTER TABLE llx_dolifarm_plot ADD COLUMN size_conducted double(24,8);
ALTER TABLE llx_dolifarm_plot ADD COLUMN zone_zvn smallint;
ALTER TABLE llx_dolifarm_plot ADD COLUMN zone_natura2000 varchar(64);

-- Indexes on these columns: here and not in the .key.sql, which runs before this script
ALTER TABLE llx_dolifarm_plot ADD INDEX idx_dolifarm_plot_conduction_end (date_conduction_end);
ALTER TABLE llx_dolifarm_plot ADD INDEX idx_dolifarm_plot_cadastral_reference (cadastral_reference);

-- The owner is optional in the form (it may not be a third party of the database)
ALTER TABLE llx_dolifarm_plot MODIFY fk_owner integer NULL;
