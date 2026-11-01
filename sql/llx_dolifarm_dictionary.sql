-- ==============================================================================
-- DIZIONARI MODULO DOLIFARM
-- Standard Dolibarr (Prefisso tabella: llx_dolifarm_c_)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS=0;
-- ------------------------------------------------------------------------------
-- 1. DIZIONARIO: TIPO DI POSSESSO TERRENO (Ownership)
-- Riferito da: llx_dolifarm_plot.fk_ownership
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_dolifarm_c_ownership (
    rowid       INTEGER AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(32) NOT NULL,
    label       VARCHAR(128) NOT NULL,
    active      TINYINT DEFAULT 1,
    module      VARCHAR(32) DEFAULT NULL,
    position    INTEGER DEFAULT 0,
    UNIQUE INDEX uk_dolifarm_c_ownership_code (code)
) ENGINE=InnoDB;

-- Dati di default
INSERT IGNORE INTO llx_dolifarm_c_ownership (code, label, active, position) VALUES 
('OWNED', 'Proprietà', 1, 10),
('RENTED', 'Affitto', 1, 20),
('LEASING', 'Leasing', 1, 30),
('USUFRUCT', 'Usufrutto', 1, 40),
('COMODATO', 'Comodato d\'uso', 1, 50);


-- ------------------------------------------------------------------------------
-- 2. DIZIONARIO: STATO BIOLOGICO (Bio Status)
-- Riferito da: llx_dolifarm_plot.fk_status_bio
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_dolifarm_c_biostatus (
    rowid       INTEGER AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(32) NOT NULL,
    label       VARCHAR(128) NOT NULL,
    description VARCHAR(255),
    active      TINYINT DEFAULT 1,
    module      VARCHAR(32) DEFAULT NULL,
    position    INTEGER DEFAULT 0,
    UNIQUE INDEX uk_dolifarm_c_biostatus_code (code)
) ENGINE=InnoDB;

-- Dati di default
INSERT IGNORE INTO llx_dolifarm_c_biostatus (code, label, description, active, position) VALUES 
('CONV', 'Convenzionale', 'Agricoltura standard non biologica', 1, 10),
('BIO_CONV_1', 'In Conversione (Anno 1)', 'Primo anno di conversione al biologico', 1, 20),
('BIO_CONV_2', 'In Conversione (Anno 2)', 'Secondo anno di conversione al biologico', 1, 30),
('BIO_CONV_3', 'In Conversione (Anno 3)', 'Terzo anno di conversione (se applicabile)', 1, 35),
('BIO_FULL', 'Biologico Certificato', 'Pienamente certificato BIO', 1, 40),
('BIODYNAMIC', 'Biodinamico', 'Certificazione Demeter o similare', 1, 50);


-- ------------------------------------------------------------------------------
-- 3. DIZIONARIO: TIPO MACCHINA (Machine Type)
-- Riferito da: llx_dolifarm_machine.fk_type
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_dolifarm_c_machinetype (
    rowid       INTEGER AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(32) NOT NULL,
    label       VARCHAR(128) NOT NULL,
    active      TINYINT DEFAULT 1,
    module      VARCHAR(32) DEFAULT NULL,
    position    INTEGER DEFAULT 0,
    UNIQUE INDEX uk_dolifarm_c_machinetype_code (code)
) ENGINE=InnoDB;

-- Dati di default
INSERT IGNORE INTO llx_dolifarm_c_machinetype (code, label, active, position) VALUES 
('TRACTOR_WHEEL', 'Trattore Gommato', 1, 10),
('TRACTOR_TRACK', 'Trattore Cingolato', 1, 20),
('HARVESTER', 'Mietitrebbia/Raccoglitrice', 1, 30),
('TOOL_PLOW', 'Aratro', 1, 40),
('TOOL_HARROW', 'Erpice', 1, 50),
('TOOL_SOWER', 'Seminatrice', 1, 60),
('TOOL_SPRAYER', 'Atomizzatore/Botte', 1, 70),
('TOOL_FERTILIZER', 'Spandiconcime', 1, 80),
('TRAILER', 'Rimorchio', 1, 90),
('IRRIGATION', 'Impianto Irrigazione Mobile', 1, 100),
('DRONE', 'Drone/UAV', 1, 110),
('OTHER', 'Altro', 1, 999);


-- ------------------------------------------------------------------------------
-- 4. DIZIONARIO AGGIUNTIVO: TIPO CARBURANTE (Opzionale ma utile)
-- Riferito da: llx_dolifarm_machine.fuel_type
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_dolifarm_c_fueltype (
    rowid       INTEGER AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(32) NOT NULL,
    label       VARCHAR(128) NOT NULL,
    active      TINYINT DEFAULT 1,
    UNIQUE INDEX uk_dolifarm_c_fueltype_code (code)
) ENGINE=InnoDB;

