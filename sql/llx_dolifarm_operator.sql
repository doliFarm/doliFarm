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

-- Operators of the treatments and fertilizations (persons of a farm or of a contractor)
CREATE TABLE IF NOT EXISTS llx_dolifarm_operator(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	ref varchar(128) NOT NULL,
	label varchar(255) NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_soc integer NOT NULL,
	fk_socpeople integer,
	fk_user integer,
	tax_code varchar(32),
	license_type smallint,
	license_number varchar(64),
	license_issuer varchar(128),
	license_date date,
	license_end date,
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
