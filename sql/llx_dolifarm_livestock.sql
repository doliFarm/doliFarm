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

-- Livestock of the farm dossier (one row for each herd registered in the BDN)
CREATE TABLE IF NOT EXISTS llx_dolifarm_livestock(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	ref varchar(128) NOT NULL,
	label varchar(255),
	entity integer DEFAULT 1 NOT NULL,
	fk_soc integer NOT NULL,
	fk_dossier integer NOT NULL,
	bdn_code varchar(32),
	fk_species varchar(32),
	address varchar(255),
	town varchar(100),
	production_type varchar(64),
	milk_authorization varchar(64),
	breeding_type varchar(64),
	breeding_mode varchar(64),
	holder varchar(255),
	date_start date,
	date_end date,
	date_bdn_update date,
	nb_heads integer,
	organic smallint,
	note_public text,
	note_private text,
	model_pdf varchar(255),
	last_main_doc varchar(255),
	status integer DEFAULT 1 NOT NULL,
	import_key varchar(14),
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer
) ENGINE=innodb;
