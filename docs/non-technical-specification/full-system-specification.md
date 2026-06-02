# FriendlyFyzio OS

**Návrh komplexného systému na mieru**
Pripravené pre FriendlyFyzio
Marec 2026

---

## 1. Vízia projektu

Cieľom je nahradiť súčasný digitálny chaos — kombináciu Ergobody, Excelov, SimpleShopu a papiera — jedným centrálnym ekosystémom. Tento systém bude navrhnutý presne podľa interných procesov kliniky FriendlyFyzio.

Výsledkom bude eliminácia manuálneho prepisovania dát, automatizácia opakujúcich sa úloh a uvoľnenie rúk majiteľke aj terapeutkám. Systém bude plne pod vašou kontrolou — žiadne závislosti na externých službách, ktoré môžu zmeniť ceny alebo podmienky.

Ergobody sa nahrádza novým systémom. Pred spustením sa vykoná jednorazový export dát z Ergobody a ich import do nového systému.

---

## 2. Kompletný redizajn webu

Súčasný web (friendlyfyzio.cz) je postavený na Webflow a plnil svoju funkciu. Nový systém však vyžaduje kompletne nový web na mieru, ktorý bude priamo prepojený s rezervačným systémom a klientskou zónou. Nejde teda len o nový dizajn, ale o funkčnú súčasť celého ekosystému.

### 2.1 Štruktúra nového webu

Na základe analýzy súčasného webu a vašich služieb bude nový web obsahovať tieto stránky:

**Úvod (Homepage)**
- Hlavný hero banner s CTA tlačidlami (Objednať sa, Masáže, Dárkový poukaz).
- Aktuálne kurzy a workshopy — dynamicky ťahané zo systému (nie manuálne prepisované).
- Nabídka služieb — prehľadové karty (Fyzioterapia, Kurzy, Masáže, Laser/kryo).
- Referencie klientov.
- Kontakt, mapa, newsletter prihlásenie.

**Fyzioterapia**
- Prehľadová stránka so všetkými typmi terapií.
- Podstránky: Fyzioterapia pánevného dna, Tehotenská fyzioterapia, Fyzioterapia pohybového aparátu, Fyzioterapia jazíev, Fyzioterapia čeľustného kĺbu.
- Každá podstránka: popis, pre koho je určená, CTA na rezerváciu.

**Pohybové kurzy / Lekcie**
- Prehľad všetkých aktuálnych kurzov a lekcií — graficky ako dlaždice s fotografiami.
- Podstránky kategórii, ktoré je možné si vytvárať: napr. Pre tehotné, SM systém, Mami&Mimi, Jóga, Mobility&Stretch, Principy pohybu, Restart po cisárskom reze, Cvičenie po rakovine prsníka. Kategória v sebe môže mať jeden alebo viac kurzov.
- Priame prihlasovanie na série — napojené na rezervačný systém.
- Neaktívne kurzy (momentálne neprihlasujeme / čoskoro) viditeľné šedo ako informácia.

**Relaxácia / Masáže / Rituály**
- Prehľad masáží a relax služieb (relaxačná, tehotenská, lymfatická, detská masáž, bylinná napárka).
- Priama rezervácia z tejto stránky.

**Laser / Kryoterapia**
- Len informačná stránka — popis prístrojovej terapie a pre koho je určená.
- Laser a kryoterapia sú doplnkové služby — nie je možné sa na ne objednať samostatne cez rezervačný formulár, jedine telefonicky zavolaním Denise.

**Workshopy**
- Dynamický zoznam aktuálnych a plánovaných workshopov — graficky s dátumom.
- Neaktívne workshopy viditeľné šedo ako informácia.
- Každý workshop: popis, dátum, kapacita, prihlásenie.

**Ceník**
- Prehľad cien všetkých služieb — spravovateľný z admin panelu (nie hard-coded).
- Rozdelenie: Fyzioterapia, Kurzy, Masáže, Laser/kryo, Doplnkové.

**Náš tím**
- Profily všetkých terapeutov, lektorov, masérov — foto, špecializácia, podrobný popis.
- Spolupracujúci terapeuti.

**Dárkové poukazy (Neskoršia fáza)**
- Online kúpa a generovanie poukazov — bude doplnené v neskoršej fáze. V aktuálnej fáze sa vždy zakúpený darčekový poukaz nahrá klientovi vo forme kreditov, ktorý môže používať ako platobnú metódu.

**Klientska zóna (prihlásený klient)**
- Správa profilu: meno, priezvisko, e-mail, telefón.
- Prehľad a správa rezervácií (aktuálne aj história).
- Náhradové tokeny — dostupné tokeny, výber náhradného termínu.
- Kreditný zostatok.

### 2.2 Rozsah prác

