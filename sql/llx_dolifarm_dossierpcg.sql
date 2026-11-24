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

-- Graphic cultivation plan (PCG) of a farm dossier, as read from the dossier of the SIAN: one row per cultivation
-- of an "appezzamento" of an "isola". Replaced at each import of the dossier for the same campaign.
CREATE TABLE IF NOT EXISTS llx_dolifarm_dossierpcg(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_dossier integer NOT NULL,
	fk_soc integer,
	campaign integer,
	island varchar(32),
	field_ref varchar(32),					-- "appezzamento" of the SIAN
	land_use_code varchar(16),				-- "occupazione del suolo"
	land_use varchar(255),
	destination varchar(255),
	land_use_detail varchar(255),			-- "uso"
	quality varchar(255),
	variety varchar(255),
	size double(24,8),						-- ha
	date_start date,
	date_end date,
	maintenance varchar(8),					-- criterion of maintenance of the surfaces (1-16)
	production_method varchar(32),
	import_key varchar(14),
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
