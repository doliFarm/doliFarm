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

CREATE TABLE llx_dolifarm_plot(
	-- BEGIN MODULEBUILDER FIELDS
	rowid int AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(128) NOT NULL, 
	label varchar(255) NOT NULL, 
	uuid varchar(36) NOT NULL,
	entity INTEGER DEFAULT 1,
	fk_soc integer NOT NULL, 
	fk_owner int NOT NULL,
	fk_ownership varchar(100),
	cadastral_comune varchar(100), 
	cadastral_sheet varchar(10), 
	cadastral_parcel varchar(10), 
	cadastral_sub varchar(10), 
	map_reference varchar(128), 
	size_total double(24,8), 
	size_sau double(24,8), 
	geo_lat double(24,8), 
	geo_lon double(24,8), 
	shape_json TEXT DEFAULT NULL,
	fk_status_bio varchar(32), 
	date_conversion_start date, 
	date_conversion_end date, 
	fk_dossier int NOT NULL, 
	note_private text, 
	note_public text, 
	model_pdf varchar(255), 
	last_main_doc   VARCHAR(255),
	status int, 
	import_key varchar(14), 
	date_creation datetime, 
	date_valid datetime, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	fk_user_creat integer, 
	fk_user_modif integer
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;

SET FOREIGN_KEY_CHECKS=1;
