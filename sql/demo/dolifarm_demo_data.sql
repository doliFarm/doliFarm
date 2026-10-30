-- ... Dictionary inserts (Keep them) ...

-- Insert Societe
INSERT IGNORE INTO llx_societe (nom, name_alias, client, code_client, status, datec, fk_user_creat, entity)
VALUES ('Azienda Agricola ROSSI Mario', 'RossiFarm', 1, 'C-ROSSI-TEST', 1, NOW(), 1, 1);

SET @id_soc_rossi = (SELECT rowid FROM llx_societe WHERE code_client = 'C-ROSSI-TEST' LIMIT 1);

-- Insert Farm Dossier (Fixed column name fk_user_author -> fk_user_creat)
INSERT IGNORE INTO llx_dolifarm_farmdossier 
(ref, fk_soc, year_validity, date_import, status, note, fk_user_validator, date_creation, tms, fk_user_creat) 
VALUES 
('FASC-2026-ROSSI', @id_soc_rossi, 2026, NOW(), 1, 'Fascicolo Completo e Verificato.', 1, NOW(), NOW(), 1);

SET @id_dossier_rossi = (SELECT rowid FROM llx_dolifarm_farmdossier WHERE ref = 'FASC-2026-ROSSI' LIMIT 1);

-- Insert Plot (Added fk_owner value, using same as fk_soc for simplicity)
INSERT IGNORE INTO llx_dolifarm_plot 
(ref, label, fk_soc, fk_owner, cadastral_comune, cadastral_sheet, cadastral_parcel, size_total, size_sau, fk_ownership, fk_dossier, status, tms, fk_user_creat) 
VALUES 
('PL-ROSSI-01', 'Vigneto DOC', @id_soc_rossi, @id_soc_rossi, 'BOLOGNA', '12', '100', 5.5000, 5.0000, 'PLOT_OWNED', @id_dossier_rossi, 1, NOW(), 1),
('PL-ROSSI-02', 'Seminativo in affitto', @id_soc_rossi, @id_soc_rossi, 'MODENA', '45', '202', 12.0000, 11.5000, 'PLOT_RENTED', @id_dossier_rossi, 1, NOW(), 1),
('PL-ROSSI-03', 'Bosco ceduo', @id_soc_rossi, @id_soc_rossi, 'BOLOGNA', '12', '105', 3.0000, 0.0000, 'PLOT_OWNED', @id_dossier_rossi, 1, NOW(), 1);

-- Insert Machine (Fixed column name fk_user_author -> fk_user_creat)
INSERT IGNORE INTO llx_dolifarm_machine 
(ref, label, fk_soc, fk_type, fk_dossier, power_kw, fuel_type, year_registration, status, tms, fk_user_creat) 
VALUES 
('TR-ROSSI-001', 'John Deere 6120M', @id_soc_rossi, 'MACH_TRACTOR', @id_dossier_rossi, 88.00, 'DIESEL', 2020, 1, NOW(), 1),
('AT-ROSSI-001', 'Aratro 3 Vomeri', @id_soc_rossi, 'MACH_TOOL', @id_dossier_rossi, 0.00, 'NONE', 2018, 1, NOW(), 1);

-- ... Repeat logic for Bianchi ...
INSERT IGNORE INTO llx_societe (nom, name_alias, client, code_client, status, datec, fk_user_creat, entity)
VALUES ('Società Agricola BIANCHI S.r.l.', 'BioBianchi', 1, 'C-BIAN-TEST', 1, NOW(), 1, 1);

SET @id_soc_bianchi = (SELECT rowid FROM llx_societe WHERE code_client = 'C-BIAN-TEST' LIMIT 1);

INSERT IGNORE INTO llx_dolifarm_farmdossier 
(ref, fk_soc, year_validity, date_import, status, note, fk_user_validator, date_creation, tms, fk_user_creat) 
VALUES 
('FASC-2026-BIANCHI', @id_soc_bianchi, 2026, NOW(), 0, 'In attesa di documentazione integrativa.', NULL, NOW(), NOW(), 1);

SET @id_dossier_bianchi = (SELECT rowid FROM llx_dolifarm_farmdossier WHERE ref = 'FASC-2026-BIANCHI' LIMIT 1);

INSERT IGNORE INTO llx_dolifarm_plot 
(ref, label, fk_soc, fk_owner, cadastral_comune, cadastral_sheet, cadastral_parcel, size_total, size_sau, fk_ownership, fk_dossier, status, tms, fk_user_creat) 
VALUES 
('PL-BIAN-01', 'Uliveto Collina', @id_soc_bianchi, @id_soc_bianchi, 'FIRENZE', '5', '500', 3.0000, 2.8000, 'PLOT_OWNED', @id_dossier_bianchi, 1, NOW(), 1);

INSERT IGNORE INTO llx_dolifarm_machine 
(ref, label, fk_soc, fk_type, fk_dossier, power_kw, fuel_type, year_registration, status, tms, fk_user_creat) 
VALUES 
('TR-BIAN-01', 'New Holland T4', @id_soc_bianchi, 'MACH_TRACTOR', @id_dossier_bianchi, 75.00, 'DIESEL', 2022, 1, NOW(), 1),
('TR-BIAN-02', 'Fiat Agri Storico', @id_soc_bianchi, 'MACH_TRACTOR', @id_dossier_bianchi, 60.00, 'DIESEL', 1995, 1, NOW(), 1);