**Dizajn — AI-asistovaný redesign**
- Developer prerobí aktuálny web do nového systému s využitím AI nástrojov.
- Branding (farby, logo, typografia) zostáva zachovaný — nemení sa.
- Dopracujú sa všetky chýbajúce sekcie a podstránky konzistentne.
- Všetky podstránky pokryté v dizajne pred programovaním.

> **Poznámka:** Veľa podstránok (kurzy, terapie) zdieľa rovnakú šablónu, len s iným obsahom. Reálny počet unikátnych layoutov je cca 10–12.

**Programovanie do HTML/CSS**
- Nakódovanie všetkých šablón do responzívneho HTML/CSS.
- Tieto šablóny sa integrujú do systému (dynamický obsah, rezervácie, admin).
- Optimalizácia: rýchlosť načítania, SEO základy, prístupnosť.

### 2.3 Náklady na redizajn

| Časť | Cena |
|------|------|
| AI-asistovaný redesign | 30 000 Kč |

---

## 3. CMS — Správa obsahu stránok

CMS je neoddeliteľnou súčasťou systému — nie je voliteľné. Správca má plnú kontrolu nad obsahom všetkých stránok vrátane podstránok služieb, bez nutnosti zasahovať do kódu.

### 3.1 Pevné a voliteľné stránky

- Niektoré stránky sú v databáze uložené napevno (napr. Homepage, Ceník, Tím, Kontakt) — nedajú sa zmazať, len skryť / odpublikovať.
- Správca si môže vytvárať ľubovoľné nové stránky (napr. špeciálne landing pages, novinky, akcie).
- Každá stránka má slug (URL adresu), meta title a meta description pre SEO.
- Každá podstránka služby (fyzioterapia, masáž, kurz, workshop...) je editovateľná z blokov rovnako ako ktorákoľvek iná stránka.

### 3.2 Flexible content — stavebnicový systém blokov

Obsah každej stránky sa skladá z opakovateľne použiteľných blokov (sekcií). Každý blok zodpovedá jednej sekcii webu a dá sa použiť na ľubovoľnej stránke.

**Dostupné bloky (príklady)**
- WYSIWYG editor — formátovaný text, nadpisy, zoznamy, obrázky.
- Hero banner — nadpis, podtitulok, CTA tlačidlá, pozadie.
- Recenzie / referencie
- Kategórie služieb — kartičky s odkazmi na jednotlivé sekcie.
- Zoznam služieb — dynamicky ťahaný zo správy služieb.
- Prebiehajúce kurzy — automaticky zobrazuje aktuálne série s kapacitami.
- Workshopy — zoznam nadchádzajúcich akcií.
- Tím — profily terapeutov.
- Instagram feed — dynamicky zobrazený obsah z profilu kliniky.
- Formulár — kontaktný alebo prihlasovací formulár.
- Mapa — vložená mapa s lokáciou kliniky.
- CTA sekcia — výrazná výzva na akciu s tlačidlom.

**Špeciálne prvky webu**
- **Bannery** — správa reklamných bannerov na stránkach (obrázok, odkaz, viditeľnosť, dátumy zobrazenia).
- **Modálne okná** — vyskakovacie okná s konfigurovateľným obsahom, spúšťačom (napr. po X sekundách, pri odchode) a frekvenciou zobrazenia.
- **Top bar** — lišta v hlavičke webu s krátkym oznamom, odkazom a možnosťou skrytia. Konfigurovateľná farba a text.

**Správa navigácie**
- **Hlavička** — správca môže pridávať, upravovať a mazať položky v hlavnej navigácii vrátane dropdown podmenu.
- **Pätička** — správa odkazov, stĺpcov a textu v pätičke stránky.

**Každý blok má konfigurovateľné možnosti**
- Viditeľnosť (zobrazený / skrytý).
- Poradie na stránke (drag & drop zoradenie).
- Vlastné nastavenia pre každý typ bloku (napr. farba pozadia, počet stĺpcov, výber konkrétnych kurzov).

### 3.3 Ako to funguje v praxi

1. Správca otvorí stránku v admin paneli.
2. Pridá blok zo zoznamu dostupných typov.
3. Vyplní obsah a nastaví možnosti.
4. Stránka sa na frontende dynamicky vykresľuje podľa poradenia blokov.

### 3.4 Cena CMS modulu

| Položka | Cena |
|---------|------|
| CMS — správa stránok, blokov, navigácie, bannerov, modálov | 35 000 Kč |

---

## 4. Rezervačný systém

Rezervačný systém pokrýva päť typov služieb, z ktorých každý má vlastnú logiku rezervácie a správy. Výber služby je vždy grafický — dlaždice s fotografiami, aby bol výber prehľadný a intuitívny.

### 4.1 Všeobecné pravidlá rezervácie

**Registrácia pri rezervácii**

