-- Demo data of doliFarm: two farms with dossier, plots and machines. Import by hand on a test instance, never in
-- production (see README.md). The file can be imported more than once: rows already there are not duplicated.

-- Farms (third parties of type farm, unique by customer code)
INSERT IGNORE INTO llx_societe (nom, name_alias, client, code_client, status, datec, fk_user_creat, entity, fk_typent)
VALUES ('Azienda Agricola ROSSI Mario', 'RossiFarm', 1, 'C-ROSSI-TEST', 1, NOW(), 1, 1, (SELECT id FROM llx_c_typent WHERE code = 'TE_FARM' LIMIT 1));

SET @id_soc_rossi = (SELECT rowid FROM llx_societe WHERE code_client = 'C-ROSSI-TEST' LIMIT 1);

-- Farm dossier (unique by ref and entity)
INSERT IGNORE INTO llx_dolifarm_farmdossier
(ref, fk_soc, year_validity, date_import, status, note, fk_user_validator, date_creation, tms, fk_user_creat)
VALUES
('FASC-2026-ROSSI', @id_soc_rossi, 2026, NOW(), 1, 'Fascicolo Completo e Verificato.', 1, NOW(), NOW(), 1);

SET @id_dossier_rossi = (SELECT rowid FROM llx_dolifarm_farmdossier WHERE ref = 'FASC-2026-ROSSI' LIMIT 1);

-- Plots: each one needs its own uuid (unique index), inserted only when its ref is not there
INSERT INTO llx_dolifarm_plot
(ref, uuid, label, fk_soc, fk_owner, cadastral_comune, cadastral_sheet, cadastral_parcel, size_total, size_sau, fk_ownership, fk_dossier, status, tms, fk_user_creat)
SELECT v.ref, UUID(), v.label, @id_soc_rossi, @id_soc_rossi, v.comune, v.sheet, v.parcel, v.size_total, v.size_sau, v.ownership, @id_dossier_rossi, 1, NOW(), 1
FROM (
	SELECT 'PL-ROSSI-01' as ref, 'Vigneto DOC' as label, 'BOLOGNA' as comune, '12' as sheet, '100' as parcel, 5.5 as size_total, 5.0 as size_sau, 'PLOT_OWNED' as ownership
	UNION ALL SELECT 'PL-ROSSI-02', 'Seminativo in affitto', 'MODENA', '45', '202', 12.0, 11.5, 'PLOT_RENTED'
	UNION ALL SELECT 'PL-ROSSI-03', 'Bosco ceduo', 'BOLOGNA', '12', '105', 3.0, 0.0, 'PLOT_OWNED'
) as v
WHERE NOT EXISTS (SELECT 1 FROM (SELECT ref FROM llx_dolifarm_plot) as e WHERE e.ref = v.ref);

-- Machines: no unique index on the ref, inserted only when the ref is not there
INSERT INTO llx_dolifarm_machine
(ref, label, fk_soc, fk_type, fk_dossier, power_kw, fuel_type, year_registration, status, tms, fk_user_creat)
SELECT v.ref, v.label, @id_soc_rossi, v.kind, @id_dossier_rossi, v.power, v.fuel, v.year, 1, NOW(), 1
FROM (
	SELECT 'TR-ROSSI-001' as ref, 'John Deere 6120M' as label, 'MACH_TRACTOR' as kind, 88.00 as power, 'DIESEL' as fuel, 2020 as year
	UNION ALL SELECT 'AT-ROSSI-001', 'Aratro 3 Vomeri', 'MACH_TOOL', 0.00, 'NONE', 2018
) as v
WHERE NOT EXISTS (SELECT 1 FROM (SELECT ref FROM llx_dolifarm_machine) as e WHERE e.ref = v.ref);

-- Second farm
INSERT IGNORE INTO llx_societe (nom, name_alias, client, code_client, status, datec, fk_user_creat, entity, fk_typent)
VALUES ('Società Agricola BIANCHI S.r.l.', 'BioBianchi', 1, 'C-BIAN-TEST', 1, NOW(), 1, 1, (SELECT id FROM llx_c_typent WHERE code = 'TE_FARM' LIMIT 1));

SET @id_soc_bianchi = (SELECT rowid FROM llx_societe WHERE code_client = 'C-BIAN-TEST' LIMIT 1);

INSERT IGNORE INTO llx_dolifarm_farmdossier
(ref, fk_soc, year_validity, date_import, status, note, fk_user_validator, date_creation, tms, fk_user_creat)
VALUES
('FASC-2026-BIANCHI', @id_soc_bianchi, 2026, NOW(), 0, 'In attesa di documentazione integrativa.', NULL, NOW(), NOW(), 1);

SET @id_dossier_bianchi = (SELECT rowid FROM llx_dolifarm_farmdossier WHERE ref = 'FASC-2026-BIANCHI' LIMIT 1);

INSERT INTO llx_dolifarm_plot
(ref, uuid, label, fk_soc, fk_owner, cadastral_comune, cadastral_sheet, cadastral_parcel, size_total, size_sau, fk_ownership, fk_dossier, status, tms, fk_user_creat)
SELECT 'PL-BIAN-01', UUID(), 'Uliveto Collina', @id_soc_bianchi, @id_soc_bianchi, 'FIRENZE', '5', '500', 3.0, 2.8, 'PLOT_OWNED', @id_dossier_bianchi, 1, NOW(), 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM (SELECT ref FROM llx_dolifarm_plot) as e WHERE e.ref = 'PL-BIAN-01');

INSERT INTO llx_dolifarm_machine
(ref, label, fk_soc, fk_type, fk_dossier, power_kw, fuel_type, year_registration, status, tms, fk_user_creat)
SELECT v.ref, v.label, @id_soc_bianchi, 'MACH_TRACTOR', @id_dossier_bianchi, v.power, 'DIESEL', v.year, 1, NOW(), 1
FROM (
	SELECT 'TR-BIAN-01' as ref, 'New Holland T4' as label, 75.00 as power, 2022 as year
	UNION ALL SELECT 'TR-BIAN-02', 'Fiat Agri Storico', 60.00, 1995
) as v
WHERE NOT EXISTS (SELECT 1 FROM (SELECT ref FROM llx_dolifarm_machine) as e WHERE e.ref = v.ref);
