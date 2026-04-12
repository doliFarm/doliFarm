-- ==============================================================================
-- POPOLAMENTO INTELLIGENTE TABELLA CROPS (IDEMPOTENTE)
-- Fonte: Legacy OES
-- Logica: Inserisce i dati o li aggiorna se il 'ref' esiste già.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS=0;
-- ==============================================================================
-- POPOLAMENTO CROPS (Tabella definita in Dolifarm)
-- ==============================================================================

-- Mappatura Colonne:
-- active -> status
-- category -> crop_type

INSERT IGNORE INTO llx_dolifarm_crops (ref, label, crop_type, scientific_name, family, planting_unit, status) VALUES

-- CEREALI
('CROP_WHEAT_DURUM', 'CROP_WHEAT_DURUM', 'CAT_CEREAL', 'Triticum durum', 'Poaceae', 'kg/ha', 1),
('CROP_WHEAT_SOFT', 'CROP_WHEAT_SOFT', 'CAT_CEREAL', 'Triticum aestivum', 'Poaceae', 'kg/ha', 1),
('CROP_BARLEY', 'CROP_BARLEY', 'CAT_CEREAL', 'Hordeum vulgare', 'Poaceae', 'kg/ha', 1),
('CROP_MAIZE', 'CROP_MAIZE', 'CAT_CEREAL', 'Zea mays', 'Poaceae', 'seeds/ha', 1),
('CROP_RICE', 'CROP_RICE', 'CAT_CEREAL', 'Oryza sativa', 'Poaceae', 'kg/ha', 1),
('CROP_OATS', 'CROP_OATS', 'CAT_CEREAL', 'Avena sativa', 'Poaceae', 'kg/ha', 1),
('CROP_RYE', 'CROP_RYE', 'CAT_CEREAL', 'Secale cereale', 'Poaceae', 'kg/ha', 1),
('CROP_SORGHUM', 'CROP_SORGHUM', 'CAT_CEREAL', 'Sorghum bicolor', 'Poaceae', 'kg/ha', 1),
('CROP_SPELT', 'CROP_SPELT', 'CAT_CEREAL', 'Triticum dicoccum', 'Poaceae', 'kg/ha', 1),

-- LEGUMINOSE
('CROP_CHICKPEA', 'CROP_CHICKPEA', 'CAT_LEGUME', 'Cicer arietinum', 'Fabaceae', 'kg/ha', 1),
('CROP_LENTIL', 'CROP_LENTIL', 'CAT_LEGUME', 'Lens culinaris', 'Fabaceae', 'kg/ha', 1),
('CROP_PEA_PROTEIN', 'CROP_PEA_PROTEIN', 'CAT_LEGUME', 'Pisum sativum', 'Fabaceae', 'kg/ha', 1),
('CROP_FAVA_BEAN', 'CROP_FAVA_BEAN', 'CAT_LEGUME', 'Vicia faba', 'Fabaceae', 'kg/ha', 1),
('CROP_SOYBEAN', 'CROP_SOYBEAN', 'CAT_LEGUME', 'Glycine max', 'Fabaceae', 'kg/ha', 1),
('CROP_BEAN_BORLOTTO', 'CROP_BEAN_BORLOTTO', 'CAT_LEGUME', 'Phaseolus vulgaris', 'Fabaceae', 'kg/ha', 1),
('CROP_LUPIN', 'CROP_LUPIN', 'CAT_LEGUME', 'Lupinus albus', 'Fabaceae', 'kg/ha', 1),

