# Multisite-refactor: förenklat flöde och åtgärdslista

Feedback från användaren 7 september 2026. Huvudchecklistan är uppdaterad efter integrationstester 9 september 2026; äldre sessionsanteckningar är historik. Markerade kodpunkter innebär inte att alla miljövarianter är slutverifierade. Se senaste testresultat och kvarvarande kontroller sist i dokumentet.

## Obligatorisk dokumentstädning inför merge till dev

Användarens instruktion: när denna branch mergas till `dev` ska alla tillfälliga dokument som skapats under arbetet tas bort, tillsammans med samtliga hänvisningar till dem. Behåll dem under pågående arbete för kontinuitet mellan sessioner; städningen ska ingå i mergeförberedelsen.

- [x] Inventera branchens dokumentdiff mot `dev`: de fem tillagda multisite-dokumenten nedan är tillfälliga. Hänvisningar finns även i README, `docs/feature-roadmap.md` och `docs/refactor-roadmap.md`. Gör om slutlig sökning vid merge.
- [ ] För över eventuell fortsatt relevant användar- och driftdokumentation till permanent dokumentation innan tillfälliga filer raderas.
- [ ] Ta bort `docs/multisite-guide-sv.md`.
- [ ] Ta bort `docs/multisite-refactor.md` (detta dokument).
- [ ] Ta bort `docs/multisite-verification.md`.
- [ ] Ta bort `docs/multisite-network-mode-plan.md`.
- [ ] Ta även bort övriga tillfälliga arbets-/granskningsdokument från branchen, inklusive `docs/multisite-code-review-2026-09-07.md`.
- [ ] Ta bort alla hänvisningar till de raderade dokumenten, inklusive länkar och arbetsinstruktioner i README, refaktorplanen och övriga filer. Ta samtidigt bort denna tillfälliga städningsinstruktion där den återges.
- [ ] Sök efter de borttagna filnamnen i återstående versionshanterade filer och kontrollera att inga hänvisningar finns kvar före merge.

## Överenskommen riktning för nästa session

Användaren har godkänt att förenkla standardflödet. Ett separat förberedelsesteg, begreppet ”kandidat” och obligatorisk manuell granskning/aktivering ska inte behövas vid vanlig uppsättning. Den tekniska uppsättningen ska hanteras internt och upplevelsen närma sig single site.

Önskat flöde:

1. Välj webbplatser och spara. Skapa index och webbplatsspecifik söknyckel automatiskt vid behov.
2. Börja använda Typesense när den automatiska uppsättningen är klar, servern svarar och webbplatsens index finns. En lyckad första indexering är inget villkor för aktivering. Fram till fungerande uppsättning används WordPress-sökning. Vid uppsättningsfel visas orsak och möjlighet att försöka igen.
3. Indexera separat med ett tydligt flöde som i single site. Vanliga `typesense index` ska kunna användas både första gången och vid löpande indexering, exempelvis nattetid.

Förtydligande godkänt av användaren 8 september 2026: ett tomt eller delvis fyllt index får användas och kan tillfälligt ge tomma eller ofullständiga sökresultat. Detta ersätter den tidigare riktningen att invänta en lyckad första indexering före aktivering.

Användaren bedömer risken för indexnamnskrock som liten och påpekar att indexnamnet väljs manuellt i den vanliga uppsättningen. En hypotetisk kollision ska därför inte styra standardgränssnittet mot en omfattande manuell process. Detta är ett designmotiv, inte ett påstående om att kollisioner är omöjliga. Behåll rimliga interna kontroller för webbplats, index, nyckel och miljö; hantera en faktisk konflikt med ett begripligt fel.

Separat förberedelse och manuell aktivering kan övervägas för migrering/serverbyte om ett konkret behov finns. Det ska inte vara standardkrav för varje webbplats.

- [x] Implementera det förenklade flödet ovan och ta bort överflödiga manuella steg från standardgränssnittet.
- [x] Anpassa villkoren för att använda Typesense till single site: fungerande uppsättning, svarande server och existerande index. Indexeringens resultat ska rapporteras separat och inte vara ett aktiveringsvillkor.
- [x] Anpassa CLI och dokumentation till samma flöde. Hantera befintliga `network`-kommandon och sparat tillstånd uttryckligen så att befintliga installationer fortsatt fungerar.

Denna riktning ersätter tidigare förslag nedan om att enbart döpa om förberedelseknappen eller granskningssteget. Avsnittet ”Historiskt beteende före refaktorn” beskriver implementationen före refaktorn, inte målbilden. Fortsätt från denna lista i nya sessioner och markera endast verifierat genomförda punkter som klara.

## Arbetsindelning

Införd 8 september 2026. Arbetet grupperas efter beteende eftersom flera punkter i checklistorna överlappar. Indelningen nedan beskriver arbetets etapper; aktuell status följer efter listan.

1. **Kartlägg och förenkla tillstånden utifrån single site.** Beskriv kedjan vald → automatisk uppsättning → aktiv, samt uppsättningsfel och omförsök. Kontrollera hur befintliga villkor för serverkontakt och existerande index används. Indexeringsstatus hålls separat; någon definition av lyckad första indexering behövs inte för aktivering. Se de överenskomna reglerna nedan.
2. **Bygg det nya flödet i backend och CLI tillsammans.** Spara webbplatsval ska ordna index och söknyckel vid behov. Typesense ska användas så snart uppsättningen är klar och server och index är tillgängliga. Vanliga `typesense index` ska fungera både första gången och vid omindexering, utan separat aktiveringssteg. WordPress-sökning används tills uppsättningen fungerar. Ta med interna konfliktkontroller, säkra omförsök och uttrycklig hantering av befintligt sparat tillstånd och gamla `network`-kommandon. Dela implementationen i mindre ändringar men verifiera hela kedjan tillsammans.
3. **Anpassa nätverkspanelen till det färdiga flödet.** Ta bort kandidat, separat förberedelse och manuell aktivering ur standardgränssnittet. Dela upp Status och Index, visa väntande webbplatser tydligt och erbjud relevanta åtgärder som ”Försök igen”. Fixa flikarnas omdirigeringar här. Undvik separat putsning av granskningsrutan som ska försvinna.
4. **Samla felmeddelanden, översättningar och verifiering.** Gör beskeden specifika och översättningsbara och utred varför befintlig svensk översättning inte används. Verifiera ny uppsättning, befintlig aktiv installation, avbruten eller delvis misslyckad indexering, omförsök, avstängning och ändrad server/miljö. Kontrollera även single site.
5. **Uppdatera permanent dokumentation och förbered merge.** Dokumentera det gemensamma flödet och CLI-kompatibiliteten. Flytta relevant driftinformation till permanent dokumentation och ta sedan bort tillfälliga dokument och samtliga hänvisningar enligt städningsinstruktionerna ovan.

