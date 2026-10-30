-- Refs of the plots: prefix APPEZ instead of the ModuleBuilder MYOBJECT, and no empty ref.
-- Idempotent: rows already fixed are not matched again.

UPDATE llx_dolifarm_plot SET ref = CONCAT('APPEZ', SUBSTRING(ref, 9)) WHERE ref LIKE 'MYOBJECT%';

-- Plots created as draft without a ref: provisional ref, the final one is given at the next save
UPDATE llx_dolifarm_plot SET ref = CONCAT('(PROV', rowid, ')') WHERE ref IS NULL OR ref = '';
