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
