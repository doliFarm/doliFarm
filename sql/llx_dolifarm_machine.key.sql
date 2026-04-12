-- Indices
ALTER TABLE llx_dolifarm_machine ADD INDEX idx_dolifarm_machine_dossier (fk_dossier);
ALTER TABLE llx_dolifarm_machine ADD INDEX idx_dolifarm_machine_soc (fk_soc);

-- Foreign Keys
ALTER TABLE llx_dolifarm_machine ADD CONSTRAINT fk_dolifarm_machine_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe(rowid);
ALTER TABLE llx_dolifarm_machine ADD CONSTRAINT fk_dolifarm_machine_dossier FOREIGN KEY (fk_dossier) REFERENCES llx_dolifarm_farmdossier(rowid) ON DELETE RESTRICT;