Ett kommando för att indexera alla webbplatser hanteras som en separat efterföljande uppgift. Omfattning och felrapportering behöver definieras, men kommandot är inget krav för det förenklade grundflödet.

Kodstegen för etapp 1–3 är genomförda enligt sessionsanteckningarna nedan. Kodsteget för etapp 4 är också genomfört enligt den senaste sessionsanteckningen. Lokala integrationskontroller och språkdiagnos är nu genomförda enligt senaste anteckningen. Etapp 4 har kvar miljöberoende slutkontroller. README är uppdaterad i etapp 5; dokumentradering och merge väntar tills kvarvarande verifiering är hanterad.

### Överenskomna regler för uppsättning och indexering

Godkända av användaren 8 september 2026. Reglerna ersätter förslaget att kräva en felfri första indexering före aktivering. Tekniska tillstånd och lagringsdetaljer konkretiseras vid kodkartläggningen.

- **När Typesense används:** Webbplatsen är vald, automatisk uppsättning av index och webbplatsspecifik söknyckel är klar, servern svarar och indexet finns. Behåll rimliga interna kontroller för rätt webbplats, nyckel och miljö.
- **Samma grundprincip som single site:** `Templates::addViewPaths()` använder `ClientFactory::isReadyWithCollection()`, som kontrollerar serverhälsa och att indexet finns, men inte dokumentantal eller en tidigare lyckad indexering. Tomt, aktivt nätverksindex verifierades mot WordPress/Typesense 9 september 2026.
- **Tomt eller delvis fyllt index:** Får användas direkt. Användaren accepterar att sökresultaten kan vara tomma eller ofullständiga under uppfyllnaden. Dokumentantal och indexeringsstatus är information, inte aktiveringsvillkor.
- **Indexeringsfel eller avbrott:** Rapportera begriplig orsak och möjlighet till omförsök. Ett indexeringsfel ska i sig inte avaktivera Typesense eller växla till WordPress-sökning. Serverns och indexets tillgänglighet bedöms fortfarande enligt samma grundprincip som single site.
- **Omförsök vid uppsättningsfel:** Ska kunna göras utan onödiga nya index eller nycklar och utan dubbla samtidiga uppsättningar för samma webbplats.
- **Första indexering och omindexering:** Körs separat från uppsättningen med vanliga `typesense index`, exempelvis nattetid. Inget manuellt gransknings- eller aktiveringssteg krävs efter körningen.
- **Status i gränssnittet:** Skilj vald/uppsatt webbplats och serverstatus från indexeringsstatus. ”Inväntar indexering” får vid behov vara information om innehållet, men ska inte innebära att Typesense väntar på aktivering.

Inga implementationsändringar är beställda genom denna dokumentuppdatering.

## Bekräftade buggar

- [x] Behåll fliken Webbplatser efter sparande och uppsättning; verifierat genom webbläsarflödet.
- [x] Ersatt: Status-fliken är borttagen. Gemensam kontroll återgår till Anslutning och webbplatskontroll till Webbplatser; kod/enhetstester och webbplatsens HTTP-flöde verifierade.
- [x] Ersatt: granskningsruta och separat aktiveringsknapp är borttagna ur standardflödet.
- [x] Gör egna felmeddelanden översättningsbara, bland annat ”This site is disabled, unprepared, or its URL/environment/server has changed.”
- [x] Utred varför ”Server connection, admin key and site search key are working.” visas på engelska trots svensk översättning i PO-filen. Kontrollera laddning, språk i webbplatsens admin-post-kontext och kompilerad MO-fil.

## Gränssnitt och begriplighet

- [x] Dela upp Status och Index. Slutligt godkänt upplägg: Vald | Webbplats | Status | Index; ingen Åtgärder-kolumn.
- [x] Visa kompakt statussymbol med tillgänglig förklaring. Förslag: grön = aktiv, grå = avstängd, gul = behöver färdigställas, röd = fel. Skilj konfigurationsstatus från senast kontrollerad serverstatus.
- [x] Visa `–` när inget index finns. Skilj planerat/sparat namn från verifierat existerande index; bevarade index på avstängda webbplatser kan fortfarande visas.
- [x] Ta bort ”kandidat” från standardflödet. Om begreppet behövs i avancerad migrationshjälp, förklara att det inte nödvändigtvis är ett separat testindex.
- [x] Ta bort den separata förberedelseknappen från standardflödet. Visa ”Försök igen” vid ett faktiskt fel i den automatiska uppsättningen.
- [x] Ersatt: statuskontroll/omförsök ligger i Status, utfällbar CLI-hjälp och separat radering i Index.
- [x] Förklara att val av webbplats startar automatisk uppsättning. Visa vald webbplats med ofärdig uppsättning som väntande eller med uppsättningsfel. En färdig uppsättning får användas även om indexet ännu är tomt.
- [x] Ta bort obligatorisk manuell granskningsruta och separat aktivering från standardflödet enligt överenskommen riktning. Nuvarande ruta verifierar inte att indexering eller innehållsgranskning faktiskt har gjorts.
- [x] Ge statuskontrollen specifika svenska besked för avstängd webbplats, ofärdig eller misslyckad uppsättning, otillgänglig server, saknat index och ändrad adress/miljö/server. Kräv inte separat aktivering i standardflödet.

## CLI: frågor och möjliga förbättringar

- [x] Dokumentera att uppsättning sker automatiskt när webbplatsval sparas och att vanliga `typesense index` används både första gången och löpande. Förklara hur äldre `network`-kommandon hanteras och att indexeringsvägarna använder samma `IndexAction`.
- [ ] Separat efterföljande uppgift: diskutera ett gemensamt kommando för alla aktiva webbplatser. Det finns inget sådant inbyggt i dag. Definiera om omfattningen ska vara faktiskt aktiva webbplatser eller även valda men ännu inte aktiverade webbplatser, samt felhantering och rapportering per webbplats.

### Historiskt beteende före refaktorn (ersatt av README §10)

