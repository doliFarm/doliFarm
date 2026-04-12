-- ==============================================================================
-- POPOLAMENTO AGROFARMACI REALI (IDEMPOTENTE)
-- Questo script popola sia llx_product che llx_dolifarm_agrodrug.
-- Può essere eseguito più volte senza creare duplicati.
-- ==============================================================================

-- Disabilitiamo i controlli FK temporaneamente per velocità
SET FOREIGN_KEY_CHECKS=0;

-- ------------------------------------------------------------------------------
-- STEP 1: Creazione Prodotti in Magazzino (llx_product)
-- Se il REF esiste già, non fa nulla (IGNORE).
-- ------------------------------------------------------------------------------

INSERT IGNORE INTO llx_product (ref, label, fk_product_type, tobuy, tosell, entity, import_key, datec) VALUES
('AGRO-IT-001', 'POLTIGLIA 20 WG (Manica)', 0, 1, 0, 1,  'MARKET_IT_26', NOW()),
('AGRO-IT-002', 'TIOVIT JET (Syngenta)', 0, 1, 0, 1,  'MARKET_IT_26', NOW()),
('AGRO-IT-003', 'SERENADE ASO (Bayer)', 0, 1, 0, 1,  'MARKET_IT_26', NOW()),
('AGRO-IT-004', 'AIRONE PIU (Isagro)', 0, 1, 0, 1,  'MARKET_IT_26', NOW()),
('AGRO-IT-005', 'SPINTOR FLY (Corteva)', 0, 1, 0, 1,  'MARKET_IT_26', NOW()),
('AGRO-IT-006', 'LASER (Corteva)', 0, 1, 0, 1,  'MARKET_IT_26', NOW()),
('AGRO-IT-007', 'PYGANIC 1.4 (Gowan)', 0, 1, 0, 1,  'MARKET_IT_26', NOW()),
('AGRO-IT-008', 'DIPEL DF (Sumitomo)', 0, 1, 0, 1,  'MARKET_IT_26', NOW()),
('AGRO-IT-009', 'ROUNDUP PLATINUM (Bayer)', 0, 1, 0, 1,  'MARKET_IT_26', NOW()),
('AGRO-IT-010', 'NEEMAZAL T/S (Adama)', 0, 1, 0, 1,  'MARKET_IT_26', NOW());


-- ------------------------------------------------------------------------------
-- STEP 2: Creazione Scheda Tecnica (llx_dolifarm_agrodrug)
-- Usiamo una INSERT ... SELECT per recuperare dinamicamente l'ID (rowid) 
-- corretto dal prodotto appena creato o esistente.
-- ------------------------------------------------------------------------------

-- POLTIGLIA 20 WG
INSERT IGNORE INTO llx_dolifarm_agrodrug (
    ref, label, fk_product, reg_number, reg_date, manufacturer, active_substance, content_percentage, formulation, hazard_classification, safety_days_default, max_dose_ha, status, date_creation, import_key
)
SELECT 
    'AGRO-IT-001', p.label, p.rowid, 
    '12928', '2005-10-04', 'MANICA SPA', 
    'Rame Metallo (da Poltiglia Bordolese)', '20%', 'WG', 'H410', 20, 4.0, 1, NOW(), 'MARKET_IT_26'
FROM llx_product p WHERE p.ref = 'AGRO-IT-001';

-- TIOVIT JET
INSERT IGNORE INTO llx_dolifarm_agrodrug (
    ref, label, fk_product, reg_number, reg_date, manufacturer, active_substance, content_percentage, formulation, hazard_classification, safety_days_default, max_dose_ha, status, date_creation, import_key
)
SELECT 
    'AGRO-IT-002', p.label, p.rowid, 
    '9482', '1998-01-20', 'SYNGENTA ITALIA', 
    'Zolfo', '80%', 'WG', 'H315', 5, 8.0, 1, NOW(), 'MARKET_IT_26'
FROM llx_product p WHERE p.ref = 'AGRO-IT-002';

-- SERENADE ASO
INSERT IGNORE INTO llx_dolifarm_agrodrug (
    ref, label, fk_product, reg_number, reg_date, manufacturer, active_substance, content_percentage, formulation, hazard_classification, safety_days_default, max_dose_ha, status, date_creation, import_key
)
SELECT 
    'AGRO-IT-003', p.label, p.rowid, 
    '16447', '2016-02-15', 'BAYER CROPSCIENCE', 
    'Bacillus subtilis (ceppo QST 713)', '1.34%', 'SC', 'NC', 0, 8.0, 1, NOW(), 'MARKET_IT_26'
FROM llx_product p WHERE p.ref = 'AGRO-IT-003';

