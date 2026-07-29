---
title: Kurzy, série a lekce
icon: heroicon-o-rectangle-stack
keywords: [kurz, série, běh, lekce, termín kurzu, struktura, kategorie, lektor série, obsazenost, max. náhrad, včasné zrušení, rozvrh, sloupec rozvrh, generování lekcí, vygenerovat lekce, dny v týdnu, den a čas kurzu, čas konání, místnost série, fotka na kartu, fotka do detailu, fotka kurzu]
---

Kurzy mají tři úrovně a je užitečné je nezaměňovat.

| Úroveň | Co to je | Příklad |
| --- | --- | --- |
| **Kurz** | Popis toho, co učíte. Nemá datum. | Cvičení pro těhotné |
| **Série** | Konkrétní běh kurzu — má termín zahájení, kapacitu a cenu. | jarní běh, úterky od 17:00 |
| **Lekce** | Jedno setkání série. | 3. lekce, 15. dubna |

Na kurz se nikdo nepřihlašuje — přihlašuje se na **sérii**. Kurz je jen zastřešující stránka na webu.

## Co nastavíte u kurzu a co u série

U **kurzu** je to, co platí pro všechny jeho běhy: kategorie, lektor, popis, cena jednorázového vstupu a **Včasné zrušení (hodin předem)** — do kolika hodin před lekcí se lze odhlásit bez ztráty nároku na náhradu.

U **série** je to, co se běh od běhu liší: termín, kapacita, cena a **Max. náhrad** na záložce *Náhrady*. Kolik lekcí si smí účastník nahradit, se odvíjí od délky konkrétního běhu — desetilekcová série unese jiný počet než šestilekcová — proto se to nastavuje u série, ne u kurzu. Podrobnosti jsou v kapitole [Docházka](?tema=kurzy/dochazka).

## Fotky kurzu

U kurzu vyplňujete **dvě** fotky, protože se na webu používají na dvou různě tvarovaných místech:

- **Fotka na kartu** — leží na šířku (zhruba jako fotka z mobilu naležato, poměr 16:9). Ukazuje se na dlaždici kurzu ve výpisu na webu.
- **Fotka do detailu** — čtvercová. Ukazuje se nahoře na stránce kurzu.

Vyplňte klidně obě, i kdyby to byl jen jinak oříznutý stejný snímek. Když jednu necháte prázdnou, doplní se za ni ta druhá — kurz tedy nikdy nezůstane bez obrázku, jen bude na chybějícím místě oříznutý přes okraje.

Fotky se vybírají z knihovny médií, takže je nahrajete jednou a můžete je použít i jinde — viz [Navigace a bannery](?tema=obsah/navigace-a-bannery).

## Kdo sérii vede

Lektor je uvedený u kurzu a odtud ho přebírají všechny jeho série. Když ale konkrétní běh vede někdo jiný, vyplňte **Lektor** přímo u série — pole je nepovinné a prázdné znamená „lektor kurzu“ (v přehledu je to označené jako *z kurzu*).

Není to jen popiska: kdo je u série uvedený, tomu se série objeví v panelu a může ji i její lekce upravovat, vidí přihlášky a zapisuje docházku. Lektor kurzu o ni nepřijde. Co přesně smí, je v kapitole [Role a oprávnění](?tema=zaklady/role-a-opravneni).

## Rozvrh série

Na záložce **Rozvrh** v nastavení série vyplníte, kdy se běh schází. Každý řádek je jeden termín v týdnu — **den**, **od** a **do** — a řádků může být víc: série, která se schází v pondělí i ve středu, dostane lekci v obou dnech. Časy se přitom nemusí shodovat, takže zvládnete i běh ve středu od 9:00 a ve čtvrtek od 10:30. Vedle rozvrhu se vybírá **místnost**, do které se lekce naplánují.

Rozvrh se **ukazuje klientům na webu** u kurzu (nahoře v info panelu i na kartě v přehledu kurzů a v shrnutí nad přihláškou), takže ho vyplňte, i když lekce zadáváte po jedné. Bez rozvrhu se série chová jako dřív — jen se u ní na webu neobjeví den a čas a nejde spustit generování lekcí.

