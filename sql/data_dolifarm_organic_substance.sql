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

-- Annex I of Reg. EU 2021/1165 (consolidated text 02021R1165-20260101). Loaded at every activation: existing codes are kept.
-- A new version of the annex is applied with UPDATE statements by code at the end of this file.
INSERT IGNORE INTO llx_dolifarm_organic_substance (code, annex_section, annex_ref, cas, label, label_en, conditions, check_type, match_tokens, annex_version, position, active) VALUES
('EQUISETUM', 1, '1C', NULL, 'Equisetum arvense L.', 'Equisetum arvense L.', NULL, NULL, 'EQUISETUM|EQUISETO', '02021R1165-20260101', 10, 1),
('CHITOSAN_HCL', 1, '2C', '70694-72-3', 'Chitosano cloridrato', 'Chitosan hydrochloride', 'ottenuto da Aspergillus o da acquacoltura biologica o da attività di pesca sostenibili, quali definite all''articolo 2 del regolamento (UE) n. 1380/2013', 'VERIFY', 'CHITOSAN HYDROCHLORIDE|CHITOSANO CLORIDRATO', '02021R1165-20260101', 20, 1),
('SUCROSE', 1, '3C', '57-50-1', 'Saccarosio', 'Sucrose', NULL, NULL, 'SUCROSE|SACCAROSIO', '02021R1165-20260101', 30, 1),
('CALCIUM_HYDROXIDE', 1, '4C', '1305-62-0', 'Idrossido di calcio', 'Calcium hydroxide', NULL, NULL, 'CALCIUM HYDROXIDE|IDROSSIDO DI CALCIO', '02021R1165-20260101', 40, 1),
('VINEGAR', 1, '5C', '90132-02-8', 'Aceto', 'Vinegar', NULL, NULL, 'VINEGAR|ACETO', '02021R1165-20260101', 50, 1),
('LECITHINS', 1, '6C', '8002-43-5', 'Lecitine', 'Lecithins', NULL, NULL, 'LECITHINS|LECITHIN|LECITINE|LECITINA', '02021R1165-20260101', 60, 1),
('SALIX', 1, '7C', NULL, 'Salix spp. corteccia', 'Salix spp. Cortex', NULL, NULL, 'SALIX', '02021R1165-20260101', 70, 1),
('FRUCTOSE', 1, '8C', '57-48-7', 'Fruttosio', 'Fructose', NULL, NULL, 'FRUCTOSE|FRUTTOSIO', '02021R1165-20260101', 80, 1),
('SODIUM_HYDROGEN_CARBONATE', 1, '9C 24D', '144-55-8', 'Idrogenocarbonato di sodio', 'Sodium hydrogen carbonate', NULL, NULL, 'SODIUM HYDROGEN CARBONATE|SODIUM BICARBONATE|BICARBONATO DI SODIO|IDROGENOCARBONATO DI SODIO', '02021R1165-20260101', 90, 1),
('WHEY', 1, '10C', '92129-90-3', 'Siero di latte', 'Whey', NULL, NULL, 'WHEY|SIERO DI LATTE', '02021R1165-20260101', 100, 1),
('DIAMMONIUM_PHOSPHATE', 1, '11C', '7783-28-0', 'Fosfato diammonico', 'Diammonium phosphate', 'solo in trappole', 'TRAPS', 'DIAMMONIUM PHOSPHATE|FOSFATO DIAMMONICO', '02021R1165-20260101', 110, 1),
('SUNFLOWER_OIL', 1, '12C', '8001-21-6', 'Olio di girasole', 'Sunflower oil', NULL, NULL, 'SUNFLOWER OIL|OLIO DI GIRASOLE', '02021R1165-20260101', 120, 1),
('URTICA', 1, '14C', '84012-40-8 90131-83-2', 'Urtica spp. (estratto di Urtica dioica, estratto di Urtica urens)', 'Urtica spp. (Urtica dioica extract) (Urtica urens extract)', NULL, NULL, 'URTICA|ORTICA', '02021R1165-20260101', 130, 1),
('HYDROGEN_PEROXIDE', 1, '15C', '7722-84-1', 'Perossido di idrogeno', 'Hydrogen peroxide', NULL, NULL, 'HYDROGEN PEROXIDE|PEROSSIDO DI IDROGENO', '02021R1165-20260101', 140, 1),
('SODIUM_CHLORIDE', 1, '16C', '7647-14-5', 'Cloruro di sodio', 'Sodium chloride', NULL, NULL, 'SODIUM CHLORIDE|CLORURO DI SODIO', '02021R1165-20260101', 150, 1),
('BEER', 1, '17C', '8029-31-0', 'Birra', 'Beer', NULL, NULL, 'BEER|BIRRA', '02021R1165-20260101', 160, 1),
('MUSTARD_SEEDS', 1, '18C', NULL, 'Polvere di semi di senape', 'Mustard seeds powder', NULL, NULL, 'MUSTARD SEEDS|MUSTARD|SENAPE', '02021R1165-20260101', 170, 1),
('TALC', 1, '19C', '14807-96-6', 'Silicato di magnesio idrogeno metasilicato (talco E 553b)', 'Magnesium hydrogen metasilicate silicate mineral (Talc E 553b)', NULL, NULL, 'TALC|TALCO', '02021R1165-20260101', 180, 1),
('ONION_OIL', 1, '20C', '8002-72-0', 'Olio di cipolla', 'Onion oil', NULL, NULL, 'ONION OIL|OLIO DI CIPOLLA', '02021R1165-20260101', 190, 1),
('L_CYSTEINE', 1, '21C', '52-89-1', 'L-cisteina (E 920)', 'L-cysteine (E 920)', NULL, NULL, 'L-CYSTEINE|CYSTEINE|CISTEINA|L-CISTEINA', '02021R1165-20260101', 200, 1),
('COW_MILK', 1, '22C', '8049-98-7', 'Latte vaccino', 'Cow milk', NULL, NULL, 'COW MILK|LATTE VACCINO', '02021R1165-20260101', 210, 1),
('ALLIUM_CEPA', 1, '23C', NULL, 'Estratto di bulbo di Allium cepa L.', 'Allium cepa L. bulb extract', NULL, NULL, 'ALLIUM CEPA', '02021R1165-20260101', 220, 1),
('CHITOSAN', 1, '24C', '9012-76-4', 'Chitosano', 'Chitosan', 'ottenuto da Aspergillus o da acquacoltura biologica o da attività di pesca sostenibili, quali definite all''articolo 2 del regolamento (UE) n. 1380/2013', 'VERIFY', 'CHITOSAN|CHITOSANO', '02021R1165-20260101', 230, 1),
('BASIC_OTHER', 1, NULL, NULL, 'Altre sostanze di base di origine vegetale o animale e basate su alimenti', 'Other basic substances from plant or animal origin and based on food', 'Le sostanze di base non devono essere utilizzate come erbicidi', 'VERIFY', NULL, '02021R1165-20260101', 240, 1),
('COS_OGA', 2, '2D', NULL, 'COS-OGA', 'COS-OGA', NULL, NULL, 'COS-OGA', '02021R1165-20260101', 250, 1),
('CEREVISANE', 2, '3D', NULL, 'Cerevisane e altri prodotti basati su frammenti di cellule di microrganismi', 'Cerevisane and other products based on fragments of cells of micro-organisms', 'non provenienti da OGM', NULL, 'CEREVISANE', '02021R1165-20260101', 260, 1),
('FERRIC_PHOSPHATE', 2, '5D', '10045-86-6', 'Fosfato ferrico (ortofosfato di ferro (III))', 'Ferric phosphate (iron (III) orthophosphate)', NULL, NULL, 'FERRIC PHOSPHATE|FOSFATO FERRICO|ORTOFOSFATO DI FERRO', '02021R1165-20260101', 270, 1),
('LAMINARIN', 2, '12D', '9008-22-4', 'Laminarina', 'Laminarin', 'l''alga bruna è ottenuta da acquacoltura biologica o da raccolta sostenibile conformemente all''allegato II, parte III, punto 2.4, del regolamento (UE) 2018/848', 'VERIFY', 'LAMINARIN|LAMINARINA', '02021R1165-20260101', 280, 1),
('ABE_IT_56', 2, '16D', NULL, 'ABE-IT 56 (componenti del lisato di Saccharomyces cerevisiae ceppo DDSF623)', 'ABE-IT 56 (components of lysate of Saccharomyces cerevisiae strain DDSF623)', 'non proveniente da OGM; non prodotto utilizzando substrati di coltivazione di origine OGM', NULL, 'ABE-IT 56|ABE-IT|DDSF623', '02021R1165-20260101', 290, 1),
('LAVANDULYL_SENECIOATE', 2, '19D', '23960-07-8', 'Lavandulyl senecioato', 'Lavandulyl senecioate', NULL, NULL, 'LAVANDULYL SENECIOATE|LAVANDULYL SENECIOATO|LAVANDULIL SENECIOATO', '02021R1165-20260101', 300, 1),
('FERRIC_PYROPHOSPHATE', 2, '20D', '10058-44-3', 'Pirofosfato ferrico', 'Ferric pyrophosphate', NULL, NULL, 'FERRIC PYROPHOSPHATE|PIROFOSFATO FERRICO', '02021R1165-20260101', 310, 1),
('LUPINUS_EXTRACT', 2, '28D', NULL, 'Estratto acquoso dei semi germinati di Lupinus albus dolce', 'Aqueous extract from the germinated seeds of sweet Lupinus albus', NULL, NULL, 'LUPINUS ALBUS|LUPINUS', '02021R1165-20260101', 320, 1),
('LOWRISK_OTHER', 2, NULL, NULL, 'Altre sostanze a basso rischio di origine vegetale o animale', 'Other low risk substances from plant or animal origin', 'non autorizzati gli usi come erbicida', 'NO_HERBICIDE', NULL, '02021R1165-20260101', 330, 1),
('POTASSIUM_HYDROGEN_CARBONATE', 2, '32D', '298-14-6', 'Idrogenocarbonato di potassio', 'Potassium hydrogen carbonate', NULL, NULL, 'POTASSIUM HYDROGEN CARBONATE|POTASSIUM BICARBONATE|BICARBONATO DI POTASSIO|IDROGENOCARBONATO DI POTASSIO', '02021R1165-20260101', 340, 1),
('LEPIDOPTERAN_PHEROMONES', 2, '38D', NULL, 'Feromoni di lepidotteri a catena lineare (acetati)', 'Straight chain lepidopteran pheromones (acetates)', NULL, 'PHEROMONE', 'YL ACETATE|DODECYL ACETATE|OCTADECADIENYL ACETATE', '02021R1165-20260101', 350, 1),
('SHEEP_FAT', 2, '39D', '98999-15-6', 'Grasso di pecora', 'Sheep fat', 'uso come repellente olfattivo', NULL, 'SHEEP FAT|GRASSO DI PECORA', '02021R1165-20260101', 360, 1),
('QUARTZ_SAND', 2, '44D', '14808-60-7 7631-86-9', 'Sabbia quarzosa, biossido di silicio', 'Quartz sand, silicon dioxide', NULL, NULL, 'QUARTZ SAND|SILICON DIOXIDE|SABBIA QUARZOSA|BIOSSIDO DI SILICIO', '02021R1165-20260101', 370, 1),
('MICROORGANISMS', 3, NULL, NULL, 'Microrganismi (allegato, parti A, B e D, del regolamento di esecuzione (UE) n. 540/2011)', 'Micro-organisms (Parts A, B and D of the Annex to Implementing Regulation (EU) No 540/2011)', 'a condizione che non provengano da OGM e soltanto se utilizzati in conformità degli usi, delle condizioni e delle restrizioni indicati nei pertinenti rapporti di riesame', NULL, 'STRAIN|STRAINS|ISOLATE|CEPPO|GRANULOVIRUS|NUCLEOPOLYHEDROVIRUS|MOSAIC VIRUS|BACILLUS|TRICHODERMA|BEAUVERIA|METARHIZIUM|AKANTHOMYCES|LECANICILLIUM|AMPELOMYCES|AUREOBASIDIUM|CANDIDA|CLONOSTACHYS|GLIOCLADIUM|CONIOTHYRIUM|PAECILOMYCES|PURPUREOCILLIUM|PURPUREOCILIUM|PSEUDOMONAS|PYTHIUM|SACCHAROMYCES|STREPTOMYCES|ISARIA|VERTICILLIUM|PHLEBIOPSIS|PSEUDOZYMA', '02021R1165-20260101', 380, 1),
('SPINOSAD', 4, '139A', '131929-60-7 131929-63-0', 'Spinosad', 'Spinosad', NULL, NULL, 'SPINOSAD', '02021R1165-20260101', 390, 1),
('CARBON_DIOXIDE', 4, '225A', '124-38-9', 'Anidride carbonica', 'Carbon dioxide', NULL, NULL, 'CARBON DIOXIDE|ANIDRIDE CARBONICA|BIOSSIDO DI CARBONIO', '02021R1165-20260101', 400, 1),
('ETHYLENE', 4, '227A', '74-85-1', 'Etilene', 'Ethylene', 'soltanto su banane e patate; può essere però usato sugli agrumi nell''ambito della strategia per la prevenzione degli attacchi della mosca della frutta', 'VERIFY', 'ETHYLENE|ETILENE', '02021R1165-20260101', 410, 1),
('FATTY_ACIDS', 4, '230A', '67701-09-1', 'Acidi grassi', 'Fatty acids', 'tutti gli usi autorizzati, salvo erbicida', 'NO_HERBICIDE', 'FATTY ACIDS|ACIDI GRASSI|PELARGONIC ACID|ACIDO PELARGONICO|CAPRIC ACID|CAPRYLIC ACID', '02021R1165-20260101', 420, 1),
('GARLIC_EXTRACT', 4, '231A', '8008-99-9', 'Estratto di aglio (Allium sativum)', 'Garlic extract (Allium sativum)', NULL, NULL, 'GARLIC EXTRACT|ESTRATTO DI AGLIO|ALLIUM SATIVUM', '02021R1165-20260101', 430, 1),
('HYDROLYSED_PROTEINS', 4, '234A', NULL, 'Proteine idrolizzate, esclusa la gelatina', 'Hydrolysed proteins excluding gelatine', NULL, NULL, 'HYDROLYSED PROTEINS|HYDROLYZED PROTEINS|PROTEINE IDROLIZZATE', '02021R1165-20260101', 440, 1),
('KAOLIN', 4, '220A', '1332-58-7', 'Silicato di alluminio (caolino)', 'Aluminium silicate (kaolin)', NULL, NULL, 'KAOLIN|CAOLINO|ALUMINIUM SILICATE|SILICATO DI ALLUMINIO', '02021R1165-20260101', 450, 1),
('KIESELGUR', 4, '236A', '61790-53-2', 'Farina fossile (terra di diatomee)', 'Kieselgur (diatomaceous earth)', NULL, NULL, 'KIESELGUR|DIATOMACEOUS EARTH|FARINA FOSSILE|TERRA DI DIATOMEE', '02021R1165-20260101', 460, 1),
('AZADIRACHTIN', 4, '343A', '11141-17-6 84696-25-3', 'Azadiractina (estratto di margosa)', 'Azadirachtin (Margosa extract)', 'estratto dai semi dell''albero del neem (Azadirachta indica)', NULL, 'AZADIRACHTIN|AZADIRACTINA', '02021R1165-20260101', 470, 1),
('CITRONELLA_OIL', 4, '240A', '8000-29-1', 'Olio di citronella', 'Citronella oil', 'tutti gli usi autorizzati, salvo erbicida', 'NO_HERBICIDE', 'CITRONELLA', '02021R1165-20260101', 480, 1),
('CLOVE_OIL', 4, '241A', '84961-50-2', 'Olio di chiodi di garofano', 'Clove oil', 'tutti gli usi autorizzati, salvo erbicida', 'NO_HERBICIDE', 'CLOVE OIL|CHIODI DI GAROFANO', '02021R1165-20260101', 490, 1),
('RAPE_SEED_OIL', 4, '242A', '8002-13-9', 'Olio di colza', 'Rape seed oil', 'tutti gli usi autorizzati, salvo erbicida', 'NO_HERBICIDE', 'RAPE SEED OIL|RAPESEED OIL|OLIO DI COLZA', '02021R1165-20260101', 500, 1),
('SPEARMINT_OIL', 4, '243A', '8008-79-5', 'Olio di menta verde', 'Spearmint oil', 'tutti gli usi autorizzati, salvo erbicida', 'NO_HERBICIDE', 'SPEAR MINT OIL|SPEARMINT OIL|MENTA VERDE', '02021R1165-20260101', 510, 1),
('ORANGE_OIL', 4, '56A', '8028-48-6 5989-27-5', 'Olio di arancio', 'Orange oil', 'tutti gli usi autorizzati, salvo erbicida', 'NO_HERBICIDE', 'ORANGE OIL|OLIO DI ARANCIO|OLIO ESSENZIALE DI ARANCIO', '02021R1165-20260101', 520, 1),
('TEA_TREE_OIL', 4, '228A', '68647-73-4', 'Olio di Melaleuca alternifolia', 'Tea tree oil', 'tutti gli usi autorizzati, salvo erbicida', 'NO_HERBICIDE', 'TEA TREE OIL|MELALEUCA', '02021R1165-20260101', 530, 1),
('PYRETHRINS', 4, '246A', '8003-34-7', 'Piretrine estratte da vegetali', 'Pyrethrins extracted from plants', NULL, NULL, 'PYRETHRINS|PYRETHRIN I|PYRETHRIN|PIRETRINE|PIRETRO', '02021R1165-20260101', 540, 1),
('SULPHUR', 4, '292A', '7704-34-9', 'Zolfo', 'Sulphur', NULL, NULL, 'SULPHUR|SULFUR|ZOLFO', '02021R1165-20260101', 550, 1),
('PARAFFIN_OILS', 4, '294A 295A', '64742-46-7 72623-86-0 97862-82-3 8042-47-5', 'Oli di paraffina', 'Paraffin oils', NULL, NULL, 'PARAFFIN OIL|PARAFFIN OILS|OLIO DI PARAFFINA|OLI DI PARAFFINA|OLIO MINERALE PARAFFINICO', '02021R1165-20260101', 560, 1),
('LIME_SULPHUR', 4, '345A', '1344-81-6', 'Zolfo calcico (polisolfuro di calcio)', 'Lime sulphur (calcium polysulphide)', NULL, NULL, 'LIME SULPHUR|CALCIUM POLYSULPHIDE|CALCIUM POLYSULPHID|POLISOLFURO DI CALCIO|ZOLFO CALCICO', '02021R1165-20260101', 570, 1),
('MALTODEXTRIN', 4, '44B', '9050-36-6', 'Maltodestrina', 'Maltodextrin', NULL, NULL, 'MALTODEXTRIN|MALTODESTRINA', '02021R1165-20260101', 580, 1),
('EUGENOL', 4, '45B', '97-53-0', 'Eugenolo', 'Eugenol', NULL, NULL, 'EUGENOL|EUGENOLO', '02021R1165-20260101', 590, 1),
('GERANIOL', 4, '46B', '106-24-1', 'Geraniolo', 'Geraniol', NULL, NULL, 'GERANIOL|GERANIOLO', '02021R1165-20260101', 600, 1),
('THYMOL', 4, '47B', '89-83-8', 'Timolo', 'Thymol', NULL, NULL, 'THYMOL|TIMOLO', '02021R1165-20260101', 610, 1),
('PHEROMONES', 4, '153B', NULL, 'Feromoni e altri semiochimici', 'Pheromones and other semiochemicals', NULL, 'PHEROMONE', 'PHEROMONE|PHEROMONES|FEROMONE|FEROMONI|SEMIOCHEMICAL|SEMIOCHEMICALS|1-OL|DODECAN|TETRADECAN|TETRADECENAL', '02021R1165-20260101', 620, 1),
('COPPER_HYDROXIDE', 4, '10E', '20427-59-2', 'Idrossido di rame', 'Copper hydroxide', 'in conformità del regolamento di esecuzione (UE) n. 540/2011 esclusivamente gli impieghi che comportano un''applicazione totale non superiore a 28 kg di rame per ettaro nell''arco di 7 anni', 'COPPER', 'COPPER HYDROXIDE|IDROSSIDO DI RAME|RAME IDROSSIDO', '02021R1165-20260101', 630, 1),
('COPPER_OXYCHLORIDE', 4, '10E', '1332-65-6 1332-40-7', 'Ossicloruro di rame', 'Copper oxychloride', 'in conformità del regolamento di esecuzione (UE) n. 540/2011 esclusivamente gli impieghi che comportano un''applicazione totale non superiore a 28 kg di rame per ettaro nell''arco di 7 anni', 'COPPER', 'COPPER OXYCHLORIDE|OSSICLORURO DI RAME|RAME OSSICLORURO', '02021R1165-20260101', 640, 1),
('COPPER_OXIDE', 4, '10E', '1317-39-1', 'Ossido di rame', 'Copper oxide', 'in conformità del regolamento di esecuzione (UE) n. 540/2011 esclusivamente gli impieghi che comportano un''applicazione totale non superiore a 28 kg di rame per ettaro nell''arco di 7 anni', 'COPPER', 'COPPER OXIDE|OSSIDO DI RAME|OSSIDO RAMEOSO', '02021R1165-20260101', 650, 1),
('BORDEAUX_MIXTURE', 4, '10E', '8011-63-0', 'Poltiglia bordolese', 'Bordeaux mixture', 'in conformità del regolamento di esecuzione (UE) n. 540/2011 esclusivamente gli impieghi che comportano un''applicazione totale non superiore a 28 kg di rame per ettaro nell''arco di 7 anni', 'COPPER', 'BORDEAUX MIXTURE|POLTIGLIA BORDOLESE', '02021R1165-20260101', 660, 1),
('TRIBASIC_COPPER_SULPHATE', 4, '10E', '12527-76-3', 'Solfato di rame tribasico', 'Tribasic copper sulphate', 'in conformità del regolamento di esecuzione (UE) n. 540/2011 esclusivamente gli impieghi che comportano un''applicazione totale non superiore a 28 kg di rame per ettaro nell''arco di 7 anni', 'COPPER', 'TRIBASIC COPPER SULFATE|TRIBASIC COPPER SULPHATE|SOLFATO DI RAME TRIBASICO|SOLFATO TRIBASICO DI RAME', '02021R1165-20260101', 670, 1),
('DELTAMETHRIN', 4, '40A', '52918-63-5', 'Deltametrina', 'Deltamethrin', 'solo in trappole con specifiche sostanze attrattive contro Bactrocera oleae, Ceratitis capitata e Rhagoletis completa', 'TRAPS', 'DELTAMETHRIN|DELTAMETRINA', '02021R1165-20260101', 680, 1),
('LAMBDA_CYHALOTHRIN', 4, '5E', '91465-08-6', 'Lambda-cialotrina', 'Lambda-cyhalothrin', 'solo in trappole con specifiche sostanze attrattive contro Bactrocera oleae e Ceratitis capitata', 'TRAPS', 'LAMBDA-CYHALOTHRIN|LAMBDA CYHALOTHRIN|LAMBDA-CIALOTRINA|LAMBDA CIALOTRINA', '02021R1165-20260101', 690, 1),
('COPPER_SULPHATE_NOT_LISTED', 0, NULL, NULL, 'Solfato di rame (non elencato come tale)', 'Copper sulphate (not listed as such)', 'Non è tra i composti di rame dell''allegato I (idrossido, ossicloruro, ossido, poltiglia bordolese, solfato tribasico): verificare la composizione sull''etichetta', 'VERIFY', 'COPPER SULFATE|COPPER SULPHATE|SOLFATO DI RAME', '02021R1165-20260101', 700, 1);
