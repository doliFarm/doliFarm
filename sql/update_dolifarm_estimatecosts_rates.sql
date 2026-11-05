-- Copyright (C) 2026 Luigi Grillo - doliFarm.com <info@dolifarm.com>
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
-- along with this program.  If not, see <https://www.gnu.org/licenses/>.

-- Standard costs of the operations per hectare: entity, date of validity and items (labour, machines, services).
-- The errors "duplicate column" and "duplicate key" are ignored when they exist.
ALTER TABLE llx_dolifarm_dolifarmestimatecosts ADD COLUMN entity integer DEFAULT 1 NOT NULL;
ALTER TABLE llx_dolifarm_dolifarmestimatecosts ADD COLUMN date_start date DEFAULT NULL;
ALTER TABLE llx_dolifarm_dolifarmestimatecosts ADD COLUMN cost_manpower_ha double(24,8) DEFAULT NULL;
ALTER TABLE llx_dolifarm_dolifarmestimatecosts ADD COLUMN cost_machine_ha double(24,8) DEFAULT NULL;
ALTER TABLE llx_dolifarm_dolifarmestimatecosts ADD COLUMN cost_service_ha double(24,8) DEFAULT NULL;
-- A total written before the items is kept as cost of the services
UPDATE llx_dolifarm_dolifarmestimatecosts SET cost_service_ha = cost_std_ha WHERE cost_std_ha <> 0 AND cost_manpower_ha IS NULL AND cost_machine_ha IS NULL AND cost_service_ha IS NULL;
ALTER TABLE llx_dolifarm_dolifarmestimatecosts ADD INDEX idx_dolifarm_estimatecosts_optype (entity, fk_operation_type, date_start);
