# FriendlyFyzio — remaining work (backlog)

_Last updated 2026-07-18, from a full `docs/` vs. code gap analysis. Items are things the docs require that are **not yet built**, ordered by launch impact. This file is the durable home for the backlog; update it as items ship._

Spec references: `docs/master-specification.md` (authoritative, "MS §…"), `docs/non-technical-specification/full-system-specification.md` ("FSS :line"), `docs/design/*` design briefs, `docs/website-content/*` (DOM snapshots of the current live site = the SEO baseline).

---

## Recently completed (2026-07-18)

- **B1 done — Public URL / SEO scheme.** Kept the new scheme (`/sluzby/…`, `/kurzy`, `/o-nas`, `/rezervace`) and shipped a 301 map + sitemap instead of restoring old slugs. `App\Support\Seo\LegacyRedirects` resolves every old live URL (data-driven for category/service slugs, curated array for `/nas-tym`, `/fyzio-kurzy[/{cat}]`, `/relaxace-ritualy/*`, booking pages, `/prihlaska-na-jednorazove-vstupy`); wired at the `PageController@show` 404 branch (single-segment) and a `Route::fallback()` (multi-segment). New `SitemapController` + `/sitemap.xml` built from model permalinks. Course-category pages 301 to `/kurzy?kategorie={slug}`.
- **B2 done (closed by decision) — `/darkove-poukazy`.** Owner decision (2026-07-18): vouchers are **just a CMS page** — the clinic drops the SimpleShop iframe into the existing **HTML brick** (`HtmlBlockBrick`, "HTML kód", raw-HTML CodeEditor that permits third-party iframes). No SimpleShop code, no `GiftVoucher` wiring, no voucher→credit redemption. The published page is seeded; `/kontakt` voucher CTAs stay as-is (owner will repoint via the CMS if wanted). `GiftVoucher` model/table remain orphaned but harmless.
- **B5 done — dropped `TherapyRecord`.** It was unused dead code duplicating the built `ClientNote` ("Poznámky z terapií"). Removed model + `therapy_records` table (drop migration) + factory + the `User`/`Reservation` relations + its entry in `PruneUnverifiedUsers`. Client-visible session records deferred (would be an `is_client_visible` flag on `ClientNote` if ever wanted).
- **B7 done — admin client-detail tabs.** Added Payments (reused `App\Filament\Support\RelationManagers\PaymentsRelationManager`), Invoices, and Substitute Tokens relation managers to `ClientResource`. Therapy-Records tab intentionally dropped (see B5).
- **UX fixes (2026-07-18, from the customer-flow E2E):** (1) storno cancel modal no longer claims "za méně než 12 hodin" for a confirmed-but-far reservation — it branches on `withinStornoWindow()`; (2) substitution redeem no longer offers a lesson the client is already booked into (`SubstituteOptions` excludes non-cancelled attendances for the client); (3) account-deactivation cancel option now has a confirm step (client-zone modal + magic-link manage page); (4) removed the "Telefon (pro kontrolu)" double-entry field from the reservation wizard. Storno on a confirmed reservation is intentional — staff waive it by voiding the raised `Payment` in Finance.

## Recently completed (2026-07-17)

- `/storno-podminky` CMS page seeded (settings-driven windows); both booking flows' mandatory terms checkboxes now link to it.
- `/sluzby` placeholder page removed; its links repointed (nav "Služby" → dropdown-only, Ceník → `/cenik`, homepage CTAs → `/rezervace` + `/sluzby/relaxace`; voucher links parked at `/kontakt` until B2).
- **Therapist-scoped admin portal**: `therapist` role now granted Reservations/Clients/Payments/CourseEnrollment/LessonAttendance; `App\Filament\Support\Concerns\ScopedToTherapist` row-scopes Reservations (own `therapist_id`), Clients (treated-by-me), and Courses/Lessons/Workshops/enrollments/attendances (own `instructor_id`); calendar defaults to own therapist. `UserResource` (System→Users) gated to admins via `canAccess()` since it shares the User model with ClientResource.
- Client zone: the three post-cancellation confirmation result screens (Storno Paid / Doctor Note Pending / Account Deactivated) built into `Zone\ReservationDetail` (`$confirmation` state). ⚠️ Fixed a real regression while doing this — a conditional Livewire **root** element (`@if…@else` at the template top) silently breaks `wire:click`; the root must always be the same element.
- Doctor's note surfaced in admin: a highlighted section on the reservation view + an icon column and "Čeká na potvrzení od lékaře" table filter (`doctor_note_requested_at` was previously write-only). B17 (a one-click resolve action) still open.

