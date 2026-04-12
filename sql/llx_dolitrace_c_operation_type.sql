-- ==============================================================================
-- DIZIONARIO TIPI OPERAZIONI COLTURALI
-- Tabella: llx_dolitrace_c_operation_type
-- Mapping: Legacy OES -> Dolitrace
-- ==============================================================================

-- Eliminazione tabella se esiste per rigenerazione pulita
DROP TABLE IF EXISTS llx_dolitrace_c_operation_type;

CREATE TABLE llx_dolitrace_c_operation_type (
  rowid       integer NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code        varchar(32) NOT NULL,
  label       varchar(128) NOT NULL,
  active      tinyint DEFAULT 1,
  module      varchar(32) DEFAULT NULL,
  
  -- Raggruppamento logico per interfaccia (es. Soil, Crop, Harvest)
  category    varchar(32) DEFAULT 'GENERIC', 
  
  -- Vincoli
  UNIQUE KEY uk_dolitrace_c_operation_type_code (code)
) ENGINE=InnoDB;

-- ------------------------------------------------------------------------------
-- POPOLAMENTO DATI
-- ------------------------------------------------------------------------------

-- 1. OPERAZIONI DI PREPARAZIONE SUOLO (Legacy: TT_Cultivation)
INSERT INTO llx_dolitrace_c_operation_type (code, label, active, category) VALUES 
('TT_Cultivation',  'Lavorazione Suolo (Generica)', 1, 'SOIL'),
('SOIL_PLOWING',    'Aratura', 1, 'SOIL'),
('SOIL_HARROWING',  'Erpicatura', 1, 'SOIL'),
('SOIL_HOEING',     'Sarchiatura', 1, 'SOIL');

-- 2. OPERAZIONI DI SEMINA/IMPIANTO (Legacy: cm_plantings)
INSERT INTO llx_dolitrace_c_operation_type (code, label, active, category) VALUES 
('PLANTING',        'Semina / Trapianto', 1, 'CROP'),
('SOWING_DIRECT',   'Semina Diretta', 1, 'CROP'),
('TRANSPLANTING',   'Trapianto', 1, 'CROP');

-- 3. TRATTAMENTI E NUTRIZIONE (Legacy: TT_Fertiliser, TT_Pesticide)
INSERT INTO llx_dolitrace_c_operation_type (code, label, active, category) VALUES 
('TT_Fertiliser',   'Concimazione', 1, 'NUTRI'),
('TT_Pesticide',    'Trattamento Fitosanitario', 1, 'PROTECTION'),
('IRRIGATION',      'Irrigazione', 1, 'WATER');

-- 4. RACCOLTA E POST-RACCOLTA (Legacy: cm_harvests)
INSERT INTO llx_dolitrace_c_operation_type (code, label, active, category) VALUES 
('HARVEST',         'Raccolta Principale', 1, 'HARVEST'),
('HARVEST_THIN',    'Diradamento', 1, 'HARVEST');

-- 5. ALTRE ATTIVITÀ (Legacy: grazings, etc.)
INSERT INTO llx_dolitrace_c_operation_type (code, label, active, category) VALUES 
('GRAZING',         'Pascolo', 1, 'ANIMAL'),
('SCOUTING',        'Monitoraggio / Scouting', 1, 'MGMT'),
('MOWING',          'Sfalerciatura / Trinciatura', 1, 'MAINTENANCE');
