# Dati demo doliFarm

I file in questa cartella NON vengono caricati all'attivazione del modulo
(Dolibarr legge solo `sql/`, `sql/tables/`, `sql/data/`).

Per caricare i dati demo su un ambiente di sviluppo:

    mysql -u <utente> -p <database> < dolifarm_demo_data.sql

Attenzione: i file usano il prefisso `llx_`. Se il database usa un altro
prefisso (es. `gd_` sul WS), sostituirlo prima dell'import:

    sed 's/llx_/gd_/g' dolifarm_demo_data.sql | mysql -u <utente> -p <database>

Non eseguire mai su produzione.

## Agrofarmaci di esempio

`dolifarm_demo_agrodrugs.sql` crea 10 prodotti e agrofarmaci di esempio (fino al 01/11/2026 era in `sql/` e si caricava a ogni attivazione). I numeri di registrazione sono quelli del catalogo del Ministero della Salute: dopo l'import, la sincronizzazione del catalogo (Setup doliFarm → Catalogo fitosanitari) ne aggiorna stato e scadenze.

## Istanza demo

Per preparare un'istanza dimostrativa (mai la produzione):

1. Dolibarr 23 o successivo con i moduli Magazzino e Lotti/numeri di serie attivi; attivare doliFarm e poi doliTrace.
2. Seguire la pagina doliFarm → **Primi passi** per i dati della società, il soggetto terzo "Azienda agricola" e la modalità azienda unica.
3. Importare, nell'ordine: `dolifarm_demo_data.sql`, `dolifarm_demo_agrodrugs.sql`, poi `../../../dolitrace/sql/demo/dolitrace_demo_data.sql`. I file si possono importare più volte senza duplicare le schede.
4. Facoltativo: import dei codici EPPO e sincronizzazione del catalogo dei fitosanitari dal setup di doliFarm.
5. Ogni pagina principale ha il pulsante **Guida** in basso a destra; "Guida completa" in Primi passi percorre tutte le pagine nell'ordine.
