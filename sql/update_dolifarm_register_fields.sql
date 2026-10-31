-- Data needed by the treatment and fertilization register (doliTrace): EPPO code of the crops,
-- IACS parcel and cultivation type (open field / non-permanent greenhouse) of the plots.
-- Dolibarr ignores "column already exists", so the script can run at every activation.

ALTER TABLE llx_dolifarm_crops ADD COLUMN eppo_code varchar(10);
ALTER TABLE llx_dolifarm_plot ADD COLUMN iacs_parcel varchar(64);
ALTER TABLE llx_dolifarm_plot ADD COLUMN cultivation_type smallint DEFAULT 1;

-- Indexes on these columns: here and not in the .key.sql, which runs before this script
ALTER TABLE llx_dolifarm_crops ADD INDEX idx_dolifarm_crops_eppo_code (eppo_code);
ALTER TABLE llx_dolifarm_plot ADD INDEX idx_dolifarm_plot_iacs_parcel (iacs_parcel);