- `wp --url=https://pitea.local typesense index --yes` indexerar webbplatsens aktiva index. Det fungerar även i nätverksläge när webbplatsens nätverkskonfiguration är aktiv och giltig.
- `wp --url=https://pitea.local typesense network index --yes` indexerar webbplatsens förberedda konfiguration via `SiteProvisioner`, även före aktivering. Det kontrollerar förberedelse/ägarskap, låser förberedelseoperationen per webbplats och kör samma `IndexAction` med det förberedda indexet som mål.
- `network` betyder här hantering av nätverkskonfigurationen för en webbplats, inte att alla webbplatser indexeras. `--url` väljer webbplatskontexten.
- `network index` skapar inte indexet och aktiverar inte sökningen. Förberedelse görs med `network prepare` eller panelens knapp; aktivering är ett separat steg.
- Förberett och aktivt index kan vara samma index. Kommandona innebär därför inte automatiskt separata index.
- PDF:er och externa källor inkluderas med respektive CLI-flagga.

### Kod att utgå från

- `source/php/Admin/NetworkSettingsPage.php`
- `views/admin/network/settings.php`
- `source/php/Multisite/SiteProvisioner.php`
- `source/php/Multisite/NetworkSettingsRepository.php`
- `source/php/CLI/NetworkCommand.php`
- `source/php/CLI/Actions/IndexAction.php`
- `languages/typesense-search-sv_SE.po`


## Sessionsanteckning 8 september 2026: första kodsteget

Avgränsning: kodkartläggning och gemensam uppsättningsoperation. Standardflödet i panel och CLI är ännu inte ändrat; huvudchecklistorna ovan är därför fortsatt öppna.

- [x] Inför `SiteProvisioner::setup()` som förbereder och aktiverar under ett och samma webbplatslås, utan indexerings- eller granskningsvillkor.
- [x] Återanvänd befintliga interna tillstånd och kontroller. `prepare()` och `activate()` behåller sitt beteende tills anropare och kompatibilitetsbesked anpassas tillsammans.
- [x] Verifiera omförsök efter synkroniseringsfel, återanvändning av index/nyckel, upprepad uppsättning, låskonflikt och bibehållen aktivering vid avbruten indexering med enhetstester.

Verifiering: `vendor/bin/phpunit` passerar med 121 tester och 235 assertions. PHP-syntaxkontroll passerar. Ingen manuell verifiering mot WordPress/Typesense har gjorts i detta steg.

### Tillstånd och kodkarta för fortsättningen

Behåll lagringen `active`, `candidate`, `previous` internt i detta skede för att återanvända befintliga installationers identitet, ägarskapsbevis och nycklar. Begreppet kandidat behöver inte visas i standardgränssnittet. Någon datamigrering har inte införts.

- **Avstängd:** inte vald (eller arkiverad/spam/raderad). `NetworkSettingsRepository::canUse()` blockerar nätverksinställningarna men sparade index och lokal konfiguration bevaras.
- **Vald, uppsättning återstår:** saknar giltig `active` för aktuell webbplats/adress/miljö/server. WordPress-sökning används. Nästa steg måste starta `setup()` automatiskt och visa väntande/fel.
- **Uppsatt:** `setup()` har verifierat indexets ägarskap och söknyckel, synkroniserat synonymer/pinnade resultat och sparat `active`. Ingen innehållsindexering krävs.
- **Tillgänglig sökning:** `Templates::addViewPaths()` använder `ClientFactory::isReadyWithCollection()` för serverhälsa och indexexistens. `canUse()` anger konfigurationsbehörighet, inte serverstatus. Dokumentantal är inget villkor.
- **Uppsättningsfel:** operationen kastar fel och släpper sitt lås; sparat mellanläge kan återanvändas. Varaktig fel-/väntandestatus och automatiskt startat omförsök återstår. En tidigare giltig aktiv konfiguration bevaras om en ny uppsättning misslyckas.
- **Indexeringsfel:** hålls separat från uppsättning; ändrar inte `active`. Server/index kan fortfarande vara otillgängligt och blockeras då av tillgänglighetskontrollen.

`NetworkSettingsPage::save()` sparar i dag bara valda ID:n. `siteAction()` kör uppsättningsåtgärder via respektive webbplats `admin-post.php`. `ProvisioningGateway::create()` bygger schema från webbplatsens inställningar och hooks; `sync()` använder webbplatsens synonymer och pinnade resultat. Kör därför inte uppsättningen direkt i en loop med enbart `switch_to_blog()` i nätverksadmin: det laddar inte målwebbplatsens tema/tillägg. Nästa session behöver välja och testa en automatisk körväg i rätt webbplatskontext, med behörighetskontroll, väntande/felstatus och omförsök. Undvik beroende av att någon besöker varje webbplats.

`CLI/NetworkCommand.php` anropar fortfarande de äldre metoderna. Koppla dess kompatibilitetsvägar till det förenklade flödet i nästa steg och verifiera vanliga `typesense index` genom hela kedjan. `CLI/Actions/IndexAction.php` är fortsatt den gemensamma indexeringsmotorn. Bygg inte ett nytt indexeringsflöde.

Nästa session: fortsätt etapp 2 från denna anteckning, därefter panelen i etapp 3. Lägg särskild vikt vid verklig sajt-kontext, befintlig aktiv installation, sparad äldre förberedelse, partiellt fel för flera valda webbplatser och ändrad server/miljö. Hantera även lås efter hårt avbruten process; nuvarande lås stjäls aldrig automatiskt.


## Sessionsanteckning 8 september 2026: etapp 2, backend och CLI

Bygger vidare på första kodstegets befintliga ändringar; inga index, nycklar eller lokala inställningar har raderats. Panelens presentation och flikbuggar hör fortsatt till etapp 3.

