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

-- Names of the EPPO Codes used for the search: latin, Italian and English active names (synonyms included)
CREATE TABLE IF NOT EXISTS llx_dolifarm_eppo_name(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	eppo_name_id integer NOT NULL,
	eppocode varchar(10) NOT NULL,
	lang varchar(2) NOT NULL,
	fullname varchar(255) NOT NULL,
	authority varchar(255),
	preferred smallint DEFAULT 0 NOT NULL,
	import_key varchar(14)
) ENGINE=innodb;