-- INDUSTRIALI
('CROP_SUNFLOWER', 'CROP_SUNFLOWER', 'CAT_INDUSTRIAL', 'Helianthus annuus', 'Asteraceae', 'seeds/ha', 1),
('CROP_RAPESEED', 'CROP_RAPESEED', 'CAT_INDUSTRIAL', 'Brassica napus', 'Brassicaceae', 'kg/ha', 1),
('CROP_HEMP_IND', 'CROP_HEMP_IND', 'CAT_INDUSTRIAL', 'Cannabis sativa', 'Cannabaceae', 'kg/ha', 1),
('CROP_TOBACCO', 'CROP_TOBACCO', 'CAT_INDUSTRIAL', 'Nicotiana tabacum', 'Solanaceae', 'plants/ha', 1),
('CROP_SUGAR_BEET', 'CROP_SUGAR_BEET', 'CAT_INDUSTRIAL', 'Beta vulgaris', 'Amaranthaceae', 'seeds/ha', 1),

-- FORAGGERE
('CROP_ALFALFA', 'CROP_ALFALFA', 'CAT_FORAGE', 'Medicago sativa', 'Fabaceae', 'kg/ha', 1),
('CROP_CLOVER_RED', 'CROP_CLOVER_RED', 'CAT_FORAGE', 'Trifolium pratense', 'Fabaceae', 'kg/ha', 1),
('CROP_RYEGRASS', 'CROP_RYEGRASS', 'CAT_FORAGE', 'Lolium multiflorum', 'Poaceae', 'kg/ha', 1),
('CROP_VETCH', 'CROP_VETCH', 'CAT_FORAGE', 'Vicia sativa', 'Fabaceae', 'kg/ha', 1),

-- ORTICOLE
('CROP_TOMATO_IND', 'CROP_TOMATO_IND', 'CAT_VEG', 'Solanum lycopersicum', 'Solanaceae', 'plants/ha', 1),
('CROP_TOMATO_TABLE', 'CROP_TOMATO_TABLE', 'CAT_VEG', 'Solanum lycopersicum', 'Solanaceae', 'plants/ha', 1),
('CROP_POTATO', 'CROP_POTATO', 'CAT_VEG', 'Solanum tuberosum', 'Solanaceae', 'kg/ha', 1),
('CROP_ONION', 'CROP_ONION', 'CAT_VEG', 'Allium cepa', 'Amaryllidaceae', 'plants/ha', 1),
('CROP_GARLIC', 'CROP_GARLIC', 'CAT_VEG', 'Allium sativum', 'Amaryllidaceae', 'kg/ha', 1),
('CROP_CARROT', 'CROP_CARROT', 'CAT_VEG', 'Daucus carota', 'Apiaceae', 'seeds/ha', 1),
('CROP_ZUCCHINI', 'CROP_ZUCCHINI', 'CAT_VEG', 'Cucurbita pepo', 'Cucurbitaceae', 'plants/ha', 1),
('CROP_PUMPKIN', 'CROP_PUMPKIN', 'CAT_VEG', 'Cucurbita maxima', 'Cucurbitaceae', 'plants/ha', 1),
('CROP_MELON', 'CROP_MELON', 'CAT_VEG', 'Cucumis melo', 'Cucurbitaceae', 'plants/ha', 1),
('CROP_WATERMELON', 'CROP_WATERMELON', 'CAT_VEG', 'Citrullus lanatus', 'Cucurbitaceae', 'plants/ha', 1),
('CROP_EGGPLANT', 'CROP_EGGPLANT', 'CAT_VEG', 'Solanum melongena', 'Solanaceae', 'plants/ha', 1),
('CROP_PEPPER', 'CROP_PEPPER', 'CAT_VEG', 'Capsicum annuum', 'Solanaceae', 'plants/ha', 1),
('CROP_LETTUCE', 'CROP_LETTUCE', 'CAT_VEG', 'Lactuca sativa', 'Asteraceae', 'plants/ha', 1),
('CROP_SPINACH', 'CROP_SPINACH', 'CAT_VEG', 'Spinacia oleracea', 'Amaranthaceae', 'seeds/ha', 1),
('CROP_ARTICHOKE', 'CROP_ARTICHOKE', 'CAT_VEG', 'Cynara scolymus', 'Asteraceae', 'plants/ha', 1),
('CROP_ASPARAGUS', 'CROP_ASPARAGUS', 'CAT_VEG', 'Asparagus officinalis', 'Asparagaceae', 'plants/ha', 1),
('CROP_BROCCOLI', 'CROP_BROCCOLI', 'CAT_VEG', 'Brassica oleracea', 'Brassicaceae', 'plants/ha', 1),
('CROP_CAULIFLOWER', 'CROP_CAULIFLOWER', 'CAT_VEG', 'Brassica oleracea', 'Brassicaceae', 'plants/ha', 1),

