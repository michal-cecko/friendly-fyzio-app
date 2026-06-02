# FriendlyFyzio OS — Logika ponúkania termínov

**Interná špecifikácia**
Marec 2026

---

## 1. Účel dokumentu

Tento dokument popisuje pravidlá podľa ktorých systém rozhoduje, ktoré termíny ponúkne klientovi pri rezervácii masáže. Cieľom je aby v rozvrhu terapeutky nevznikali zbytočné medzery medzi klientmi.

Dokument je pripravený na schválenie klientom pred implementáciou.

---

## 2. Základné pojmy

| Pojem | Vysvetlenie |
|-------|-------------|
| **Blok** | Základná jednotka času — 15 minút. |
| **Dĺžka služby** | 30 min = 2 bloky, 60 min = 4 bloky, 90 min = 6 blokov. |
| **Prestávka** | 15 min (1 blok) po každej masáži. Výnimka: ak koniec masáže bez prestávky = koniec pracovnej doby, prestávka sa nevyžaduje — terapeutka ju má prirodzene. |
| **Kotva** | Čas od ktorého môže začať nová rezervácia. Vzniká na začiatku pracovnej doby a po každej rezervácii (koniec + prestávka). |
| **Gap** | Voľný časový úsek medzi dvoma kotvami (alebo medzi kotvou a koncom pracovnej doby). |

---

## 3. Pravidlá zobrazovania termínov

### 3.1 Prázdny deň (žiadne rezervácie)

Berme že pracovná doba (jeden pracovný celok v jednom dni, môže ich byť viac) je od 8:00 do 12:15. Klient si môže vybrať ľubovoľný 15-minútový blok za týchto podmienok:

- **Najskôr 8:00** — ak si klient vyberie 8:00, berie na seba že pred ním nie je priestor pre ďalšieho klienta v ten deň.
- **Alebo najskôr 9:15** — aby pred jeho rezerváciou ostalo aspoň 75 min (60 min masáž + 15 min prestávka) pre prípadného ďalšieho klienta pred ním.
- Za jeho rezerváciou (vrátane prestávky) musí ostať aspoň 60 min do konca pracovnej doby.

### 3.2 Deň s existujúcimi rezerváciami

Platí prísne pravidlo lepenia — termín sa ponúkne z kotvy len ak je splnená jedna z týchto podmienok:

- **Priame lepenie:** koniec rezervácie + prestávka dopadne PRESNE na začiatok najbližšej existujúcej rezervácie alebo koniec pracovnej doby.
- **Reťazové lepenie:** existuje kombinácia viacerých po sebe idúcich rezervácií, ktorá gap vyčerpá presne. V takom prípade systém ponúkne prvý článok reťaze — a keď si ho niekto zarezervuje, ponúkne ďalší článok atď.

**Kľúčový princíp:** každá rezervácia musí nadväzovať presne na predchádzajúcu (vrátane prestávky). Žiadne voľné medzery medzi klientmi. Ak gap nie je riešiteľný žiadnou kombináciou dĺžok, zostane nedostupný.

### 3.3 Gap po poslednej rezervácii dňa

Voľné pravidlo — od kotvy (koniec poslednej rezervácie + prestávka) sa ponúkne ľubovoľný termín, pokiaľ za ním ostane aspoň 60 min do konca pracovnej doby.

---

## 4. Príklady

Terapeutka Denisa, pracovná doba 8:00–16:00. Prestávka vždy 15 min po každej masáži. Výnimka: ak koniec masáže = 16:00, prestávka sa nevyžaduje.

### Prípad 1 — prázdny deň

| Dĺžka | Dostupné časy |
|--------|---------------|
| 30 min | 8:00 alebo 9:15 – 15:30 (každých 15 min) |
| 60 min | 8:00 alebo 9:15 – 15:00 (každých 15 min) |
| 90 min | 8:00 alebo 9:15 – 14:30 (každých 15 min) |

### Prípad 2 — existujúca rezervácia o 9:15 (60 min → kotva 10:30)

Gap 8:00–9:15 = 75 min. Z kotvy 8:00:
- 30 min → kotva 8:45. 8:45 ≠ 9:15. Neponúkne.
- 60 min → kotva 9:15. 9:15 = 9:15. ✓ Ponúkne o 8:00.
- 90 min → nestačí (potrebuje 105 min, gap má 75 min). Neponúkne.

| Dĺžka | Dostupné časy |
|--------|---------------|
| 60 min | 8:00 |
| 30 min | 10:30 – 15:30 |
| 60 min | 10:30 – 15:00 |
| 90 min | 10:30 – 14:30 |

### Prípad 3 — existujúca rezervácia o 9:15 (30 min → kotva 10:00)

Gap 8:00–9:15 = 75 min. Z kotvy 8:00:
- 60 min → kotva 9:15. 9:15 = 9:15. ✓ Ponúkne o 8:00.
- 30 min → kotva 8:45. ≠ 9:15. Neponúkne.
- 90 min → nestačí. Neponúkne.

| Dĺžka | Dostupné časy |
|--------|---------------|
| 60 min | 8:00 |
| 30 min | 10:00 – 15:30 |
| 60 min | 10:00 – 15:00 |
| 90 min | 10:00 – 14:30 |

### Prípad 4 — existujúca rezervácia o 9:15 (90 min → kotva 10:45)

Gap 8:00–9:15 = 75 min. Z kotvy 8:00:
- 60 min → kotva 9:15. 9:15 = 9:15. ✓ Ponúkne o 8:00.
- 30 min → kotva 8:45. ≠ 9:15. Neponúkne.
- 90 min → nestačí. Neponúkne.

