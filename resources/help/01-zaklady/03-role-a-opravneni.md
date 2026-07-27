---
title: Role a oprávnění
icon: heroicon-o-shield-check
keywords: [role, oprávnění, admin, terapeut, lektor, práva, přístup, tržby, co vidí lektor, mazání]
---

Oprávnění se v systému skládají z **schopností**. Jeden účet jich může mít víc najednou — nejde o jedinou roli, ze které si musíte vybrat.

| Schopnost | Co umožňuje |
| --- | --- |
| **Super administrátor** | Vše včetně správy rolí a oprávnění. |
| **Administrátor** | Přístup do celé administrace. |
| **Terapeut** | Vlastní rezervace a vlastní záznamy v kalendáři; klienti jsou společní pro celý tým. Objeví se v nabídce terapeutů. |
| **Lektor** | Může být uvedený jako vyučující u kurzu, série i jednotlivé lekce — a to, co vede, si sám spravuje. |
| **Přehled tržeb** | Zpřístupní finanční přehledy komukoli, kdo není administrátor. |

Kombinace jsou běžné a záměrné: fyzioterapeutka, která zároveň vede kurz, má **Terapeut + Lektor**. Majitelka, která také ošetřuje, má **Administrátor + Terapeut**.

## Zákazník je něco jiného

Být klientem není schopnost. Kdokoli — i členka týmu — může být zároveň vedená jako klientka a mít vlastní rezervace. Tyto dvě identity se nepřekrývají a nepřekážejí si.

## Co vidí terapeut

Účet, který má jen schopnost **Terapeut**, vidí zúžený panel: v seznamu rezervací má svoje termíny, finance ani nastavení nevidí vůbec. Klienti jsou naopak společní — kartu klientky si otevře a upraví kdokoli z týmu.

Celá agenda kurzů je navíc podmíněná schopností **Lektor** — terapeutka, která neučí, ji v menu nemá vůbec.

## Terapeut v kalendáři

Kalendář je výjimka: terapeutka v něm vidí **provoz celé ambulance**, ne jen sebe. Bez toho by neviděla, která místnost je obsazená.

Cizí záznamy jsou ale jen ke čtení — jsou zesvětlené a kliknutí je neotevře. Upravovat a mazat může jen to, co je její:

| Záznam | Čí je |
| --- | --- |
| **Rezervace** | Té terapeutky, která je u rezervace uvedená. |
| **Pracovní doba** | Té terapeutky, které blok patří. |
| **Blokace místnosti** | Toho, kdo ji založil. Pronájmy, importované a administrátorem zadané blokace nepatří nikomu z terapeutů — ti si je jen přečtou. |
| **Lekce kurzů a akce** | Lektora, který je vede; v kalendáři jsou stejně jen náhled. |

Platí to i pro hromadný výběr: cizí kartu do výběru nejde přidat, takže ji hromadné zrušení ani smazání nemůže potkat.

Administrátor tímhle omezený není — v kalendáři spravuje všechno.

## Co vidí a smí lektor

Kdo učí a není administrátor, má v menu rovnou položky **Kurzy** a **Lekce** — nezabalují se do společné sekce, protože nic jiného z ní stejně nevidí. A vidí v nich jen to, co sám vede; cizí kurzy, série a lekce se mu vůbec nenačtou.

Vede to, u čeho je uvedený jako lektor:

| Uvedený u | Dostane |
| --- | --- |
| **Kurzu** | celý kurz, všechny jeho série a lekce, přihlášky i docházku |
| **Série** | tuhle jednu sérii, její lekce, přihlášky a docházku — a k tomu kurz, ve kterém série je (jinak by se k ní neproklikal) |
| **Lekce** | tuhle jednu lekci a její docházku |

Co s tím může dělat:

- **Sérii a lekci upravuje** — termín, místnost, kapacitu, cenu. Může účastníkům rozeslat e-mail, poslat pozvánku a zapsat docházku.
- **Kurz a přihlášky si jen čte.** Popis kurzu, ceník ani stav přihlášky nepřepíše — to zůstává administrátorovi.
- **Mazat nemůže nic.** Smazání kurzu, série i lekce je vždy na administrátorovi.

Uvedení lektora u série nikoho nepřipravuje o přístup: lektor kurzu vidí i sérii, kterou předal kolegyni.

## Přidání schopnosti

Schopnosti se nastavují na kartě uživatele v sekci **Provoz → Uživatelé**, v části s oprávněními. Změna se projeví hned po dalším načtení panelu.

> **Terapeut potřebuje profil.** Aby se terapeutka objevila v kalendáři a v rezervačním formuláři, nestačí jí dát schopnost Terapeut — musí mít vytvořený profil s pracovní dobou. Bez pracovních bloků nemá kdy ordinovat a systém jí nenabídne žádný termín.