---

## Launch-blocking (deferred by choice)

- **B1. ✅ Done (2026-07-18)** — see "Recently completed". Old live URLs 301 to the new scheme; `/sitemap.xml` shipped.
- **B2. ✅ Done / closed by decision (2026-07-18)** — vouchers are a CMS page with a SimpleShop iframe in an HTML brick; no code. See "Recently completed".
- **B3. Ergobody data migration (FSS :15/:534 — explicit MVP).** One-off XLSX import of clients + anamnesis + appointment history. **Owner decision: do this LAST, once everything else is done** (needs the clinic's export file; it's a one-shot pre-launch load).

## Significant admin / therapist features

- **B4. ✅ Done (2026-07-18) — ADMIN dashboard.** Four admin-only widgets (`app/Filament/Widgets/`, gated by the `AdminOnly` trait): `AdminStatsOverview` (dnešní rezervace / čeká na potvrzení / noví klienti / **obsazenost tento týden** — booked÷available work-block minutes via `App\Support\CalendarAvailability`), `UpcomingReservationsWidget` (compact table, 5 closest upcoming, links to calendar), `ProblemsWidget` (room + therapist double-bookings via `App\Support\Reservations\ConflictFinder`, each with a „Vyřešit" link), `RevenueChartWidget` (Apex, stacked by offering type Terapie/Kurzy/Workshopy/Lekce, week/month filter — `FilamentApexChartsPlugin` registered on the panel). Quick actions in the Dashboard header. Conflicts are also surfaced as a banner on the reservation detail (`ConflictFinder::forReservation` + a Section in `ReservationInfolist`). Pure therapists see the plain near-empty page (their own dashboard — My Today/Week/Stats/Pending Notes — is a later build). Owner decisions: revenue by offering type; therapists keep the empty page; the earlier timeline / unpaid-table / waitlist-feed / aktivní-kurzy+nezaplacené stat cards were dropped as not useful.
- **B5. ✅ Done (2026-07-18)** — `TherapyRecord` dropped as dead code; `ClientNote` covers therapy notes. See "Recently completed".
- **B6. Air Bank IMAP payment matching (MS §2.5, FSS :356).** Poll bank notification emails over IMAP → match by variable symbol → auto mark-paid → generate + attach the invoice. Nothing exists (no IMAP, no "Bank" settings group). QR codes, variable symbols and the invoice pipeline are all built and waiting for it. **Owner decision: not needed now — save for later** (bank payments reconciled by hand meanwhile).
- **B7. ✅ Done (2026-07-18)** — Payments / Invoices / Substitute-Tokens tabs added to the admin client. See "Recently completed".

## Smaller / cleanups

- **B9. ✅ Closed by decision (2026-07-20)** — the per-service `auto_cancel_after_days` (auto-storno on non-payment) is not wanted; unpaid reservations are rare/high-stakes and handled manually by admins (void the raised `Payment` in Finance). The dead column + its service form/infolist fields were removed. See "Deferred by the docs".
- **B11. ✅ Done (stale entry, verified 2026-07-20)** — rescheduling a lesson (or one-off event) notifies every active enrollee **and** the instructor. `App\Support\Enrollments\NotifyScheduleChange` sends `EmailTemplateKey::LessonScheduleChanged` (client) + `TherapistLessonScheduleChanged` (instructor) with the pre-edit term captured via `OfferScheduleSnapshot`; wired through the `NotifiesScheduleChange` trait + `NotifyParticipantsToggle` on `EditCourseLesson`, the inline `LessonsRelationManager`, and `EditOneOffEvent` (watched fields: date/time/room). Covered by `tests/Feature/Enrollments/ScheduleChangeNotificationTest.php`.
- **B12. ✅ Done (2026-07-20) — manual substitute override.** A "Přesunout klienta do lekce" header action on the lesson's Docházka relation manager (`App\Filament\Support\Actions\MoveClientIntoLessonAction`) lets a lecturer/admin move any active course client into the lesson, excusing them from one of their own upcoming lessons in the same step. Bypasses the série pairing / token limits / capacity (capacity shown, over-book warned but allowed). Domain service `App\Support\Substitutes\MoveClientToLesson` writes the two attendance rows (cancelled source + target) with **no** token minted and no hit to `max_substitutions`; `SubstituteOptions::freeSpots()` now counts foreign attendances (not tokens) so manual placements decrement capacity. New client email key `EmailTemplateKey::SubstituteManualMove` names both the original and the náhradní lesson. (Therapist reassignment for 1:1 **therapies** is a non-issue — the reservation edit form already allows changing `therapist_id` and notifies via `ReservationChanged`.)
- **B13. Google Calendar one-way sync** (MS §2.5 L1019, FSS :490).
- **B15. ✅ Done (2026-07-20) — therapist day-waitlist ("pořadník").** The wizard's amber full-day badge is now live: clicking it opens a join modal (guests welcome — name/email/phone, account linked by e-mail). Scope is a **therapist's day**, service-agnostic (`reservation_day_waitlist_entries`: therapist-or-null + date, nullable browsed `service_id` only for the link) — `App\Support\Reservations\JoinReservationDayWaitlist`. When any slot frees on that therapist's day (`ReservationObserver` on cancel / reschedule-away / soft-delete → `NotifyReservationDayWaitlist`), **every** pending waiter is e-mailed at once ("open race", first-to-book) with a deep-link prefilled to the concrete freed therapist + day; an "any"-therapist entry fires for whichever therapist frees. Re-check via new service-agnostic `ReservationSlots::therapistHasOpening()` guards against a reactivation re-taking the slot. New client email keys `ReservationDayWaitlistJoined` / `...SpotAvailable`; kill-switch setting `reservation.day_waitlist_enabled`; admin resource in Provoz ("Pořadník na dny") with a manual "Upozornit" action; past-date entries auto-pruned. `SlotCalendar` gained a `waitlist` cell state for days the visitor already joined.
- **B16. ✅ Done (2026-07-20)** — removed the `/dev/er-diagram` route + its `resources/views/dev/` view, and dropped the now-obsolete `dev` reserved-prefix carve-outs from the `page.show` / `one-off-event.show` catch-all regexes.

## Deferred by the docs (decisions already made — not gaps)

Anamnesis hidden from clients "prozatím" (MS §2.5 L826 / FSS :308; the docs contradict themselves at FSS :305); in-app voucher purchase + PDF generation (later phase); payroll / výplatný modul (FSS :446-449); blog with articles + categories + scientific references (FSS :557-560); mini e-shop / products sold during therapy (FSS :549-551); **SMS explicitly not wanted** ("Notifikácie sa odosielajú výlučne e-mailom", FSS :480); **customer pay-with-credit explicitly not wanted** (owner, 2026-07-18) — the client-zone credits page stays display-only; credit is staff-applied via `AdjustCreditAction`; **per-service auto-storno on non-payment not wanted** (owner, 2026-07-20, was B9) — reservations left unpaid are handled manually by admins (void the raised `Payment`), so the `cancellation_rules.auto_cancel_after_days` column + its form/infolist fields were removed; TBD pages the docs haven't decided (`/pristrojova-terapie/ultrazvuk`, `/elektroterapie`, `/relaxace/relaxacni-ritualy`); Jin Jóga removed from scope (MS §3.4 L1287).

## Not in any doc (pre-launch judgement calls, only if you want them)

Cookie / GDPR consent banner, analytics, sitemap.xml + structured data, 2FA, audit / activity log, EET, accounting integrations (Pohoda / Fakturoid / ISDOC), payment gateways, ARES lookup, i18n (Czech-only by design), FAQ. Backups are covered as a hosting line item (FSS :498), not app code.

## Known divergences that are intentional (no action)

- Working hours: spec's `therapist_weekly_schedules` / `nonstandard_dates` / `calendar_blocks` → materialized `therapist_work_blocks` + series generator.
- Modals + top bars: spec's 3 tables → one unified `Banner` (`BannerType`: topbar/floating/popup).
- Anamnesis: spec's `anamneses` table → `client_profiles.anamnesis` (no history/`created_by`).
- Money: spec says haler; built as whole-CZK integers.
- Client zone: spec's Filament customer panel → public `/muj-ucet`.
- Massage blocks: FSS :223 says 30-min; build follows the 15-min model used everywhere else (correct).
