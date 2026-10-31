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

-- EPPO Codes (www.eppo.int), shared by all the entities like the agrodrugs: one row for each code of the
-- EPPO file "XML full", updated by the import of the file and by the EPPO API (replacedby)
CREATE TABLE IF NOT EXISTS llx_dolifarm_eppo_code(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	eppocode varchar(10) NOT NULL,
	eppo_id integer,
	datatype varchar(3),
	is_active smallint DEFAULT 1 NOT NULL,
	prefname varchar(255),
	authority varchar(255),
	name_it varchar(255),
	name_en varchar(255),
	parent_code varchar(10),
	replacedby varchar(10),
	date_eppo_creation date,
	date_eppo_modification date,
	import_key varchar(14),
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
