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

-- Correspondence between the codes of the SIAN database of plant protection products and the EPPO Codes,
-- shared by all the entities. Pests: list of the SIAN search page (code = EPPO code for most of them);
-- crops: SIAN code of the crop (e.g. C1401), EPPO code proposed from the Italian name and confirmed by the user.
CREATE TABLE IF NOT EXISTS llx_dolifarm_sian_map(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	map_type varchar(8) NOT NULL,				-- crop, pest
	sian_code varchar(16) NOT NULL,
	sian_label varchar(255) NOT NULL,			-- crop name, or pest class - pest name
	eppo_code varchar(10),
	confirmed smallint DEFAULT 0 NOT NULL,		-- 1 = EPPO code chosen or confirmed by a user
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
