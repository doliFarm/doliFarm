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

-- Organic control bodies authorised in Italy (official code IT-BIO-xxx), with the data of the national head office.
-- Source: list of the authorised control bodies of the Regione Emilia-Romagna, update of 03/10/2024 (from the MASAF
-- authorisations). The administrator updates it in Setup > Dictionaries; a body chosen in a dossier or an inspection
-- becomes a third party of type "Organic control body" with these data.
CREATE TABLE IF NOT EXISTS llx_dolifarm_c_biocontrolbody (
    rowid        INTEGER AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(32) NOT NULL,
    label        VARCHAR(255) NOT NULL,
    acronym      VARCHAR(64) DEFAULT NULL,
    address      VARCHAR(255) DEFAULT NULL,
    zip          VARCHAR(25) DEFAULT NULL,
    town         VARCHAR(100) DEFAULT NULL,
    country_code VARCHAR(2) DEFAULT 'IT',
    phone        VARCHAR(32) DEFAULT NULL,
    email        VARCHAR(128) DEFAULT NULL,
    url          VARCHAR(255) DEFAULT NULL,
    active       TINYINT DEFAULT 1,
    module       VARCHAR(32) DEFAULT NULL,
    position     INTEGER DEFAULT 0,
    UNIQUE INDEX uk_dolifarm_c_biocontrolbody_code (code)
) ENGINE=InnoDB;

INSERT IGNORE INTO llx_dolifarm_c_biocontrolbody (code, label, acronym, address, zip, town, country_code, phone, email, url, active, position) VALUES
('IT-BIO-002', 'Codex S.r.l.', 'CODEX', 'Via G. Amendola, 14', '95048', 'Scordia', 'IT', '095 650634', 'codex@codexsrl.it', 'www.codexsrl.it', 1, 10),
('IT-BIO-004', 'Suolo e Salute S.r.l.', 'Suolo e Salute', 'Via P. Borsellino, 12/B', '61032', 'Fano', 'IT', '0721 860543', 'info@suoloesalute.it', 'www.suoloesalute.it', 1, 20),
('IT-BIO-005', 'BIOS S.r.l.', 'BIOS', 'Via Montello, 6', '36063', 'Marostica', 'IT', '0424 471125', 'info@certbios.it', 'www.certbios.it', 1, 30),
('IT-BIO-006', 'Istituto per la Certificazione Etica e Ambientale', 'ICEA', 'Via Giovanni Brugnoli, 15', '40122', 'Bologna', 'IT', '051 272986', 'icea@icea.info', 'www.icea.it', 1, 40),
('IT-BIO-007', 'Bioagricert S.r.l.', 'Bioagricert', 'Via dei Macabraccia, 8/3-4-5', '40033', 'Casalecchio di Reno', 'IT', '051 562158', 'info@bioagricert.org', 'www.bioagricert.org', 1, 50),
('IT-BIO-008', 'Ecogruppo Italia S.r.l.', 'Ecogruppo Italia', 'Via Siracusa snc', '95037', 'San Giovanni La Punta', 'IT', '095 8256464', 'info@ecogruppoitalia.it', 'www.ecogruppoitalia.it', 1, 60),
('IT-BIO-009', 'CCPB S.r.l.', 'CCPB', 'Viale Masini, 36', '40126', 'Bologna', 'IT', '051 6089811', 'ccpb@ccpb.it', 'www.ccpb.it', 1, 70),
('IT-BIO-012', 'Sidel S.p.A.', 'Sidel', 'Via Tosarelli, 362', '40055', 'Villanova di Castenaso', 'IT', '051 0403611', 'segreteria.bio@sidelitalia.it', 'www.sidelitalia.it', 1, 80),
('IT-BIO-013', 'ABCert S.r.l.', 'ABCert', 'Zona industriale, 1/5', '39011', 'Lana', 'IT', '0473 864500', 'info@abcert.it', 'www.abcert.it', 1, 90),
('IT-BIO-014', 'QCertificazioni S.r.l.', 'QCertificazioni', 'Via Paolo Fraiese, 37', '53100', 'Siena', 'IT', '0577 327234', 'lettera@qc.bureauveritas.com', 'www.qcertificazioni.it', 1, 100),
('IT-BIO-015', 'Valoritalia S.r.l.', 'Valoritalia', 'Via Venti Settembre, 98/G', '00187', 'Roma', 'IT', '0141 436915', 'biologico@valoritalia.it', 'www.valoritalia.it', 1, 110),
('IT-BIO-016', 'Siquria S.p.A.', 'Siquria', 'Via Pigafetta, 47', '37047', 'San Bonifacio', 'IT', '045 4857514', 'info@siquria.it', 'www.siquria.it', 1, 120),
('IT-BIO-017', 'CEVIQ S.r.l.', 'CEVIQ', 'Via Bortolossi, 1', '33040', 'Pradamano', 'IT', '0432 510619', 'info@ceviq.it', 'www.ceviq.it', 1, 130),
('IT-BIO-018', 'RINA Agrifood S.p.A.', 'RINA Agrifood', 'Viale Cesare Pavese, 305', '00144', 'Roma', 'IT', '06 54228675', 'biologico@rina.org', 'www.rina.org', 1, 140),
('IT-BIO-019', 'Istituto Nord Ovest Qualità Soc. Coop.', 'INOQ', 'Piazza Carlo Alberto Grosso, 82', '12033', 'Moretta', 'IT', '0172 911323', 'bio@inoq.it', 'www.inoq.it', 1, 150),
('IT-BIO-020', 'Dipartimento di Qualità Agroalimentare S.r.l.', 'DQA', 'Via Nazionale, 89/A', '00161', 'Roma', 'IT', '06 85451246', 'info@dqacertificazioni.it', 'www.dqacertificazioni.it', 1, 160),
('IT-BIO-021', 'CSQA Certificazioni S.r.l.', 'CSQA', 'Via S. Gaetano, 74', '36016', 'Thiene', 'IT', '0445 313011', 'csqa@csqa.it', 'www.csqa.it', 1, 170),
('IT-BIO-022', 'Ambiente Sostenibilità e Territorio S.r.l.', 'A.S.TER.', 'Via Ravegnana, 126', '47122', 'Forlì', 'IT', '0543 1796986', 'info@aster.bio', 'www.aster.bio', 1, 180),
('IT-BIO-023', 'Qualitaly S.r.l.', 'Qualitaly', 'Via Case Sparse, 2', '29010', 'Castelvetro Piacentino', 'IT', '0523 815106', 'info@qualitaly.bio', 'www.qualitaly.bio', 1, 190),
('IT-BIO-001BZ', 'Bio Garantie S.r.l. (solo Provincia di Bolzano)', 'Bio Garantie', 'Troyenbachstraße, 1E', '39030', 'Vandoies', 'IT', '+43 5 9292 3100', 'suedtirol@bio-garantie.it', 'www.bio-garantie.it', 1, 200),
('IT-BIO-003BZ', 'QC&I GmbH (solo Provincia di Bolzano)', 'QC&I', 'Gleuelerstrasse, 286', '50935', 'Köln', 'DE', '+49 6551 147641', 'qci.koeln@qci.de', 'www.qci.de', 1, 210);
