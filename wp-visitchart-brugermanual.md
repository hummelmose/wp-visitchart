# WP VisitChart — Komplet Brugermanual

> Version 2.7.1 · Lavet af Jens Hummelmose · Public Domain (Unlicense)
> GitHub: [hummelmose/wp-visitchart](https://github.com/hummelmose/wp-visitchart)

---

## Indholdsfortegnelse

1. [Introduktion](#1-introduktion)
2. [Systemkrav](#2-systemkrav)
3. [Installation](#3-installation)
4. [Opgradering](#4-opgradering)
5. [Admin-dashboardet](#5-admin-dashboardet)
6. [Mobilsiden](#6-mobilsiden)
7. [WordPress Dashboard-widget](#7-wordpress-dashboard-widget)
8. [Admin bar-tæller](#8-admin-bar-tæller)
9. [Sidevisnings-kolonne i indlægsoversigten](#9-sidevisnings-kolonne-i-indlægsoversigten)
10. [Indstillinger](#10-indstillinger)
11. [Badges: Trending og Featured](#11-badges-trending-og-featured)
12. [Data og privatliv](#12-data-og-privatliv)
13. [Databasetabeller](#13-databasetabeller)
14. [Ydeevne og caching](#14-ydeevne-og-caching)
15. [Kompatibilitet](#15-kompatibilitet)
16. [Ofte stillede spørgsmål](#16-ofte-stillede-spørgsmål)

---

## 1. Introduktion

WP VisitChart er et selvhosted realtids-analyticsplugin til WordPress inspireret af Chartbeat. Det viser live besøgstal, dagens trafikgraf sammenlignet med samme ugedag ugen før, trafikkilder, enhedsstatistik, trending artikler og sidevisningsstatistik per indlæg — alt kørende udelukkende på din egen server uden tredjeparts-tjenester, abonnementer eller data der forlader dit site.

**Nøgleprincipper:**
- Al data gemmes i din egen database
- Ingen eksterne analytics-tjenester eller sporingspixels
- Ingen cookies nødvendige til kernefunktionaliteten
- Public domain (Unlicense) — brug det som du vil

---

## 2. Systemkrav

| Krav | Mindste version |
|---|---|
| WordPress | 5.8 |
| PHP | 7.4 |
| MySQL | 5.7 |
| MariaDB | 10.3 |

---

## 3. Installation

1. Download `wp-visitchart.zip` fra [Releases-siden](https://github.com/hummelmose/wp-visitchart/releases)
2. Gå til **Plugins → Tilføj plugin → Upload plugin** i WordPress-admin
3. Vælg zip-filen og klik **Installer nu**
4. Klik **Aktivér plugin**
5. Gå til **WP VisitChart** i venstre admin-menu

Pluginnet opretter automatisk tre databasetabeller ved aktivering: `wp_lstats_heartbeats`, `wp_lstats_sessions` og `wp_lstats_post_views`.

---

## 4. Opgradering

1. Download den seneste `wp-visitchart.zip` fra [Releases-siden](https://github.com/hummelmose/wp-visitchart/releases)
2. Gå til **Plugins → Tilføj plugin → Upload plugin**
3. Upload zip-filen og klik **Erstat nuværende med uploadet**
4. Aktivér pluginnet

Databaseskema-ændringer anvendes automatisk. Ingen manuel SQL er nødvendig.

**Bemærk:** `lstats_sessions`-tabellen (v2.3.0+) fyldes fra det første besøg efter opgradering. Trafikkilder genopfyldes inden for få minutter.

---

## 5. Admin-dashboardet

Gå til **WP VisitChart** i venstre admin-menu. Dashboardet opdaterer automatisk al data.

### 5.1 Sticky live-bjælke

En fast bjælke øverst viser det aktuelle antal live-besøgende til enhver tid. Tallet blinker kortvarigt når det ændrer sig og forbliver synligt mens du scroller.

**Opdateringsinterval:** Hvert 10. sekund.

**Hvad tæller:** Unikke browsersessioner med et heartbeat inden for de seneste 120 sekunder.

### 5.2 Trafikgraf

Fuldbredde-graf med besøgende og sidevisninger i 5-minutters intervaller for i dag, sammenlignet med samme ugedag fra ugen før.

| Linje | Beskrivelse |
|---|---|
| Blå hel | Unikke besøgende i dag |
| Rød stiplet | Sidevisninger i dag |
| Grå hel | Unikke besøgende, samme ugedag sidste uge |
| Grå stiplet | Sidevisninger, samme ugedag sidste uge |

Rør grafen for at se præcise tal. På mobilsiden lukker tooltip automatisk 5 sekunder efter du løfter fingeren.

**Opdateringsinterval:** Hvert 60. sekund. **Dataopbevaring:** 8 dage.

### 5.3 Mest aktive sider lige nu

Top 10 sider med aktive besøgende i det aktuelle 120-sekunders vindue.

Badges: 🔥 **Trending** (mindst 3 aktive + 50% vækst) · ⭐ **Featured** (valgt kategori i indstillinger)

**Opdateringsinterval:** Hvert 10. sekund.

### 5.4 Mest besøgte sider i dag

Top 20 sider efter sidevisninger siden midnat. Baseret på JavaScript-pings — ét ping per session per artikel.

⭐ **Featured-badge** vises for artikler i den valgte Featured-kategori.

**Opdateringsinterval:** Hvert 60. sekund.

### 5.5 Trafikkilder i dag

Fordeling af unikke sessioner siden midnat:

| Kategori | Hvad den inkluderer |
|---|---|
| **Direkte** | Ingen referrer eller intern navigation |
| **Søgemaskiner** | Google, Bing, Yahoo, DuckDuckGo, Yandex, Baidu m.fl. |
| **Sociale medier** | Facebook (inkl. fbclid), Instagram, Twitter/X, LinkedIn, Reddit, TikTok, Pinterest, YouTube |
| **Andre hjemmesider** | Alle andre eksterne referrers |

UTM-parametre har forrang over referrer-headeren.

**Opdateringsinterval:** Hvert 60. sekund.

### 5.6 Enheder i dag

Fordeling af unikke sessioner siden midnat efter enhedstype (Mobil, Tablet, Desktop).

**Opdateringsinterval:** Hvert 60. sekund.

### 5.7 Bots registreret

Viser bot-user-agents detekteret i de seneste 120 sekunder. Bots identificeres via user-agent matching mod kendte crawlere inkl. Googlebot, Bingbot, AhrefsBot, SemrushBot, GPTBot, ClaudeBot m.fl.

**Opdateringsinterval:** Hvert 10. sekund.

### 5.8 Mest henvisende domæner

Top 15 eksterne domæner der sendte besøgende i dag. Facebook fbclid-links mærkes `facebook.com (fbclid)`. UTM-sources vises med ` (utm)` suffix.

**Opdateringsinterval:** Hvert 60. sekund.

---

## 6. Mobilsiden

Selvstændig side uden login-krav til bogmærke på telefon eller startskærm. Viser de samme live-data i mobiloptimeret layout.

**Adgang:** Find URL under **WP VisitChart → Indstillinger** og kopier den med clipboard-ikonet.

**Sikkerhed:** Beskyttet af et hemmeligt token i URL'en. Nulstil tokenet i Indstillinger for at ugyldiggøre den gamle URL.

**Layout (fra top):**
1. Sticky live-besøgstal
2. Trafikgraf med tooltip (auto-lukker 5 sek. efter du løfter fingeren)
3. Mest aktive sider (med 🔥 og ⭐)
4. Mest besøgte i dag (med ⭐)
5. Trafikkilder i dag
6. Enheder i dag
7. Bots registreret
8. Mest henvisende domæner

---

## 7. WordPress Dashboard-widget

Widget med titlen **WP VisitChart – Top 20 i dag** på WordPress-dashboardet (`/wp-admin/`).

| Kolonne | Beskrivelse |
|---|---|
| Artikel | Nummer og titel, linket til indlægget |
| I dag | Sidevisninger i dag (blå, fed) — sorteringskolonne |
| Total | Samlede sidevisninger inkl. i dag |

Link til WP VisitChart-dashboardet vises nederst. Vises kun når **Sidevisnings-kolonne** er aktiveret i Indstillinger.

---

## 8. Admin bar-tæller

Live besøgstal i WordPress' admin-bjælke, synligt på alle admin-sider og på frontend når du er logget ind. Klik for at gå til dashboardet. Opdateres hvert 10. sekund. Kan slås til/fra i Indstillinger.

---

## 9. Sidevisnings-kolonne i indlægsoversigten

Graf-ikon-kolonne i indlægs- og sidesoversigten med sidevisningsstatistik per artikel. Kan sorteres. Bruger ét JavaScript-ping per session per artikel.

`views_today` overføres til `views_total` ved dagsstart på det første besøg.

---

## 10. Indstillinger

Gå til **WP VisitChart → Indstillinger**.

### Admin bar-tæller
Aktivér/deaktivér live besøgstal i admin-bjælken. **Standard:** Aktiveret.

### Mobilside
Aktivér/deaktivér den offentlige mobilstatistikside. URL vises med clipboard-kopieringsknap. **Nulstil adgangskode** genererer et nyt token og ugyldiggør den gamle URL. **Standard:** Aktiveret.

### Sidevisnings-kolonne
Aktivér/deaktivér sidevisnings-kolonnen i indlægsoversigten, dashboard-widget'en og sidevisnings-sporingen. **Standard:** Aktiveret.

### Udelad indloggede brugere
Udelad indloggede WordPress-brugere fuldstændigt fra al sporing. Anbefales på redaktionelle sites. **Standard:** Deaktiveret.

### Featured-kategori
Vælg én WordPress-kategori. Artikler i den valgte kategori vises med ⭐ i artiklellister. Vælg **— Ingen —** for at deaktivere. **Standard:** Ingen.

---

## 11. Badges: Trending og Featured

Badges vises mellem artiklens titel og besøgstallet så de altid er synlige uanset skærmbredde.

### 🔥 Trending-badge
Vises kun i **Mest aktive sider lige nu** når alle disse betingelser er opfyldt:
- Mindst 3 aktive besøgende lige nu
- Besøgstallet er mindst 50% højere end i det foregående 120-sekunders vindue
- Besøgstallet er strengt højere end det foregående

### ⭐ Featured-badge
Vises i **Mest aktive sider** og **Mest besøgte sider i dag** når artiklen tilhører Featured-kategorien valgt i Indstillinger.

---

## 12. Data og privatliv

**Hvad indsamles:** Sessions-ID (sessionStorage, ikke cookie), side-URL, referrer-URL, user-agent streng, IP-adresse (kun server-side til sessions-ID, gemmes ikke), tidsstempel.

**Opbevaring:** Al data i din egen WordPress-database. Heartbeats og sessionsdata slettes efter 8 dage. Sidevisningstal gemmes på ubestemt tid.

**Cookies:** Ingen. Sessions-ID'er gemmes i browserens `sessionStorage` og ryddes når fanen lukkes.

**Bot-trafik:** Kendte bots udelades fra al besøgsstatistik og vises separat.

---

## 13. Databasetabeller

### `wp_lstats_heartbeats`
Rådata fra heartbeat-signaler. Kolonnerne inkluderer: `id`, `post_id`, `session_id`, `url`, `is_bot`, `source`, `user_agent`, `referrer`, `device_type`, `created_at`.

Indekser: `idx_bot_source_time (is_bot, source, created_at)`, `idx_graph_covering (is_bot, source, created_at, session_id, post_id)` m.fl.

**Opbevaring:** 8 dage.

### `wp_lstats_sessions`
Én række per unik session skrevet med `INSERT IGNORE` på første heartbeat. Kolonnerne inkluderer: `session_id` (PK), `referrer`, `url`, `device_type`, `is_bot`, `category`, `domain`, `count_date`, `first_seen`.

**Opbevaring:** 8 dage.

### `wp_lstats_post_views`
Daglige og samlede sidevisninger per artikel. Kolonnerne inkluderer: `post_id` (PK), `views_today`, `views_total`, `count_date`, `updated_at`.

**Bemærk:** Det sande samlede total for en aktiv dag er `views_total + views_today`. `views_today` overføres til `views_total` ved dagsstart.

---

## 14. Ydeevne og caching

| Data | Cache-varighed |
|---|---|
| Live besøgstal | 8 sekunder |
| Trafikgrafdata | 30 sekunder |
| Mest besøgte i dag | 30 sekunder |
| Trafikkilder og domæner | 30 sekunder |
| Enhedsfordeling | 30 sekunder |

**WP Rocket:** REST-endpoints er udelukket fra side-cache og preloader. Transients ryddes automatisk ved indlægspublicering. Kritisk CSS leveres via `admin_head` inline styles immune over for WP Rockets CSS-optimering.

**Redis/Memcached:** `wp_cache_delete()` kaldes uden gruppe-parameter efter hvert transient-write for korrekt eviction.

**Covering index:** `idx_graph_covering` giver graf-queryen mulighed for at løses udelukkende fra indekset. Trafikkildeforespørgsler kører mod `lstats_sessions` (~25.000 rækker) i stedet for `lstats_heartbeats` (millioner af rækker).

---

## 15. Kompatibilitet

| Plugin/System | Kompatibel |
|---|---|
| WP Rocket | ✅ Ja |
| Cloudflare | ✅ Ja |
| W3 Total Cache | ✅ Ja |
| Redis / Memcached | ✅ Ja |
| Google Analytics / Matomo | ✅ Ja (kører uafhængigt) |
| WordPress Multisite | ⚠️ Ikke testet |

---

## 16. Ofte stillede spørgsmål

**Hvorfor er mit live besøgstal højere end Matomo?**
Live-tællere og traditionelle analytics måler fundamentalt forskelligt. WP VisitChart tæller sessioner aktive i 120 sekunder — en session udløber når en fane lukkes. Et tal 1,5–2× højere end Matomo er forventet.

**Hvorfor er trafikkildedata tomme efter opgradering?**
`lstats_sessions` fyldes fra første besøg efter opgradering og genopfyldes inden for få minutter.

**Hvorfor er "Total" lavere end "I dag" i widget'en?**
`views_total` inkluderer ikke i dag endnu — de lægges til ved midnat. Widget'en viser `views_total + views_today` som korrekt total.

**Virker pluginnet uden WP Rocket?**
Ja. WP Rocket-hooks fyrer kun hvis WP Rocket er installeret.

**Kan jeg bruge dette på flere sites?**
Ja. Installer uafhængigt på hvert site.

**Hvordan stopper jeg redaktørers besøg fra at tælle?**
Aktivér **Udelad indloggede brugere** i Indstillinger.

**Virker opgradering fra 1.x?**
Ja. Databaseskema opdateres automatisk. Al eksisterende data bevares.

---


---

## 17. Lys / Mørk tilstand

Både admin-dashboardet og mobilsiden understøtter lys og mørk tilstand.

### Admin-dashboard

En måne (🌙) / sol (☀️) toggle-knap sidder i højre side af den sticky live-bjælke øverst på dashboardet.

**Gemning:** Den valgte tilstand gemmes per WordPress-bruger via `user_meta`. Hver redaktør eller forfatter på sitet har sin egen uafhængige præference der bevares på tværs af sessioner og enheder.

**Flash-forebyggelse:** Den gemte tilstand sættes på `<html>`-elementet via et inline script i `admin_head` inden siden renderes — der er ingen hvid flash ved indlæsning af en mørk-tilstand-session.

### Mobilsiden

Samme måne/sol toggle-knap sidder i højre side af den sticky live-bjælke.

**Gemning:** Gemmes i `localStorage` i browseren. Præferencen bevares på tværs af besøg på samme enhed og browser, uafhængigt af WordPress-login.

### Hvad der ændres i mørk tilstand

- Alle kortbaggrunde, kanter og tekstfarver skifter til en mørk palette
- Trafikgrafens farver opdateres dynamisk så de forbliver læsbare på mørk baggrund
- WordPress' admin `#wpcontent`-baggrund overskrives så der ikke er lyse gab mellem kortene
- Den sticky live-bjælke og alle sektionsetiketter tilpasser sig den mørke palette

---


---

## 18. Opdateringssiden

Gå til **WP VisitChart → Opdateringer** for at tjekke om der er en nyere version tilgængelig på GitHub.

**Automatisk tjek:** Pluginet tjekker GitHub's releases API én gang hver 24. time, cachet via en WordPress-transient. Fejlede tjek (f.eks. netværksproblemer) forsøges igen efter 1 time i stedet for at vente hele døgnet ud.

**Menu-badge:** Et rødt notifikationsbadge — samme stil som WordPress' eget plugin-opdateringsikon — vises ved siden af "Opdateringer" i admin-menuen, når der er en nyere version tilgængelig på GitHub.

**Sidens indhold:**

- Din installerede versionsnummer
- En statusbanner: grøn hvis du kører nyeste version, rød hvis en opdatering er tilgængelig
- En **Download**-knap der linker direkte til release-zip-filen — vises kun når en opdatering faktisk er tilgængelig
- Et **Se på GitHub**-link til at se den fulde release på GitHub
- De komplette release notes for den nyeste version, renderet fra Markdown til formateret HTML

**Bemærk:** Hvis din installerede version allerede er lig med eller nyere end den seneste GitHub-release, skjules download-knappen — kun GitHub-linket vises, da der ikke er noget nyere at downloade.

---

*WP VisitChart v2.7.1 · Public Domain (Unlicense) · Lavet af Jens Hummelmose*
*Kildekode: [github.com/hummelmose/wp-visitchart](https://github.com/hummelmose/wp-visitchart)*
