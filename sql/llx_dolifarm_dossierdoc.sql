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

-- Documents of the paper dossier listed by the SIAN (identity document, CCIAA, cadastral survey...), with their expiry.
-- One row per farm, protocol and type: a new import updates it and moves it to the last dossier.
CREATE TABLE IF NOT EXISTS llx_dolifarm_dossierdoc(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	ref varchar(255) NOT NULL,				-- technical (type of document without special characters)
	label varchar(255),						-- type of document
	section varchar(255),					-- section of the dossier
	fk_dossier integer NOT NULL,
	fk_soc integer,
	protocol varchar(64),
	date_signature date,
	date_start date,
	date_end date,							-- expiry
	status smallint DEFAULT 1 NOT NULL,
	import_key varchar(14),
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
