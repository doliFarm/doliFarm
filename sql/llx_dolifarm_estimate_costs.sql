-- ==============================================================================
-- TABELLA STIME (ESTIMATES)
-- Gestisce tempi e costi standard o specifici per azienda.
-- ==============================================================================

CREATE TABLE llx_dolifarm_estimate (
    rowid               INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity              INTEGER DEFAULT 1,
    
    -- Chiavi di ricerca
    fk_operation_type   VARCHAR(32) NOT NULL,   -- Link a llx_dolitrace_c_operation_type.code
    fk_soc              INTEGER DEFAULT NULL,   -- NULL = Standard Globale, ID = Specifico Azienda
    
    -- Parametri di Stima
    duration_min_ha     INTEGER DEFAULT 0,      -- Minuti stimati per Ettaro
    cost_std_ha         DOUBLE(24,8) DEFAULT 0, -- Costo stimato per Ettaro (Eur/Ha)
    
    -- Campi descrittivi
    label               VARCHAR(255),           -- Chiave di traduzione (se Standard) o Testo libero (se Azienda)
    note_public         TEXT,
    
    -- Standard Dolibarr
    date_creation       DATETIME,
    tms                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat       INTEGER,
    fk_user_modif       INTEGER,
    import_key          VARCHAR(14),
    
    -- Vincolo: Una sola stima per tipo operazione + azienda
    UNIQUE INDEX uk_dolifarm_estimate_target (fk_operation_type, fk_soc, entity)
) ENGINE=InnoDB;