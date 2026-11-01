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

-- Catalogue of the plant protection products of the Italian Ministry of Health (open data "fitosanitari",
-- Italian Open Data Licence v2.0), shared by all the entities like the agrodrugs. One row for each registration
-- number, updated by DoliFarmPppSync. Substances and contents are separated by "|" as in the source file.
CREATE TABLE IF NOT EXISTS llx_dolifarm_ppp_catalog(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	reg_key varchar(16) NOT NULL,
	reg_number varchar(16) NOT NULL,
	label varchar(255),
	holder varchar(255),
	date_registration date,
	date_expiry date,
	hazard text,
	activity varchar(128),
	formulation_code varchar(16),
	formulation varchar(128),
	substances text,
	contents text,
	parallel_import smallint DEFAULT 0,
	admin_status varchar(128),
	revocation_reason varchar(255),
	date_revocation_decree date,
	date_revocation date,
	row_hash varchar(32),
	source_file varchar(64),
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
