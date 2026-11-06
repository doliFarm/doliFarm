# Dati demo doliFarm

## Demo completa (consigliata)

`scripts/demo_data.php` crea i dati di una demo completa della suite con le classi dei moduli, quindi con le stesse convalide, gli stessi movimenti di magazzino e lo stesso registro dei trattamenti delle pagine. Solo per un'istanza demo, mai sulla produzione.

    php custom/dolifarm/scripts/demo_data.php <login admin> --demo

Lo script:

- imposta società, paese (Italia), lingua e attiva i moduli necessari (terzi, prodotti, magazzino, lotti, progetti, lavori pianificati, doliFarm, doliTrace, doliField) e la modalità con più aziende;
- crea due aziende agricole affiliate e un trasformatore:
  - Agrumi dell'Etna (Paternò, Biancavilla, Lentini): agrumeti Tarocco e Moro biologici dal 2015, uliveto in conversione dal 2024, seminativo in affitto con grano duro e fava;
  - Valle del Belice (Castelvetrano, Partanna): uliveto Nocellara del Belice, vigneti Grillo e Nero d'Avola, seminativo con grano duro e cece;
  - Frantoio Oleario del Belice: riceve le olive delle due aziende e produce l'olio, un lotto per azienda e annata;
- per ogni azienda: fascicolo convalidato, appezzamenti con dati catastali e coordinate, periodi bio, mezzi con controllo funzionale delle irroratrici, fabbricati, operatori con patentino, fitosanitari con nome e numero di registrazione dei dati demo precedenti e usi indicativi (coltura, avversità, dose: non verificati sulle etichette, non sono indicazioni d'impiego), concimi, sementi, acquisti con lotti nel magazzino dei mezzi tecnici;
- tre annate (2024 siccitosa, 2025, 2026 in corso) e due piani in bozza per il 2027 (uno con l'avviso di rotazione): operazioni, trattamenti e fertilizzazioni importati dal CSV del registro e convalidati (scarico del magazzino, impronta del registro), raccolte convalidate con lotti (anche "in conversione"), piani delle annate passate chiusi;
- ispezione bio 2025 chiusa (dati congelati fino alla data) e ispezione 2026 da fare, costi standard per tipo di operazione, punti meteo di doliField.

Dati inventati: nomi, codici fiscali (DEMO...), partite IVA, protocolli e patentini non corrispondono a soggetti reali. Gli organismi di controllo sono quelli del dizionario di doliFarm.

Si può rilanciare: ciò che c'è già (stesso riferimento) non viene duplicato. Lo storico meteo si scarica solo dopo aver inserito la chiave del Climate Data Store nel setup di doliField.

## File SQL (vecchi)

I file `.sql` di questa cartella sono i dati demo precedenti (aziende di Bologna e Firenze, scritti direttamente nelle tabelle senza magazzino né convalide). Restano per le istanze che li usano; per una demo nuova usare lo script.

Non vengono caricati all'attivazione del modulo. Import a mano su un ambiente di prova, nell'ordine `dolifarm_demo_data.sql`, `dolifarm_demo_agrodrugs.sql`, `../../../dolitrace/sql/demo/dolitrace_demo_data.sql`:

    mysql -u <utente> -p <database> < dolifarm_demo_data.sql

Se il database usa un prefisso diverso da `llx_`, sostituirlo prima dell'import (`sed 's/llx_/gd_/g' ...`). Non eseguire mai su produzione.

## Guida

Ogni pagina principale ha il pulsante **Guida** in basso a destra; "Guida completa" in Primi passi percorre tutte le pagine nell'ordine.
