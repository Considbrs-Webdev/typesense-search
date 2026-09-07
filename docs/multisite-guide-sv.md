# Typesense: vanlig installation och multisite

En praktisk guide till den implementerade funktionen, uppdaterad 7 september 2026.
En **collection** är ett sökindex i Typesense som innehåller webbplatsens sökbara dokument.

## Skillnaden mellan lägena

| | Vanlig installation | Multisite med nätverksaktiverat tillägg |
| --- | --- | --- |
| Anslutning till Typesense | Ställs in på webbplatsen. | Ställs in gemensamt i nätverksadministrationen. |
| Admin-API-nyckel | Hanteras på webbplatsen. | Hanteras av nätverksadministratören. |
| Collection och söknyckel | Befintliga lokala inställningar används. | Varje webbplats får en egen collection och en egen begränsad söknyckel. |
| Vilka webbplatser använder Typesense? | Den lokala installationens inställningar gäller. | Nätverksadministratören väljer webbplatser. Nya webbplatser är avstängda från början. |
| Vad indexeras och hur ser sökningen ut? | Ställs in på webbplatsen. | Ställs fortfarande in separat på varje webbplats. |

**Multisite där tillägget bara är aktiverat lokalt på en webbplats fungerar som en vanlig installation.** Det gemensamma läget börjar gälla först när tillägget nätverksaktiveras.

## Inställningar på nätverksnivå

Öppna **Nätverksadministration → Inställningar → Typesense-sök**.

### Anslutning

- **Typesense-värd:** serverns adress, inklusive `http://` eller `https://` och eventuell port.
- **Admin-API-nyckel:** används av WordPress för administration och indexering. Den sparade nyckeln visas inte i formuläret. Lämna fältet tomt för att behålla den.
- **Värd för frontend:** valfri separat adress som besökarnas webbläsare använder för sökning. Om den lämnas tom används Typesense-värden. Adressen måste vara nåbar från besökarnas webbläsare.

Alla valda webbplatser använder denna gemensamma anslutning. Adminnyckeln ska inte användas i frontend; där används webbplatsens egen söknyckel.

### Webbplatser

Här kan nätverksadministratören:

- Välja vilka webbplatser som ska använda Typesense och spara valet.
- Se status och namn på aktiv collection och eventuell kandidat.
- **Förbereda / försöka igen:** skapa eller återanvända webbplatsens collection och söknyckel.
- **Aktivera:** börja använda kandidaten efter att innehållet har indexerats och granskats.

En **kandidat** är den collection och söknyckel som förberetts för aktivering. Förberedelse innebär inte att innehållet automatiskt har indexerats.

### Status

Kontrollera den gemensamma anslutningen eller en enskild webbplats. Webbplatskontrollen verifierar serveruppgifterna och den aktiva söknyckeln. Kontrollerna körs när du begär dem.

## Inställningar som fortfarande görs per webbplats

Följande hanteras i respektive webbplats egen administration:

- Innehållstyper, PDF-indexering och Modularity-inställningar.
- Sökutseende, filter/facetter och snabbsökning.
- Synonymer och fästa sökresultat.
- Sökstatistik och dess inställningar.

De lokala flikarna för anslutning och status visar i nätverksläget att inställningarna hanteras centralt. En lokal administratör kan inte ändra den gemensamma anslutningen eller välja en annan webbplats collection via dessa flikar.

## Vilket namn får en collection?

I nätverksläget skapas namnet automatiskt:

```text
{domän-och-eventuell-sökväg}__{miljö}_b{webbplats-id}
```

| Webbplatsens adress | Miljö | Webbplats-ID | Collection |
| --- | --- | --- | --- |
| `https://pitea.local` | `development` | 1 | `pitea-local__development_b1` |
| `https://pitea.local/test2/` | `development` | 3 | `pitea-local-test2__development_b3` |
| `https://www.pitea.se` | `production` | 1 | `www-pitea-se__production_b1` |
| `https://www.pitea.se/test2/` | `staging` | 3 | `www-pitea-se-test2__staging_b3` |

De två sista raderna är exempel, inte uppgifter om produktionsmiljön.

- Namnet bygger på webbplatsens publika hemadress, inte adressen till WordPress-kärnan eller adminpanelen. Därför kommer `/wp/wp-admin/` inte med i namnet.
- Bokstäver blir gemener. Punkter, snedstreck och andra tecken utanför `a–z` och `0–9` i adressdelen ersätts med bindestreck. En eventuell port tas också med.
- `b3` betyder WordPress webbplats-ID 3. Test 2 har ID 3 trots sitt namn.
- Miljön hämtas från `WP_ENVIRONMENT_TYPE`, exempelvis `development`, `staging` eller `production`. Om miljön inte anges använder WordPress `production`.
- Namnet är högst 128 tecken. En lång adressdel kortas, men miljö och webbplats-ID behålls.
- Det finns inget fält för ett eget collection-namn i nätverkspanelen. Vanlig lokal aktivering behåller sitt befintliga sätt att ange collection.

