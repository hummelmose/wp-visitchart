# WP VisitChart – Brugermanual

**Version:** 1.9.2  
**Forfatter:** Jens E. Hummelmose  
**Copyright:** © 2026 Jens E. Hummelmose

---

## Indholdsfortegnelse

1. [Hvad er WP VisitChart?](#hvad-er-wp-visitchart)
2. [Installation og aktivering](#installation-og-aktivering)
3. [Dashboardet](#dashboardet)
4. [Indstillinger](#indstillinger)
5. [Mobilsiden](#mobilsiden)
6. [Admin-bjælken](#admin-bjælken)
7. [Sidevisninger i indlægsoversigten](#sidevisninger-i-indlægsoversigten)
8. [Sådan indsamles data](#sådan-indsamles-data)
9. [Hvad de forskellige tal betyder](#hvad-de-forskellige-tal-betyder)
10. [Kendte begrænsninger](#kendte-begrænsninger)

---

## Hvad er WP VisitChart?

WP VisitChart er et selvbygget WordPress-plugin til live-trafikovervågning, inspireret af professionelle analytics-værktøjer som Chartbeat. Det viser i realtid, hvem der er på sitet lige nu, hvad de læser, og hvordan dagens trafik udvikler sig – direkte i WordPress-admin, på en bookmarkbar mobilside og i selve admin-bjælken øverst på skærmen.

Pluginet kræver ingen tredjepartstjenester, ingen abonnementer og sender ikke data ud af dit site. Alt kører på din egen server.

---

## Installation og aktivering

1. Upload `wp-visitchart.zip` via **Plugins → Tilføj plugin → Upload plugin**
2. Aktiver pluginet
3. WordPress opretter automatisk de nødvendige databasetabeller
4. Gå til **WP VisitChart** i venstre admin-menu

Pluginet begynder at indsamle data med det samme efter aktivering. Der er ingen opsætningsguide – standardindstillingerne virker med det samme.

**Vigtigt ved opgradering fra WP VisiChart:** Da plugin-mappen er omdøbt, skal du deaktivere og slette det gamle plugin, inden du installerer WP VisitChart. Dine eksisterende data bevares, da databasetabellerne har samme navne.

---

## Dashboardet

Find dashboardet under **WP VisitChart** i venstre admin-menu.

### Live besøgende lige nu

Det store, blå tal øverst viser antallet af unikke besøgende, der har sendt et aktivt signal fra deres browser inden for de seneste 120 sekunder. Tallet opdaterer sig automatisk hvert 10. sekund og blinker kortvarigt, når det ændrer sig.

### Trafikkilder i dag

Viser fordelingen af dagens trafik i fire kategorier med antal og procentandel:

- **Direkte** – besøgende der kom via bogmærke, ved at skrive URL'en direkte, eller fra apps der ikke sender kildeoplysninger
- **Søgemaskiner** – trafik fra Google, Bing, DuckDuckGo m.fl.
- **Sociale medier** – trafik fra Facebook, Instagram, X/Twitter, LinkedIn, Reddit, TikTok m.fl.
- **Andre hjemmesider** – trafik via links på andre sites

### Enheder i dag

Opdeling af dagens besøgende på enhedstype – mobil, tablet og desktop – med antal og procentandel. Enhedstypen bestemmes ud fra skærmbredden i besøgendes browser.

### Gns. tid på sitet

Den gennemsnitlige aktive tid, besøgende bruger på sitet i dag. Beregnes ud fra heartbeat-signaler og tæller kun aktive perioder – pauser, hvor fanen lå i baggrunden, tælles ikke med.

### Grafen – Besøgende i dag

Linjegrafen viser dagens trafik time for time i 5-minutters-intervaller fra 00:00 til 23:55:

- **Blå linje** – unikke besøgende i dag
- **Rød stiplet linje** – sidevisninger i dag (kan overstige besøgende, da én person kan læse flere artikler)
- **Grå linjer** – samme to tal fra **samme ugedag sidste uge** som sammenligningsgrundlag

Fører du musen over grafen, vises en lodret streg og en boks med alle fire tal for det pågældende 5-minutters-interval, f.eks. "08:40 – 08:45".

### Mest aktive sider lige nu

De ti sider med flest aktive besøgende i det aktuelle 120-sekunders-vindue. Klik på en artikel-titel for at åbne den. Sider, der stiger hurtigt i besøgstal, markeres automatisk med et 🔥-ikon (trending).

**Trending:** En side markeres som trending, hvis den har mindst 3 aktive besøgende lige nu og besøgstallet er steget mindst 50 % i forhold til det foregående tidsvindue.

### Mest besøgte sider i dag

De femten sider med flest unikke besøgende i løbet af hele dagen – ikke kun i det seneste vindue.

### Bots registreret

Antal bots og crawlere registreret i dag, samt en liste over de senest aktive bots med navn og antal sessioner. Bots filtreres fra i alle de øvrige tal, så de ikke forurener statistikken.

### Mest henvisende domæner

Hvilke konkrete domæner (f.eks. google.com, facebook.com, version2.dk) der har sendt flest besøgende i dag. Se afsnit om [trafikkilde-detection](#trafikkilde-detection) nedenfor for en forklaring af, hvordan dette tal beregnes.

---

## Indstillinger

Find indstillingerne under **WP VisitChart → Indstillinger**.

### Admin-bjælke

Slår live-tælleren til eller fra i WordPress' sorte admin-bjælke øverst på skærmen.

- **Slået til:** Et lille tal viser live-besøgende øverst, synligt på alle sider i wp-admin og på selve sitet, når du er logget ind som administrator. Klik på tallet for at gå direkte til dashboardet.
- **Slået fra:** Tælleren fjernes helt fra admin-bjælken.

### Mobilside

Slår den login-frie mobilside til eller fra.

- **Slået til:** Mobilsiden er tilgængelig via det hemmelige link under "Mobilside – adgang" nedenfor på siden.
- **Slået fra:** Mobilsiden viser en fejlmeddelelse, selv med det korrekte link.

### Sidevisninger i indlægsoversigten

Slår en ekstra kolonne til i WordPress' Posts- og Pages-oversigt.

- **Slået til:** Kolonnen "Sidevisninger" vises med dagens og samlede sidevisninger for hvert indlæg. Kolonnen kan sorteres.
- **Slået fra:** Kolonnen skjules. Eksisterende data i databasen bevares.

Sidevisninger tælles i realtid via et separat JavaScript-ping, der fyrer præcis én gang per session per artikel – uanset om besøget er kort eller langt.

### Mobilside – adgang

Her finder du linket til mobilsiden med den hemmelige adgangskode i URL'en, samt en knap til at **nulstille adgangskoden**. Klikker du "Nulstil adgangskode", genereres en ny kode øjeblikkeligt, og det gamle link holder op med at virke. Brug dette, hvis du har delt linket for bredt eller mistænker misbrug.

---

## Mobilsiden

Mobilsiden er en selvstændig, letvægts side, du kan bogmærke på din telefon eller tilføje til din hjemmeskærm – uden at skulle logge ind på WordPress.

**Link-format:**
```
https://ditsite.dk/?lstats_mobile=DIN-HEMMELIGE-KODE
```

Find linket under **WP VisitChart → Indstillinger → Mobilside – adgang**.

Mobilsiden viser de samme informationer som dashboardet, men tilpasset en lille skærm. Live-besøgstallet sidder fastlåst i toppen og forbliver synligt, selv når du scroller ned gennem listerne.

**Sikkerhed:** Siden er ikke beskyttet af login, men af den hemmelige kode i URL'en. Del ikke linket offentligt.

---

## Admin-bjælken

Når funktionen er aktiveret i indstillinger, vises et lille tal med et graf-ikon i WordPress' sorte admin-bjælke. Det viser det samme tal som "Live besøgende lige nu" på dashboardet og opdaterer sig hvert 10. sekund.

Admin-bjælke-tælleren er synlig:
- På alle sider i wp-admin
- På selve sitet, når du er logget ind som administrator

Et klik på tælleren fører direkte til dashboardet.

---

## Sidevisninger i indlægsoversigten

Når funktionen er slået til i indstillinger, vises kolonnen **"Sidevisninger"** i WordPress' Posts- og Pages-oversigt.

Kolonnen viser:
- **Stort tal** – samlede sidevisninger for dette indlæg (inkl. i dag)
- **"i dag: X"** – sidevisninger alene i dag

Klik på kolonneoverskriften **"Sidevisninger"** for at sortere hele oversigten efter popularitet.

**Vigtigt:** Kolonnen skal aktiveres i WordPress' Skærmindstillinger (øverst til højre på oversigt-siden) for at være synlig. Sæt hak i "Sidevisninger" i listen over tilgængelige kolonner.

### Sådan tælles sidevisninger

Sidevisninger bruger et separat JavaScript-ping, der er uafhængigt af heartbeat-systemet:

- Ping'et fyrer præcis **én gang per session per artikel**
- `sessionStorage` sikrer, at genindlæsning eller navigation væk og tilbage ikke dobbelttæller
- Bots og crawlere filtreres fra via user-agent-tjek
- Ingen cron-jobs – alt opdateres direkte i databasen ved hvert besøg

**Daglig rollover:** Første besøg på en artikel efter midnat lukker automatisk gårsdagens "i dag"-tal ind i totalen og starter forfra. Der kræves ingen manuel handling eller planlagt opgave.

---

## Sådan indsamles data

WP VisitChart bruger tre metoder til at indsamle trafikdata:

### 1. Heartbeat (JavaScript)
Et lille script kører i baggrunden på alle sider og sender et signal hvert 12. sekund, så længe besøgende har fanen åben og aktiv. Pauser automatisk, når fanen skjules (besøgende skifter til en anden fane). Dette er kilden til live-tallet, grafen og de aktive sider.

### 2. Sidevisnings-ping (JavaScript)
Et separat, enkelt signal der kun sendes én gang, første gang en besøgende åbner en given artikel i en session. Bruges udelukkende til sidevisnings-kolonnen i indlægsoversigten.

### 3. Server-side logging
Hvert sideopkald logges direkte af serveren, uanset om JavaScript kører. Fanger besøgende og bots, som heartbeat-scriptet aldrig ser.

### Trafikkilde-detection

Kildedetektion sker i denne rækkefølge:

1. **`fbclid`-parameteren i URL'en** – Facebook-links indeholder altid denne parameter, selv når browseren ikke sender referrer-information. Giver pålidelig Facebook-detektion.
2. **`utm_source`-parameteren** – frivillige UTM-mærker i links, f.eks. fra nyhedsbreve eller sociale kampagner.
3. **Referrer-headeren** – browserens oplysning om, hvilken side den besøgende kom fra.
4. **Ingen af ovenstående** – tæller som Direkte.

Resultatet kategoriseres som Søgemaskiner, Sociale medier, Andre hjemmesider eller Direkte.

### Data-opbevaring

Rå heartbeat-data gemmes i **8 dage** og ryddes automatisk derefter. 8 dage er nødvendigt for at kunne vise sammenligningen med "samme ugedag sidste uge" i grafen.

Sidevisningsdata i `lstats_post_views`-tabellen bevares uden tidsbegrænsning og ryddes ikke automatisk.

---

## Hvad de forskellige tal betyder

| Tal | Hvad det måler | Opdateres |
|---|---|---|
| Live besøgende | Unikke sessions med heartbeat de seneste 120 sek. | Hvert 10. sek. |
| Trafikkilder i dag | Unikke sessions opdelt efter kilde | Hvert 60. sek. |
| Enheder i dag | Unikke sessions opdelt efter skærmbredde | Hvert 60. sek. |
| Gns. tid på sitet | Aktiv tid baseret på heartbeat-intervaller | Hvert 60. sek. |
| Graf – i dag | Unikke sessions per 5-minutters-interval | Hvert 60. sek. |
| Graf – sidste uge | Samme beregning, 7 dage tilbage | Hvert 60. sek. |
| Sidevisninger (kolonne) | Sessions-unikke sideindlæsninger per artikel | I realtid |
| Bots | Registrerede bot-sessioner i dag | Hvert 60. sek. |

---

## Kendte begrænsninger

**"Direkte" trafik er ofte overvurderet.** Mange browsere og apps sender ikke referrer-information af privatlivshensyn, selv når besøgende klikker på et link. Disse tæller som Direkte, selvom de reelt kom fra et andet sted.

**Live-tallet og grafen måler ikke det samme.** Live-tallet bruger et glidende 120-sekunders-vindue. Grafen bruger 5-minutters-buckets. De to tal er ikke direkte sammenlignelige.

**Gennemsnitlig tid er en tilnærmelse.** Beregnes ud fra mellemrum mellem heartbeats. Besøg, der er kortere end 12 sekunder, registreres ikke som aktiv tid. Fanepauser over 60 sekunder tæller ikke med.

**WP Cron er ikke en rigtig systemcron.** Heartbeat-oprydning og andre planlagte opgaver udløses kun, når nogen besøger sitet. På et site med lav nattrafik kan der gå op til et par timer, inden en planlagt opgave faktisk kører.

**Bot-detektion er ikke 100 %.** Avancerede bots, der udgiver sig for at være almindelige browsere, kan glide igennem filteret og tælle som rigtige besøgende.

**Sidevisnings-ping kræver JavaScript.** Besøgende uden JavaScript, bots og crawlere registreres ikke som sidevisninger i kolonnen, da ping'et er JavaScript-baseret.

---

*WP VisitChart 1.9.2 – Copyright © 2026 Jens E. Hummelmose*