-- AIRONE PIU
INSERT IGNORE INTO llx_dolifarm_agrodrug (
    ref, label, fk_product, reg_number, reg_date, manufacturer, active_substance, content_percentage, formulation, hazard_classification, safety_days_default, max_dose_ha, status, date_creation, import_key
)
SELECT 
    'AGRO-IT-004', p.label, p.rowid, 
    '13364', '2009-02-10', 'ISAGRO SPA', 
    'Rame (Ossicloruro + Idrossido)', '28%', 'WG', 'H302', 20, 3.5, 1, NOW(), 'MARKET_IT_26'
FROM llx_product p WHERE p.ref = 'AGRO-IT-004';

-- SPINTOR FLY
INSERT IGNORE INTO llx_dolifarm_agrodrug (
    ref, label, fk_product, reg_number, reg_date, manufacturer, active_substance, content_percentage, formulation, hazard_classification, safety_days_default, max_dose_ha, status, date_creation, import_key
)
SELECT 
    'AGRO-IT-005', p.label, p.rowid, 
    '12266', '2004-11-29', 'CORTEVA AGRISCIENCE', 
    'Spinosad', '0.024%', 'CB', 'NC', 7, 1.2, 1, NOW(), 'MARKET_IT_26'
FROM llx_product p WHERE p.ref = 'AGRO-IT-005';

-- LASER
INSERT IGNORE INTO llx_dolifarm_agrodrug (
    ref, label, fk_product, reg_number, reg_date, manufacturer, active_substance, content_percentage, formulation, hazard_classification, safety_days_default, max_dose_ha, status, date_creation, import_key
)
SELECT 
    'AGRO-IT-006', p.label, p.rowid, 
    '10398', '2000-03-01', 'CORTEVA AGRISCIENCE', 
    'Spinosad', '44.2%', 'SC', 'H410', 15, 0.25, 1, NOW(), 'MARKET_IT_26'
FROM llx_product p WHERE p.ref = 'AGRO-IT-006';

-- PYGANIC 1.4
INSERT IGNORE INTO llx_dolifarm_agrodrug (
    ref, label, fk_product, reg_number, reg_date, manufacturer, active_substance, content_percentage, formulation, hazard_classification, safety_days_default, max_dose_ha, status, date_creation, import_key
)
SELECT 
    'AGRO-IT-007', p.label, p.rowid, 
    '15911', '2014-01-20', 'GOWAN ITALIA', 
    'Piretrine naturali', '1.4%', 'EC', 'H410', 2, 2.5, 1, NOW(), 'MARKET_IT_26'
FROM llx_product p WHERE p.ref = 'AGRO-IT-007';

-- DIPEL DF
INSERT IGNORE INTO llx_dolifarm_agrodrug (
    ref, label, fk_product, reg_number, reg_date, manufacturer, active_substance, content_percentage, formulation, hazard_classification, safety_days_default, max_dose_ha, status, date_creation, import_key
)
SELECT 
    'AGRO-IT-008', p.label, p.rowid, 
    '14002', '2008-01-10', 'SUMITOMO CHEMICAL', 
    'Bacillus Thuringiensis (Kurstaki)', '54%', 'WG', 'NC', 0, 1.0, 1, NOW(), 'MARKET_IT_26'
FROM llx_product p WHERE p.ref = 'AGRO-IT-008';

-- ROUNDUP PLATINUM
INSERT IGNORE INTO llx_dolifarm_agrodrug (
    ref, label, fk_product, reg_number, reg_date, manufacturer, active_substance, content_percentage, formulation, hazard_classification, safety_days_default, max_dose_ha, status, date_creation, import_key, note_public
)
SELECT 
    'AGRO-IT-009', p.label, p.rowid, 
    '16301', '2016-04-01', 'BAYER', 
    'Glifosate (sale potassico)', '48%', 'SL', 'H411', 0, 3.0, 1, NOW(), 'MARKET_IT_26', 'NON AMMESSO IN BIO'
FROM llx_product p WHERE p.ref = 'AGRO-IT-009';

-- NEEMAZAL T/S
INSERT IGNORE INTO llx_dolifarm_agrodrug (
    ref, label, fk_product, reg_number, reg_date, manufacturer, active_substance, content_percentage, formulation, hazard_classification, safety_days_default, max_dose_ha, status, date_creation, import_key
)
SELECT 
    'AGRO-IT-010', p.label, p.rowid, 
    '11561', '2003-01-20', 'ADAMA ITALIA', 
    'Azadiractina A (da olio di Neem)', '1%', 'EC', 'H411', 3, 3.0, 1, NOW(), 'MARKET_IT_26'
FROM llx_product p WHERE p.ref = 'AGRO-IT-010';

SET FOREIGN_KEY_CHECKS=1;