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

- **B4. Admin dashboard (MS §2.5, brief §4).** 4 stat cards (today's appointments, unpaid, new registrations this week, active courses), today's schedule timeline, revenue chart (`leandrocfe/filament-apex-charts` is installed but unused), upcoming-conflicts widget (room double-bookings — also MVP FSS :526), unpaid reservations, waitlist activity, quick actions. Currently the dashboard only hosts the calendar.
- **B5. ✅ Done (2026-07-18)** — `TherapyRecord` dropped as dead code; `ClientNote` covers therapy notes. See "Recently completed".
- **B6. Air Bank IMAP payment matching (MS §2.5, FSS :356).** Poll bank notification emails over IMAP → match by variable symbol → auto mark-paid → generate + attach the invoice. Nothing exists (no IMAP, no "Bank" settings group). QR codes, variable symbols and the invoice pipeline are all built and waiting for it. **Owner decision: not needed now — save for later** (bank payments reconciled by hand meanwhile).
- **B7. ✅ Done (2026-07-18)** — Payments / Invoices / Substitute-Tokens tabs added to the admin client. See "Recently completed".

## Smaller / cleanups

- **B8. Recurring / control-therapy series** (MS §1.5/§2.5, FSS :209) — needs `reservations.parent_reservation_id` (column doesn't exist) + a "recurring" option in the create wizard ("e.g. 6 weeks, every Tuesday").
- **B9. `auto_cancel_after_days`** (per-service `cancellation_rules`) — stored + editable in the service form, never enforced by any command (only the global `reservation.auto_cancel_hours` is).
- **B10. Newsletter local record** (MS §1.7 L637-646) — no `newsletter_subscriptions` table, so no `service_segment` segmentation, no `synced_to_mailerlite_at`, and no sync-status/manual-resync UI (brief :236-237). Sign-ups are pushed to MailerLite and forgotten.
- **B11. Lesson reschedule + notify-all-enrolled** (MS §2.5 L879, FSS :280) — lessons are editable, but no notify-participants flow and no email key.
- **B12. Manual substitute override** (FSS :246) — lecturer/admin move a client into a lesson even where substitutions aren't normally allowed.
- **B13. Google Calendar one-way sync** (MS §2.5 L1019, FSS :490).
- **B14. Credit-expiry notification** (brief :197) — `credits:expire` expires credit silently, notifies nobody.
- **B15. Dead UI — wizard day-waitlist.** The booking wizard renders an amber "V pořadníku" badge + legend for full days, but the markup is inert ("Awaits the day-waitlist backend", `reservation-wizard.blade.php:262`). The master spec doesn't require a day-level physio/massage waitlist — so **build it or delete the badge**.
- **B16. Remove `/dev/er-diagram`** — route is live and self-labelled "remove before production".
- **B17. Doctor-note resolution action** — an admin action to mark a doctor's note received / waive the storno fee, closing the loop (the note itself is being surfaced this round).

## Deferred by the docs (decisions already made — not gaps)

Anamnesis hidden from clients "prozatím" (MS §2.5 L826 / FSS :308; the docs contradict themselves at FSS :305); in-app voucher purchase + PDF generation (later phase); payroll / výplatný modul (FSS :446-449); blog with articles + categories + scientific references (FSS :557-560); mini e-shop / products sold during therapy (FSS :549-551); **SMS explicitly not wanted** ("Notifikácie sa odosielajú výlučne e-mailom", FSS :480); **customer pay-with-credit explicitly not wanted** (owner, 2026-07-18) — the client-zone credits page stays display-only; credit is staff-applied via `AdjustCreditAction`; TBD pages the docs haven't decided (`/pristrojova-terapie/ultrazvuk`, `/elektroterapie`, `/relaxace/relaxacni-ritualy`); Jin Jóga removed from scope (MS §3.4 L1287).

## Not in any doc (pre-launch judgement calls, only if you want them)

Cookie / GDPR consent banner, analytics, sitemap.xml + structured data, 2FA, audit / activity log, EET, accounting integrations (Pohoda / Fakturoid / ISDOC), payment gateways, ARES lookup, i18n (Czech-only by design), FAQ. Backups are covered as a hosting line item (FSS :498), not app code.

## Known divergences that are intentional (no action)

- Working hours: spec's `therapist_weekly_schedules` / `nonstandard_dates` / `calendar_blocks` → materialized `therapist_work_blocks` + series generator.
- Modals + top bars: spec's 3 tables → one unified `Banner` (`BannerType`: topbar/floating/popup).
- Anamnesis: spec's `anamneses` table → `client_profiles.anamnesis` (no history/`created_by`).
- Money: spec says haler; built as whole-CZK integers.
- Client zone: spec's Filament customer panel → public `/muj-ucet`.
- Massage blocks: FSS :223 says 30-min; build follows the 15-min model used everywhere else (correct).
