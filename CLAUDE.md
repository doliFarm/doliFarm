# doliFarm – indicazioni per Claude

Modulo esterno Dolibarr per la gestione dell'azienda agricola: fascicolo aziendale, appezzamenti, macchine, agrofarmaci, colture, stime dei costi. Fa parte della suite doliFarm insieme a doliTrace (tracciabilità e quaderno di campagna), doliField (dati di campo: sensori, meteo, satellite; in sviluppo), doliAgroPass e doliGas.

## Regole fondamentali

- **Il core di Dolibarr non si modifica mai.** Tutto passa da classi, hook, trigger e file del modulo.
- **Il nome della cartella è `dolifarm`, tutto minuscolo.** Tutti i percorsi del modulo usano `/dolifarm/...`. Su Linux una cartella `doliFarm`, creata dal `git clone` di `doliFarm/doliFarm`, impedisce l'attivazione. Clonare con `git clone git@github.com:doliFarm/doliFarm.git dolifarm`.
- **Ambiti:** doliFarm gestisce fascicolo e anagrafiche; doliTrace gestisce tracciabilità, piani colturali e quaderno di campagna. Il codice di doliFarm che usa doliTrace deve essere protetto con `isModEnabled('dolitrace')` (vedi `farmdossier_cropplans.php`, `lib/dolifarm_farmdossier.lib.php`).
- Trattamenti e raccolte muovono il magazzino nativo di Dolibarr tramite le sue classi (`MouvementStock`, ecc.), **mai con SQL diretto** su giacenze e lotti.

## Ambienti e compatibilità

| | Sviluppo (Docker sulla WS) | VPS (Linux, Docker, test) |
|---|---|---|
| URL | http://localhost:8089 | — |
| Dolibarr | 24.0.2 | 24.0.2 |
| PHP | 8.4 | 8.4 / 8.5 |
| Database | MySQL 8.4 | MySQL 8.x |

- **Unica piattaforma di sviluppo e di prova: l'istanza Docker** in `C:\dolibarr\docker-php8` (vedi il README lì). Monta `C:\dolibarr\www\dolibarr\htdocs`; database e documenti sono suoi. L'installazione DoliWamp della WS (porta 8088, PHP 7.4, MariaDB) **non si usa più** (deciso il 01/11/2026): niente prove, reinizializzazioni o controlli lì.

- Requisito minimo del modulo: **Dolibarr 23** (`need_dolibarr_version`), perché usa `dolBuildUrl()` e `getDolDefaultContextPage()`. Nessun fallback per versioni precedenti.
- PHP **8.2 o successivo** (`$this->phpmin = array(8, 2)`), provato su 8.4; la VPS può passare a 8.5:
  - la sintassi di PHP 8 è ammessa (`match`, `?->`, `str_contains()`, ...); non serve più la compatibilità con PHP 7;
  - niente deprecazioni PHP 8.x: dichiarare sempre le proprietà delle classi, non passare `null` alle funzioni native, nullable esplicito (`?Tipo $x = null`), niente `curl_close()`.
- SQL per **MySQL 8**, provato nell'istanza Docker. Restare su SQL semplice e portabile (niente funzioni solo MySQL se c'è un'alternativa standard), così da non escludere MariaDB. Nomi di tabelle e colonne in minuscolo: sulla VPS i nomi sono case-sensitive.
- Verifica della sintassi (Git Bash): `cd /c/dolibarr/docker-php8 && MSYS_NO_PATHCONV=1 docker compose exec -T web php -l /var/www/html/custom/<modulo>/<file>`.
- Script di prova da riga di comando nel container: `MSYS_NO_PATHCONV=1 docker compose exec -T -w /var/www/html web php < script.php` (percorsi `/var/www/html/...`).

## Licenza e intestazioni

- Licenza del modulo: **AGPL v3 o successiva** (`COPYING`, `LICENSE`). Titolare: Luigi Grillo - doliFarm.com <info@dolifarm.com>.
- Ogni file nuovo ha l'intestazione AGPL con `Copyright (C) <anno> Luigi Grillo - doliFarm.com <info@dolifarm.com>`.
- I file derivati dai modelli di ModuleBuilder con il copyright di autori di Dolibarr mantengono la loro intestazione **GPL v3 o successiva** (testo in `COPYING-GPL-3.0`): non cambiarla, aggiungere solo la riga del titolare.

## Struttura

- `core/modules/modDolifarm.class.php`: descrittore (numero 500001, permessi, menu, widget, `init()`).
- `class/`: oggetti `CommonObject` generati da ModuleBuilder (`DoliFarmFarmDossier`, `DoliFarmPlot`, `DoliFarmMachine`, `DoliFarmAgroDrug`, `DoliFarmCrops`, `DoliFarmEstimateCosts`), più `DoliFarmImporter` e `ActionsDolifarm` (hook).
- Pagine nella radice: `dolifarm<oggetto>_{list,card,agenda,contact,document,note}.php`, più pagine custom (`dolifarmfarmdossier_plots.php`, `_machines.php`, `farmdossier_cropplans.php`).
- `lib/`: funzioni di supporto e intestazioni delle schede; `lib/dolifarm_icons.lib.php` per le icone.
- `core/boxes/`: widget. Il nome del file deve coincidere con il nome della classe.
- `admin/`: setup ed extrafields. `ajax/`: endpoint, compreso il proxy WMS.
- `langs/{it_IT,en_US,fr_FR}/dolifarm.lang`: traduzioni. Le chiavi sono in inglese; il testo italiano va solo nei valori.

## Database e SQL

- Dolibarr esegue `sql/llx_*.sql`, poi `sql/llx_*.key.sql`, poi `sql/data*.sql` **a ogni attivazione del modulo**. Gli script devono essere **idempotenti e non distruttivi**: `CREATE TABLE IF NOT EXISTS`, niente `DROP TABLE`, `INSERT` che non duplicano (`INSERT IGNORE` o chiavi uniche).
- I dati demo stanno in `sql/demo/` e si importano a mano: non vanno mai caricati in automatico.
- Gli indici unici sugli oggetti multi-entity devono includere `entity`.
- Prefisso in SQL: `llx_` nei file `.sql`; nel codice usare `MAIN_DB_PREFIX` o `$this->db->prefix()`.

## Convenzioni di codice

- Seguire lo stile del codice Dolibarr circostante: tab, `GETPOST()`/`GETPOSTINT()` per l'input, `$this->db->escape()` e cast `(int)` nelle query, `dol_syslog()` per i log.
- Permessi con `$user->hasRight('dolifarm', 'oggetto', 'azione')`, non `$user->rights->...`.
- Costanti con `getDolGlobalString()`/`getDolGlobalInt()` e `dolibarr_set_const()`, non `$conf->global->...` in scrittura.
- Ogni pagina controlla permessi ed entity (`restrictedArea()` o equivalente).
- Niente file di debug, niente scritture in `/tmp`, niente log a livello WARNING per messaggi informativi.

## Git e deploy

- Lavoro su `develop`; `main` è ciò che va sulla VPS. Release con tag `vX.Y.Z` allineato a `$this->version` e `ChangeLog.md`.
- Fine riga LF nel repository (`.gitattributes`). Sulla WS i file possono avere CRLF: è normale.
- Deploy sulla VPS: `git pull` manuale nel container `farm-dolibarr` (`/var/www/html/custom/dolifarm`). Log: `docker exec farm-dolibarr tail -f /var/www/documents/dolibarr.log`.
- `docs/` contiene documenti di progettazione interni ed è escluso da git: piano di lavoro, analisi, specifica del quaderno di campagna.
