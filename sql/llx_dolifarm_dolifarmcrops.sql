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


CREATE TABLE llx_dolifarm_crops(
	-- BEGIN MODULEBUILDER FIELDS
	rowid int AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(128) NOT NULL, 
	label varchar(255) NOT NULL, 
	fk_default_product INTEGER DEFAULT NULL,
	entity INTEGER DEFAULT 1,
	scientific_name varchar(255), 
	family varchar(128), 
	variety varchar(128), 
	crop_type varchar(32), 
	planting_unit varchar(32), 
	estimated_yield_std double(24,8), 
	status int, 
	note_private text, 
	note_public text, 
	date_creation datetime, 
	date_valid datetime, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	fk_user_creat integer, 
	fk_user_modif integer, 
	import_key varchar(14),
	-- END MODULEBUILDER FIELDS
	UNIQUE INDEX uk_dolifarm_crop_ref (ref, entity)
) ENGINE=innodb;
