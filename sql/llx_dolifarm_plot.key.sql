-- Indices
ALTER TABLE llx_dolifarm_plot ADD UNIQUE INDEX uk_dolifarm_plot_ref (ref);
ALTER TABLE llx_dolifarm_plot ADD INDEX idx_dolifarm_plot_dossier (fk_dossier);
ALTER TABLE llx_dolifarm_plot ADD  UNIQUE INDEX uk_dolitrace_plot_uuid (uuid);
-- Foreign Keys
ALTER TABLE llx_dolifarm_plot ADD CONSTRAINT fk_dolifarm_plot_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe(rowid);
ALTER TABLE llx_dolifarm_plot ADD CONSTRAINT fk_dolifarm_plot_dossier FOREIGN KEY (fk_dossier) REFERENCES llx_dolifarm_farmdossier(rowid) ON DELETE RESTRICT;
-- Fix: Remove comma at the end and ensure table name is correct (llx_societe)
ALTER TABLE llx_dolifarm_plot ADD CONSTRAINT fk_dolifarm_plot_owner FOREIGN KEY (fk_owner) REFERENCES llx_societe(rowid);
