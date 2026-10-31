-- Warehouses of the farm on the dossier: technical inputs (agrodrugs, fertilizers) and harvested products.
-- Dolibarr ignores "column already exists", so the script can run at every activation.
-- No foreign key on llx_entrepot: the table exists only when the Stock module has been enabled.

ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN fk_warehouse_inputs integer;
ALTER TABLE llx_dolifarm_farmdossier ADD COLUMN fk_warehouse_products integer;

-- Indexes on these columns: here and not in the .key.sql, which runs before this script
ALTER TABLE llx_dolifarm_farmdossier ADD INDEX idx_dolifarm_farmdossier_wh_inputs (fk_warehouse_inputs);
ALTER TABLE llx_dolifarm_farmdossier ADD INDEX idx_dolifarm_farmdossier_wh_products (fk_warehouse_products);
