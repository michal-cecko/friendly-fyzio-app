# FriendlyFyzio OS - Dashboard Design Brief

> Design document for Pencil.dev + Claude Opus. Covers the Filament admin panel with role-based views.

---

## 1. Project Context

FriendlyFyzio is a physiotherapy clinic in Ostrava-Poruba, Czech Republic, specializing in pelvic floor therapy, pregnancy physiotherapy, jaw therapy, scar therapy, movement courses, massages, and workshops. The current workflow uses a mix of Ergobody, Excel, SimpleShop, and paper. This new system replaces everything with a single Filament-based admin panel.

**Tech stack:** Laravel 13, Filament 5, Laravel Octane (RoadRunner), PostgreSQL, Redis, Filament Shield for role-based access.

**Single panel architecture:** One `/admin` panel. Filament Shield controls which resources/pages each role sees. No separate panels.

---

## 2. Brand & Visual Identity

### Color Palette
| Token | Hex | Usage |
|-------|-----|-------|
| Primary | `#ED86A3` |

Other colors are default colors of filamentphp.

### Typography
- **Headings:** Montserrat (Semi-Bold / Bold)
- **Body:** Inter (Filament default) or Open Sans
- **Monospace:** For invoice numbers, codes: JetBrains Mono

### Logo
- "Friendly" in Montserrat regular, "*Fyzio*" in italic/script style
- Pink accent on "Fyzio"
- Used in sidebar header

---

## 3. Roles & Access Overview

### ADMIN (Spravce)
Full access to everything. Manages the entire clinic system.

### THERAPIST (Terapeutka / Lektorka)
Sees their own calendar, clients, therapy records, courses they teach. Cannot access financials, system settings, or other therapists' private data.

### CUSTOMER (Klient)
Sees their own profile, reservations, payment history, substitute tokens, credit balance, invoices, and anamnesis. Cannot access any admin functions.

---

## 4. Dashboard Pages - ADMIN Views

### 4.1 Admin Dashboard (Home)
**Purpose:** At-a-glance clinic overview.

**Widgets:**
- **Today's Schedule** - Timeline of all therapists' appointments today (color-coded by therapist)
- **Stats Row:** Today's appointments count | Pending payments | New registrations this week | Active courses
- **Revenue Chart** (Apex Charts) - Weekly/monthly revenue with breakdown by service type
- **Upcoming Conflicts** - Room double-bookings or therapist overlaps (danger cards)
- **Unpaid Reservations** - List of overdue payments needing attention
- **Waitlist Activity** - Recent waitlist movements (someone cancelled, next person notified)
- **Quick Actions:** Create reservation | Add client | Block calendar | Generate invoice

### 4.2 Calendar / Schedule Management
**Purpose:** Visual overview of all therapists' schedules.

**Layout:**
- **Full-width calendar view** (week/day toggle)
- **Left sidebar filter:** By therapist, by room, by service type
- **Color coding:** Each therapist has a unique color. Blocked time = gray striped. Breaks = light gray.
- **15-minute block grid** visible in day view
- **Click-to-create:** Click on empty block to create reservation
- **Drag-to-reschedule:** Drag existing reservation to new time
- **Room view toggle:** Switch to room-based view showing all rooms as columns
- **Conflict indicators:** Red border on overlapping reservations

### 4.3 Clients (Klienti)
**Resource: Client**

**List view columns:**
- Avatar (initials) | Full name | Email | Phone | Tags (VIP, new, etc.) | Credit balance | Last visit | Actions

**Detail/Edit tabs:**
- **Profile** - Name, surname, email, phone, date of birth, address (city), occupation, weight, height, company billing (ICO, DIC, billing address)
- **Anamnesis** - WYSIWYG editor (managed by therapist, not visible to client in current phase)
- **Therapy Records** - List of all sessions with WYSIWYG notes per session
- **Reservations** - Upcoming + history, filterable
- **Payments** - All payments with status, method, linked invoice
- **Credit** - Current balance, validity, history of charges/deductions
- **Substitute Tokens** - Active tokens, used tokens, expired tokens
- **Invoices** - All invoices with PDF download
- **Notes** - Internal notes visible only to staff

