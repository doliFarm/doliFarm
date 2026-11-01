-- Dictionaries aligned with the lists of the AGEA field notebook (circ. AGEA 21371/2024, Allegato 3).
-- agea_value: item of the AGEA list (no official codes yet); fert_group: ORG organic (sheet 6), MIN mineral (sheet 7).
-- Dolibarr ignores "column already exists" and duplicate errors, so the script can run at every activation.
-- The values are set only where empty, so the changes made in the dictionaries are kept.

ALTER TABLE llx_dolifarm_c_operation_type ADD COLUMN agea_value varchar(128) DEFAULT NULL;
ALTER TABLE llx_dolifarm_c_fert_type ADD COLUMN fert_group varchar(8) DEFAULT NULL;
ALTER TABLE llx_dolifarm_c_fert_type ADD COLUMN agea_value varchar(128) DEFAULT NULL;

-- Operation types: the 31 crop operations of sheet 2 ("fasi colturali")
INSERT IGNORE INTO llx_dolifarm_c_operation_type (code, label, active, category) VALUES
('SOIL_REFINING',       'Affinamento', 1, 'SOIL'),
('MECH_DEVITALIZATION', 'Devitalizzazione meccanica', 1, 'CROP'),
('SOIL_BREAKING',       'Dissodamento', 1, 'SOIL'),
('WEED_REMOVAL',        'Eliminazione infestanti', 1, 'MAINTENANCE'),
('SOIL_CULTIVATING',    'Estirpatura', 1, 'SOIL'),
('SOIL_MILLING',        'Fresatura', 1, 'SOIL'),
('PLANTATION',          'Impianto', 1, 'CROP'),
('FROST_IRRIGATION',    'Irrigazione antibrina', 1, 'WATER'),
('GREEN_PRUNING',       'Potatura verde', 1, 'MAINTENANCE'),
('RESIDUE_COLLECTION',  'Raccolta residui colturali', 1, 'HARVEST'),
('EARTHING_UP',         'Rincalzatura', 1, 'SOIL'),
('SUBSOILING',          'Ripuntatura', 1, 'SOIL'),
('ROLLING',             'Rullatura', 1, 'SOIL'),
('DEEP_PLOWING',        'Scasso', 1, 'SOIL'),
('GREEN_MANURE',        'Sovescio', 1, 'CROP'),
('LEVELLING',           'Spianamento', 1, 'SOIL'),
('STONE_REMOVAL',       'Spietramento', 1, 'SOIL'),
('SUCKER_REMOVAL',      'Spollonatura', 1, 'MAINTENANCE'),
('OVERSEEDING',         'Trasemina', 1, 'CROP'),
('SHREDDING',           'Trinciatura', 1, 'MAINTENANCE'),
('DIGGING',             'Vangatura', 1, 'SOIL'),
('HOEING',              'Zappatura', 1, 'SOIL');

