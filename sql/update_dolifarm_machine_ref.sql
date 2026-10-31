-- Machines saved without a ref, before the ref was given at creation.
-- Provisional ref, the final one is given at the next save. Idempotent: rows already fixed are not matched again.

UPDATE llx_dolifarm_machine SET ref = CONCAT('(PROV', rowid, ')') WHERE ref IS NULL OR ref = '';