Pri odoslaní rezervácie systém automaticky vytvorí klientovi účet (ak ešte neexistuje). Prihlasovacie údaje prídu e-mailom. Klient môže účet využívať na správu rezervácií, náhradových tokenov a histórie platieb — ak ho nevyužíva, nevadí.

**Formulárové polia pri rezervácii**
- Meno a priezvisko.
- E-mailová adresa.
- Telefónne číslo.
- Poznámka (voliteľná).
- Checkbox: Prihlásiť sa k newsletteru.

**Notifikácie**
- Všetky notifikácie (potvrdenie, pripomenutie, zrušenie) sa odosielajú výlučne e-mailom. Email obdrží aj terapeutka.
- Pre každý typ služby je možné nastaviť odlišnú odosielaciu e-mailovú adresu.

**Storno pravidlá (konfigurovateľné per typ)**

Storno pravidlá sú definované zvlášť pre každý typ služby:
- **Terapie a masáže** (kalendárne rezervácie): Klient môže zrušiť do X hodín pred termínom. Ak nezruší sám od seba, dostane notifikáciu o tom, že mu jeho termín pripomíname, ktorý je už ale záväzný, musí za neho zaplatiť či príde alebo nie.
- **Kurzy a workshopy** (dlhodobé): automatické storno pri nezaplatení do X dní; klient môže zrušiť do X dní pred začiatkom kurzu/workshopu.
- **Jednorázové lekcie**: automatické storno pri nezaplatení do X dní; klient môže zrušiť do X hodín/dní pred lekciou.
- Po uplynutí storno lehoty nie je možné rezerváciu zrušiť online — klient kontaktuje kliniku priamo.

**Systém 15-minútových blokov**

Pracovný kalendár každého terapeuta je rozdelený na 15-minútové bloky. Každá služba má definovaný počet blokov (15 min = 1 blok, 30 min = 2 bloky, 60 min = 4 bloky, 90 min = 6 blokov). Systém zobrazuje len termíny, kde je k dispozícii dostatočný počet voľných blokov za sebou.

- **Prestávka per služba per terapeut:** ku každej službe je možné nastaviť prestávku po jej skončení — definovaná ako počet 15-min blokov (napr. 1 blok = 15 min prestávka po každej masáži).
- Pracovná doba terapeuta sa rozdeluje na celky, ktoré majú svoje sledy blokov. Napr. doobedný blok a poobedný. (medzi nimi Obedná pauza 30 min napr.)
- Neštandardné termíny (soboty, predĺžená streda) sa dajú zadať samostatne s možnosťou hromadného jednorázového pridania.
- Medzery v rozvrhu prirodzene nevznikajú — rezervácia vrátane prestávky zaberá presne príslušný počet blokov.

### 4.2 Individuálna fyzioterapia

Podstránky s grafickými dlaždicami a fotografiami pre každý typ terapie:
- Fyzioterapia pánevného dna
- Tehotenská fyzioterapia
- Fyzioterapia pohybového aparátu
- Fyzioterapia jazíev
- Fyzioterapia čeľustného kĺbu

Pri rezervácii fyzioterapie si klient zvolí jednu z dvoch ciest:
- **Vybrať si terapeutku** — zobrazí sa zoznam terapeutiek s fotografiami. Po kliknutí na terapeutku sa otvorí jej kalendár s voľnými termínmi.
- **Vybrať si termín** — zobrazí sa kalendár všetkých voľných termínov naprieč terapeutkami.

**Vstupné a kontrolné terapie**
- Vstupná terapia (90 min) je verejne dostupná na rezerváciu cez web. Dostupná je aj 60 minútová terapia, ale len pre prihlásených, alebo tých ktorí existujú v databáze a boli tam najneskôr polroka dozadu (polroka je X, dá sa nastaviť).
- Kontrolné terapie (60 min) NIE sú viditeľné pre verejnosť — vypisujú a rezervujú ich terapeutky priamo v systéme. Je možné hromadne vytvoriť kontrolné terapie napr. 6 týždnov každý utorok o XX.
- Klient dochádza na celú liečbu k jednej terapeutke. Ale nie je to nutnosť. Napr. ak nemôže terapeutka v danú kontrolnú terapiu, vie ju presunúť pod niekoho iného.
- Systém automaticky detekuje, ak terapeutka zarezervuje kontrolnú terapiu na termín, ktorý je zároveň vypisaný pre verejnosť ako vstupné vyšetrenie — takýto verejný termín sa automaticky zruší pre verejnosť.
- Terapeutky môžu termíny kontrolných terapií vypisovať s opakovaním (napr. každý týždeň v rovnaký čas).

### 4.3 Masáže

Podstránky s grafickými dlaždicami pre každý typ masáže:
- Relaxačné masáže
- Lymfatické masáže
- Tehotenské masáže
- Masáže miminek a detí
- Bylinná napárka — rezervácia len telefonicky / e-mailom, v systéme bez online bookingu.

