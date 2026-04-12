-- ==============================================================================
-- POPOLAMENTO STIME STANDARD (Init Data)
-- ==============================================================================

INSERT INTO llx_dolifarm_estimate (fk_operation_type, duration_min_ha, cost_std_ha, label, date_creation, import_key) 
VALUES
-- Lavorazioni Suolo
('SOIL_PLOWING',   120, 150.00, 'EstimateStd_PlowingMedium', NOW(), 'STD_INIT'),
('SOIL_HARROWING',  60,  80.00, 'EstimateStd_Harrowing', NOW(), 'STD_INIT'),
('SOIL_HOEING',     90,  90.00, 'EstimateStd_Hoeing', NOW(), 'STD_INIT'),

-- Semine
('SOWING_DIRECT',   45,  60.00, 'EstimateStd_SowingDirect', NOW(), 'STD_INIT'),
('TRANSPLANTING',  480, 800.00, 'EstimateStd_Transplanting', NOW(), 'STD_INIT'),

-- Trattamenti
('TT_Pesticide',    30,  45.00, 'EstimateStd_TreatmentPesticide', NOW(), 'STD_INIT'),
('TT_Fertiliser',   40,  50.00, 'EstimateStd_TreatmentFertiliser', NOW(), 'STD_INIT'),

-- Raccolta
('HARVEST',        180, 250.00, 'EstimateStd_HarvestGeneric', NOW(), 'STD_INIT')

ON DUPLICATE KEY UPDATE 
    duration_min_ha = VALUES(duration_min_ha),
    cost_std_ha = VALUES(cost_std_ha),
    label = VALUES(label);