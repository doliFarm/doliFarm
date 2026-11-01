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

-- Annex II of Reg. EU 2021/1165 (consolidated text 02021R1165-20260101). Loaded at every activation: existing codes are kept.
INSERT IGNORE INTO llx_dolifarm_organic_fertilizer (code, label, conditions, check_type, annex_version, position, active) VALUES
('MANURE', 'Letame', 'prodotto costituito da un miscuglio di deiezioni animali e materiali vegetali (lettiera e materie prime per mangimi); proibito se proveniente da allevamenti industriali', 'VERIFY', '02021R1165-20260101', 10, 1),
('DRIED_MANURE', 'Letame essiccato e pollina disidratata', 'proibiti se proveniente da allevamenti industriali', 'VERIFY', '02021R1165-20260101', 20, 1),
('COMPOSTED_MANURE', 'Effluenti di allevamento compostati, compresi pollina e stallatico compostato', 'proibiti se proveniente da allevamenti industriali', 'VERIFY', '02021R1165-20260101', 30, 1),
('LIQUID_MANURE', 'Effluenti di allevamento liquidi', 'uso: previa fermentazione controllata e/o diluizione adeguata; proibiti se proveniente da allevamenti industriali', 'VERIFY', '02021R1165-20260101', 40, 1),
('ORGANIC_WASTE', 'Rifiuti organici compostati o fermentati [direttiva 2008/98/CE]', 'prodotto ottenuto dalla raccolta differenziata alla fonte di rifiuti organici, sottoposti a compostaggio o a fermentazione anaerobica per la produzione di biogas; solo rifiuti organici vegetali e animali; solo se prodotti all''interno di un sistema di raccolta chiuso e sorvegliato, ammesso dallo Stato membro; concentrazioni massime in mg/kg di sostanza secca: cadmio 0,7; rame 70; nichel 25; piombo 45; zinco 200; mercurio 0,4; cromo (totale) 70; cromo (VI) non rilevabile', 'VERIFY', '02021R1165-20260101', 50, 1),
('PEAT', 'Torba', 'impiego limitato all''orticoltura (colture orticole, floricole, arboricole, vivai)', 'VERIFY', '02021R1165-20260101', 60, 1),
('MUSHROOM_SUBSTRATE', 'Residui di fungaie', 'la composizione iniziale del substrato deve essere limitata ai prodotti del presente allegato', NULL, '02021R1165-20260101', 70, 1),
('VERMICOMPOST', 'Deiezioni di vermi (vermicompost) e miscela di escrementi di insetti', 'ove del caso in conformità del regolamento (CE) n. 1069/2009', NULL, '02021R1165-20260101', 80, 1),
('GUANO', 'Guano', NULL, NULL, '02021R1165-20260101', 90, 1),
('PLANT_COMPOST', 'Miscela di materiali vegetali compostata o fermentata', 'prodotto ottenuto da miscele di materiali vegetali sottoposte a compostaggio o a fermentazione anaerobica per la produzione di biogas', NULL, '02021R1165-20260101', 100, 1),
('BIOGAS_DIGESTATE', 'Digestato da biogas contenente sottoprodotti di origine animale codigestati con materiale di origine vegetale o animale elencato nell''allegato', 'sottoprodotti di origine animale di categoria 3 e contenuto del tubo digerente di categoria 2 (reg. (CE) n. 1069/2009); proibito se proveniente da allevamenti industriali; processi conformi al regolamento (UE) n. 142/2011; non applicabili alle parti commestibili della coltura', 'VERIFY', '02021R1165-20260101', 110, 1),
('ANIMAL_PRODUCTS', 'Prodotti o sottoprodotti di origine animale: farina di sangue, di zoccoli, di corna, di ossa (anche degelatinata), di pesce, di carne; pennone; lana; pellami; peli e crini; prodotti lattiero-caseari; proteine idrolizzate', 'pellami: cromo (VI) non rilevabile; proteine idrolizzate: non applicabili alle parti commestibili della coltura', 'VERIFY', '02021R1165-20260101', 120, 1),
('PLANT_PRODUCTS', 'Prodotti e sottoprodotti di origine vegetale', 'ad esempio: panelli di semi oleosi, gusci di cacao, radichette di malto', NULL, '02021R1165-20260101', 130, 1),
('PLANT_HYDROLYSED_PROTEINS', 'Proteine idrolizzate di origine vegetale', NULL, NULL, '02021R1165-20260101', 140, 1),
('SEAWEED', 'Alghe e prodotti a base di alghe', 'se ottenuti direttamente mediante processi fisici (disidratazione, congelamento, macinazione), estrazione con acqua o soluzione acquosa acida e/o alcalina, fermentazione; solo biologiche o da raccolta sostenibile (allegato II, parte III, punto 2.4, del reg. (UE) 2018/848)', 'VERIFY', '02021R1165-20260101', 150, 1),
('SAWDUST', 'Segatura e trucioli di legno', 'legname non trattato chimicamente dopo il taglio', NULL, '02021R1165-20260101', 160, 1),
('COMPOSTED_BARK', 'Cortecce compostate', 'legname non trattato chimicamente dopo il taglio', NULL, '02021R1165-20260101', 170, 1),
('WOOD_ASH', 'Cenere di legno', 'proveniente da legname non trattato chimicamente dopo il taglio', NULL, '02021R1165-20260101', 180, 1),
('SOFT_ROCK_PHOSPHATE', 'Fosfato naturale tenero', 'titolo minimo 25 % P2O5; si applicano i pertinenti limiti di contaminanti fissati nel regolamento (UE) 2019/1009', NULL, '02021R1165-20260101', 190, 1),
('ALUMINO_CALCIUM_PHOSPHATE', 'Fosfato alluminocalcico', 'titolo minimo 30 % P2O5; impiego limitato ai terreni basici (pH > 7,5); si applicano i pertinenti limiti di contaminanti fissati nel regolamento (UE) 2019/1009', 'VERIFY', '02021R1165-20260101', 200, 1),
('BASIC_SLAG', 'Scorie di defosforazione (fosfati Thomas o scorie Thomas)', 'titolo minimo 12 % P2O5 (o 10 % solubile in acido citrico al 2 %); si applicano i pertinenti limiti di contaminanti fissati nel regolamento (UE) 2019/1009', NULL, '02021R1165-20260101', 210, 1),
('CRUDE_POTASSIUM_SALT', 'Sale grezzo di potassio', 'titolo minimo 9 % K2O e 2 % MgO; si applicano i pertinenti limiti di contaminanti fissati nel regolamento (UE) 2019/1009', NULL, '02021R1165-20260101', 220, 1),
('POTASSIUM_SULPHATE', 'Solfato di potassio, che può contenere sale di magnesio', 'prodotto ottenuto da sale grezzo di potassio mediante un processo di estrazione fisica', NULL, '02021R1165-20260101', 230, 1),
('STILLAGE', 'Borlande ed estratti di borlande', 'escluse le borlande estratte con sali ammoniacali', NULL, '02021R1165-20260101', 240, 1),
('CALCIUM_CARBONATE', 'Carbonato di calcio (creta, marna, calcare macinato, litotamnio (maerl), creta fosfatica)', 'solo di origine naturale', NULL, '02021R1165-20260101', 250, 1),
('MOLLUSC_SHELLS', 'Gusci di molluschi', 'soltanto da acquacoltura biologica o attività di pesca sostenibili (art. 2 del reg. (UE) n. 1380/2013)', 'VERIFY', '02021R1165-20260101', 260, 1),
('EGG_SHELLS', 'Gusci d''uovo', 'proibiti se proveniente da allevamenti industriali', 'VERIFY', '02021R1165-20260101', 270, 1),
('CALCIUM_MAGNESIUM_CARBONATE', 'Carbonato di calcio e di magnesio (creta magnesiaca, magnesio macinato, calcare)', 'solo di origine naturale', NULL, '02021R1165-20260101', 280, 1),
('MAGNESIUM_SULPHATE', 'Solfato di magnesio (kieserite)', 'solo di origine naturale', NULL, '02021R1165-20260101', 290, 1),
('CALCIUM_CHLORIDE', 'Soluzione di cloruro di calcio', 'solo per trattamento fogliare su melo, per prevenire una carenza di calcio', 'VERIFY', '02021R1165-20260101', 300, 1),
('CALCIUM_SULPHATE', 'Solfato di calcio (gesso)', 'prodotto d''origine naturale; titolo minimo 25 % CaO e 35 % SO3; si applicano i pertinenti limiti di contaminanti fissati nel regolamento (UE) 2019/1009', NULL, '02021R1165-20260101', 310, 1),
('SUGAR_SLUDGE', 'Fanghi industriali provenienti da zuccherifici', 'sottoprodotto della produzione di zucchero di barbabietola e di canna da zucchero', NULL, '02021R1165-20260101', 320, 1),
('SALT_SLUDGE', 'Fanghi industriali derivanti dalla produzione di sale mediante estrazione per dissoluzione', 'sottoprodotto della produzione di sale da salamoie naturali presenti in zone montane', NULL, '02021R1165-20260101', 330, 1),
('ELEMENTAL_SULPHUR', 'Zolfo elementare', 'si applicano i pertinenti limiti di contaminanti fissati nel regolamento (UE) 2019/1009', NULL, '02021R1165-20260101', 340, 1),
('MICRONUTRIENTS', 'Concimi inorganici a base di microelementi', 'si applicano i pertinenti limiti di contaminanti fissati nel regolamento (UE) 2019/1009', NULL, '02021R1165-20260101', 350, 1),
('SODIUM_CHLORIDE', 'Cloruro di sodio', NULL, NULL, '02021R1165-20260101', 360, 1),
('ROCK_DUST', 'Farina di rocce, sabbia di origine naturale, argille e minerali argillosi', 'ad esempio perlite, sabbia e vermiculite, anche quando sottoposte a trattamento termico', NULL, '02021R1165-20260101', 370, 1),
('LEONARDITE', 'Leonardite (sedimenti organici grezzi ricchi di acidi umici)', 'solo se ottenuta come sottoprodotto di attività estrattive', NULL, '02021R1165-20260101', 380, 1),
('HUMIC_FULVIC_ACIDS', 'Acidi umici e fulvici', 'solo se estratti con sali/soluzioni di natura inorganica esclusi i sali di ammonio; o se ottenuti dalla potabilizzazione dell''acqua', NULL, '02021R1165-20260101', 390, 1),
('XYLITOL', 'Xilitolo', 'solo se ottenuto come sottoprodotto di attività estrattive (ad esempio dell''estrazione di lignite)', NULL, '02021R1165-20260101', 400, 1),
('CHITIN', 'Chitina (polisaccaride ottenuto dall''esoscheletro dei crostacei)', 'ottenuta da acquacoltura biologica o attività di pesca sostenibili (art. 2 del reg. (UE) n. 1380/2013)', 'VERIFY', '02021R1165-20260101', 410, 1),
('SAPROPEL', 'Sedimento ricco di materie organiche formatosi dai corpi idrici di acqua dolce in ambiente anaerobico (ad esempio sapropel)', 'solo sottoprodotti della gestione di corpi idrici di acqua dolce o estratti da zone precedentemente coperte da acqua dolce; da fonti non contaminate; si applicano i pertinenti limiti di contaminanti fissati nel regolamento (UE) 2019/1009', 'VERIFY', '02021R1165-20260101', 420, 1),
('BIOCHAR', 'Biochar (prodotto della pirolisi di materiali organici di origine vegetale, impiegato come ammendante)', 'solo da materiali vegetali, se trattati dopo il raccolto soltanto con prodotti dell''allegato I; si applicano i pertinenti limiti di contaminanti fissati nel regolamento (UE) 2019/1009', NULL, '02021R1165-20260101', 430, 1),
('STRUVITE', 'Struvite recuperata e precipitati di sali di fosfato', 'requisiti del regolamento (UE) 2019/1009; il letame usato come materiale di partenza non può provenire da allevamenti industriali', 'VERIFY', '02021R1165-20260101', 440, 1),
('SODIUM_NITRATE', 'Nitrato di sodio', 'solo per la produzione di alghe su terraferma in sistemi chiusi', 'VERIFY', '02021R1165-20260101', 450, 1),
('POTASSIUM_CHLORIDE', 'Cloruro di potassio (muriato di potassio)', 'solo di origine naturale', NULL, '02021R1165-20260101', 460, 1),
('SELENIUM_SALTS', 'Sali di selenio', 'solo in caso di carenza nei terreni utilizzati per l''allevamento e/o il pascolo o per la produzione di colture foraggere', 'VERIFY', '02021R1165-20260101', 470, 1),
('CARBON_DIOXIDE', 'Biossido di carbonio', 'per arricchire l''acqua nella produzione di alghe in sistemi chiusi su terraferma (qualità alimentare); può essere usato anche per la produzione in serra', 'VERIFY', '02021R1165-20260101', 480, 1),
('CALCIUM_ACETATE', 'Acetato di calcio', 'solo per l''applicazione fogliare sugli ortaggi nelle serre e sui meli per prevenire carenze di calcio; ottenuto da carbonato di calcio di origine naturale', 'VERIFY', '02021R1165-20260101', 490, 1),
('CALCIUM_PHOSPHATE', 'Fosfato di calcio', 'solo se derivato da ceneri di fanghi di depurazione e contenuto in prodotti conformi al regolamento (UE) 2019/1009', 'VERIFY', '02021R1165-20260101', 500, 1),
('PLANT_FIBRE_MATS', 'Tappeti di fibre vegetali', 'come mezzo inerte nella produzione di semi germogliati; senza aggiunta di concimi, ammendanti o nutrienti né additivi o leganti', 'VERIFY', '02021R1165-20260101', 510, 1),
('CALCIUM_MAGNESIUM_GLUCONATE', 'Gluconato di calcio e magnesio', 'solo mediante fermentazione microbica', NULL, '02021R1165-20260101', 520, 1),
('MICROORGANISMS', 'Preparati a base di microrganismi (allegato II, parte I, punto 1.9.6, del reg. (UE) 2018/848)', 'per migliorare le condizioni generali del suolo o la disponibilità di elementi nutritivi; solo in conformità delle specifiche e delle restrizioni delle normative nazionali e dell''Unione', NULL, '02021R1165-20260101', 530, 1);
