# Multisite-refactor: förenklat flöde och åtgärdslista

Feedback från användaren 7 september 2026. Listan är sparad för gemensam genomgång; inga punkter nedan är genomförda genom denna dokumentation.

## Obligatorisk dokumentstädning inför merge till dev

Användarens instruktion: när denna branch mergas till `dev` ska alla tillfälliga dokument som skapats under arbetet tas bort, tillsammans med samtliga hänvisningar till dem. Behåll dem under pågående arbete för kontinuitet mellan sessioner; städningen ska ingå i mergeförberedelsen.

- [ ] Gå igenom branchens diff mot `dev` och identifiera samtliga tillfälliga dokument. Listan nedan är inte uttömmande.
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

- [ ] Implementera det förenklade flödet ovan och ta bort överflödiga manuella steg från standardgränssnittet.
- [ ] Anpassa villkoren för att använda Typesense till single site: fungerande uppsättning, svarande server och existerande index. Indexeringens resultat ska rapporteras separat och inte vara ett aktiveringsvillkor.
- [ ] Anpassa CLI och dokumentation till samma flöde. Hantera befintliga `network`-kommandon och sparat tillstånd uttryckligen så att befintliga installationer fortsatt fungerar.

Denna riktning ersätter tidigare förslag nedan om att enbart döpa om förberedelseknappen eller granskningssteget. Avsnittet ”Nuvarande beteende” beskriver implementationen före refaktorn, inte målbilden. Fortsätt från denna lista i nya sessioner och markera endast verifierat genomförda punkter som klara.

## Arbetsindelning

Införd 8 september 2026. Arbetet grupperas efter beteende eftersom flera punkter i checklistorna överlappar. Detta är en plan, inte genomförda ändringar. Kodkartläggning återstår inför implementationen.

1. **Kartlägg och förenkla tillstånden utifrån single site.** Beskriv kedjan vald → automatisk uppsättning → aktiv, samt uppsättningsfel och omförsök. Kontrollera hur befintliga villkor för serverkontakt och existerande index används. Indexeringsstatus hålls separat; någon definition av lyckad första indexering behövs inte för aktivering. Se de överenskomna reglerna nedan.
2. **Bygg det nya flödet i backend och CLI tillsammans.** Spara webbplatsval ska ordna index och söknyckel vid behov. Typesense ska användas så snart uppsättningen är klar och server och index är tillgängliga. Vanliga `typesense index` ska fungera både första gången och vid omindexering, utan separat aktiveringssteg. WordPress-sökning används tills uppsättningen fungerar. Ta med interna konfliktkontroller, säkra omförsök och uttrycklig hantering av befintligt sparat tillstånd och gamla `network`-kommandon. Dela implementationen i mindre ändringar men verifiera hela kedjan tillsammans.
3. **Anpassa nätverkspanelen till det färdiga flödet.** Ta bort kandidat, separat förberedelse och manuell aktivering ur standardgränssnittet. Dela upp Status och Index, visa väntande webbplatser tydligt och erbjud relevanta åtgärder som ”Försök igen”. Fixa flikarnas omdirigeringar här. Undvik separat putsning av granskningsrutan som ska försvinna.
4. **Samla felmeddelanden, översättningar och verifiering.** Gör beskeden specifika och översättningsbara och utred varför befintlig svensk översättning inte används. Verifiera ny uppsättning, befintlig aktiv installation, avbruten eller delvis misslyckad indexering, omförsök, avstängning och ändrad server/miljö. Kontrollera även single site.
5. **Uppdatera permanent dokumentation och förbered merge.** Dokumentera det gemensamma flödet och CLI-kompatibiliteten. Flytta relevant driftinformation till permanent dokumentation och ta sedan bort tillfälliga dokument och samtliga hänvisningar enligt städningsinstruktionerna ovan.

Ett kommando för att indexera alla webbplatser hanteras som en separat efterföljande uppgift. Omfattning och felrapportering behöver definieras, men kommandot är inget krav för det förenklade grundflödet.

Nästa arbetspass föreslås omfatta etapp 1 och kodkartläggning inför etapp 2.

### Överenskomna regler för uppsättning och indexering

Godkända av användaren 8 september 2026. Reglerna ersätter förslaget att kräva en felfri första indexering före aktivering. Tekniska tillstånd och lagringsdetaljer konkretiseras vid kodkartläggningen.

