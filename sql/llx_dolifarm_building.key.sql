-- Copyright (C) 2026		SuperAdmin
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

ALTER TABLE llx_dolifarm_building ADD UNIQUE INDEX uk_dolifarm_building_ref (ref, entity);
ALTER TABLE llx_dolifarm_building ADD INDEX idx_dolifarm_building_soc (fk_soc);
ALTER TABLE llx_dolifarm_building ADD INDEX idx_dolifarm_building_dossier (fk_dossier);
ALTER TABLE llx_dolifarm_building ADD INDEX idx_dolifarm_building_plot (fk_plot);
ALTER TABLE llx_dolifarm_building ADD CONSTRAINT fk_dolifarm_building_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe(rowid);
ALTER TABLE llx_dolifarm_building ADD CONSTRAINT fk_dolifarm_building_dossier FOREIGN KEY (fk_dossier) REFERENCES llx_dolifarm_farmdossier(rowid) ON DELETE RESTRICT;
