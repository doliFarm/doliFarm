--
-- Inserimento Tipi di Terzi per Dolifarm
--

-- Inseriamo FARM solo se non esiste (INSERT IGNORE evita errori fatali)
-- Inseriamo 'dolifarm' nella colonna module per tracciare l'origine
-- INSERT IGNORE INTO llx_c_typent (code, libelle, active, position, module) 
-- VALUES ('TE_FARM', 'Azienda Agricola', 1, 20, 'dolifarm');

-- Se esisteva già ma era disattivato (active=0), lo riattiviamo
-- UPDATE llx_c_typent SET active = 1 WHERE code = 'TE_FARM';

--
-- File dati per Dolifarm
-- Inserimento robusto in llx_c_typent con calcolo manuale dell'ID
--

-- ==============================================================================
-- INSERIMENTO TIPI DI TERZI (Con calcolo ID manuale)
-- ==============================================================================

-- 1. TE_FARM: Azienda Agricola
INSERT IGNORE INTO llx_c_typent (id, code, libelle, active, module)
SELECT 
    (SELECT COALESCE(MAX(id), 0) + 1 FROM llx_c_typent),
    'TE_FARM', 
    'Azienda Agricola', 
    1, 
    'dolifarm'
FROM DUAL
WHERE NOT EXISTS (SELECT code FROM llx_c_typent WHERE code = 'TE_FARM');

-- 2. TE_LAB: Laboratorio
INSERT IGNORE INTO llx_c_typent (id, code, libelle, active, module)
SELECT 
    (SELECT COALESCE(MAX(id), 0) + 1 FROM llx_c_typent),
    'TE_LAB', 
    'Laboratorio', 
    1, 
    'dolifarm'
FROM DUAL
WHERE NOT EXISTS (SELECT code FROM llx_c_typent WHERE code = 'TE_LAB');

-- 3. TE_HUB: Food Hub
INSERT IGNORE INTO llx_c_typent (id, code, libelle, active, module)
SELECT 
    (SELECT COALESCE(MAX(id), 0) + 1 FROM llx_c_typent),
    'TE_HUB', 
    'Food Hub', 
    1, 
    'dolifarm'
FROM DUAL
WHERE NOT EXISTS (SELECT code FROM llx_c_typent WHERE code = 'TE_HUB');

-- 4. TE_GROSS: Rivenditore
INSERT IGNORE INTO llx_c_typent (id, code, libelle, active, module)
SELECT 
    (SELECT COALESCE(MAX(id), 0) + 1 FROM llx_c_typent),
    'TE_GROSS', 
    'Rivenditore', 
    1, 
    'dolifarm'
FROM DUAL
WHERE NOT EXISTS (SELECT code FROM llx_c_typent WHERE code = 'TE_GROSS');