# CHANGELOG MODULE DOLIFARM FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.4.0

Produzione biologica, mezzi tecnici e dati AGEA del quaderno di campagna. È la versione richiesta da doliTrace 1.2.0: aggiornare doliFarm prima di doliTrace. Dopo l'aggiornamento disattivare e riattivare il modulo (tabelle, colonne, dizionari, extrafield e menu nuovi). Requisito minimo PHP 8.2 e Dolibarr 23; provato su PHP 8.4, MySQL 8.4 e Dolibarr 24.0.2.

### Produzione biologica
- Appezzamento: storia dello stato bio (convenzionale, conversione, biologico, biodinamico) con motivo e riferimento, stato alla data, anni di conversione proposti dalla coltura (3 per frutteti, vigneti e frutta a guscio).
- Agrofarmaci: verifica per l'uso in biologico su tre livelli (sostanze attive nell'allegato I del Reg. UE 2021/1165 con le condizioni, autorizzazione in Italia, usi dell'etichetta), esito Ammesso / Non ammesso / Da verificare, conferma manuale con data e utente; contenuto di rame.
- Fertilizzanti: natura del prodotto, categoria dell'allegato II del Reg. UE 2021/1165, data della verifica, rame, efficienza dell'azoto, sostanza secca, densità dei liquidi.
- Prodotti: stato bio delle sementi e del materiale di moltiplicazione (biologico, in conversione, non biologico).
- Soggetti terzi: certificato bio, organismo di controllo e scadenza, anche nel widget delle scadenze.
- Magazzini: "Dedicato a (biologico)" per separare i prodotti bio, in conversione e non bio.

### Mezzi tecnici
- Catalogo dei prodotti fitosanitari del Ministero della Salute (open data): sincronizzazione da Setup → Prodotti fitosanitari, cron settimanale disattivato di default; avvisi per prodotti revocati o sospesi.
- Usi autorizzati dell'agrofarmaco (coltura e avversità EPPO, dose, intervallo di sicurezza e numero massimo di trattamenti per coltura), con i gruppi EPPO (es. agrumi) che coprono le loro specie; colture e avversità cercate su tutto il database EPPO.
- Lettura degli usi dal SIAN su richiesta, un prodotto alla volta: proposte da confermare, corrispondenze SIAN → EPPO salvate, etichetta PDF nei Documenti.
- Gruppi del modo d'azione FRAC/IRAC/HRAC e numero massimo di trattamenti per ciclo.
- Pagina "Registro dei mezzi tecnici" con la verifica bio di agrofarmaci e fertilizzanti.

### Quaderno di campagna AGEA
- Macchine: certificato e data del controllo funzionale dell'irroratrice; fabbricati con indirizzo e magazzino; voci AGEA nei dizionari; tipi di operazione per gli eventi colturali.

### Altro
- Visibilità per commerciale anche negli elenchi a discesa e filtro "Commerciale" facoltativo nelle liste; controllo lato server dell'azienda al salvataggio.
- Home: widget agricoli e gruppi della Vista globale.
- Licenza AGPL-3.0 o successiva, titolare Luigi Grillo - doliFarm.com.
- Pulizia: file SQL non usati, etichette delle note, traduzioni en/fr, pacchetto.

## 1.3.0

Visibilità delle aziende per commerciale e modalità azienda unica. È la versione richiesta da doliTrace 1.1.0 (registro dei trattamenti): aggiornare doliFarm prima di doliTrace. Dopo l'aggiornamento disattivare e riattivare il modulo.

### Visibilità per commerciale
- Stessa regola di Dolibarr: l'utente interno senza il permesso "vedere tutti i terzi" vede solo i terzi che segue (anche quelli dei subordinati con MAIN_SEE_SUBORDINATES), l'utente esterno solo il proprio; gli oggetti senza terzo restano visibili.
- Filtrate le liste di fascicoli, appezzamenti, macchine, fabbricati, allevamenti, operatori e stime dei costi, i widget e l'elenco delle scadenze.
- Schede e relative linguette controllano il terzo e l'entità dell'oggetto.
- Funzioni comuni in lib/dolifarm_access.lib.php, usate anche da doliTrace.
- Rimossi gli endpoint ajax inutilizzati generati da ModuleBuilder, che permettevano a un utente con permesso di scrittura di modificare qualsiasi campo di qualsiasi oggetto.

### Azienda unica
- Nuova impostazione "Azienda (modalità azienda singola)" (DOLIFARM_FARM_SOCID), disponibile quando la modalità multi-azienda è spenta.
- I moduli di creazione di fascicoli, appezzamenti, macchine, fabbricati, allevamenti, operatori e stime dei costi propongono l'azienda e il suo fascicolo in corso.
- Senza azienda indicata, il magazzino dell'azienda è quello dell'azienda unica, prima dei magazzini predefiniti del setup.
- Funzioni comuni in lib/dolifarm_farm.lib.php, usate anche da doliTrace.

## 1.2.0

Anagrafiche per il registro dei trattamenti e delle fertilizzazioni (Reg. UE 2023/564) e codici EPPO. Dopo l'aggiornamento disattivare e riattivare il modulo per applicare tabelle, indici, dizionari e job pianificati.

