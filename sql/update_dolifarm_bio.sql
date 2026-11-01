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

-- Organic production (01/11/2026): plant protection products allowed in organic farming (Reg. EU 2021/1165, annex I)
-- and their copper (g of copper metal for kg or l of product, as written on the label).
-- Dolibarr ignores "column already exists" errors, so the script can run at every activation.

ALTER TABLE llx_dolifarm_agrodrug ADD COLUMN organic_allowed smallint DEFAULT 0;
ALTER TABLE llx_dolifarm_agrodrug ADD COLUMN cu_content double(24,8);
