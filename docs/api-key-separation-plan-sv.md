# Implementationsplan: separera Typesense-nycklar

Status: plan, ingen implementation genomförd.

## Mål

Den nyckel som WordPress, cron och WP-CLI använder för vanlig indexering ska inte behöva administrera API-nycklar. Söknycklar ska skapas och raderas med en separat provisioneringsnyckel. Avsaknad av provisioneringsnyckel får aldrig leda till fallback till indexerings- eller bootstrap-nyckeln.

Rekommenderad driftmodell är att provisioneringsnyckeln injiceras endast i en separat CLI-körning. Permanent konfiguration stöds, men ger inte isolering vid ett intrång i WordPress/PHP-processen.

## Utgångsläge

- `Typesense/ClientFactory.php` bygger ordinarie klient från `getAdminKey()`.
- `Admin/Ajax/SearchKeyActions.php` använder adminnyckeln för att skapa och reparera söknycklar. Generering kan använda serveradress från formuläret och fel returnerar råa undantagsmeddelanden.
- `Multisite/ProvisioningGateway.php` använder samma nyckel för collections och för att skapa, lista och radera nycklar.
- `Multisite/SiteProvisioner.php` har redan låsning, ägarskapskontroll och sparat kandidatläge för återförsök.
- `wp --url=… typesense network setup` finns redan och bör återanvändas.
- Befintliga söknycklar identifieras vid radering genom listning, prefix, beskrivning och exakt scope.

## Säkerhetskrav

1. Indexeringsklienten anropar aldrig `keys`-endpoints.
2. Provisioneringsnyckeln läses endast från konstant eller miljövariabel `TYPESENSE_PROVISIONING_KEY`, aldrig från options eller HTTP-indata. Dokumentera prioritet: definierad konstant gäller före miljövariabel; tom konstant innebär otillgänglig nyckel.
3. Provisioneringsklienten använder en betrodd serveradress. En adress från formulär eller AJAX får inte styra vart nyckeln skickas. För permanent webbåtkomst bör destinationen vara serverkonfigurerad, exempelvis genom en separat `TYPESENSE_PROVISIONING_REMOTE`; verifiera att den motsvarar indexeringsdestinationen. Utan betrodd destination ska automatisk generering nekas.
4. Privata nycklar återges aldrig i HTML, frontend-konfiguration, REST/AJAX-svar eller loggar. Detta gäller även redan sparad indexeringsnyckel.
5. Söknycklar får endast `documents:search` och exakt avsedd collection. De ger tillgång till sökbart innehåll och är avsedda för publik data.
6. Collection-scope skyddar inte globala resurser. Provisioneringsnyckeln ska behandlas som särskilt privilegierad även med begränsad actions-lista.
7. Alla befintliga behörighets- och noncekontroller i administrationsflöden ska bevaras. I nätverksläge ska endast behörig nätverksadministration kunna initiera nyckelhantering.

## Steg 1: kartlägg endpoints och konfiguration

- Inventera samtliga klienter, HTTP-anrop, nyckeloperationer och privata nyckelvärdens väg till vyer, JavaScript och felhantering.
- Läs befintliga tester och nätverkets setup-/avprovisioneringsflöden innan tjänster ändras.
- Gör en endpoint–action-matris för indexering, schemaändring, statistik, serverkapabiliteter, synonymer och pinned results. Ta särskilt med `/debug`, dokumentimport och `PATCH /collections/{collection}`.
- Verifiera rättigheter mot en isolerad Typesense 30.2-instans med testnycklar. Dokumentationens action-tabell är inte ett tillräckligt integrationstest.
- Verifiera vilka rättigheter som krävs för att skapa en söknyckel med provisioneringsnyckeln; anta inte att collection-scope begränsar nyckeladministration eller skapade nycklars behörigheter.

Leverans: verifierad behörighetsmatris med tydligt markerade eventuella osäkerheter.

## Steg 2: inför separata klienter utan onödiga namnbyten

Berör främst `Services/SettingsRepository.php`, `Multisite/NetworkSettingsRepository.php`, `Typesense/ClientFactory.php`, `ConstantsLoader.php` och `Admin/Settings/OptionKeys.php`.