INSERT IGNORE INTO llx_dolifarm_c_fueltype (code, label, active) VALUES 
('DIESEL', 'Gasolio Agricolo', 1),
('GASOLINE', 'Benzina', 1),
('ELECTRIC', 'Elettrico', 1),
('HYBRID', 'Ibrido', 1),
('METHANE', 'Biometano', 1),
('NONE', 'Nessuno (Attrezzo Passivo)', 1);




-- ==============================================================================
-- 5. DIZIONARIO TIPI OPERAZIONI COLTURALI
-- Tabella: llx_dolifarm_c_operation_type
-- Mapping: Legacy OES -> Dolifarm
-- ==============================================================================

-- Eliminazione tabella se esiste per rigenerazione pulita

CREATE TABLE IF NOT EXISTS llx_dolifarm_c_operation_type (
  rowid       integer NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code        varchar(32) NOT NULL,
  label       varchar(128) NOT NULL,
  active      tinyint DEFAULT 1,
  module      varchar(32) DEFAULT NULL,
  position integer DEFAULT 0,
  
  -- Raggruppamento logico per interfaccia (es. Soil, Crop, Harvest)
  category    varchar(32) DEFAULT 'GENERIC', 
  
  -- Voce della lista AGEA del quaderno di campagna (fasi colturali e fenologiche)
  agea_value  varchar(128) DEFAULT NULL,

  -- Vincoli
  UNIQUE KEY uk_dolifarm_c_operation_type_code (code)
) ENGINE=InnoDB;

-- ------------------------------------------------------------------------------
-- POPOLAMENTO DATI
-- ------------------------------------------------------------------------------

-- a. OPERAZIONI DI PREPARAZIONE SUOLO (Legacy: TT_Cultivation)
INSERT IGNORE INTO llx_dolifarm_c_operation_type (code, label, active, category) VALUES 
('TT_Cultivation',  'Lavorazione Suolo (Generica)', 1, 'SOIL'),
('SOIL_PLOWING',    'Aratura', 1, 'SOIL'),
('SOIL_HARROWING',  'Erpicatura', 1, 'SOIL'),
('SOIL_HOEING',     'Sarchiatura', 1, 'SOIL');

-- b. OPERAZIONI DI SEMINA/IMPIANTO (Legacy: cm_plantings)
INSERT IGNORE  INTO llx_dolifarm_c_operation_type (code, label, active, category) VALUES 
('PLANTING',        'Semina / Trapianto', 1, 'CROP'),
('SOWING_DIRECT',   'Semina Diretta', 1, 'CROP'),
('TRANSPLANTING',   'Trapianto', 1, 'CROP');

-- c. TRATTAMENTI E NUTRIZIONE (Legacy: TT_Fertiliser, TT_Pesticide)
INSERT IGNORE  INTO llx_dolifarm_c_operation_type (code, label, active, category) VALUES 
('TT_Fertiliser',   'Concimazione', 1, 'NUTRI'),
('TT_Pesticide',    'Trattamento Fitosanitario', 1, 'PROTECTION'),
('IRRIGATION',      'Irrigazione', 1, 'WATER');

-- d. RACCOLTA E POST-RACCOLTA (Legacy: cm_harvests)
INSERT IGNORE  INTO llx_dolifarm_c_operation_type (code, label, active, category) VALUES 
('HARVEST',         'Raccolta Principale', 1, 'HARVEST'),
('HARVEST_THIN',    'Diradamento', 1, 'HARVEST');

-- e. ALTRE ATTIVITÀ (Legacy: grazings, etc.)
INSERT IGNORE  INTO llx_dolifarm_c_operation_type (code, label, active, category) VALUES 
('GRAZING',         'Pascolo', 1, 'ANIMAL'),
('SCOUTING',        'Monitoraggio / Scouting', 1, 'MGMT'),
('MOWING',          'Sfalcio', 1, 'MAINTENANCE');

-- f. EVENTI COLTURALI del quaderno di campagna (sezione "eventi colturali" del QDCA AGEA)
INSERT IGNORE INTO llx_dolifarm_c_operation_type (code, label, active, category) VALUES
('EMERGENCE',       'Emergenza / Ripresa vegetativa', 1, 'PHENO'),
('FLOWERING',       'Fioritura', 1, 'PHENO'),
('FRUIT_SET',       'Allegagione', 1, 'PHENO'),
('VERAISON',        'Invaiatura / Inizio maturazione', 1, 'PHENO'),
('PRUNING',         'Potatura', 1, 'MAINTENANCE'),
('CROP_END',        'Fine coltura / Estirpo', 1, 'CROP');

SET FOREIGN_KEY_CHECKS=1;


CREATE TABLE IF NOT EXISTS llx_dolifarm_c_paying_agency (
    rowid       INTEGER AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(32) NOT NULL,
    label       VARCHAR(128) NOT NULL,
    active      TINYINT DEFAULT 1,
    module      VARCHAR(32) DEFAULT NULL,
    position    INTEGER DEFAULT 0,
    UNIQUE INDEX uk_dolifarm_c_paying_agency_code (code)
) ENGINE=InnoDB;