Varje söknyckel får bara söka i sin egen collection. Funktionen ger alltså separata sökindex, inte gemensam sökning över hela nätverket.

## Så aktiverar du en webbplats

1. Nätverksaktivera tillägget och spara den gemensamma anslutningen.
2. Kontrollera anslutningen under **Status**.
3. Välj webbplatsen under **Webbplatser** och spara valet.
4. Klicka på **Förbered / försök igen**.
5. Indexera webbplatsen med WP-CLI i rätt webbplatskontext.
6. Granska dokument och sökresultat i kandidatens collection, exempelvis i Typesense Dashboard.
7. Markera att innehållet har granskats och klicka på **Aktivera**.
8. Kontrollera status och prova sökningen på webbplatsen.

Exempel för Test 2, körda från installationsroten `/Users/michaelclaesson/Sites/pitea.se`:

```sh
# Motsvarar knappen Förbered / försök igen:
wp --path=wp --url=https://pitea.local/test2/ typesense network prepare

# Indexera kandidaten:
wp --path=wp --url=https://pitea.local/test2/ typesense network index --yes --batch-size=100

# Alternativ till aktivering i panelen, efter granskning:
wp --path=wp --url=https://pitea.local/test2/ typesense network activate --yes
```

Lägg vid behov till `--include-pdf` och/eller `--include-external` på indexeringskommandot. Det finns ingen knapp som indexerar hela nätverket i ett steg; varje webbplats hanteras separat.

Vid den första övergången till nätverksläge används vanlig WordPress-sökning tills respektive webbplats är klar och aktiverad. Planera därför övergången även om webbplatsen redan använder Typesense lokalt.

## Avstängning, återaktivering och ändringar

| Åtgärd | Vad händer? |
| --- | --- |
| Avmarkera en webbplats och spara | Typesense-sökning, snabbsökning och tilläggets indexering stoppas där. Vanlig WordPress-sökning används. |
| Aktivera valet igen | En fortfarande giltig aktiv konfiguration återanvänds. Indexera för att ta igen innehållsändringar från tiden då webbplatsen var avstängd. |
| Ändra Typesense-server, webbplatsadress eller miljö | Den gamla konfigurationen används inte i den nya kontexten. Förbered, indexera, granska och aktivera för den nya kontexten. |
| Förberedelsen misslyckas | Rätta orsaken och använd Förbered / försök igen. Befintliga collections raderas inte. |
| Avsluta nätverksaktiveringen och aktivera tillägget lokalt | De bevarade lokala inställningarna används igen. Nätverksinställningarna kopieras inte automatiskt till lokala fält. |

Avstängning raderar inga dokument eller collections och återkallar inte redan utfärdade söknycklar. Rensning av gamla index görs separat. Tillägget tar inte heller automatiskt över en befintlig collection som det inte kan verifiera tillhör webbplatsens förberedelse.

## Om inställningar anges i konfigurationsfiler

I nätverksläge gäller följande konstanter före sparade nätverksinställningar:

- `TYPESENSE_HOST`
- `TYPESENSE_ADMIN_KEY`
- `TYPESENSE_FRONTEND_HOST`

Globala `TYPESENSE_COLLECTION` och `TYPESENSE_SEARCH_KEY` måste tas bort för nätverksläget, eftersom varje webbplats behöver egna värden. Panelen visar en konflikt om de finns. Vid vanlig lokal aktivering behåller alla dessa konstanter sitt tidigare beteende.

Kontrollera alltid miljö och adresser när en produktionsdatabas kopieras till utveckling. En kopia med exakt samma adress, miljö och server kan inte automatiskt skiljas från originalet.

## Att kontrollera hemma

Efter våra tester den 7 september är de tidigare lokala inställningarna återställda. **Nätverksläget är inte lämnat aktiverat**, och den tillfälliga testservern är borttagen. Välj en tillgänglig Typesense-server när du vill prova nätverksläget igen.

Bra adresser:

- [Huvudwebbplatsens admin](https://pitea.local/wp/wp-admin/)
- [Test 2:s admin](https://pitea.local/test2/wp-admin/)
- [Nätverkets webbplatser](https://pitea.local/wp/wp-admin/network/sites.php)
- [Typesense nätverksinställningar](https://pitea.local/wp/wp-admin/network/settings.php?page=typesense-network) – kräver nätverksaktivering.

Checklista efter att du följt aktiveringsstegen ovan:

- [ ] Nätverkspanelen visar den avsedda servern och miljön.
- [ ] Huvudwebbplatsen och Test 2 har olika collection-namn.
- [ ] Respektive webbplats visar sina egna sökresultat.
- [ ] Lokala innehålls- och sökinställningar går fortfarande att ändra separat.
- [ ] De lokala anslutningsflikarna hänvisar till nätverksadministrationen.
- [ ] En avstängd webbplats använder vanlig WordPress-sökning och saknar Typesense-snabbsökning.
- [ ] Återaktivering och indexering fungerar utan att en ny collection skapas i onödan.

Mer tekniska testresultat och avgränsningar finns i [testprotokollet](multisite-verification.md).
