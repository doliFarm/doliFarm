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

-- Uses of an agrodrug read from the SIAN database: proposals to confirm before they become rows of
-- llx_dolifarm_agrodrug_use. Replaced at each reading. The SIAN gives crop, pest and pre-harvest interval, not the dose.
CREATE TABLE IF NOT EXISTS llx_dolifarm_agrodrug_sianuse(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_agrodrug integer NOT NULL,
	crop_sian varchar(16) NOT NULL,
	crop_class varchar(32),					-- Coltura, Derrata, Concia...
	crop_label varchar(255),
	pest_class varchar(64),
	pest_label varchar(255),
	pest_sian varchar(16),					-- code from llx_dolifarm_sian_map, empty when not found
	safety_days integer,
	safety_days_greenhouse integer,
	safety_text varchar(64),				-- pre-harvest interval as written, when not a number
	date_read datetime NOT NULL,
	fk_user integer,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