INSERT IGNORE INTO llx_dolifarm_c_paying_agency (code, label, active, position) VALUES
('AGEA', 'AGEA - Agenzia per le Erogazioni in Agricoltura', 1, 10),
('AGREA', 'AGREA - Emilia-Romagna', 1, 20),
('APPAG', 'APPAG - Provincia autonoma di Trento', 1, 30),
('ARCEA', 'ARCEA - Calabria', 1, 40),
('ARGEA', 'ARGEA - Sardegna', 1, 50),
('ARPEA', 'ARPEA - Piemonte', 1, 60),
('ARTEA', 'ARTEA - Toscana', 1, 70),
('AVEPA', 'AVEPA - Veneto', 1, 80),
('OPLO', 'OPLO - Lombardia', 1, 90),
('OPPAB', 'OPPAB - Provincia autonoma di Bolzano', 1, 100);

-- Comuni with their cadastral (Belfiore) code, data in data_dolifarm_cadastral_comune.sql (ISTAT, CC-BY 4.0)
CREATE TABLE IF NOT EXISTS llx_dolifarm_c_cadastral_comune (
    rowid       INTEGER AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(4) NOT NULL,
    label       VARCHAR(128) NOT NULL,
    province    VARCHAR(2) DEFAULT NULL,
    istat_code  VARCHAR(6) DEFAULT NULL,
    active      TINYINT DEFAULT 1,
    UNIQUE INDEX uk_dolifarm_c_cadastral_comune_code (code)
) ENGINE=InnoDB;

-- Livestock species (consistenza zootecnica)
CREATE TABLE IF NOT EXISTS llx_dolifarm_c_livestock_species (
    rowid       INTEGER AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(32) NOT NULL,
    label       VARCHAR(128) NOT NULL,
    active      TINYINT DEFAULT 1,
    module      VARCHAR(32) DEFAULT NULL,
    position    INTEGER DEFAULT 0,
    UNIQUE INDEX uk_dolifarm_c_livestock_species_code (code)
) ENGINE=InnoDB;

INSERT IGNORE INTO llx_dolifarm_c_livestock_species (code, label, active, position) VALUES
('BOVINI', 'Bovini', 1, 10),
('BUFALINI', 'Bufalini', 1, 20),
('OVINI', 'Ovini', 1, 30),
('CAPRINI', 'Caprini', 1, 40),
('SUINI', 'Suini', 1, 50),
('EQUINI', 'Equini', 1, 60),
('ASININI', 'Asinini', 1, 70),
('AVICOLI', 'Avicoli', 1, 80),
('CUNICOLI', 'Cunicoli', 1, 90),
('API', 'Api', 1, 100),
('ACQUACOLTURA', 'Acquacoltura', 1, 110),
('ALTRO', 'Altre specie', 1, 900);

-- Fertilizer types (registro delle fertilizzazioni)
CREATE TABLE IF NOT EXISTS llx_dolifarm_c_fert_type (
    rowid       INTEGER AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(32) NOT NULL,
    label       VARCHAR(128) NOT NULL,
    active      TINYINT DEFAULT 1,
    module      VARCHAR(32) DEFAULT NULL,
    position    INTEGER DEFAULT 0,
    fert_group  VARCHAR(8) DEFAULT NULL,
    agea_value  VARCHAR(128) DEFAULT NULL,
    UNIQUE INDEX uk_dolifarm_c_fert_type_code (code)
) ENGINE=InnoDB;

INSERT IGNORE INTO llx_dolifarm_c_fert_type (code, label, active, position) VALUES
('MIN_N', 'Concime minerale azotato', 1, 10),
('MIN_P', 'Concime minerale fosfatico', 1, 20),
('MIN_K', 'Concime minerale potassico', 1, 30),
('MIN_NPK', 'Concime minerale composto', 1, 40),
('MESO_MICRO', 'Concime a base di meso o microelementi', 1, 50),
('ORGMIN', 'Concime organo-minerale', 1, 60),
('ORG', 'Concime organico', 1, 70),
('AMMEND', 'Ammendante', 1, 80),
('CORRECT', 'Correttivo', 1, 90),
('BIOSTIM', 'Biostimolante', 1, 100),
('MANURE', 'Effluente zootecnico palabile (letame)', 1, 110),
('SLURRY', 'Effluente zootecnico non palabile (liquame)', 1, 120),
('DIGEST', 'Digestato tal quale', 1, 130),
('SLUDGE', 'Fanghi di depurazione', 1, 140),
('OTHER', 'Altro', 1, 900);

-- Label of an operation type already installed: mowing and shredding are distinct in the AGEA list
UPDATE llx_dolifarm_c_operation_type SET label = 'Sfalcio' WHERE code = 'MOWING' AND label IN ('Sfalerciatura / Trinciatura', 'Sfalciatura / Trinciatura');
