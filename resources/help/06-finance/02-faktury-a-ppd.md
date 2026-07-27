---
title: Faktury, PPD a číselné řady
icon: heroicon-o-document-text
keywords: [faktura, doklad, PPD, číselná řada, QR kód, IBAN, PDF, číslování]
---

## Faktura

Fakturu vystavíte k platbě nebo přímo k rezervaci či přihlášce. Údaje o vaší firmě se přebírají z **Nastavení → Fakturace**.

PDF se generuje na vyžádání a **neukládá se** — vždycky vznikne znovu z aktuálních dat. Náhled si můžete prohlédnout, aniž byste soubor stahovali.

## Příjmový doklad (PPD)

Pro platby v hotovosti se doklad vystaví **automaticky**. Není potřeba ho zakládat ručně.

## Číselné řady

Číslování dokladů řídí číselné řady — samostatně pro faktury a samostatně pro PPD. Řada určuje formát čísla a kde se začíná. Na začátku roku zpravidla zakládáte novou řadu.

Číslo přidělené dokladu se už nemění, i kdybyste doklad později upravili.

## QR platba

QR kód se na fakturu vygeneruje jen tehdy, když je způsob platby **QR platba**. U hotovosti nedává smysl.

Číslo účtu se bere z faktury, pokud ho má uložené; jinak z nastavení. Zkontrolujte si po nasazení, že v **Nastavení → Platby** je vyplněný ostrý IBAN — během vývoje tam bývá testovací hodnota.

> **Částky jsou v celých korunách.** Systém nepracuje s haléři.
