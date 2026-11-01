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

-- Fertilisers, soil conditioners and nutrients allowed in organic production (Reg. EU 2021/1165, annex II), shared by
-- all the entities. check_type: empty = no condition to check, VERIFY = condition on origin or use to check by hand.
CREATE TABLE IF NOT EXISTS llx_dolifarm_organic_fertilizer(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	code varchar(32) NOT NULL,
	label varchar(255) NOT NULL,
	conditions text,
	check_type varchar(16),
	annex_version varchar(32),
	position integer DEFAULT 0,
	active smallint DEFAULT 1 NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