| Dĺžka | Dostupné časy |
|--------|---------------|
| 60 min | 8:00 |
| 30 min | 10:45 – 15:30 |
| 60 min | 10:45 – 15:00 |
| 90 min | 10:45 – 14:30 |

### Prípad 5 — existujúca rezervácia o 10:30 (60 min → kotva 11:45)

Gap 8:00–10:30 = 150 min. Platné kombinácie blokov ktoré dajú 150 min:
- 30 + 90 min: 45 + 105 = 150 ✓
- 60 + 60 min: 75 + 75 = 150 ✓
- 90 + 30 min: 105 + 45 = 150 ✓

Z kotvy 8:00:
- 30 min → kotva 8:45. Zvyšok do 10:30 = 105 min = 90+15. ✓ Ponúkne o 8:00.
- 60 min → kotva 9:15. Zvyšok do 10:30 = 75 min = 60+15. ✓ Ponúkne o 8:00.
- 90 min → kotva 9:45. Zvyšok do 10:30 = 45 min = 30+15. ✓ Ponúkne o 8:00.

| Dĺžka | Dostupné časy |
|--------|---------------|
| 30 min | 8:00 |
| 60 min | 8:00 |
| 90 min | 8:00 |
| 30 min | 11:45 – 15:30 |
| 60 min | 11:45 – 15:00 |
| 90 min | 11:45 – 14:30 |

### Prípad 6 — existujúca rezervácia o 11:00 (60 min → kotva 12:15)

Gap 8:00–11:00 = 180 min. Platné kombinácie blokov ktoré dajú 180 min:
- 4 × 30 min: 45+45+45+45 = 180 ✓
- 60 + 90 min: 75 + 105 = 180 ✓
- 90 + 60 min: 105 + 75 = 180 ✓

Z kotvy 8:00:
- 30 min → kotva 8:45. Zvyšok = 135 min. Riešiteľné? 3×45=135 ✓. Ponúkne o 8:00.
- 60 min → kotva 9:15. Zvyšok = 105 min = 90+15. ✓ Ponúkne o 8:00.
- 90 min → kotva 9:45. Zvyšok = 75 min = 60+15. ✓ Ponúkne o 8:00.

Keďže 30-minútovky nie sú verejné, ponúkajú sa len posledné články verejných reťazí:
- Reťaz 90 + 60: kotva po 90 min = 9:45. Ponúkne sa 60 min o 9:45 (9:45 + 75 = 11:00 ✓).
- Reťaz 60 + 90: kotva po 60 min = 9:15. Ponúkne sa 90 min o 9:15 (9:15 + 105 = 11:00 ✓).

Reťaz pre 4× 30 min: 8:00 → 8:45 → 9:30 → 10:15 → 11:00. Každá kotva ponúkne 30 min pokiaľ zvyšok gapu je riešiteľný.

| Dĺžka | Dostupné časy |
|--------|---------------|
| 30 min | 8:00 |
| 60 min | 8:00 |
| 90 min | 8:00 |
| 30 min | 12:15 – 15:30 |
| 60 min | 12:15 – 15:00 |
| 90 min | 12:15 – 14:30 |

### Prípad 7 — dve rezervácie: 9:15 (60 min → kotva 10:30) a 13:00 (90 min → kotva 14:30)

**Gap 1:** 8:00–9:15 = 75 min. Z kotvy 8:00:
- 60 min → kotva 9:15. = 9:15. ✓ Ponúkne o 8:00.

**Gap 2:** 10:30–13:00 = 150 min. Platné kombinácie: 30+90, 60+60, 90+30. Z kotvy 10:30:
- 30 min → kotva 11:15. Zvyšok = 105 min = 90+15. ✓ Ponúkne o 10:30.
- 60 min → kotva 11:30. Zvyšok = 90 min. Riešiteľné? 2×45=90. ✓ Ponúkne o 10:30.
- 90 min → kotva 12:15. Zvyšok = 45 min = 30+15. ✓ Ponúkne o 10:30.

**Gap 3:** po 13:00 (kotva 14:30) — posledná rezervácia → voľné pravidlo. 90 min o 14:30: koniec = 16:00 → prestávka sa nevyžaduje. ✓

| Dĺžka | Dostupné časy |
|--------|---------------|
| 60 min | 8:00 |
| 30 min | 10:30 |
| 60 min | 10:30 |
| 90 min | 10:30 |
| 30 min | 14:30 – 15:30 |
| 60 min | 14:30 – 15:00 |
| 90 min | 14:30 |

### Prípad 8 — rezervácia o 15:00 (60 min, koniec 16:00 → prestávka sa nevyžaduje)

Gap 8:00–15:00 = 420 min → voľné pravidlo. Koniec + prestávka musí byť ≤ 15:00:
- 30 min: najskôr 8:00 alebo 9:15, najneskôr 14:15 (14:15+30+15=15:00).
- 60 min: najskôr 8:00 alebo 9:15, najneskôr 13:45 (13:45+60+15=15:00).
- 90 min: najskôr 8:00 alebo 9:15, najneskôr 13:15 (13:15+90+15=15:00).

| Dĺžka | Dostupné časy |
|--------|---------------|
| 30 min | 8:00 alebo 9:15 – 14:15 |
| 60 min | 8:00 alebo 9:15 – 13:45 |
| 90 min | 8:00 alebo 9:15 – 13:15 |