- [x] Sparande av webbplatsval (och nätverksanslutning) startar uppsättning för valda webbplatser via `SetupDispatcher`. Varje mål får en egen fullständig WordPress-förfrågan till sin `admin-post.php`, utan beroende av besök eller WP-Cron. Ingen uppsättning körs med enbart `switch_to_blog()`.
- [x] Anropen använder tidsbegränsade slumpmässiga engångstoken, vars hash lagras per webbplats. Mottagaren kontrollerar målwebbplats, nätverk, aktuellt webbplatsval och att den beställande användaren fortfarande har `manage_network_options`. Inga inloggningscookies eller Typesense-nycklar skickas; omdirigeringar följs inte.
- [x] Separata optioner lagrar väntande/körande/klart/fel för uppsättning. Uteblivet svar eller hårt avbrott visas som fel via `SetupDispatcher::status()` efter fem minuter. SDK-feltexter sparas inte. Ett transportfel på en webbplats stoppar inte utskick till andra.
- [x] Omförsök görs genom att spara webbplatsvalet igen, via webbplatsåtgärden `setup`, eller med `wp --url=... typesense network setup`. Ett ännu giltigt väntande jobb skickas inte igen. Uppsättningen återanvänder första kodstegets index, nyckel och webbplatslås.
- [x] Vanliga `typesense index` går genom `IndexAction` och färdigställer vald men ofärdig nätverkskonfiguration före skrivande indexering, även när det saknas publicerat innehåll. En redan aktiv konfiguration behålls. `--dry-run` gör ingen uppsättning. Den gamla spärren i `IndexCommand::index()` är borttagen så att den gemensamma motorn kan nå uppsättningen.
- [x] `network prepare` och `network activate` är kompatibilitetsalias för `setup` med ett tydligt CLI-besked om att aktivering sker direkt. `network index` hänvisar till och kör samma `IndexAction` som vanliga `index`, med oförändrade flaggor. Gamla adminåtgärder `prepare`/`activate` går också via `setup`, utan granskningskrav.
- [x] Sparad äldre kandidat kan färdigställas utan datamigrering; giltig aktiv konfiguration och lokal konfiguration bevaras. Indexeringsresultat ändrar inte aktivering.

### Drift och kvarvarande verifiering

HTTP-anropen är asynkrona. Webbservern måste kunna nå respektive webbplats admin-URL och hantera ytterligare PHP-förfrågningar. TLS-valideringen är kvar. Om anrop blockeras av exempelvis DNS, TLS eller HTTP-autentisering blir uppsättningen ett omförsöksbart fel; CLI kan användas i rätt `--url`-kontext. Fem minuter är en gräns för att rapportera utebliven slutföring, inte en rätt att stjäla ett lås eller avbryta en process.

Lås efter hårt processavbrott återtas inte automatiskt. Kontrollera först att den gamla processen är avslutad, kör sedan `wp --url=... option delete typesense_network_provision_lock` och försök uppsättningen igen. Ett pågående arbete får aldrig låsas upp enbart på grund av ålder.

Verifiering: `vendor/bin/phpunit` passerar med **132 tester och 275 assertions**. Syntaxkontroll av ändrade PHP-källfiler och `git diff --check` passerar. Nya tester täcker transportfel per webbplats, utebliven förfrågan, behörighet/utgången token/fel webbplats/återspelning, låskonflikt, äldre kandidat, ändrad miljö, CLI-alias och vanlig CLI-indexering med väntande/aktiv konfiguration, tomt innehåll, dry-run och single site. Första kodstegets tester för återanvändning och indexeringsavbrott passerar också.

Försök till lokal WordPress-kontroll med `wp --skip-plugins --skip-themes core is-installed` gav **Error establishing a database connection**. Därför är verklig HTTP-körning med målwebbplatsens tema/tillägg och integration mot Typesense ännu inte verifierad. Gör den kontrollen när databasen är tillgänglig, inklusive flera valda webbplatser med partiellt fel och schema/synonymer/pinnade resultat från rätt webbplats.

Nästa session: etapp 3 ska använda `SetupDispatcher::status($siteId)` för uppsättningsstatus, erbjuda `setup` som ”Försök igen”, ta bort kandidat/granskning/manuell aktivering ur panelen och rätta flikarnas omdirigeringar. Därefter återstår den bredare integrationsverifieringen och översättningarna i etapp 4 samt permanent dokumentation och städning i etapp 5. Huvudchecklistorna lämnas öppna tills hela beteendet är verifierat.


## Sessionsanteckning 8 september 2026: etapp 3, nätverkspanelen

Bygger vidare på de befintliga ändringarna från första kodsteget och etapp 2. Intern lagring och CLI-kompatibilitet behålls.

- [x] Standardpanelen visar inte längre kandidat, förberedelse, granskningsruta eller manuell aktivering. Hjälptexterna beskriver automatisk uppsättning vid sparande och separat indexering med vanliga `typesense index --yes` i rätt `--url`-kontext.
- [x] Tabellen har kolumnerna Vald, Webbplats, Status, Index och Åtgärder. Konfigurationsstatus visas med statussymbol och läsbar text; färg är inte enda informationsbäraren.
- [x] `SetupDispatcher::status()` används för väntande/körande/fel. Faktiskt uppsättningsfel ger knappen `setup` (”Retry”, avsedd svensk översättning ”Försök igen” i etapp 4). Pågående uppsättning ger uppmaning att uppdatera sidan och ingen extra uppsättningsknapp. Avstängda webbplatser får inga operationsknappar. Ofärdiga webbplatser utan registrerat fel hänvisas till att spara valet.
- [x] En giltig aktiv konfiguration fortsätter visas som aktiv även om ett upprepat uppsättningsförsök misslyckas. Felet och omförsöket visas separat. Varken dokumentantal eller indexeringsresultat används som aktiveringsvillkor.
- [x] Statuskontroll erbjuds för färdig konfiguration och kontrollerar serverhälsa, administratörsnyckel och indexåtkomst med webbplatsens söknyckel. Senaste resultat och tid lagras separat i `typesense_network_status_check`. En kontroll beskriver historisk tillgänglighet, inte aktuell driftstatus. Ändrad anslutning/nyckel/mappning gör kontrollen inaktuell; en obehörig eller avstängd konfiguration visar den inte som aktuell.
- [x] Indexnamn skiljs från verifierad existens. Saknas sparat namn visas ”–”; annars markeras det som sparat namn, eller som verifierad indexåtkomst vid senaste lyckade kontroll. Inga Typesense-anrop eller byten av webbplatskontext sker vid rendering.
- [x] Sparande återgår till rätt flik. Gemensam anslutningskontroll återgår till Status. Webbplatsåtgärder bär med sig ursprungsfliken, begränsad till tillåtna flikar. Äldre statusformulär utan flik återgår till Status.
- [x] Avinstallation städar även uppsättningsjobb, uppsättningsstatus och den nya kontrollstatusen.

Verifiering: **144 tester och 316 assertions** passerar, inklusive 12 nya paneltester för tillstånd, HTML/escaping, indexeringskommando, borttagna manuella steg och omdirigeringar. PHP-syntaxkontroll och `git diff --check` passerar. Vites produktionsbygge passerar, kört via den tillgängliga Node-binären eftersom `npm` saknas i skalets PATH; inga versionshanterade byggfiler ändrades.

