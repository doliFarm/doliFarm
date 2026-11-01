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

-- Catalog of the fertilizers (concimi, ammendanti, correttivi) used in the fertilization register
CREATE TABLE IF NOT EXISTS llx_dolifarm_fertilizer(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	ref varchar(128) NOT NULL,
	label varchar(255) NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_product integer,
	fk_fert_type varchar(32),
	manufacturer varchar(255),
	registration_number varchar(64),
	n_content double(8,2),
	p2o5_content double(8,2),
	k2o_content double(8,2),
	cu_content double(8,2),
	efficiency double(8,2),
	dry_matter double(8,2),
	density double(8,3),
	organic smallint,
	product_kind varchar(16),
	annex2_code varchar(32),
	organic_check_date date,
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