-- ARBOREE
('CROP_VINE_WINE', 'CROP_VINE_WINE', 'CAT_FRUIT', 'Vitis vinifera', 'Vitaceae', 'plants/ha', 1),
('CROP_VINE_TABLE', 'CROP_VINE_TABLE', 'CAT_FRUIT', 'Vitis vinifera', 'Vitaceae', 'plants/ha', 1),
('CROP_OLIVE_OIL', 'CROP_OLIVE_OIL', 'CAT_FRUIT', 'Olea europaea', 'Oleaceae', 'plants/ha', 1),
('CROP_OLIVE_TABLE', 'CROP_OLIVE_TABLE', 'CAT_FRUIT', 'Olea europaea', 'Oleaceae', 'plants/ha', 1),
('CROP_APPLE', 'CROP_APPLE', 'CAT_FRUIT', 'Malus domestica', 'Rosaceae', 'plants/ha', 1),
('CROP_PEAR', 'CROP_PEAR', 'CAT_FRUIT', 'Pyrus communis', 'Rosaceae', 'plants/ha', 1),
('CROP_PEACH', 'CROP_PEACH', 'CAT_FRUIT', 'Prunus persica', 'Rosaceae', 'plants/ha', 1),
('CROP_APRICOT', 'CROP_APRICOT', 'CAT_FRUIT', 'Prunus armeniaca', 'Rosaceae', 'plants/ha', 1),
('CROP_CHERRY', 'CROP_CHERRY', 'CAT_FRUIT', 'Prunus avium', 'Rosaceae', 'plants/ha', 1),
('CROP_PLUM', 'CROP_PLUM', 'CAT_FRUIT', 'Prunus domestica', 'Rosaceae', 'plants/ha', 1),
('CROP_KIWI', 'CROP_KIWI', 'CAT_FRUIT', 'Actinidia deliciosa', 'Actinidiaceae', 'plants/ha', 1),
('CROP_CITRUS_ORANGE', 'CROP_CITRUS_ORANGE', 'CAT_FRUIT', 'Citrus sinensis', 'Rutaceae', 'plants/ha', 1),
('CROP_CITRUS_LEMON', 'CROP_CITRUS_LEMON', 'CAT_FRUIT', 'Citrus limon', 'Rutaceae', 'plants/ha', 1),

-- GUSCIO
('CROP_HAZELNUT', 'CROP_HAZELNUT', 'CAT_NUT', 'Corylus avellana', 'Betulaceae', 'plants/ha', 1),
('CROP_WALNUT', 'CROP_WALNUT', 'CAT_NUT', 'Juglans regia', 'Juglandaceae', 'plants/ha', 1),
('CROP_ALMOND', 'CROP_ALMOND', 'CAT_NUT', 'Prunus dulcis', 'Rosaceae', 'plants/ha', 1),
('CROP_PISTACHIO', 'CROP_PISTACHIO', 'CAT_NUT', 'Pistacia vera', 'Anacardiaceae', 'plants/ha', 1),

-- OFFICINALI
('CROP_LAVENDER', 'CROP_LAVENDER', 'CAT_HERB', 'Lavandula angustifolia', 'Lamiaceae', 'plants/ha', 1),
('CROP_BASIL', 'CROP_BASIL', 'CAT_HERB', 'Ocimum basilicum', 'Lamiaceae', 'plants/ha', 1),
('CROP_SAFFRON', 'CROP_SAFFRON', 'CAT_HERB', 'Crocus sativus', 'Iridaceae', 'kg/ha', 1);

SET FOREIGN_KEY_CHECKS=1;