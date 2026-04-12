ALTER TABLE llx_dolifarm_agrodrug ADD UNIQUE INDEX uk_dolitrace_agrodrug_ref (ref, entity);
ALTER TABLE llx_dolifarm_agrodrug ADD UNIQUE INDEX uk_dolitrace_agrodrug_fk_prod (fk_product);
ALTER TABLE llx_dolifarm_agrodrug ADD INDEX idx_dolitrace_agrodrug_reg (reg_number);
ALTER TABLE llx_dolifarm_agrodrug ADD CONSTRAINT fk_dolitrace_agro_prod FOREIGN KEY (fk_product) REFERENCES llx_product(rowid) ON DELETE CASCADE;