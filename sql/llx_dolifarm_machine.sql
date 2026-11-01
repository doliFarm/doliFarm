-- Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU Affero General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU Affero General Public License for more details.
--
-- You should have received a copy of the GNU Affero General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE IF NOT EXISTS llx_dolifarm_machine(
	-- BEGIN MODULEBUILDER FIELDS
	rowid int AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(128), 
	label varchar(255) NOT NULL, 
	entity INTEGER DEFAULT 1,
	fk_soc integer NOT NULL, 
	fk_type varchar(32), 
	fk_dossier int NOT NULL, 
	power_kw double(10,2), 
	fuel_type varchar(32), 
	year_registration int, 
	date_revision_next date,
	date_insurance_end date,
	sprayer_cert_ref varchar(64),
	date_sprayer_check date,
	date_sprayer_check_end date,
	datec DATETIME,
    date_valid DATETIME,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	fk_user_creat integer, 
	fk_user_modif integer,
	model_pdf       VARCHAR(255),
    last_main_doc   VARCHAR(255),
    note_private    TEXT,
    note_public     TEXT,
	status integer
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;

SET FOREIGN_KEY_CHECKS=1;