V každej podsekci sa zobrazuje CTA na rezervačný formulár. Rezervácia prebieha cez systém 30-minútových blokov — každý typ masáže má definovanú dĺžku v blokoch (napr. 60 min = 2 bloky, 90 min = 3 bloky).

- Masáže a terapie sa platia až na mieste po absolvovaní služby — nie je potrebná platba vopred.

### 4.4 Pohybové kurzy

Grafický prehľad kurzov ako dlaždice s fotografiami. Po kliknutí na dlaždicu sa otvorí detail kurzu s možnosťou prihlásenia.

- Neaktívne kurzy (momentálne neprihlasujeme, ale pravidelne poriádame) sú zobrazené šedo ako informácia.
- V detaile kurzu je sekcia Recenzie / Ohlasy so spätnými väzbami predchádzajúcich klientov.
- Klient kúpi celú sériu naraz. Systém automaticky vygeneruje účet a prihlasovacie údaje pošle e-mailom.
- Predpredaj pre stálych klientov: možnosť vygenerovať skryté odkazy pre prístup skôr, než sa otvorí verejný predaj.

**Kapacity a čakacia listina**
- Systém automaticky stráži maximálny počet účastníkov.
- Po naplnení kapacity sa aktivuje čakacia listina — klient je automaticky zaregistrovaný, ak sa niekto odhlási.

**Systém náhrad pre kurzy**
- Klient, ktorý si zaplatí celý kurz, môže sa odhlasovať z jednotlivých lekcií.
- V systéme je nastavený maximálny počet lekcií, ktoré si môže nahradiť (napr. max. 2 za kurz).
- Pre každý kurz je definované, v ktorých lekciách / iných kurzoch je možné náhradové tokeny uplatniť.
- Je nastavený čas, do kedy je odhláška považovaná za včasnú (token sa vygeneruje) a kedy je neskoro (token sa nevygeneruje).
- Lektorky v systéme vidia, kto príde na ktorú konkrétnu lekciu.
- Lektorky aj správca môžu klienta manuálne odhlásiť z lekcie a prihlásiť ho na inú — aj na lekcie, kde sa náhrady bežne nepovolia (výnimky).

### 4.5 Jednorázové lekcie

Grafický prehľad typov lekcií ako dlaždice (napr. Jin jóga, Tehotenská jóga). Po kliknutí sa zobrazí zoznam voľných termínov.

- V detaile lekcie je sekcia Recenzie / Ohlasy.
- Storno pravidlá a čakacia listina fungujú rovnako ako pri kurzoch.

### 4.6 Workshopy a ostatní

Grafický prehľad workshopov s dátumom priamo na dlaždici. Po kliknutí sa otvorí detailný popis a možnosť prihlásenia.

- Neaktívne workshopy (bežne poriádame, ale aktuálne nie sú vypsané) sú zobrazené šedo ako informácia.
- V detaile workshopu je sekcia Recenzie / Ohlasy.
- Storno pravidlá a čakacia listina fungujú rovnako ako pri kurzoch.

### 4.7 Pracovná doba a optimalizácia rozvrhu

**Nastavenie pracovnej doby per terapeut**

Každý terapeut má vlastný pracovný kalendár s flexibilným nastavením:
- **Týždenný opakujúci sa rozvrh:** pre každý deň v týždni sa nastavia rôzne pracovné celky (napr. pondelok 8:00–12:00 a 14:00–16:00, streda 10:00–18:00).
- **Párny / nepárny týždeň:** možnosť definovať odlišnú pracovnú dobu pre párne a nepárne týždne — každý deň v každom type týždňa môže mať inú dobu.
- **Neštandardné termíny:** jednorazové termíny mimo bežnej pracovnej doby.

**Priradenie miestnosti per deň**
- Tá istá služba môže byť vykonávaná v rôznych ambulanciách v rôzne dni — nastavuje sa priamo v kalendári terapeuta.
- Napr. Ema môže vykonávať fyzioterapiu v pondelok v ambulancii 1, streda v ambulancii 2.
- Systém pri rezervácii automaticky priradí správnu miestnosť podľa dňa a terapeuta.

**Blokovanie kalendára**
- Terapeut si môže zablokovať ľubovoľné časové obdobie — niekoľko hodín, celý deň alebo viacero dní za sebou (dovolenka, choroba, školenie).

> **Poznámka:** Systém bude fungovať, že v celom kurze sú vypísané lekcie s presnými dátumami. Ak sa stane napríklad, deň, dva dopredu, že lektorka nemôže prísť na lekciu, stačí, že len prepíše v admine dátum tej danej lekcie na inokedy. Pri tej zmene by mala možnosť notifikovať aj všetkých účastníkov kurzu.

- Blokáciu zadáva terapeut alebo správca priamo v kalendári.
- Zablokované termíny nie sú dostupné pre verejnú rezerváciu a zobrazujú sa vo farebnom rozvrhu.

