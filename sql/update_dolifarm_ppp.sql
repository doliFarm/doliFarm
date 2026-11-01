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

-- Catalogue of the Ministry of Health (01/11/2026): administrative status of the agrodrug, copied by the sync.
-- Dolibarr ignores "column already exists" errors, so the script can run at every activation.

ALTER TABLE llx_dolifarm_agrodrug ADD COLUMN admin_status varchar(128);
ALTER TABLE llx_dolifarm_agrodrug ADD COLUMN date_revocation date;
ALTER TABLE llx_dolifarm_agrodrug ADD COLUMN date_catalog_sync datetime;
-- Check for organic production (annex I): confirmation by hand of the use, with user and date
ALTER TABLE llx_dolifarm_agrodrug ADD COLUMN organic_confirm_user integer;
ALTER TABLE llx_dolifarm_agrodrug ADD COLUMN organic_confirm_date datetime;
-- Register of the technical inputs (M3): kind of fertiliser, category of annex II, date of the check in the register of
-- the fertilisers allowed in organic farming (MASAF, D.Lgs. 75/2010)
ALTER TABLE llx_dolifarm_fertilizer ADD COLUMN product_kind varchar(16);
ALTER TABLE llx_dolifarm_fertilizer ADD COLUMN annex2_code varchar(32);
ALTER TABLE llx_dolifarm_fertilizer ADD COLUMN organic_check_date date;
