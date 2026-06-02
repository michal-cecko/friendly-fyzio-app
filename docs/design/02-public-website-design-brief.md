# FriendlyFyzio - Public Website Content & Structure Brief

> Content documentation for Pencil.dev + Claude Opus. Describes what each page contains and the site structure. Design decisions are left to the designer.

---

## 1. Project Context

Complete redesign of www.friendlyfyzio.cz. The current site is on Webflow. The new site will be integrated into the Laravel application, powered by a CMS (block-based page builder in Filament admin). Content is dynamic - pulled from the database, not hard-coded.

The website serves as the clinic's public face AND the entry point for the reservation system. Every service page should guide users toward booking.

**Location:** FriendlyFyzio, Zednicka 1109/2, Ostrava-Poruba, Czech Republic
**Owner:** Mgr. Lucie Fickerova (+420 604 793 255, info@friendlyfyzio.cz)
**ICO:** 06816967

---

## 2. Brand Essentials

### Color Palette
| Token | Hex |
|-------|-----|
| Primary | `#ED86A3` |
| Primary Light | `#FFDBE5` |
| Neutral 900 | `#1A1A1A` |
| White | `#FFFFFF` |

### Typography
- **Headings:** Montserrat (Semi-Bold / Bold)
- **Body:** Open Sans or similar pairing with Montserrat