V seznamu sérií je rozvrh ve sloupci **Rozvrh**; kde chybí, stojí *Nenastaveno* — hledejte podle toho série, u kterých ho ještě nikdo nedoplnil. Stejný řádek najdete i v detailu série, tam s místností navíc.

> Rozvrh říká, kdy se série schází pravidelně. Skutečné termíny jsou lekce — když jednu z nich posunete jinam, rozvrh se tím nemění. Chcete-li, aby si klient přečetl správný den, opravte rozvrh; jednotlivé posuny vidí ve výpisu *Termíny lekcí* na stránce kurzu.

## Lekce

Lekce jsou jednotlivá setkání série. Buď je **vygenerujete z rozvrhu**, nebo je přidáte po jedné tlačítkem *Přidat lekci*. Dají se kdykoli smazat i posunout jednotlivě — když třeba jeden týden odpadá kvůli svátku, lekci prostě smažete.

Lekce zabírá místnost a lektora stejně jako rezervace, takže se do kalendáře promítne a hlídá se u ní konflikt.

## Generování lekcí

Když má série vyplněný rozvrh, objeví se na záložce *Lekce* tlačítko **Vygenerovat lekce**. Před spuštěním vám okno řekne, kolik termínů z rozvrhu vychází a kolik z nich se doopravdy založí.

Uložíte-li novou sérii rovnou s rozvrhem, zeptá se panel sám hned po uložení, jestli k ní lekce vygenerovat.

Generování se dá pouštět **opakovaně** a nikdy nic nepřepíše — doplní jen to, co chybí:

- Zakládají se jen dny z rozvrhu, které padnou **mezi začátek a konec série**. Chcete-li lekce dál, posuňte nejdřív **Konec** série a spusťte generování znovu.
- Termín, který už lekci má, se **přeskočí** — i když ji někdo posunul na jiný čas nebo do jiné místnosti. Jen u dne, na kterém má série v rozvrhu dva různé časy, se hlídá i ten čas, aby se založily obě skupiny.
- **Smazané lekce se neobnovují.** Zrušená lekce zůstane zrušená i po dalším spuštění, takže si vynechaný svátek nemusíte hlídat.
- Vygenerované lekce převezmou **lektora a místnost ze série**. U jednotlivé lekce se to pak dá změnit.
- Najednou se založí nejvýš 200 lekcí. Když na to narazíte, zkontrolujte, jestli má série správně vyplněný konec.

Když má série přihlášené účastníky, nové lekce se jim rovnou objeví v docházce. Pamatujte také, že **cena pro nově příchozí** se počítá z poměru zbývajících lekcí — generováním se tedy změní. Nejklidnější pořadí je vygenerovat lekce dřív, než se začnou lidé hlásit.

Tlačítko je vidět i u série bez rozvrhu, ale nejde stisknout — napoví, že je potřeba nejdřív vyplnit záložku *Rozvrh*.

## Sloupec Obsazenost

U série i u lekce ukazuje **Obsazenost** proužek s číslem nad ním. Číslo je **volná místa z celkové kapacity** — `20/20` znamená prázdnou sérii, `0/20` plnou. Proužek se plní tím, jak místa ubývají, a mění barvu: zelená do 40 % obsazení, oranžová do 80 %, červená nad 80 %. Šedý proužek znamená, že kapacita není vyplněná.

Proužek je vždy stejně dlouhý, i když je prázdný nebo úplně plný — porovnáváte tak sérii se sérií na první pohled. Najetím myší se ukáže i obsazení číslem.

## Stav série

Série má stav (připravuje se, probíhá, ukončená) a viditelnost. Historické série z importu jsou vedené tak, aby se na webu nezobrazovaly — slouží jen jako doklad, kdo co absolvoval.

## Jednorázové akce

Workshopy a jednorázové lekce nejsou kurz se sérií — mají vlastní agendu **Akce** s vlastními kategoriemi. Fungují podobně, ale mají jediný termín a jednodušší přihlašování.

Fotky se u nich vyplňují stejně jako u kurzu — na záložce *Veřejný prodej* jsou tytéž dvě pole. U lekce, která patří ke kurzu, je můžete nechat prázdné a použije se fotka kurzu.
