-- ALTER TABLE llx_dolifarm_crops ADD UNIQUE INDEX uk_dolifarm_crop_ref (ref, entity);
-- ALTER TABLE llx_dolifarm_crops ADD CONSTRAINT fk_dolifarm_crops_product FOREIGN KEY (fk_default_product) REFERENCES llx_product(rowid);
ALTER TABLE llx_dolifarm_crops ADD CONSTRAINT fk_dolifarm_crops_product FOREIGN KEY (fk_default_product) REFERENCES llx_product(rowid) ON DELETE SET NULL;