- Behåll befintliga adminnyckelkonstanter och optionnamn för bakåtkompatibilitet; kalla rollen indexeringsnyckel i gränssnitt och dokumentation.
- Inför en gemensam läsare för provisioneringskonfiguration och en separat klientbyggare som kräver både nyckel och betrodd destination.
- Använd tydliga metoder som `getProvisioningKey()`, `hasProvisioningKey()` och `buildProvisioningClient()`, anpassade till befintlig arkitektur.
- Lägg inte provisioneringsnyckeln i generella inställningsarrayer som kan sparas, renderas eller loggas.
- Låt befintlig ordinarie klientbyggare fortsätta använda indexeringsnyckeln.
- Returnera ett specifikt, säkert fel när provisioneringskonfiguration saknas. Ingen fallback.

## Steg 3: anpassa single site och administration

Berör främst `Typesense/ApiKey.php`, `Admin/Ajax/SearchKeyActions.php`, anslutningsvyn och tillhörande JavaScript.

- Flytta både generering och reparation av söknycklar till provisioneringsklienten.
- Behåll stöd för manuellt angiven publik söknyckel utan provisioneringskonfiguration.
- Dölj eller inaktivera generering när konfiguration saknas och förklara det manuella alternativet. Kontrollera samma krav på servern.
- Skicka aldrig provisioneringsnyckeln till en osparad eller godtyckligt angiven formuläradress. Ge ett begripligt fel vid destinationskonflikt.
- Ersätt råa undantagsmeddelanden med kontrollerade feltexter för saknad konfiguration, nekad autentisering/behörighet och misslyckad generering. Påstå inte specifikt att `keys:create` saknas om svaret inte bevisar detta.
- Visa aldrig sparad indexeringsnyckel i formulärfält. Tomt fält betyder behåll; en separat uttrycklig åtgärd tar bort nyckeln. Anpassa anslutningstest och sparlogik till detta.

## Steg 4: anpassa multisite och återförsök

Berör främst `Multisite/ProvisioningGateway.php`, `Multisite/SiteProvisioner.php`, `Multisite/SetupException.php` och nätverkets tillståndslagring.

- Collection-operationer fortsätter använda indexeringsklienten. Skapa, lista och radera nycklar använder enbart provisioneringsklienten.
- Bevara prefix, ägarskapskontroll, låsning och kandidat-/aktivt läge. Återförsök får inte skriva över en fungerande aktiv mappning med ofullständigt tillstånd.
- Om collectionen skapats men nyckelsteget misslyckas: behåll återupptagbart tillstånd och visa ett särskilt fel för detta.
- Spara nyckelns ID tillsammans med värde och collection för nya nycklar. Anpassa `ApiKey` med en metod som returnerar båda, utan att bryta eventuella befintliga stränganrop i onödan.
- Använd sparat ID vid radering, med verifiering av att metadata motsvarar tilläggets förväntade nyckel. Behåll försiktig legacy-matchning för gamla mappningar; gissa aldrig vid tvetydighet.
- Planera uttryckligen för avbrott efter att Typesense skapat nyckeln men före lokal lagring. Använd sparad operationsidentitet och metadata för avstämning; om värdet inte kan återvinnas, återkalla säkert identifierad övergiven nyckel före ersättning. Testa även osäkra nätverksutfall och samtidighet. Lova inte fullständig idempotens utan verifierad mekanism.
- Behåll tillstånd vid misslyckad avprovisionering så att den kan köras igen. Redan raderad resurs ska hanteras som uppnått resultat.

## Steg 5: återanvänd CLI-flödet

- Utöka `wp --url=… typesense network setup` i stället för att införa ett parallellt provisioneringskommando.
- Säkerställ att miljövariabler når den process som faktiskt gör nyckelanropen, även om setup använder dispatch eller underprocesser.
- CLI-körningen ska kunna slutföra en tidigare delvis genomförd setup. Vanlig nattlig indexering av redan provisionerade webbplatser ska fungera utan provisioneringsnyckel.
- Granska om indexeringskommandon implicit försöker provisionera. Oprovisionerade webbplatser ska få tydligt fel, utan fallback eller krav på provisioneringsnyckel för andra webbplatser.
- Rekommendera hemlighetsinjektion från driftmiljön. Exempel med fiktiva värden ska inte uppmuntra lagring av riktiga nycklar i shellhistorik eller versionshanterade filer.

## Steg 6: dokumentation och uppgradering

