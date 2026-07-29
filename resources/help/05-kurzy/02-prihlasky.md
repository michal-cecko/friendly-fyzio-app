---
title: Přihlášky a platby za kurz
icon: heroicon-o-clipboard-document-check
keywords: [přihláška, přihlásit, zrušit přihlášku, zaplaceno, nezaplaceno, kapacita]
---

Přihláška spojuje klienta se sérií nebo akcí.

## Stavy přihlášky

| Stav | Význam |
| --- | --- |
| **Aktivní** | Klient je zapsaný a počítá se do kapacity. |
| **Náhradník** | Je v pořadníku, místo zatím nemá. |
| **Zrušeno** | Přihláška neplatí, místo je volné. |

## Platba se neupravuje ručně

Stav zaplacení se u přihlášky **needitoval ručně** — odvozuje se z evidovaných plateb. Chcete-li přihlášku označit jako zaplacenou, zaevidujte platbu; stav se přepne sám. Stejně tak se sám vrátí, když platbu smažete.

Proto přihláška nemá klasickou editaci — mění se přes akce (zrušit, obnovit, poslat e-mail), ne přepisováním polí.

## Nezaplacené přihlášky

Přihlášky, které zůstanou nezaplacené déle, než dovoluje nastavení, se automaticky ruší a místo se uvolní pro náhradníky. Lhůta se nastavuje v **Nastavení → Přihlášky**.

## Do kdy se klient může odhlásit sám

U **kurzu** platí jedna lhůta pro všechny: **Nastavení → Přihlášky → Odhlášení z kurzu (dní předem)**.

U **jednorázových akcí** se lhůta liší podle typu akce, proto se skládá ze tří úrovní — platí ta nejbližší, která je vyplněná:

1. **Konkrétní akce** – pole *Odhlášení klientem (hodin předem)* na lekci. Použijte, když jedna akce potřebuje víc času než ostatní ve své kategorii.
2. **Kategorie akcí** – stejné pole u kategorie (Kurzy → Kategorie akcí). Sem patří běžné rozdíly: jednorázová lekce 48 hodin, workshop třeba 168.
3. **Nastavení → Přihlášky → Odhlášení z akce (hodin předem)** – výchozí hodnota pro kategorie, které si nic nenastavily.

Klient vidí konkrétní datum a čas u akce na webu i u své přihlášky v klientské zóně. Po uplynutí lhůty se tlačítko *Odhlásit se* skryje — zrušit přihlášku pak může jen personál, a to bez ohledu na lhůtu.

> Nepleťte si to s **Včasným zrušením (hodin předem)** u kurzu. To neřeší odhlášení z celého kurzu, ale omluvu z jedné lekce a nárok na náhradní vstup.

## Zrušení a obnovení

Zrušená přihláška jde **obnovit**, pokud je v sérii místo. Zrušení lze provést i natvrdo (smazat), pokud šlo o omyl a nechcete ho mít v historii.

> **Kapacitu hlídá systém, ne vy.** Když je série plná, přihláška z webu spadne rovnou mezi náhradníky. V administraci můžete kapacitu překročit vědomě — bude to vidět.