**Detekcia konfliktov**
- Systém zobrazuje upozornenia pri konfliktoch: obsadenie tej istej miestnosti dvoma terapeutmi v rovnakom čase, prekryv rezervácií terapeuta a pod.
- Konflikty sú viditeľné v admin paneli a vo farebnom rozvrhu miestností.

---

## 5. Klientska zóna

Klientska zóna pokrýva správu osobného účtu, rezervácií, platieb, zdravotných záznamov, kreditného zostatku a náhradových tokenov.

### 5.1 Správa profilu

- Meno a priezvisko.
- E-mailová adresa.
- Telefónne číslo.
- Zmena hesla.

### 5.2 Anamnéza

- Každý klient má v profile anamnézu — volný popis zdravotného stavu, obmedzení a relevantných informácií.
- Anamnézu spravuje terapeutka v admin paneli, klient ju vidí vo svojom profile.
- Formát: WYSIWYG editor.

> **Poznámka:** Anamnézy nie sú viditeľné pre klientov. Prozatím.

> **Poznámka:** Terapeutky ku klientom ukladajú aj dátum narodenia/rodné číslo, adresu (napr. len mesto), pracovnú pozíciu, hmotnosť a výšku. Na to by tam niekde mali byť polia.

### 5.3 Záznamy z terapií

- Ku každej absolvovanej rezervácii (terapii) je možné priložiť zápis.
- Zápis spravuje terapeutka — klient ho vidí vo svojom profile.
- Formát: WYSIWYG editor.

### 5.4 Rezervácie

- Prehľad aktuálnych rezervácií (nadchádzajúce termíny).
- História minulých rezervácií.
- Možnosť zrušenia rezervácie v súlade s pravidlami kliniky.

### 5.5 História platieb

- Prehľad všetkých platieb klienta — dátum, suma, spôsob platby, za čo bolo zaplatené.
- Možnosť stiahnutia dokladu/faktúry ku každej platbe.

### 5.6 Náhradové tokeny

- Slúžia na náhradu lekcie za inú v súbežnom inom kurze.
- Klient je automaticky notifikovaný pri voľnom náhradnom mieste.
- Prehľad dostupných tokenov a ich platnosti.
- Výber a uplatnenie náhradného termínu priamo z klientskej zóny.

### 5.7 Kreditný zostatok

- Aktuálny zostatok kreditu a jeho platnosť (napr. do 6 mesiacov od nabíjania).
- História nabíjaní a čerpaní.

> **Poznámka:** V aktuálnej fáze to bude tak, že ak si u vás niekto zakúpi darčekový poukaz cez váš SimpleShop, manuálne jeho hodnotu pridáte ku kreditu účtu zákazníka. Keď sa naozaj zaregistruje na terapiu.

### 5.8 Faktúry

- Prehľad všetkých faktúr klienta — číslo faktúry, dátum, suma, stav.
- Možnosť stiahnutia PDF ku každej faktúre.
- Klient s účtom si môže vyplniť firemné fakturačné údaje (IČO, DIČ, fakturačná adresa) priamo vo svojom profile.

---

## 6. Finančná a administratívna automatizácia

### 6.1 Automatizované platby

- **QR platby:** Pre kurzy, lekcie a workshopy systém generuje QR kód s variabilným symbolom. Masáže a terapie sa platia až na mieste po absolvovaní služby.
- **Párovanie s bankou:** Automatická kontrola platieb podľa variabilného symbolu — realizované cez IMAP čítaním e-mailových notifikácií prichádzajúcich platieb z Air Bank (napr. platby@friendlyfyzio.cz).
- **Upozornenia:** Notifikácie na nezaplatené rezervácie.

### 6.2 Kreditný systém

Interná platobná mena — 1 kredit = 1 Kč. Kredit je alternatívna platobná metóda popri QR kóde a hotovosti.

- **Nabíjanie kreditu:** kredit nabíja terapeutka manuálne v admin paneli, alebo sa automaticky nabije z darčekového poukazu.
- **Čerpanie:** terapeutka a klient sa dohodnú na platbe kreditom — terapeutka odpíše príslušnú sumu jedným klikom. Je možné platiť kreditom aj za celé kurzy aj jednorázové lekcie.
- **Platnosť kreditu:** konfigurovateľná (napr. 6 mesiacov od nabíjania).
- **História:** kompletný záznam nabíjaní, čerpaní a exspirácií per klient.
- **Zostatok** vidí klient vo svojej klientskej zóne, terapeutka v admin paneli.

### 6.3 Fakturačný modul

Po každej zaplatenej rezervácii systém automaticky vygeneruje faktúru a pošle ju klientovi e-mailom ako prílohu — bez akéhokoľvek zásahu terapeutky.

**Automatické generovanie**