### 4.4 Reservations (Rezervace)
**Resource: Reservation**

**Tabs:** All | Physiotherapy | Massages | Courses | One-time Lessons | Workshops

**List columns:**
- Date/Time | Client | Service | Therapist/Instructor | Room | Status (Confirmed/Pending/Cancelled) | Payment status | Actions

**Filters:** Date range, therapist, service type, status, payment status

**Create form:**
- Service type selector (tiles with icons)
- Client search/create
- Therapist selector
- Date/time picker (shows only available slots per 15-min block logic)
- Room auto-assigned based on therapist + day
- Notes field
- Recurring option (for control therapies: repeat weekly for X weeks)

### 4.5 Services Management (Sluzby)
**Resource: Service**

**Service types:** Physiotherapy | Massage | Course | One-time Lesson | Workshop

**Fields per service:**
- Name, description (WYSIWYG), category, duration (in 15-min blocks), price
- Break after service (in 15-min blocks, per therapist)
- Assigned therapists
- Assigned rooms (which rooms are suitable)
- Cancellation rules (hours/days before, auto-cancel on non-payment days)
- Visibility: Public / Logged-in only / Private (staff only)
- Photo/thumbnail
- Active/inactive toggle
- Custom email sender address

### 4.6 Courses Management (Kurzy)
**Resource: Course**

**List:** Course name | Category | Status (Active/Inactive/Full) | Enrolled/Capacity | Series count

**Detail tabs:**
- **Info** - Name, category, description, photo, active/inactive, price
- **Series** - List of series with date ranges, each containing individual lessons with dates/times
- **Enrollments** - List of enrolled clients per series
- **Waitlist** - Clients waiting for a spot
- **Substitute Rules** - Max substitutions per course, which other courses accept tokens, early cancellation deadline
- **Reviews** - Client feedback/testimonials shown on public site
- **Pre-sale Links** - Generate hidden links for early access