Förnyad lokal WordPress-kontroll (`wp --skip-plugins --skip-themes core is-installed`) ger fortfarande **Error establishing a database connection**. Visuell kontroll i riktig nätverksadmin samt HTTP- och Typesense-integration är därför inte verifierade. Huvudchecklistorna lämnas öppna tills den bredare verifieringen är genomförd.

Nästa session: etapp 4. Alla nya paneltexter använder översättningsfunktioner, men POT/PO/MO och utredningen av fel admins språk återstår. Samla även de äldre uppsättningsfelen och gör statusbeskeden specifika. Kontrollera panelens layout och verkliga åtgärder på både Webbplatser och Status när databasen fungerar, inklusive väntande jobb, omförsök, avstängning, ändrad miljö/server och indexeringsavbrott. Permanent dokumentation och dokumentstädning hör fortsatt till etapp 5.

## Sessionsanteckning 8 september 2026: etapp 4, felbesked och översättningar

Bygger vidare på första kodsteget och etapp 2–3 utan att återställa befintliga ändringar. Kod- och katalogarbetet nedan är verifierat; etapp 4 är inte slutverifierad mot körande WordPress/Typesense.

- [x] Egna uppsättningsfel använder översättningsfunktioner. Äldre instruktioner om förberedelse/granskning har ersatts där de visades som villkor för uppsättning.
- [x] `SetupException` skiljer uttryckligen författade fel från SDK-/transportfel. Okända undantag, även vanliga `RuntimeException`, visas inte med rå feltext eftersom den kan innehålla nycklar eller anslutningsuppgifter. Samma felklassificering används i panelen, bakgrundsuppsättningen och båda CLI-vägarna.
- [x] Felbeskeden skiljer saknat index, nekad API-nyckel, otillgänglig server och uppsättningsfel. Konfigurationskontrollen skiljer avstängd webbplats, konstantskonflikt, saknad anslutning, ändrad identitet och ofärdig uppsättning. Indexeringsresultatet är fortsatt inget aktiveringsvillkor.
- [x] Bakgrundsanrop utan inloggningscookie byter till den behörighetskontrollerade beställarens språk med `switch_to_user_locale()` och återställer språket i `finally`, även vid fel. Ingen användaridentitet eller behörighet byts.
- [x] Textdomänens sökväg härleds från pluginets basename i stället för ett hårdkodat katalognamn.
- [x] POT och svensk PO har uppdaterats med WP-CLI, nya nätverkstexter har översatts och MO har kompilerats med `msgfmt --check`. Kontroll mot POT visar att alla aktuella texter i nätverkspanelen och multisite-klasserna finns i svensk MO.

### Utredning av det engelska anslutningsbeskedet

Den ursprungliga MO-filen innehöll redan en korrekt svensk översättning av ”Server connection, admin key and site search key are working.”. Det specifika felet kan därför inte förklaras med att strängen saknas i pluginets kompilerade fil.

Lokala WordPress-källor (`wp/wp-admin/admin-post.php` och `wp/wp-includes/l10n.php`) visar att admin-post är adminkontext och att `determine_locale()` väljer användarens språk där. Bakgrundsanropen saknar däremot inloggningscookie och behöver det uttryckliga språkbytet ovan. Detta rättar bakgrundsflödet, men bevisar inte orsaken till det tidigare synkrona statusbeskedet. Sparade besked är fortfarande text på språket vid kontrollen/uppsättningen; de översätts inte retroaktivt när en annan administratör läser dem.

När databasen fungerar: kontrollera `determine_locale()`, användarens språk, språkfilter och faktiskt laddad textdomän i målwebbplatsens admin-post-kontext. Kontrollera även eventuell överordnad språkfil under `wp-content/languages/plugins`. Kör en ny statuskontroll efter språkändring så att ett gammalt sparat besked inte förväxlas med aktuell översättning.

### Verifiering och fortsättning

`vendor/bin/phpunit`: **153 tester, 341 assertions**, samtliga passerar. Nya tester täcker specifika och översättningsbara SDK-fel, maskering av okända fel, skilda konfigurationsorsaker samt språkbyte/återställning och tokenhantering vid lyckad och misslyckad bakgrundsuppsättning. Befintliga tester för single site, första indexering, tomt innehåll, aktiv/äldre konfiguration, omförsök, avstängning, ändrad miljö och indexeringsavbrott passerar också. PHP-syntaxkontroll, `git diff --check` och MO-validering passerar.

`wp --skip-plugins --skip-themes core is-installed` ger fortfarande **Error establishing a database connection**. Därför återstår verklig HTTP-/Typesense-integration och visuell kontroll i nätverksadmin, inklusive flera webbplatser med partiellt fel, rätt tema/schema/synonymer/pinnade resultat, serverbyte, indexeringsavbrott och single site. Ingen databas eller extern tjänst har ändrats för att kringgå detta.

Nästa arbetspass: slutför dessa integrationstester och språkdiagnosen innan etapp 4 markeras helt klar. Permanent dokumentation och dokumentstädning hör fortsatt till etapp 5.

### Uppföljning: databasanslutning och sandbox

Efter användarens uppmaning testades samma skrivskyddade kommando igen: `wp --skip-plugins --skip-themes core is-installed`. I sandboxen gav det anslutningsfel; med utökad exekveringsbehörighet lyckades det med exitkod 0. Databasen är alltså tillgänglig utanför sandboxen. Tidigare anslutningsfel ska inte tolkas som att databasen är nere. Fortsatt integrationsverifiering kan använda motsvarande behörighet; lyckad installationskontroll verifierar inte i sig pluginets HTTP-/Typesense-flöde.

### Uppföljning: förenklad panel och bekräftad orsak till engelskt statusbesked

Användaren godkände att ta bort den separata Status-fliken, flytta ”Kontrollera gemensam anslutning” till Anslutning och flytta ”Indexering med WP-CLI” från Åtgärder till webbplatsradens Status-kolumn. Detta är genomfört. Gemensam kontroll återgår till Anslutning, webbplatskontroll till Webbplatser. Gamla `tab=status`-länkar leder till Webbplatser; äldre formulär för gemensam kontroll fungerar fortfarande.

Diagnos mot körande WordPress utanför sandboxen: `determine_locale()` gav `sv_SE` och direkt översättning gav svensk text, men webbplats 3 hade det tidigare engelska lyckade statusbeskedet sparat i `typesense_network_status_check`. Lyckade kontroller översätts nu vid visning utifrån resultatet. Sparade engelska fel/notiser slås också upp i aktuell textdomän vid visning. Inga sparade kontroller eller inställningar har skrivits om.