UPDATE llx_dolifarm_c_operation_type SET agea_value = 'affinamento' WHERE code = 'SOIL_REFINING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'aratura' WHERE code = 'SOIL_PLOWING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'devitalizzazione meccanica' WHERE code = 'MECH_DEVITALIZATION' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'dissodamento' WHERE code = 'SOIL_BREAKING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'eliminazione infestanti' WHERE code = 'WEED_REMOVAL' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'erpicatura' WHERE code = 'SOIL_HARROWING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'estirpatura' WHERE code = 'SOIL_CULTIVATING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'fresatura' WHERE code = 'SOIL_MILLING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'impianto' WHERE code = 'PLANTATION' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'irrigazione antibrina' WHERE code = 'FROST_IRRIGATION' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'potatura' WHERE code = 'PRUNING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'potatura verde' WHERE code = 'GREEN_PRUNING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'raccolta' WHERE code = 'HARVEST' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'raccolta residui colturali' WHERE code = 'RESIDUE_COLLECTION' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'rincalzatura' WHERE code = 'EARTHING_UP' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'ripuntatura' WHERE code = 'SUBSOILING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'rullatura' WHERE code = 'ROLLING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'sarchiatura' WHERE code = 'SOIL_HOEING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'scasso' WHERE code = 'DEEP_PLOWING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'semina' WHERE code IN ('PLANTING', 'SOWING_DIRECT') AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'sfalcio' WHERE code = 'MOWING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'sfoltimento' WHERE code = 'HARVEST_THIN' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'sovescio' WHERE code = 'GREEN_MANURE' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'spianamento' WHERE code = 'LEVELLING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'spietramento' WHERE code = 'STONE_REMOVAL' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'spollonatura' WHERE code = 'SUCKER_REMOVAL' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'trapianto' WHERE code = 'TRANSPLANTING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'trasemina' WHERE code = 'OVERSEEDING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'trinciatura' WHERE code = 'SHREDDING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'vangatura' WHERE code = 'DIGGING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'zappatura' WHERE code = 'HOEING' AND agea_value IS NULL;
-- Phenological events: main BBCH stage of sheet 2 ("fasi fenologiche")
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'BBCH 0 - Germinazione' WHERE code = 'EMERGENCE' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'BBCH 6 - Fioritura' WHERE code = 'FLOWERING' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'BBCH 7 - Sviluppo dei frutti' WHERE code = 'FRUIT_SET' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_operation_type SET agea_value = 'BBCH 8 - Maturazione' WHERE code = 'VERAISON' AND agea_value IS NULL;

-- Fertilizer types: effluents of sheet 6
INSERT IGNORE INTO llx_dolifarm_c_fert_type (code, label, active, position) VALUES
('DIGEST_SOLID', 'Digestato separato solido', 1, 132),
('DIGEST_LIQUID', 'Digestato separato liquido', 1, 134),
('WASTEWATER', 'Acque reflue (DM 5046/2016)', 1, 136);
UPDATE llx_dolifarm_c_fert_type SET label = 'Digestato tal quale' WHERE code = 'DIGEST' AND label = 'Digestato';

UPDATE llx_dolifarm_c_fert_type SET fert_group = 'MIN' WHERE code IN ('MIN_N', 'MIN_P', 'MIN_K', 'MIN_NPK', 'MESO_MICRO', 'ORGMIN', 'CORRECT', 'BIOSTIM') AND fert_group IS NULL;
UPDATE llx_dolifarm_c_fert_type SET fert_group = 'ORG' WHERE code IN ('ORG', 'AMMEND', 'MANURE', 'SLURRY', 'DIGEST', 'DIGEST_SOLID', 'DIGEST_LIQUID', 'WASTEWATER', 'SLUDGE') AND fert_group IS NULL;
UPDATE llx_dolifarm_c_fert_type SET agea_value = 'letame' WHERE code = 'MANURE' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_fert_type SET agea_value = 'liquame' WHERE code = 'SLURRY' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_fert_type SET agea_value = 'digestato tal quale' WHERE code = 'DIGEST' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_fert_type SET agea_value = 'digestato separato solido' WHERE code = 'DIGEST_SOLID' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_fert_type SET agea_value = 'digestato separato liquido' WHERE code = 'DIGEST_LIQUID' AND agea_value IS NULL;
UPDATE llx_dolifarm_c_fert_type SET agea_value = 'acque reflue ai sensi del decreto n. 5046/2016' WHERE code = 'WASTEWATER' AND agea_value IS NULL;

-- Fertilizers: copper (sheet 7), efficiency and dry matter (sheet 6)
ALTER TABLE llx_dolifarm_fertilizer ADD COLUMN cu_content double(8,2);
ALTER TABLE llx_dolifarm_fertilizer ADD COLUMN efficiency double(8,2);
ALTER TABLE llx_dolifarm_fertilizer ADD COLUMN dry_matter double(8,2);
