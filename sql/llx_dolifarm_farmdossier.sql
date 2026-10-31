-- Copyright (C) 2026		SuperAdmin
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE IF NOT EXISTS llx_dolifarm_farmdossier(
	-- BEGIN MODULEBUILDER FIELDS
	rowid int AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(128) NOT NULL, 
	entity INTEGER DEFAULT 1,
	fk_soc integer NOT NULL, 
	fk_user_validator integer, 
	year_validity int, 
	paying_agency varchar(32), 
	act_type integer, 
	sheet_number varchar(32), 
	protocol varchar(64), 
	date_signature date, 
	date_validity_end date, 
	date_reference date, 
	fk_soc_caa integer, 
	date_mandate date, 
	ote_code varchar(16), 
	economic_size double(24,8), 
	fk_soc_bio_body integer, 
	date_bio_membership date, 
	fk_warehouse_inputs integer, 
	fk_warehouse_products integer, 
	date_import datetime, 
	status int, 
	source_file varchar(255), 
	note text, 
	date_creation datetime, 
	date_valid datetime, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	fk_user_creat integer, 
	fk_user_modif integer, 
	import_key varchar(14),
	model_pdf       VARCHAR(255),
    last_main_doc   VARCHAR(255),
    note_private    TEXT,
    note_public     TEXT
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;

SET FOREIGN_KEY_CHECKS=1;