Faktúra sa vygeneruje sama — terapeutka nemusí robiť nič. Systém sleduje tri situácie, pri ktorých k tomu dôjde:
- Air Bank pošle e-mail o prijatej platbe → systém ho prečíta, spáruje s rezerváciou a vygeneruje faktúru.
- Terapeutka odpíše klientovi kredit za službu → faktúra negeneruje, klient už nejakým spôsobom zaplatil za svoje kredity, preto dostáva faktúri iným spôsobom.
- Terapeutka zaznamená hotovostnú platbu na mieste → faktúra sa vygeneruje len na vyžiadanie klienta. Klient ju môže vyžiadať cez svoju klientskú zónu alebo mu ju odošle terapeut manuálne na email.

Vygenerovaná faktúra sa priloží priamo k existujúcemu e-mailu o prijatí platby, ktorý klient dostane. Nie je to teda ďalší e-mail navyše — len k tomu, čo by klient dostal aj doteraz, pribudne PDF príloha.

**Faktúra vo formáte PDF**

Každá faktúra sa generuje ako PDF dokument v dizajne FriendlyFyzio — s logom, farbami a kontaktnými údajmi kliniky. Obsahuje aj QR kód pre prípad, že by klient chcel platiť dodatočne (formát QR Platba, štandard používaný v ČR).

V momente vygenerovania faktúry sa uložia všetky údaje — kto to vystavil, komu, za čo, za akú sumu. Ak sa neskôr zmenia údaje klienta alebo kliniky, historická faktúra zostane vždy presne taká, aká bola v čase vystavenia.

**Príjmový pokladničný doklad**

Pri každej hotovostnej platbe systém automaticky vygeneruje okrem faktúry aj príjmový pokladničný doklad (PPD). Ide o samostatný PDF dokument, ktorý slúži ako potvrdenie o prijatí hotovosti.

Doklad obsahuje:
- Číslo dokladu (vlastná číselná rada, nezávislá od faktúr)
- Meno klienta
- Sumu prijatej hotovosti
- Dátum prijatia
- Číslo príslušnej faktúry, ku ktorej bol vydaný
- Podpis majiteľky

Klient dostane oba dokumenty — faktúru aj pokladničný doklad — ako prílohy v jednom e-maile.

**Typy klientov**

Systém rozlišuje dva typy klientov na faktúre:
- **Klient s účtom** — štandardný klient FriendlyFyzio, ktorý má vytvorený účet v systéme. Faktúra sa automaticky priradí k jeho profilu, vidí ju vo svojej klientskej zóne a môže si ju kedykoľvek stiahnuť.
- **Klient bez účtu** — niektorí klienti nepotrebujú účet a existujú v systéme len ako kontakt pre účely fakturácie (meno, e-mail, prípadne firemné údaje). Faktúra sa im odošle e-mailom, ale nemajú prístup do klientskej zóny.

**Firemní klienti**

Väčšina klientov FriendlyFyzio sú súkromné osoby — pre nich faktúra funguje automaticky bez akéhokoľvek nastavenia.

Niektorí klienti však potrebujú faktúru vystavenú na firmu. Pre takýchto klientov terapeutka alebo správca doplní firemné údaje priamo v ich profile (IČO, DIČ, fakturačná adresa). Klient s účtom si ich môže doplniť aj sám vo svojej klientskej zóne. Systém potom automaticky použije tieto údaje pri generovaní faktúry.

**Číselné rady**

Faktúry sú číslované automaticky — číslo si systém prideľuje sám. Je možné nastaviť viacero číselných rád — napríklad zvlášť pre fyzioterapie a zvlášť pre kurzy. Každá rada má vlastný formát čísla (napr. FT-2026-001, KU-2026-001) a každý rok sa číslovanie automaticky resetuje od jednotky.

Príjmové pokladničné doklady majú vlastnú číselnú radu nezávislú od faktúr.

**Stavy faktúry**

Každá faktúra prechádza stavmi: Nová → Odoslaná → Zaplatená / Po splatnosti. Správca vidí na prvý pohľad, ktoré faktúry sú v poriadku a ktoré si vyžadujú pozornosť. Faktúry, ktoré neboli zaplatené do dátumu splatnosti, systém automaticky označí ako Po splatnosti.

**Čo dostane klinika a účtovníčka**
- **Prehľad v admin paneli** — všetky faktúry na jednom mieste, filtrovateľné podľa klienta, obdobia, typu služby alebo číselnej rady.
- **Export do Excelu** — jedným kliknutím sa vygeneruje súbor pre účtovníčku. Obsahuje číslo faktúry, meno klienta, sumu, dátum vystavenia, spôsob platby a stav.
- **Hromadné stiahnutie PDF** — správca môže označiť ľubovoľné faktúry a stiahnuť ich všetky naraz ako jeden ZIP súbor — napríklad všetky faktúry za daný mesiac jedným kliknutím.

