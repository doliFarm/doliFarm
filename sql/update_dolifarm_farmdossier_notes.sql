-- Fascicolo aziendale: the legacy "note" field is replaced by the standard note_private.
-- Copy it only where note_private is empty, so the script can run at every activation.
UPDATE llx_dolifarm_farmdossier SET note_private = note WHERE note IS NOT NULL AND note <> '' AND (note_private IS NULL OR note_private = '');