Verifiering: panelen renderades med verkliga WordPress-data via WP-CLI. Webbplatsfliken innehåller det svenska statusbeskedet och inte det engelska; anslutningsfliken innehåller den gemensamma kontrollknappen; Status-fliken saknas. Enhetstester verifierar omdirigeringarna och att CLI-hjälpen ligger i statuscellen, inte åtgärdscellen. 153 tester och 344 assertions passerar. Detta är HTML-verifiering, inte visuell webbläsarverifiering eller full Typesense-integration.

### Uppföljning: automatisk uppsättning når inte fram i lokal HTTP-kontroll

Användaren observerade att indexet skapades först vid `typesense index`. Den gemensamma CLI-motorn kör avsiktligt `setup()` när nätverkskonfigurationen är ofärdig, vilket förklarar detta beteende. Enbart ändrad väntandetext löser inte ett uteblivet HTTP-anrop.

Diagnostiskt `wp_remote_post` utanför sandboxen, med ogiltig token (ingen uppsättning kan starta), till båda webbplatsernas riktiga admin-post-adresser gav `cURL error 60: SSL certificate OpenSSL verify result: unable to get local issuer certificate (20)`. PHP/WordPress via CLI litar alltså inte på den lokala HTTPS-kedjan. Kontrollera även webbserverns PHP-miljö innan detta fastställs som exakt orsak för panelens tidigare anrop. TLS-valideringen har inte stängts av och inga certifikat eller serverinställningar har ändrats. Nästa steg är att få den lokala certifikatkedjan betrodd av PHP och därefter verifiera att sparat webbplatsval verkligen skapar index utan CLI-indexering.

### Lokalt TLS-undantag och ytterligare hinder

Ett uttryckligt filter `typesense_search_allow_insecure_local_setup` kan nu sättas till true för att slå av certifikatkontrollen endast för uppsättningens HTTP-anrop, och endast när `wp_get_environment_type()` är `local` eller `development`. Standard är fortsatt kontroll; staging/produktion ignorerar undantaget. Aktivera från lokal konfiguration som körs efter att WordPress filter-API laddats, exempelvis ett lokalt MU-plugin: `add_filter('typesense_search_allow_insecure_local_setup', '__return_true');`. Filtret har bara aktiverats under diagnostiska CLI-anrop, inte permanent.

Test med ogiltig token passerade TLS men fick HTTP 302 till respektive webbplats startsida. Municipios `library/Admin/Roles/General.php` registrerar just en sådan admin_init-omdirigering för användare utan edit_posts, inklusive bakgrundsanrop utan cookie. Anropsvägens kompatibilitet med detta behöver lösas innan automatisk uppsättning är slutverifierad. Omdirigeringar följs fortfarande inte. Inga index raderades eller skapades under diagnostiken.

155 tester, 352 assertions passerar; nya tester kontrollerar att TLS-undantaget kräver aktivt val och inte fungerar i staging/produktion. Användaren har även föreslagit radering vid avaktivering; val mellan automatisk radering och separat raderingsåtgärd är fortfarande under klargörande. Tidigare bevarandebeteende är kvar tills detta är avgjort.

## Sessionsanteckning 9 september 2026: separat radering och fortsatt val av uppsättningsflöde

Återupptaget från de ofärdiga ändringarna föregående kväll. Användarens val är en separat åtgärd för radering, inte radering vid avmarkering.