Uppdatera `README.md`, `docs/multisite-guide-sv.md`, single-site-vyn och nätverksinställningarna.

- Beskriv bootstrap-, provisionerings-, indexerings- och publik söknyckel samt respektive verifierade actions.
- Visa kompletta fiktiva exempel för single site och multisite, inklusive betrodd destination, prefix och konstant-/miljövariabelprioritet.
- Förklara regex-scope och att globala resurser som nycklar, synonym sets och curation sets inte isoleras av collection-prefix.
- Dokumentera separat CLI-provisionering och permanent PHP-konfiguration med dess begränsade isolering. PHP-konstant minskar vissa databas-/formulärexponeringar men skyddar inte mot godtycklig PHP-körning.
- Ge korta informationsrutor med länkar till säkerhetsavsnittet.
- Dokumentera rotation: skapa ersättare, konfigurera, verifiera användning, återkalla gammal nyckel. Ta hänsyn till cachad frontend vid rotation av söknycklar.
- Uppgradering ska bevara fungerande index och manuella söknycklar. Automatisk nyckelhantering kräver däremot den nya konfigurationen.
- Kräv ett uttryckligt driftsteg för att ersätta och återkalla befintlig bred adminnyckel. Kodändringen minskar inte gamla nycklars behörigheter automatiskt.

## Preliminär behörighetsmatris

| Roll | Utgångspunkt | Återstår att verifiera |
| --- | --- | --- |
| Indexering | `collections:create`, `collections:get`, `collections:delete`, `documents:search`, `documents:upsert`, `documents:delete`; scope till installationens collections | Import, PATCH, `/debug`, statistik och eventuella övriga endpoints |
| Provisionering | `keys:create`, `keys:list`, `keys:delete`; `keys:get` om metadata hämtas per ID | Faktiskt skapande och livscykel med dessa rättigheter på 30.2 |
| Publik sökning | Endast `documents:search`, exakt collection | Att frontend fungerar och ingen annan collection kan sökas direkt |
| Valfria synonymer/pinned results | Bland annat `synonym_sets:create`, `synonym_sets:get`, `curation_sets:upsert`, `curation_sets:get` | Faktiska använda endpoints, kopplande collection-PATCH och global åtkomst |

Använd inte `collections:edit` som antaget action-namn. Vid behov av `collections:*`, verifiera och förklara varför. Tilldela inte globala extrarättigheter till alla installationer när funktionen är valfri.

Referens: [Typesense 30.2 API Keys](https://typesense.org/docs/30.2/api/api-keys.html).

## Tester och acceptanskriterier

- Enhetstester för konfigurationsprioritet, saknad nyckel/destination och frånvaro av fallback.
- Klient-/gatewaytester som bevisar att collection- och nyckelanrop använder olika autentisering.
- AJAX-tester för behörighet, nonce, destinationsmanipulation, manuellt söknyckelflöde och sanerade fel.
- Kontroll att privata nycklar inte förekommer i renderad HTML, frontend-konfiguration, AJAX-/REST-svar eller loggning, inklusive felvägar.
- Multisite-tester för korrekt prefix, exakt collection-scope, endast `documents:search`, sparat nyckel-ID och försiktig hantering av äldre mappningar.
- Feltester för misslyckad nyckelgenerering efter skapad collection, avbruten lagring efter nyckelskapande, återförsök, samtidighet och delvis genomförd radering.
- CLI-tester för setup med injicerad provisioneringsnyckel samt vanlig indexering utan den.
- Regressionstester för uppgradering med befintliga inställningar och söknycklar.
- Integrationstest mot Typesense 30.2 för samtliga rättigheter i slutlig matris, inklusive att indexeringsnyckeln nekas nyckeladministration och åtkomst utanför collection-scope.

Kör relevanta befintliga tester och projektets ordinarie kontroller. Redovisa separat vad som verifierats med testdubblar och vad som verifierats mot riktig server. Om server saknas ska rättigheterna markeras som ej integrationsverifierade.

## Slutleverans

Implementation, tester, informationsrutor och dokumentation levereras tillsammans. Slutrapporten ska ange ändrade filer, designbeslut, kompatibilitet, verifierade actions, konfiguration för single site/multisite, testresultat och kvarvarande osäkerheter. Ingen produktionsrotation eller ändring på en riktig Typesense-server ingår automatiskt i implementationen.