### Logo
- "Friendly" in regular weight, "*Fyzio*" in italic/script
- Pink accent (#ED86A3) on "Fyzio"

### Photography
- Warm, natural light, real clinic environment
- Women in pink/white activewear
- Real people, not stock photos

---

## 3. Site Map & Navigation

**Header navigation:** Fyzioterapie | Laser/kryoterapie | Pohybove kurzy | Relaxace | Workshopy | Cenik | O nas | [CTA: Chci se objednat]

**Top bar (optional):** Configurable announcement bar from admin.

**Footer:** Logo, nav links, contact info, social links, newsletter signup, copyright + ICO.

---

## 4. Pages & Content

### 4.1 Homepage

**Sections on the page:**
- Hero with main heading "Specializovana fyzioterapie", list of specializations (Tehotenska fyzioterapie, Fyzioterapie panevniho dna, Fyzioterapie celistniho kloubu, Fyzioterapie jizev), hero image, 3 CTAs: "Objednat vstupni vysetreni", "Chci na masaz", "Koupit darkovy poukaz"
- Announcement banners - dynamic, currently used for enrollment periods (e.g., "Prave probiha prihlasovani na lekce a kurzy leden-duben") with links to individual courses
- Services overview "Nase nabidka" - 4 categories: Fyzioterapie, Pohybove kurzy, Masaze a relaxace, Laser/kryoterapie
- Client testimonials "Doporuceni nasich klientu" - carousel of reviews
- Instagram feed "Sledujte nas na Instagramu"
- Currently enrolling courses/workshops - dynamic from database
- Contact section with Mgr. Lucie Fickerova info, CTAs, address
- Newsletter signup
- Google Maps embed

### 4.2 Fyzioterapie (Overview)

**Content:**
- Intro: "Obor fyzioterapie se zabyva diagnostikou, lecbou a prevenci dysfunkci pohyboveho systemu cloveka."
- Clinic specializes in women's physiotherapy but also treats men
- Target groups: women with painful menstruation, infertility, pregnancy, postpartum, incontinence, pelvic organ prolapse, oncological conditions
- Also offers jaw therapy and scar therapy

**4 therapy types listed:**
1. **Terapie panevniho dna** - Terapie nesnadnych porodu, opakovanych potratu, inkontinence, poklesu organu male panve, bolestive nebo nepravidelne menstruace, kostrcoveho syndromu, vyherzu plotynek.
2. **Tehotenska fyzioterapie** - Kondicni a uvolnujici cviceni pro tehotne zeny, prevence negativnich dusledku tehotenstvi, priprava na porod, terapie poporodni diastazy a dalsich nasledku porodu a tehotenstvi.
3. **Terapie jizev** - Pece o jizvy po cisarskych rezech, nastrizich hraze, laparoskopiich, plastikach...ty cerstve i ty zapomenute. Osetreni jizev domacim prostredim nebo primo v nemocnici.
4. **Terapie celistniho kloubu** - Dysfunkce celistniho kloubu mohou byt pricinou mnoha obtizi, napr. bolesti hlavy, dechovych obtizi, bolesti krcni patere a ramenniho pletence nebo i dysfunkci svalu panevniho dna.

CTA to booking. Contact section.

### 4.3 Therapy Subpages (4 pages, same template)

**Pages:**
- `/fyzioterapie/terapie-panevniho-dna`
- `/fyzioterapie/tehotenska-fyzioterapie`
- `/fyzioterapie/terapie-jizev`
- `/fyzioterapie/terapie-celistniho-kloubu`

**Each contains:**
- Page title + featured image
- Rich text content from CMS (symptoms, conditions, what therapy involves)
- CTA to booking
- Reviews section "Ohlasy klientek" - testimonials specific to this therapy type
- Contact section

**Example content (Terapie panevniho dna):**
- Symptoms: incontinence, pelvic pain, postpartum issues, prolapse, painful intercourse, constipation
- Diagnosis via questioning + physical examination
- Initial assessment is comprehensive, controls follow regularly
- Therapy includes pelvic floor control check after birth, pelvic exercises, scar treatment etc.

### 4.4 Pristrojova terapie (Overview)

**Content:**
- Page title "Pristrojova terapie"
- Two services listed:
  1. **Lokalni kryoterapie** - Urychleni hojeni akutnich bolestivych stavu chladem. Okamzity efekt pri urazech svalu, kloubu a slach. Podpora lecby pooperacnich stavu. Rychle zmirneni bolesti, lecba otoku, zanetu slach a kloubu.
  2. **Laseroterapie** - Presne zacileni terapeuticke casti laserovym zarenim do hloubky az 4 cm. Uleva od bolesti, urychleni a podpora hojeni postizene tkane, redukce otoku. Podpora a urychleni hojeni pooperacnich stavu.
- Note: these are supplementary services, booking by phone only
- Contact section

### 4.5 Pristrojova terapie - Subpages (2 pages)

- `/pristrojova-terapie/lokalni-kryoterapie`
- `/pristrojova-terapie/laseroterapie`

Each: detailed description, how it works, for whom, note about phone-only booking. Contact section.

### 4.6 Pohybove kurzy / Lekce (Overview)

**Intro text:** Overview of group exercise classes led by physiotherapists.

**Course categories (each with name, description, active/inactive status):**

1. **Pro zeny po rakovine prsu** - Physiotherapeutic exercise for women after breast cancer treatment
2. **Pro tehotne zeny** - Conditioning for pregnant women, group exercise
3. **SM a CORE system** - Training deep stabilization system, "inner corset" training
4. **Mami&Mimi** - Exercise for mothers with babies, supports postpartum recovery
5. **Joga** - Hormonalni joga, Somaticka joga, Jin joga
6. **Mobility&Stretch** - Stretching and mobility focused classes
7. **Restart po cisarskem rezu** - Specialized rehabilitation after C-section
8. **Principy pohybu pro zacatniky** - Movement fundamentals for beginners

**Inactive courses** shown differently (gray/muted) as "Momentalne neprihlasujeme" or "Coskoro"

**Below:** Participant testimonials "Ohlasy ucastniku"
Contact section.

### 4.7 Course Category Detail Pages (8 pages)

**Pages:**
- `/fyzio-kurzy/joga`
- `/fyzio-kurzy/pro-tehotne`
- `/fyzio-kurzy/sm-core-system`
- `/fyzio-kurzy/mami-mimi`
- `/fyzio-kurzy/mobility-stretch`
- `/fyzio-kurzy/restart-po-cisarskem-rezu`
- `/fyzio-kurzy/principy-pohybu`
- `/fyzio-kurzy/pro-zeny-po-rakovine-prsu`

**Each contains:**
- Category name + hero image
- List of course types within the category
- CTA to sign up
- Per course type: detailed description (multiple paragraphs), schedule (day, time), number of lessons, price per course, instructor name
- Links to sign up for specific courses or one-time lessons
- Important notice about substitute rules ("Nahrady vcas omluvenych lekci jsou mozne v soubezne skupine")
- Contact section

**Example (Joga page) course types:**
- **Hormonalni joga** - Supports hormonal balance, suitable for PMS, menopause. Tuesday 16:30, 10 lessons, 2,000 Kc/kurz. Instructor: Simona Horinova.
- **Somaticka joga** - Return to body awareness, breathing, gentle movements. Tuesday 18:45, 260 Kc/lekce. Instructor: Kristyna Cerna.
- **Jin joga** - Slow, passive yoga for regeneration and flexibility. Tuesday 17:45 + Thursday 18:00, 14-15 lessons, 2,800 Kc/kurz. Instructor: Simona Horinova.

### 4.8 Relaxace / Masaze (Overview)

**Intro:** "V dnesnim uspechanem svete je cim dal tim tezsi najit si cas sama pro sebe a jen tak byt."

**Services listed:**
1. **Masaze dospelych** - Klasicke relaxacni masaze, tehotenske masaze, lymfaticke masaze, rebozo
2. **Bylinna naparka** - Prijemny vonovy ritual s salkem praveho kakaa ci sklenici cerveneho vina. A co treba naparku doplnit masazi?
3. **Jin joga** - Jemna, pomala a pasivni forma jogy, uci odpocivat a respektovat sve telo. Podporuje regeneraci.
4. **Masaze miminek a deti** - Podporuji traveni, ulevuji pri kolikach, zmirmuji nadymani, zlepsmuji spanek.

CTA to massage booking. Contact section.

### 4.9 Relaxace - Subpages (4 pages)

- `/relaxace/masaze-dospelych`
- `/relaxace/bylinna-naparka`
- `/relaxace/jin-joga`
- `/relaxace/masaze-miminek-a-deti`

Each: detailed description, what to expect, duration, for whom, CTA to booking, reviews. Contact section.

### 4.10 Workshopy (Overview)

**Heading:** "Workshopy"
**Subtitle:** "Vzdelavejte se pod dohledem zkusenych fyzioterapeutek!"

**Current workshops:**
1. **Detska noha v pohybu** - Child foot development, milestones, footwear guidance, podoscope diagnostics. When to see a specialist.
2. **Zaklady pohybu: fyzioterapeuticky pohled na dospelou nohu** - Foot influence on body posture, flat feet, barefoot vs shoes, plantar fascia, insoles.
3. **Handling - manipulace s novorozencem** - How to handle a newborn: lifting, positioning for day and sleep, preventing asymmetry.

Each with status (active with date / inactive). Contact section.

### 4.11 Workshop Detail Page

Per workshop:
- Full description
- Date, time, location
- Capacity (spots remaining)
- Price
- Instructor info
- Registration form / CTA
- Reviews from past attendees
- If full: waitlist option
- Contact section

### 4.12 Cenik (Pricing)

**Tabs:** Fyzioterapie a kurzy | Masaze | Laser/kryo | Ostatni

**Fyzioterapie a kurzy tab:**
| Service | Price | Duration |
|---------|-------|----------|
| Fyzioterapie - vstupni vysetreni | 1,750 Kc | 90 min |
| Fyzioterapie - kontrolni vysetreni/terapie | 1,250 Kc | 55 min |
| Fyzioterapie - kontrolni vysetreni (balicek 5ks, platnost 6 mesicu) | 5,750 Kc | 55 min |
| Fyzio pohotovost (terapie do 24 hodin) | + 600 Kc | 55 min |
| Terapie v domacim prostredi po brisnich operacich | 1,300 Kc + 7 Kc za km | 60 min |
| Kinesiotaping mimo terapii | 3 Kc | 1 cm |
| Cross tape | 5 Kc | ks |
| Fyzio kurzy 3-5 osob | 220 Kc | 1 lekce |
| Fyzio kurzy 6-10 osob | 200 Kc | 1 lekce |
| Jednorazovy vstup na lekci | 260 Kc | 1 lekce |
| Individualni kondicni trenink/lekce Principy pohybu/SM System (az pro 2 osoby) | 850 Kc | 1 lekce |

**Masaze tab, Laser/kryo tab, Ostatni tab** - similar pricing rows from admin.

**Gift vouchers mention:** "Darkove poukazy" with link.

**Storno podminky:**
- "Vazeni klienti, domluvene terminy jsou zavazne."
- "Omluva po 17 hodine predchoziho dne/v den terapie je prijimana pouze ze zdravotnich duvodu potvrzenych lekarem."
- "V opacnem pripade je klient povinen uhradit 100% z ceny terapie."

**Payment note:** Cash or QR code with immediate transfer. No insurance. No prescription requests.

Contact section.

### 4.13 Nas tym (Our Team)

**Heading:** "Tym Friendly Fyzio"
**Team group photo**

**Team members:**

1. **Mgr. Lucie Fickerova** - Owner, physiotherapist. Specializations: fyzioterapie panevniho dna, poporodni fyzioterapie, tehotenska fyzioterapie. Contact: phone, email. Detailed bio.
2. **Mgr. Renata Barova** - Physiotherapist. Specializations: fyzioterapie panevniho dna, poporodni fyzioterapie. Bio.
3. **Karolina Krystufkova** - Physiotherapist. Specializations listed. Bio.
4. **Mgr. Sarka Matchova** - Physiotherapist. Specializations: SM system, celistni kloub. Bio.
5. **Mgr. Daniela Balusikova** - Physiotherapist. Specializations listed. Bio.
6. **Simona Horinova** - Instructor (joga, kurzy). Bio.
7. **Kristyna Cerna** - Instructor, masseuse. Bio.
8. **Blanka Cerna** - Yoga instructor. Bio.
9. **Denisa Neuwirthova** - Masseuse. Bio.

**Section: "Spolupracujici terapeute"** - collaborating external therapists.

Contact section.

### 4.14 Rezervace vstupniho vysetreni (Physiotherapy Booking)

**Information section:**
- "Pred vyberem pro vas mame par informaci"
- Komplexni fyzioterapeuticke vysetreni a terapie zahrnuje vstupni vysetreni, diagnostiku pohyboveho systemu a naslednou terapii.
- Vstupni vysetreni trva 90 minut
- Lze vyuzit tehotenske a poporodni prispevky z pojistovny, fond FKSP ci Benefit
- Jsme zdravotnicke zarizeni, radi Vam dame razitko na propustku do prace

**Step 1 - Choose therapy type and therapist:**
- Tehotenska fyzioterapie / priprava k porodu - Renata, Ema, Lada, Daniela
- Terapie panevniho dna/poporodni fyzioterapie - Renata, Sarka, Ema, Daniela
- Terapie jizev - Renata, Sarka, Ema, Daniela
- Terapie temporomandibularniho (celistniho) kloubu - Sarka
- SM system - Sarka, Ema

**Step 2 - Booking form:**
- Select available time slot (date + time + therapist)
- First name, Last name
- Problem description / week of pregnancy
- Phone (for confirmation), Phone (backup)
- Email
- Checkbox: Agree to cancellation terms
- Checkbox: Subscribe to newsletter
- Submit: "Zavazna objednavka"

**Alternative booking paths (from spec, not on current site yet):**
- Choose therapist first -> see their calendar
- Choose time first -> see all available across therapists

**Fallback:** "Nevyhovuje Vam zadny termin? Kontaktujte nas na tel. cisle: +420 604 793 255"

Contact section.

### 4.15 Rezervace masazi (Massage Booking)

**Two booking forms on the page:**

**Form 1 - Masaze:**
- Info: Denisa - tehotenske, klasicke relaxacni a lymfaticke masaze (pouze zeny) - 60 min/90 min. Bylinne naparky, masaze miminek a deti.
- Note: if specific service/duration is in the slot name, cannot change. Otherwise choose freely.
- Select: Available time slot (date + time + therapist)
- Fields: Name + surname, Notes (type of massage), Phone (confirmation), Phone (backup), Email
- Checkboxes: Terms, Newsletter
- Submit: "Zavazna objednavka"

**Form 2 - Rezervace bylinne naparky:**
- Same therapist info
- "Suggest your own time" dropdown option
- Fields: Name, Surname, Notes (e.g., "naparka, naparka s masazi..."), Phone
- Checkboxes: Terms
- Submit: "Zavazna objednavka"

**Fallback contact note.** Contact section.

### 4.16 Course/Workshop Registration Page

Dynamic page per course/workshop:
- Course/workshop summary (name, schedule, price, spots remaining/capacity)
- Registration form: Name, Surname, Email, Phone, Note
- QR code for payment (auto-generated with variable symbol)
- Terms agreement checkbox
- Submit
- Post-registration: confirmation message + email with QR payment details

### 4.17 Darkove poukazy (Gift Vouchers)

**Current voucher options:**
| Voucher | Price |
|---------|-------|
| Darkovy poukaz na relaxacni masaz 90 min | 1,300 Kc |
| Darkovy poukaz na tehotenskou masaz 90 min | 1,400 Kc |
| Darkovy poukaz na vstupni vysetreni s fyzioterapeutem | 1,750 Kc |
| Darkovy poukaz na vstupni vysetreni a 3 kontrolni terapie | 5,500 Kc |
| Darkovy poukaz na vstupni vysetreni a 5 kontrolnich terapii | 7,500 Kc |
| Volny poukaz (na vsechny sluzby Friendly Fyzio) | 2,000 Kc+ |
| 7x osetreni laserem s kryoterapii | 2,800 Kc |

Each: selectable, quantity selector, price display. Total calculation.

Payment method: Bank transfer.

Billing form: Email, First name, Last name, Street, City, ZIP, Country, Recipient name.

**Note:** MVP phase may redirect to SimpleShop. Later phase: built-in purchase + PDF voucher generation.

### 4.18 Login Page

- Logo
- Email + Password fields
- Login button
- "Forgot password?" link
- Note: account is created automatically upon first reservation

---

## 5. Shared Page Elements

These elements appear across multiple pages:

### Contact Section
Present on every page. Contains:
- Mgr. Lucie Fickerova
- +420 604 793 255
- info@friendlyfyzio.cz
- CTA: "Online rezervace vstupniho vysetreni"
- CTA: "Online rezervace masazi"
- Address: FriendlyFyzio, Zednicka 1109/2, Ostrava-Poruba
- ICO: 06816967
- Photo of Lucie

### Google Maps
Clinic location embed. Address: Zednicka 1109/2, Ostrava.

### Reviews/Testimonials
Used on: therapy subpages, course pages, workshop pages, homepage.
Each review: client quote text, client name. Some with link to full review.

### Newsletter Signup
Heading: "Prihlaste se k odberu novinek (kurzy, workshopy, atp.) Chcete se o nich dozvedet jako prvni?"
Email input, submit button, consent checkbox.

### CMS Blocks (admin-configurable)
The following block types can be placed on any page by the admin:
- WYSIWYG rich text
- Hero banner (heading, subtitle, CTAs, background)
- Reviews/testimonials
- Service category cards
- Service list (dynamic)
- Active courses (dynamic with capacities)
- Workshops list (dynamic)
- Team profiles
- Instagram feed
- Contact/registration form
- Map
- CTA section
- Banners (image, link, visibility dates)
- Modals (trigger: timer or exit intent, configurable frequency)
- Top bar (short announcement, link, configurable)

---

## 6. Content Notes

- **Language:** Czech (cs). All UI text in Czech.
- **Inactive items:** Courses/workshops that are currently not enrolling should be visible but visually muted, labeled "Momentalne neprihlasujeme" or "Coskoro".
- **Booking flow:** Therapy and massage reservations are booked via forms on the site. Courses and workshops are enrolled in via registration pages with QR payment. Laser/cryo is phone-only.
- **Payments:** Therapies and massages are paid on-site after the service. Courses, lessons, and workshops are paid in advance via QR bank transfer.
- **Cancellation:** Terms vary per service type, configured in admin. Currently: 17:00 day before, health reasons only accepted day-of.

---

## 7. Current Website Reference

Screenshots and content snapshots from www.friendlyfyzio.cz saved in:
- `docs/website-screenshots/` - Full-page PNG screenshots
- `docs/website-content/` - Accessibility snapshots with full text content

**Pages captured:**
- homepage-full.png / homepage.md
- fyzioterapie.png / fyzioterapie.md
- pristrojova-terapie.png / pristrojova-terapie.md
- fyzio-kurzy.png / fyzio-kurzy.md
- kurz-joga.png / kurz-joga.md
- relaxace.png / relaxace.md
- workshopy.png / workshopy.md
- cenik.png / cenik.md
- nas-tym.png / nas-tym.md
- rezervace-vstupni.png / rezervace-vstupni.md
- rezervace-masazi.png / rezervace-masazi.md
- darkove-poukazy.png / darkove-poukazy.md
- terapie-panevniho-dna.png / terapie-panevniho-dna.md

---

## 8. Design Deliverables

All pages listed above need to be designed. Total: 32 pages.

**Each page:** Desktop + Mobile versions.