### 6.4 Modul náhrad (absencie)

**Omluvenie a tokeny**
- **Včasné omluvenie:** Systém automaticky vygeneruje náhradový token.
- **Token = nárok:** Platnosť (napr. 30 dní), viditeľný v profile klienta.
- **Neskoré omluvenie:** Token sa nevygeneruje. Pravidlá jasne komunikované.

**Samoobslužná náhrada**
- Klient vidí voľné náhradné miesta v iných kurzoch/lekciách.
- Vyberie termín a uplatní token. Náhradné miesta nie sú viditeľné pre verejnosť.

**Automatický náhradník**
- Systém oslovuje ľudí na čakacej listine.
- Prvý kto potvrdí, miesto dostáva. Ak nikto nereaguje, miesto sa uvoľní pre verejnosť.

### 6.5 Výplatný modul (Neskoršia fáza)

- Automatický výpočet odmien terapeutov podľa odpracovaných hodín a typu terapie.
- Automatický výpočet odmien lektorov vrátane bonusov podľa počtu účastníkov.
- Pohľad terapeuta / lektora: prehľad vlastných odmien a odpracovaných hodín.
- Pohľad správcu: prehľad všetkých výplat, celkových nákladov a vyťaženosti.

---

## 7. Správa priestorov a integrácie

### 7.1 Správa budov a miestností

Systém pracuje s dvojúrovňovou štruktúrou: budovy (miesta) → miestnosti.

**Budovy / miesta**
- Správa viacerých budov alebo pobočiek (napr. hlavná klinika, externé priestory).
- Každá budova má vlastnú adresu a zoznam miestností.

> **Poznámka:** Aktuálne máme 1 budovu, ale 1 kurz prebieha mimo túto budovu, na inom mieste.

**Miestnosti**
- Každá miestnosť patrí pod konkrétnu budovu.
- Stráženie kapacít — 2 ambulancie a 2 telocvične (rozšíriteľné).
- Každý typ terapie má priradené vhodné miestnosti.
- Vizualizácia obsadenosti — farebný rozvrh per miestnosť.

### 7.2 Notifikácie (e-mail)

Systém automaticky odosiela e-mailové notifikácie klientom aj terapeutkám pri dôležitých udalostiach:
- **Potvrdenie rezervácie** — ihneď po rezervácii.
- **Pripomenutie** — automaticky 24 h pred termínom (nastaviteľné).
- **Zrušenie / zmena** — pri zrušení alebo zmene termínu.
- **Náhradníček** — automatické oslovenie ľudí z čakacej listiny pri uvoľnení miesta.
- **Platby** — upozornenie na nezaplatené rezervácie, potvrdenie prijatia platby.

> **Poznámka:** Notifikácie sa odosielajú výlučne e-mailom. SMS notifikácie nie sú v súčasnosti požadované.

### 7.3 MailerLite

- Automatické zbieranie kontaktov nových klientov.
- Segmentácia podľa typu služby.
- Pripravené na kampane a automatizácie.

### 7.4 Google Kalendár

- Jednosmerné prepojenie v Google kalendári terapeuta.

---

## 8. Prevádzkové náklady a návratnosť

| Položka | Detail |
|---------|--------|
| Fixný hosting | Cca 2 500 Kč / rok. Cena je konečná bez ohľadu na počet terapeutov či klientov. Zahŕňa: hosting a server bežiaci nepretržite, automatické zálohy, SSL certifikáty. Nezahŕňa: development a zmeny v kóde, urgentnú podporu mimo pracovného času, nové features. |
| Časová návratnosť | Uvoľnenie cca 10–15 hodín mesačne manuálnej administratívy (rezervácie, platby). |

---

## 9. Odhad náročnosti a fázovanie

Každá fáza je použiteľná samostatne — nemusíte čakať na dokončenie celého systému.

MVP zahŕňa kompletný rezervačný systém, CMS, finančnú agendu vrátane kreditného systému, fakturácie, anamnézy a záznamy z terapií. Ergobody je nahradené vlastným systémom — dáta sa jednorazovo importujú z XLSX exportu pred spustením.

> **MVP** (Minimum Viable Product) — prvá funkčná verzia systému, ktorá obsahuje len to, čo je nevyhnutné pre každodenné fungovanie kliniky. Cieľom nie je dokonalý systém hneď od začiatku, ale rýchle nasadenie toho najdôležitejšieho — nový web, rezervácie, fakturácia. Všetko ostatné sa dopĺňa postupne v ďalších fázach podľa reálnych potrieb.

### MVP Fáza: Jadro + Web + Financie

**Cieľ:** Funkčný nový web, rezervačný systém a finančná automatizácia. Klinika začína plnohodnotne fungovať v novom systéme.

