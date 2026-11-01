-- ==============================================================================
-- ANAGRAFICA PRODOTTI FITOSANITARI
-- Estensione tecnica della tabella llx_product standard
-- ==============================================================================


CREATE TABLE IF NOT EXISTS llx_dolifarm_agrodrug(
	-- BEGIN MODULEBUILDER FIELDS
	rowid int AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(128) NOT NULL, 
	label varchar(255) NOT NULL, 
	entity INTEGER DEFAULT 1,
	-- Link al Magazzino Dolibarr (Fondamentale per stock e acquisti)
	fk_product integer NOT NULL, 
	-- Link al Magazzino Dolibarr (Fondamentale per stock e acquisti)
	reg_number varchar(64), 
	reg_date date, 
	auth_expiration_date date, 
	manufacturer varchar(255), 
	active_substance text, 
	content_percentage varchar(64), 
	formulation varchar(128), 
	hazard_classification varchar(128), 
	-- Dati Agronomici
	safety_days_default int, 
	max_dose_ha double(24,8), 
	max_applications integer,
	moa_codes varchar(128),
	organic_allowed smallint DEFAULT 0,
	cu_content double(24,8),
	admin_status varchar(128),
	date_revocation date,
	date_catalog_sync datetime,
	organic_confirm_user integer,
	organic_confirm_date datetime,
	note_public text, 
	note_private text, 
	status int, 
	-- Tracking
	date_creation datetime, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	fk_user_creat integer, 
	fk_user_modif integer, 
	import_key varchar(14)
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
