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

ALTER TABLE llx_dolifarm_fertilizer ADD UNIQUE INDEX uk_dolifarm_fertilizer_ref (ref, entity);
ALTER TABLE llx_dolifarm_fertilizer ADD INDEX idx_dolifarm_fertilizer_product (fk_product);
ALTER TABLE llx_dolifarm_fertilizer ADD INDEX idx_dolifarm_fertilizer_type (fk_fert_type);
ALTER TABLE llx_dolifarm_fertilizer ADD CONSTRAINT fk_dolifarm_fertilizer_product FOREIGN KEY (fk_product) REFERENCES llx_product(rowid);