**Obsah prác:**
- Dizajn webu — AI-asistovaný redesign (30 000 Kč)
- HTML/CSS kódovanie zo šablón (responzívny frontend)
- Admin panel — správa obsahu stránok, ceníka, tímu, workshopov, terapeutov
- 5 typov rezervácií: individuálna fyzioterapia, masáže, pohybové kurzy, jednorázové lekcie, workshopy
- Systém 15-minútových blokov — pracovný kalendár terapeutov, prestávka per služba per terapeut, neštandardné termíny
- Kontrolné terapie — viditeľné len terapeutkám, automatická detekcia konfliktu s verejnými termínmi
- Správa budov a miestností — kapacity, priradenie k terapiám, farebný rozvrh
- Pracovná doba per terapeut — týždenný rozvrh, párny/nepárny týždeň, neštandardné termíny
- Priradenie miestnosti per deň per terapeut — tá istá služba v rôznych ambulanciách v rôzne dni
- Blokovanie kalendára — dovolenka, choroba, viacero dní za sebou
- Detekcia konfliktov — miestnosti, terapeuti
- Klientska zóna — profil, správa rezervácií, história platieb, náhradové tokeny, faktúry
- Storno pravidlá — automatické storno pri nezaplatení, konfigurovateľné lehoty
- Prihlasovací formulár na newsletter — vložiteľný na ľubovoľnú stránku webu
- Recenzie / Ohlasy — sekcia pri kurzoch, lekciách a workshopoch
- Párovanie platieb s bankou — cez IMAP čítaním e-mailových notifikácií z Air Bank
- QR platby — generovanie QR kódov pre kurzy, lekcie a workshopy
- Modul náhrad — tokeny, samoobslužná náhrada, čakacia listina, manuálne výnimky
- Import dát z Ergobody — jednorazový import klientov, anamnéz a histórie termínov z XLSX exportu
- Kreditný systém — in-app mena (1 kredit = 1 Kč), nabíjanie terapeutkou, platnosť kreditu, história
- Fakturačný modul — automatické PDF faktúry, príjmové pokladničné doklady, číselné rady, export pre účtovníčku

**Možnosti CMS:**

| Varianta | Cena vývoja | AI dizajn + Spolu |
|----------|------------|-------------------|
| MVP s CMS | 165 000 Kč | 195 000 Kč |
| MVP s CMS + Fakturačný modul | 200 000 Kč | 230 000 Kč |

CMS sa dá doplniť kedykoľvek neskôr bez nutnosti prepisovania frontendu. Web bude od začiatku postavený tak, aby rozšírenie o správu obsahu bolo prirodzeným krokom.

### Neskoršia fáza

- Produkty na predaj počas terapií, zahrnú sa do platby.
- Výplatný modul + náklady (napr. nakúpili sa šatky za 2k Kč)
- Mini eshop — predaj darčekových poukazov

### Fáza Blog

**Cieľ:** Obsahový marketing a budovanie dôvery cez odborné články.

- Blog — zoznam článkov s kategorizáciou (napr. fyzioterapia, pohyb, výživa).
- Článok — WYSIWYG obsah, kategória, dátum, autor, perex, titulná fotografia.
- Vedecké referencie — každý článok môže obsahovať sekciu s odkazmi na štúdie a zdroje.
- Admin rozhranie — správa článkov a kategórií v admin paneli.

---

## 10. Náklady

| Fáza | Hlavné časti | Náklady | Výsledok |
|------|-------------|---------|----------|
| MVP | AI dizajn, HTML/CSS, rezervácie, financie, anamnézy, import Ergobody, klientska zóna | 195 000 Kč | Funkčná klinika |
| z toho: AI dizajn | Developer (AI-asistovaný redesign) | 30 000 Kč | — |
| z toho: CMS | Správa obsahu stránok a blokov | 35 000 Kč | — |
| z toho: Fakturácia | Faktúry, PPD, číselné rady, export | 35 000 Kč | — |
| z toho: Vývoj | Rezervácie, platby, anamnézy, import, admin zóna, klientska zóna, Mailerlite | 95 000 Kč | — |
| Neskoršia fáza | Výplaty, Náklady, Produkty, Eshop | Musí sa došpecifikovať | — |
| Fáza Blog | Blog, kategórie, vedecké referencie | Musí sa došpecifikovať | Obsahový marketing |
| **SPOLU (len MVP)** | | **230 000 Kč** | |

---

## 11. Záver

Cieľom tohto projektu je vytvoriť z FriendlyFyzio moderne riadenú kliniku, kde technológia pracuje pre vás — nie vy pre ňu.

MVP fáza pokrýva všetko potrebné pre každodenné fungovanie: nový web, plný rezervačný systém, kreditný systém, fakturáciu, anamnézy a záznamy z terapií. Ďalšie fázy dopĺňajú systém postupne, podľa vašich reálnych potrieb a kapacity.