- Panelen erbjuder ”Radera index och söknyckel” för avstängda webbplatser med sparat ägarskap och aktuell identitet. Formuläret visar indexnamnet, kräver bekräftelse och använder befintlig nonce och nätverksbehörighetskontroll i målwebbplatsens inloggade admin-post-anrop.
- Servern kontrollerar att webbplatsen är avstängd, aktuell anslutning/mappning motsvarar formulärets fingerprint och att det existerande indexets ägarskapsmarkör stämmer. Upp­sättningslåset används även vid radering. Ett gammalt eller främmande index raderas inte via denna åtgärd.
- Nycklar identifieras med sparat prefix, exakt indexavgränsning, search-only-behörighet och pluginets beskrivning. Prefixkollision eller ändrad omfattning blockerar radering innan några resurser ändras. Typesense returnerar endast prefix vid listning; detta kontrollerades mot den officiella API-dokumentationen (https://typesense.org/docs/30.2/api/api-keys.html).
- Nycklar raderas före indexet. Vid fel bevaras tillståndet för omförsök; redan borttagna resurser tolereras. Matchande active/candidate/previous-poster tas bort först efter lyckad fjärrradering. Poster för andra miljöer/servrar och lokala inställningar bevaras. Inga befintliga användarindex eller nycklar har raderats under utvecklingen.
- Svenska PO/MO och POT är uppdaterade. README beskriver separat radering. Hela äldre installationsbeskrivningen i README behöver fortfarande anpassas i etapp 5.

Verifiering: 163 tester, 382 assertions passerar, inklusive panelens synlighet/bekräftelse, aktiva webbplatser, främmande ägarskap, ändrad anslutning, nyckelidentifiering, låskonflikt och omförsök. Verklig destruktiv radering har inte körts mot användarens befintliga index.

Användaren ifrågasätter serverns självanrop och oinloggad admin-post. Nuvarande anrop har tokenbaserad autentisering och kontrollerar beställarens behörighet; ”nopriv” betyder inte att vem som helst ska kunna utföra operationen. Transporten har ändå bekräftade TLS-/Municipio-hinder. Rekommendationen är ett stegvis inloggat flöde i webbplatsernas egna kontexter efter sparandet. Fråga om ombyggnad kontra fortsatt tokenautentiserad bakgrundstransport är ställd; transporten är inte ombyggd ännu och Municipios skydd har inte kringgåtts.

## Sessionsanteckning 9 september 2026: inloggad uppsättning efter sparandet

Användaren godkände ett stegvis inloggat flöde eftersom uppsättningen normalt går snabbt.

- `SetupDispatcher` lagrar nu bara en tidsbegränsad följd av webbplats-ID:n per administratör. Sparandet startar en följd; nätverkspanelen skickar ett vanligt top-level POST-formulär till nästa sajts admin-post. Inga anrop görs med `wp_remote_post`, inga token-/nopriv-mottagare registreras och det lokala TLS-undantaget har tagits bort.
- Varje uppsättningsanrop går genom samma `siteAction()` som manuella administratörsåtgärder: inloggning/nätverksbehörighet, nonce, rätt webbplats/nätverk samt rätt körning och nästa steg kontrolleras. Tema/tillägg laddas i målwebbplatsens kontext. Inga ändringar i Municipios skydd behövs.
- Panelen visar framsteg och skickar nästa formulär automatiskt. Knappen ”Fortsätt uppsättningen” finns som reserv utan JavaScript. Varje färdigt steg återgår till rätt flik på nätverkets serverbestämda adminadress, även från en mappad domän. Fel sparas per sajt och normala uppsättningsfel stoppar inte nästa sajt. Misslyckad lagring av framsteg stoppar automatisk fortsättning.
- En ny sparning ersätter administratörens tidigare följd; gammal URL kan inte köra den nya. Äldre väntande tokenjobb kan inte köras och presenteras som avbrutna med uppmaning att spara igen. Återstående steg körs inte om sidan stängs. Mappade domäner kräver giltig administratörssession även på målwebbplatsen; inloggnings-/noncefel kan avbryta följden.
- README och översättningarna är uppdaterade för det nya flödet.

Verifiering: **161 tester, 365 assertions** passerar. Äldre transport-/tokentester ersattes med tester för administratörsspecifik följd, fel steg/gammal körning, ersatt körning, lyckat/misslyckat uppsättningssteg och fortsättning. Befintliga setup-, CLI- och raderingstester passerar.

Verklig webbläsarkontroll med den inloggade administratören: sparade befintligt val (enbart sajt 3/test2). Webbläsaren körde uppsättningen och återvände till Webbplatser med ”Typesense är nu aktivt för denna webbplats.” och aktiv status. Ingen CLI-indexering, certifikatbypass eller ändring av Municipio behövdes. Befintligt index återanvändes. Första skapande av ett nytt index via detta webbläsarflöde, flera verkliga sajter i följd samt mappade domäner återstår som bredare integrationskontroller; testerna täcker dessa kärnoperationer isolerat. Inga befintliga index har raderats under denna verifiering.

### Paneljustering: stabila kolumner och tydligare information

Efter användarens skärmbilder: Åtgärder-kolumnen är borttagen. Statuskontroll/omförsök ligger i Status; indexeringshjälp och separat radering ligger i Index. Tabellen använder fast layout, en 60 px bred valkolumn samt fördelning mellan webbplats/status/index som inte räknas om när details öppnas. Smala skärmar får horisontell rullning i tabellens behållare. Statuslampa och etikett hålls ihop med inline-flex och nowrap. Miljön visas som en separat märkning vid rubriken, och introduktionen är kortad till två stycken.

Verifiering: 161 tester/365 assertions passerar efter anpassning av befintliga HTML-kontroller. PHP-syntax, msgfmt och diff-kontroll passerar. Panelen granskades visuellt i verklig WordPress med två avstängda sajter utan sparade index. Inga webbplatsval ändrades för layoutkontrollen. Utfällda rader kontrollerades med HTML-testdata; visuell öppning av den lokala testfilen blockerades av webbläsarverktygets URL-policy och kringgicks inte.

## Nästa session: integrationstester

Överenskommet med användaren 9 september 2026: fortsätt integrationstesterna i en ny session. Codex utför testerna och dokumenterar resultaten. Användaren kan därefter göra en kort egen kontroll av gränssnitt och arbetsflöde.

- Börja med att läsa senaste sessionsanteckningarna och kontrollera befintliga arbetsändringar; återställ dem inte. Det aktuella flödet använder inloggade, stegvisa webbläsar-POST, inte serverns tidigare tokenbaserade självanrop.
- Verifiera nytt index via panelen, flera sajter i följd, partiella fel/omförsök, separat radering och återaktivering, avbruten indexering, ändrad server/miljö samt single site.
- Använd tillfälliga, tydligt identifierade testresurser för radering och feltester. Befintliga användarindex ska inte påverkas. Återställ eventuella tillfälliga testinställningar och städa endast resurser som skapats för testerna.
- Mappade domäner och miljöer som saknas lokalt ska redovisas som ej verifierade, med konkreta kontroller för användaren i tillgänglig miljö.
- Databasen fungerar utanför sandboxen. Den inloggade webbläsaren har tidigare kunnat köra befintlig test2-uppsättning genom panelen. Kontrollera aktuell sajt-/indexstatus innan testerna; användaren har ändrat dessa under arbetets gång.
- Senast verifierat: 161 enhetstester/365 assertions, syntaxkontroll, MO-validering och diff-kontroll passerade. Återuppsättning av befintlig test2 via webbläsaren lyckades. Panelens nya layout har granskats och godkänts av användaren.
- Efter integrationstesterna: uppdatera huvudchecklistan så att genomfört, ersatt och kvarvarande arbete framgår. Slutför därefter etapp 5 med permanent dokumentation och mergeförberedelser. Tillfälliga dokument tas bort först enligt instruktionen inför merge till dev, inte innan kontinuiteten till nästa session är säkrad.

Inga ytterligare integrationstester startades i denna avslutande session.

## Sessionsanteckning 9 september 2026: genomförda integrationstester

Befintliga arbetsändringar bevarades. Testerna använde körande WordPress 6.9.4 på `pitea.local`, miljön `development`, inloggad nätverksadministratör och den befintliga Typesense-anslutningen. Två tillfälliga testsajter skapades: ID 4 och 5, `/codex-integration-20260909-a/` respektive `-b/`, båda med Municipio. Deras index hade samma tydliga testprefix. Inga ändringar av PHP-certifikat, nätverksanslutning eller autentiseringsskydd behövdes.

### Resultat och verifieringsnivå

| Kontroll | Resultat | Utförande |
| --- | --- | --- |
| Nytt index utan CLI-indexering | Godkänd | Panelens sparande skapade sajt 5:s index och söknyckel. Sajt 4 skapades genom panelens omförsök. API/WordPress bekräftade 0 dokument, `canUse() = true` och `isReadyWithCollection() = true`. |
| Flera sajter och partiellt fel | Godkänd | Ett tomt testindex med avsiktligt främmande ägarskap blockerade sajt 4. Webbläsarföljden fortsatte automatiskt till sajt 5, som blev aktiv. Sajt 4 visade svenskt ägarskapsfel och Försök igen. |
| Omförsök | Godkänd | Efter borttagning av enbart konfliktfixturen skapade Försök igen sajt 4:s index. Upprepad `setup()` via WP-CLI behöll exakt samma mappning, ägarskap och nyckel. |
| Indexering | Godkänd | En nyskapad sajt utan valda innehållstyper gav förväntat CLI-fel. Efter att page aktiverats på testsajten indexerade vanliga `typesense index --post-type=page --yes` fyra sidor, 0 fel. README förtydligar detta förkrav. |
| Avbruten indexering | Godkänd för Ctrl-C | En verklig omindexering med `--sleep=10000` avbröts vid 25 %. Nästa WordPress-process bekräftade aktiv/tillgänglig sökning och inget kvarvarande uppsättningslås. SIGKILL under pågående uppsättning testades inte. |
| Söknyckelisolering | Godkänd | Sajt 4:s söknyckel nekades sökning i sajt 5:s index av Typesense. Inga nyckelvärden skrevs ut. |
| Avaktivering | Godkänd | Båda testsajterna avmarkerades och sparades i panelen. Fjärrkontroll bekräftade bevarade index, inklusive sajt 4:s fyra dokument. |
| Separat radering | Backendintegration godkänd | Pluginets verkliga `SiteProvisioner::delete()` kördes via WP-CLI på avstängd testsajt med korrekt fingerprint och ägarskap. Index gav därefter 404, den gamla söknyckeln gav 401, mappning och lås var borta. Panelens destruktiva POST kördes inte i denna session; formulär/behörighet täcks av befintliga enhetstester. |
| Återaktivering efter radering | Godkänd | Panelen återskapade sajt 4:s tomma index och slutförde också sajt 5. Därefter fyllde samma CLI-kommando åter indexet med fyra sidor, 0 fel. |
| Svenskt statusbesked | Godkänd via HTTP | Kontrollera status på testsajt 4 gick genom dess inloggade admin-post och återvände till Webbplatser med ”Serveranslutningen, adminnyckeln och webbplatsens söknyckel fungerar.” Testsajtens vanliga CLI-locale var en_US. |
| Ändrad server | Delvis verifierad | Lokal host-konstant har företräde framför databasinställningen. En injicerad repository med `127.0.0.1:1` nekade gammal identitet; verkligt anslutningsfel under setup bevarade aktiv mappning och släppte låset. Originalrepository var fortfarande användbar. Ingen full migrering mellan två servrar kördes. |
| Ändrad miljö | Delvis verifierad | Injicerad staging-identitet nekade development-mappningen i verklig WordPress. Ingen global miljökonstant ändrades och inget staging-index skapades. |
| Single site | Delvis verifierad | Processlokala WordPress-filter valde lokala inställningsvägen och ett fungerande testindex. Detta är inte en fristående single site-installation med `is_multisite() = false`. Befintliga enhetstester passerar. |

Server-/miljöinjektionerna fanns endast i testprocessen. Inga beständiga gemensamma anslutningsinställningar ändrades. Inga nya produktkodändringar behövdes för de genomförda testen.

### Återställning och slutkontroll

- Ursprungligt val var enbart sajt 3. Under testföljderna var bara testsajterna valda för att undvika uppsättning/synkronisering av användarindexet. Ursprungligt val återställdes utan ny uppsättning på sajt 3.
- Båda testindexen och deras söknycklar raderades genom pluginets raderingsoperation. Gamla testnycklar verifierades som obehöriga. Konfliktfixturen togs bort först efter kontroll av dess testmarkör och 0 dokument.
- Båda tillfälliga WordPress-sajterna och deras testinnehåll togs bort. Panelen visade därefter endast sajt 1 (avstängd) och sajt 3 (aktiv).
- Befintliga sajters sparade nätverkstillstånd hade identiska SHA-256-kontrollsummor före/efter. Indexuppsättningen och dokumentantalen var identiska: `pitea-local-test2__development_b3` 0, `pitea` 1314, `intranet_posts` 1792 och `alingsas-prod` 1539. Detta är inte en full jämförelse av varje dokument, men inga testoperationer riktades mot dessa index.
- Jämförelseunderlaget innehöll endast sajtval, kontrollsummor, indexnamn och dokumentantal. Ett tidigare försök att spara nyckeluppgifter stoppades av automatisk granskning och genomfördes inte. Användningsgränsen avbröt senare städningen; den slutfördes efter användarens fortsättningsinstruktion.
- Slutkontroller: **161 tester, 365 assertions**, PHP-syntax för 16 ändrade/nya PHP-filer, `msgfmt --check` och `git diff --check` passerar. WP-CLI:s egna deprecation-varningar förekommer i lokal PHP-miljö; de hindrade inte testerna.

### Kvarvarande verifiering före slutlig merge

- [ ] **Mappad domän:** välj två disponibla sajter på olika domäner, var inloggad på båda, spara och kontrollera återgång till nätverksadmin. Upprepa med utgången session på målservern: begäran ska stanna och inga resurser skapas utan giltig session/nonce. Ingen sådan domänmiljö användes här.
- [ ] **Fristående single site:** konfigurera ett disponibelt index i en installation där `is_multisite()` är false; verifiera uppsättning, innehållsval, indexering, sökning och serverbortfall. Lokala inställningsvägen ovan ersätter inte detta.
- [ ] **Fullt server-/miljöbyte:** i disponibel miljö, prova A → B → A samt development → staging; kontrollera nya index, nycklar, skydd av äldre resurser och återanvändning när originalidentiteten återställs.
- [ ] **Webbläsarens raderingsformulär:** på en disponibel sajt, avaktivera, öppna separat radering, verifiera bekräftelse och submit, och återaktivera. Backendoperationen är nu verkligt verifierad; denna sessions webbläsartest slutade före destruktiv submit.
- [ ] **Sajtspecifika anpassningar:** verifiera två olika tema-/schemafilter samt olika synonym- och pinnade regler genom webbläsarföljden. Båda testsajterna ovan använde Municipio utan sådana särskilda testregler.
- [ ] Användarens korta egenkontroll av panel och arbetsflöde.

Huvudchecklistan har markerats utifrån genomförd kod, enhetstester och dessa integrationstester. Ersatta UI-förslag anges uttryckligen. Etapp 4 är inte markerad helt klar eftersom kontrollerna ovan återstår. Etapp 5: README beskriver det aktuella flödet, inklusive innehållsval, partiella uppsättningsfel och indexeringsavbrott. Diffen mot dev och tillfälliga dokument/hänvisningar har inventerats; dokumenten bevaras för nästa session. Ingen merge eller dokumentradering har utförts.