- **När Typesense används:** Webbplatsen är vald, automatisk uppsättning av index och webbplatsspecifik söknyckel är klar, servern svarar och indexet finns. Behåll rimliga interna kontroller för rätt webbplats, nyckel och miljö.
- **Samma grundprincip som single site:** `Templates::addViewPaths()` använder `ClientFactory::isReadyWithCollection()`, som kontrollerar serverhälsa och att indexet finns, men inte dokumentantal eller en tidigare lyckad indexering. Detta kontrollerades i koden 8 september 2026; hela nätverksflödet återstår att anpassa och verifiera.
- **Tomt eller delvis fyllt index:** Får användas direkt. Användaren accepterar att sökresultaten kan vara tomma eller ofullständiga under uppfyllnaden. Dokumentantal och indexeringsstatus är information, inte aktiveringsvillkor.
- **Indexeringsfel eller avbrott:** Rapportera begriplig orsak och möjlighet till omförsök. Ett indexeringsfel ska i sig inte avaktivera Typesense eller växla till WordPress-sökning. Serverns och indexets tillgänglighet bedöms fortfarande enligt samma grundprincip som single site.
- **Omförsök vid uppsättningsfel:** Ska kunna göras utan onödiga nya index eller nycklar och utan dubbla samtidiga uppsättningar för samma webbplats.
- **Första indexering och omindexering:** Körs separat från uppsättningen med vanliga `typesense index`, exempelvis nattetid. Inget manuellt gransknings- eller aktiveringssteg krävs efter körningen.
- **Status i gränssnittet:** Skilj vald/uppsatt webbplats och serverstatus från indexeringsstatus. ”Inväntar indexering” får vid behov vara information om innehållet, men ska inte innebära att Typesense väntar på aktivering.

Inga implementationsändringar är beställda genom denna dokumentuppdatering.

## Bekräftade buggar

- [ ] Behåll fliken Webbplatser efter ”Spara val av webbplatser”. Sparandet saknar i dag flik i omdirigeringen.
- [ ] Behåll fliken Status efter kontroll av gemensam anslutning eller webbplatsstatus. `finish()` skickar i dag alltid till Webbplatser.
- [ ] Dölj granskningsrutan och aktiveringsknappen när den förberedda konfigurationen redan är aktiv. Visa inte samma index som en väntande kandidat efter aktivering.
- [ ] Gör egna felmeddelanden översättningsbara, bland annat ”This site is disabled, unprepared, or its URL/environment/server has changed.”
- [ ] Utred varför ”Server connection, admin key and site search key are working.” visas på engelska trots svensk översättning i PO-filen. Kontrollera laddning, språk i webbplatsens admin-post-kontext och kompilerad MO-fil.

## Gränssnitt och begriplighet

- [ ] Dela upp status och index i separata kolumner. Förslag: Vald | Webbplats | Status | Index | Åtgärder.
- [ ] Visa kompakt statussymbol med tillgänglig förklaring. Förslag: grön = aktiv, grå = avstängd, gul = behöver färdigställas, röd = fel. Skilj konfigurationsstatus från senast kontrollerad serverstatus.
- [ ] Visa `–` när inget index finns. Skilj planerat/sparat namn från verifierat existerande index; bevarade index på avstängda webbplatser kan fortfarande visas.
- [ ] Ta bort ”kandidat” från standardflödet. Om begreppet behövs i avancerad migrationshjälp, förklara att det inte nödvändigtvis är ett separat testindex.
- [ ] Ta bort den separata förberedelseknappen från standardflödet. Visa ”Försök igen” vid ett faktiskt fel i den automatiska uppsättningen.
- [ ] Renodla Åtgärder och visa bara relevanta kontroller för webbplatsens tillstånd. Överväg utfällbar indexeringshjälp med CLI-kommandot.
- [ ] Förklara att val av webbplats startar automatisk uppsättning. Visa vald webbplats med ofärdig uppsättning som väntande eller med uppsättningsfel. En färdig uppsättning får användas även om indexet ännu är tomt.
- [ ] Ta bort obligatorisk manuell granskningsruta och separat aktivering från standardflödet enligt överenskommen riktning. Nuvarande ruta verifierar inte att indexering eller innehållsgranskning faktiskt har gjorts.
- [ ] Ge statuskontrollen specifika svenska besked för avstängd webbplats, ofärdig eller misslyckad uppsättning, otillgänglig server, saknat index och ändrad adress/miljö/server. Kräv inte separat aktivering i standardflödet.

## CLI: frågor och möjliga förbättringar

- [ ] Dokumentera att uppsättning sker automatiskt när webbplatsval sparas och att vanliga `typesense index` används både första gången och löpande. Förklara hur äldre `network`-kommandon hanteras och att indexeringsvägarna använder samma `IndexAction`.
- [ ] Diskutera ett gemensamt kommando för alla aktiva webbplatser. Det finns inget sådant inbyggt i dag. Definiera om omfattningen ska vara faktiskt aktiva webbplatser eller även valda men ännu inte aktiverade webbplatser, samt felhantering och rapportering per webbplats.

### Nuvarande beteende (kontrollerat i koden)

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
