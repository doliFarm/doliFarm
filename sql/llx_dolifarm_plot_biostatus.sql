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

-- History of the organic status of a plot (Reg. EU 2018/848, annex II part I point 1.7; DM 229771/2022 art. 3).
-- status: CONV conventional, CONVERSION in conversion, BIO organic. A period without date_end is still open.
-- The status of the plot at a date is read from here by the register (doliTrace).
CREATE TABLE IF NOT EXISTS llx_dolifarm_plot_biostatus (
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_plot integer NOT NULL,
	status varchar(16) NOT NULL,
	date_start date NOT NULL,
	date_end date,
	conversion_years smallint,
	reason varchar(32),
	reference varchar(128),
	note varchar(255),
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
