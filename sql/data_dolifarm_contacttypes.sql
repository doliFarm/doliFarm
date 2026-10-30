--
-- Contact types of the farm dossier
--

-- CAA operator who validates the dossier (external contact, usually of the CAA third party)
INSERT IGNORE INTO llx_c_type_contact (element, source, code, libelle, active, module, position) VALUES ('dolifarmfarmdossier', 'external', 'CAAOPERATOR', 'Operatore CAA', 1, 'dolifarm', 10);