### Registro dei trattamenti
- Nuovo oggetto Operatore (riferimento OPER): terzo o contatto o utente, codice fiscale, patentino con tipo, numero, ente, rilascio e scadenza; la scadenza del patentino entra nelle scadenze.
- Nuovo oggetto Fertilizzante (riferimento FERT): prodotto, tipo (dizionario "Tipi di fertilizzante"), produttore, numero di registrazione, titolo di N, P2O5 e K2O, biologico.
- Colture: codice EPPO. Appezzamenti: identificativo della parcella IACS e tipo di coltivazione.

### Magazzini e aziende affiliate
- Fascicolo: magazzino dei mezzi tecnici e magazzino dei prodotti (con il modulo Magazzini attivo); nel setup i magazzini predefiniti per la modalità azienda singola.
- Modalità multi-azienda (setup): il campo "Azienda affiliata" del soggetto terzo crea il fascicolo in bozza e i magazzini AZ-<codice>, -IN e -PROD; togliendolo i magazzini vengono chiusi e il fascicolo disattivato, senza cancellare nulla.

### Codici EPPO
- Import del file "XML full" scaricato da data.eppo.int (anche compresso in gzip), dalla scheda "Codici EPPO" del setup o da riga di comando (scripts/eppo_import.php). Solo codici e nomi, secondo la EPPO Open Data Licence; attribuzione e data dei dati mostrate dove compaiono i codici.
- Colture: ricerca del codice per nome o codice, controllo del tipo (piante) e compilazione dal nome scientifico; nella scheda "Codici EPPO" il completamento delle colture senza codice.
- Aggiornamento dall'API EPPO dei codici creati o modificati (codici disattivati e codice sostitutivo): pulsante "Aggiorna ora" e job settimanale, disattivato per default. La chiave API si inserisce nelle impostazioni del modulo, viene verificata e salvata cifrata.
- Dopo l'aggiornamento: importare il file EPPO, inserire la chiave e attivare il job "Aggiornamento codici EPPO" nei lavori pianificati.

### Altro
- Riferimento assegnato alla creazione per macchine (non modificabile, come gli appezzamenti); per agrofarmaci, colture e fascicolo è facoltativo e, se vuoto, viene generato (AGRO, COLT, FASC).
- Schede Note e Documenti attive per tutti gli oggetti; rimossi i modelli di documento di esempio di ModuleBuilder.

## 1.1.1

Correzioni emerse dall'aggiornamento di un'installazione esistente alla 1.1.0. Dopo l'aggiornamento disattivare e riattivare il modulo.

- Attivazione: gli indici sulle colonne aggiunte dagli script update_*.sql (conduzione e riferimento catastale degli appezzamenti, CAA e validità del fascicolo) passano dai .key.sql agli update_*.sql; sulle installazioni vecchie l'attivazione falliva.
- Appezzamenti: i record senza uuid ne ricevono uno, così l'indice unico sull'uuid può essere creato; corretto il prefisso della tabella nel controllo di unicità.
- Widget: un errore SQL viene mostrato nel riquadro invece di bloccare l'intera pagina.

## 1.1.0

Requisito minimo: Dolibarr 23. Dopo l'aggiornamento disattivare e riattivare il modulo per applicare tabelle, indici e costanti.

### Fascicolo aziendale
- Testata della scheda di validazione AGEA, filtri per tipo di terzo, pulsante per creare un nuovo terzo dalla scheda.
- CUAA salvato sul soggetto terzo; correzioni dell'importatore XML.
- Nuove schede Consistenza zootecnica e Fabbricati, con badge nelle schede del fascicolo.

### Appezzamenti
- Dati di conduzione, poligono salvato, catasto AdE e layout a fasce.
- Riferimento APPEZ, filtro per fascicolo, periodi, note e scheda PDF con QR che apre la scheda dell'appezzamento.
- Mappa: proxy WMS catastale unico con whitelist degli host e verifica SSL.

### Allevamenti e fabbricati
- Nuovi oggetti con riferimento ALLEV e FABB, liste, schede, note, documenti ed extrafields.
- Dizionario "Specie allevate".

### Scadenze
- Scadenze di validità del fascicolo, fine conduzione di appezzamenti e fabbricati, autorizzazione degli agrofarmaci, fine conversione biologica.
- Nuovi campi delle macchine: prossima revisione, scadenza assicurazione, scadenza controllo irroratrice.
- Badge nelle liste e nelle schede, widget "doliFarm - Scadenze", eventi in agenda aggiornati dai trigger.
- Setup: giorni di preavviso, attivazione degli eventi in agenda, sincronizzazione delle scadenze già registrate.

### Numerazione e pulizia
- Prefissi FASC, MACCH, AGRO, COLT, STIMA e costanti di numerazione per oggetto.
- Indici unici su (ref, entity) per fascicolo e appezzamento.
- Permessi con hasRight() in tutte le pagine; codici dei trigger e costanti dei modelli allineati agli oggetti.
- Rimossi i residui di esempio di ModuleBuilder (hook, parametri demo).
- Script SQL non distruttivi e idempotenti; dati demo separati in sql/demo.

## 1.0

Initial version
