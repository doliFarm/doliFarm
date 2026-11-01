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

-- Authorised uses of a plant protection product, from its label: crop, pest, dose, pre-harvest interval
CREATE TABLE IF NOT EXISTS llx_dolifarm_agrodrug_use (
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_agrodrug integer NOT NULL,
	crop_eppo varchar(10) NOT NULL,
	crop_label varchar(255),
	adversity_code varchar(10),
	adversity_label varchar(255),
	dose_min double(24,8),
	dose_max double(24,8),
	dose_unit varchar(16),
	safety_days integer,
	max_applications integer,
	note varchar(255),
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
