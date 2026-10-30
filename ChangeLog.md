# CHANGELOG MODULE DOLIFARM FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

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