**Lesson detail:**
- Date, time, instructor, room, attendees list (who's coming, who cancelled)
- Ability to reschedule individual lesson + notify all participants

### 4.7 Workshops (Workshopy)
**Resource: Workshop**

Similar to courses but single-event. Fields: name, description, date, time, capacity, price, instructor, room, photo, active/inactive, reviews, waitlist.

### 4.8 Working Hours (Pracovna doba)
**Resource: TherapistSchedule**

**Per therapist:**
- **Weekly recurring schedule** - For each day: time blocks (e.g., Mon 8:00-12:00, 14:00-16:00)
- **Even/odd week toggle** - Different schedule for even vs odd weeks
- **Non-standard dates** - One-off additions (e.g., Saturday 9:00-13:00)
- **Calendar blocks** - Vacation, sick leave, training (multi-day support)
- **Room assignment per day** - Which room this therapist uses on which day

**Visual:** Weekly grid showing the schedule with room assignments as colored badges.

### 4.9 Rooms & Buildings (Mistnosti)
**Resource: Room (under Building)**

**Buildings:** Name, address, rooms list
**Rooms:** Name, building, capacity, suitable service types
**Room occupancy view:** Color-coded weekly grid per room showing all reservations

### 4.10 Financial Module

#### 4.10.1 Invoices (Faktury)
**List columns:** Invoice # | Date | Client | Amount | Service type | Payment method | Status (New/Sent/Paid/Overdue)

**Filters:** Date range, client, status, numbering series, service type

**Actions:** Download PDF | Send email | Mark as paid | Bulk download ZIP | Export Excel

**Invoice settings:** Multiple numbering series (e.g., FT-2026-001 for therapy, KU-2026-001 for courses). Auto-reset yearly.

**Cash receipt (PPD):** Separate numbering series, auto-generated on cash payment.

#### 4.10.2 Payments (Platby)
**Overview:** All payments across the system.
- QR payment matching (via IMAP reading Air Bank email notifications)
- Manual cash recording by therapist
- Credit deduction by therapist
- Automatic payment status tracking

#### 4.10.3 Credit System (Kredity)
**Per client management:**
- Add credit (manual by therapist, or from gift voucher)
- Deduct credit (one-click by therapist after service)
- Credit validity (configurable, e.g., 6 months from charge)
- Expiration tracking and notifications
- Full history log

### 4.11 CMS - Content Management
**Resource: Page**

**Fixed pages** (cannot be deleted, only hidden): Homepage, Pricing, Team, Contact
**Custom pages:** Admin can create new pages with custom slug, meta title, meta description

**Block builder (flexible content):**
Each page is composed of reorderable blocks (drag & drop):
- WYSIWYG Editor
- Hero Banner (heading, subtitle, CTA buttons, background image)
- Reviews/Testimonials
- Service Categories (cards with links)
- Service List (dynamic from database)
- Active Courses (auto-populated with capacities)
- Workshops (upcoming events)
- Team Profiles
- Instagram Feed
- Contact Form / Registration Form
- Map
- CTA Section

**Per block settings:** Visibility toggle, order, custom options (background color, columns, specific item selection)

**Special elements:**
- **Floating windows** - Image/Icon, Heading, description, link, visibility dates, placement
- **Fullscreen modals** - Image/Icon, Heading, description, link, visibility dates, placement
- **Top bar** - Short announcement text, link, color, hide toggle

**Navigation management:**
- **Header** - Add/edit/remove items, dropdown submenus
- **Footer** - Columns, links, text

### 4.12 Newsletter & MailerLite
**Integration page:**
- Auto-collect new client contacts
- Segment by service type
- View sync status
- Manual resync

### 4.13 Notifications (E-mail sablony)
**Resource: EmailTemplate**

Templates for each event type:
- Reservation confirmed
- Reservation reminder (24h before, configurable)
- Reservation cancelled/changed
- Waitlist spot available
- Payment received (with invoice PDF attached)
- Payment overdue reminder
- Substitute token generated
- Course lesson rescheduled

Per service type: custom sender email address.

### 4.14 Settings (Nastaveni)
- Clinic info (name, address, ICO, bank account for QR codes)
- Cancellation rules per service type
- Credit validity period
- Substitute token validity
- Reminder timing (hours before)
- Invoice numbering series configuration
- IMAP settings for Air Bank payment matching
- Google Calendar sync settings

---

## 5. Dashboard Pages - THERAPIST Views

Therapists see a filtered version of the admin panel. Shield hides resources they shouldn't access.

### 5.1 Therapist Dashboard (Home)
**Widgets:**
- **My Today** - Personal schedule for today (timeline)
- **My Week** - Upcoming appointments this week
- **My Stats** - This month's appointments, hours worked
- **Pending Notes** - Sessions missing therapy records

### 5.2 My Calendar
- Personal calendar (only their own schedule)
- Can create/edit their own reservations
- Can block their own time (vacation, sick)
- See room assignments
- Create recurring control therapy appointments

### 5.3 My Clients
- Only clients they have treated/are treating
- Full access to: anamnesis, therapy records, reservation history
- Can add credit, deduct credit
- Can manage substitute tokens

### 5.4 My Courses / Lessons
- Courses they instruct
- Attendee list per lesson
- Mark attendance
- Reschedule lesson + notify participants

### 5.5 Record Cash Payment
- Quick action: select client, enter amount, confirm
- Auto-generates invoice if requested

---

## 6. Dashboard Pages - CUSTOMER Views

Customers access the same `/admin` panel but see only their own data.

### 6.1 Customer Dashboard (Home)
**Widgets:**
- **My Upcoming** - Next reservations (cards with date, time, therapist, room, CTA to cancel if within deadline)
- **My Tokens** - Active substitute tokens with "Use token" action
- **My Credit** - Current balance + validity
- **Quick Actions:** View reservations | View invoices | Edit profile

### 6.2 My Profile (Muj profil)
- Name, surname, email, phone
- Change password
- Company billing details (ICO, DIC, billing address) - optional

### 6.3 My Reservations / Therapies (Moje rezervace)
**Tabs:** Upcoming | Past

**Card per reservation:** Date, time, service name, therapist, room, status, cancel button (if within cancellation window)

### 6.4 My Substitute Tokens (Nahradove tokeny)
- List of available tokens with expiry date
- "Use token" action -> shows available substitute slots in compatible courses
- Used tokens history

### 6.5 My Credit (Kredity)
- Current balance + expiry info
- History: date, description (charge/deduction/expiry), amount, resulting balance

### 6.6 My Payments (Platby)
- Payment history: date, amount, method, service, linked invoice

### 6.7 My Invoices (Faktury)
- List of all invoices with PDF download
- Status indicator (Paid / Overdue)

### 6.8 My courses (Courses)
- List of my courses, substitute tokens, waitlist status
- "Enroll" button (if available)

---

## 7. Key UI Patterns

### Navigation Structure (Sidebar)

**ADMIN sees:**
```
Dashboard
Calendar
---
Clients
Reservations
---
Services
Courses
Workshops
---
Rooms & Buildings
Working Hours
---
Invoices
Payments
Credits
---
Pages (CMS)
Banners
Navigation
---
Email Templates
MailerLite
---
Settings
Shield (Roles & Permissions)
```

**THERAPIST sees:**
```
Dashboard
My Calendar
---
My Clients
Reservations (filtered)
---
My Courses
---
Record Payment
```

**CUSTOMER sees:**
```
Dashboard
My Profile
---
My Reservations
My Tokens
My Credit
---
My Payments
My Invoices
```

### Calendar Component
- Weekly view as default, day view for detailed scheduling
- 15-minute block grid with snap-to-grid behavior
- Color-coded per therapist
- Gray striped = blocked time
- Light gray = break between sessions
- Red border = conflict detected
- Hover tooltip: client name, service, duration

### Status Badges
| Status | Color | Background |
|--------|-------|------------|
| Confirmed | Success green | Light green |
| Pending | Warning amber | Light amber |
| Cancelled | Danger red | Light red |
| Paid | Success green | Light green |
| Unpaid | Neutral gray | Light gray |
| Overdue | Danger red | Light red |
| Active | Primary pink | Primary light |
| Inactive | Neutral gray | Light gray |
| Full | Warning amber | Light amber |

### Form Patterns
- Service selection: visual tiles with icons/photos (not dropdowns)
- Client selection: searchable with auto-complete, option to create inline
- Date/time: calendar picker that shows only available 15-min slots
- WYSIWYG: Mason editor for rich content
- File uploads: drag & drop with preview (for photos, documents)

---

## 8. Filament Components Reference

The design should be built using these Filament components:
- **Tables** with filters, bulk actions, column toggles
- **Forms** with sections, tabs, fieldsets, wizards
- **Infolists** for read-only detail views (customer-facing)
- **Widgets** (stats, charts via Apex Charts, tables, custom)
- **Actions** (button actions, modal confirmations)
- **Notifications** (toast notifications for success/error)
- **Navigation** (sidebar groups, badges for counts)
- **Shield** for permission management UI

---

## 9. Design Deliverables

Design every page listed above as a full Pencil.dev screen:

1. Admin Dashboard
2. Admin Calendar (week + day view)
3. Client List
4. Client Detail (all tabs)
5. Reservation List
6. Reservation Create (wizard flow)
7. Service List + Edit
8. Course List + Detail (all tabs)
9. Workshop List + Detail
10. Working Hours configuration
11. Room management + occupancy view
12. Invoice List + Detail + PDF preview
13. Payment overview
14. Credit management
15. CMS Page editor with block builder
16. Banner / Modal / Top bar management
17. Navigation editor
18. Email template editor
19. Settings page
20. Therapist Dashboard
21. Therapist Calendar
22. Therapist Client view
23. Customer Dashboard
24. Customer Profile
25. Customer Reservations
26. Customer Tokens
27. Customer Credit
28. Customer Payments/Invoices
29. Login page
30. Shield Roles & Permissions

**Each screen:** Desktop (1440px) + Mobile (400px) widths.
