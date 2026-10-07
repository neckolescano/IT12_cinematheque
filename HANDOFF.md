# HANDOFF — Cinematheque Centre Davao: Ticketing & Reservation **Website** (Laravel)

Prepared 2026-10-07. This replaces every earlier handoff (`Desktop\CCD-HANDOFF.md`, and earlier versions of `Desktop\CCD-WEB-HANDOFF.md`).

- **The codebase is the source of truth for what is implemented. This document is the source of truth for decisions.**
- Items marked `[NEEDS CONFIRMATION]` are unsettled.
- **Do not confuse this project with `Desktop\ccd-mobile`**, a separate Flutter + Firebase app for another course (CCE106). The website borrows the mobile app's **design language** (customer side) and its About content; nothing else.
- Setting the project up on another computer: see **`SETUP.md`** in the project root.

---

## 1. PROJECT OVERVIEW

- **Project:** web ticketing and reservation system for **Cinematheque Centre Davao**, an FDCP Cinematheque Centre on Palma Gil St., Davao City. Student group project (IT12/L, "System Integration and Architecture").
  - Group: Alexandra Dela Cruz, Jaica-an Mansaludo, Nicho Lescano.
- **Purpose:**
  - Moviegoers browse screenings, reserve specific seats (one declared attendee per seat), pay online for paid screenings, and get an e-ticket.
  - Staff schedule screenings, approve free bookings, see payment status, admit people at the door, and read reports.
- **Location:** `C:\Users\User\Desktop\ccd-web` (only working copy). `Desktop\ccd\cinematheque-ui` is an **outdated backup; don't use it**. **No git repository.**
- **Stack:** Laravel 12, PHP 8.2 (XAMPP PHP 8.2.12, **no GD extension**); MySQL via XAMPP MariaDB 10.4 (`cinematheque`, test DB `cinematheque_test`); Blade with hand-written CSS/JS (**no npm build, no Tailwind, no Bootstrap**); PayMongo hosted Checkout (sandbox); Laravel Mail over SMTP (Mailtrap sandbox).
- **Timezone:** `Asia/Manila`.
- **Users:** **Staff** (AVT or PDO, identical access) at `/ccdadmin`. **Moviegoers have no accounts.**
- **Run:** start MySQL in XAMPP → `php artisan serve` → `http://127.0.0.1:8000/cinemathequecentredavao` (staff: `/ccdadmin`).
- **Tests:** `php artisan test` → **85 pass (421 assertions)** as of 2026-10-07.

## 2. ARCHITECTURE (server-rendered MVC)

```
Browser ─▶ public/index.php ─▶ routes/web.php
   web group: session, CSRF, + ExpireUnpaidReservations (expires overdue unpaid bookings on every request)
   staff routes: + auth + EnsureStaffIsActive
 ─▶ Controller ─▶ FormRequest (validation) / Policy (authorization)
              ─▶ Services: ReservationPayments (only code that marks a payment paid), PayMongoClient,
                           ReservationMailer, UnpaidReservationExpiry
              ─▶ Eloquent models ─▶ MySQL
 ◀─ Blade view or redirect    assets: public/css/{cinematheque,admin}.css, public/js/{cinematheque,admin}.js
PayMongo ─▶ POST /webhooks/paymongo (signature-checked, no CSRF) ─▶ ReservationPayments::sync
```

- No SPA/JSON API, except staff admission endpoints which answer JSON (`Accept: application/json`) with the re-rendered reservation ("party") so the attendance table updates in place.
- Session auth (`SESSION_DRIVER=database`). Posters on the `public` disk under `posters/` (needs `php artisan storage:link`). Seeder copies the demo posters from `database/seeders/posters/`.
- Optional scheduler `reservations:expire-unpaid` (not needed; middleware does it).

## 3. PROJECT STRUCTURE (important files)

```
routes/web.php                customer (/cinemathequecentredavao), admin (/ccdadmin), webhook, "/" → redirect
app/Http/Controllers/
  PublicScreeningController   home (This week = next 7 days / Upcoming), screening page
  BookingController           seats step, "Who's coming?" step, store, pay, paymentReturn, show (booking-flow page),
                              lookup (reference OR email), ticket (ticket-only page)
  PayMongoWebhookController   signature check → sync
  Auth/LoginController
  Staff/DashboardController   Dashboard: Today table, Next 7 days, needs action, recent bookings
  Staff/ScreeningController   Attendance list, screening page (parties), create/edit page, approvePending, filmFor()
  Staff/AttendanceController  admit one / admit party (storeGroup) / remarks / undo → JSON party or redirect
  Staff/ReservationController reservations table, booking page, confirm (free only), cancel, resend, syncPayment
  Staff/ReportController      Attendance tab + Demographics tab (+ CSV for each)
  Staff/{Movie,User,Search}Controller
app/Http/Requests/StoreReservationRequest.php   seat 1 = booker; +639 digits; required attendee fields; max 10 seats
app/Http/Requests/ScreeningRequest.php          film|programme, film details, genre "Other", capacity fixed (120)
app/Models/  Seat (CAPACITY = 120), Screening, Reservation (cancel, paymentDeadline, wasExpired, staffState),
             ReservationSeat, ReservationAttendee, Payment, Attendance, Movie (GENRES, genreList, customGenres,
             syncDetails), Actor, Director, Genre, User
resources/views/
  layouts/app (customer), layouts/staff (admin), auth/login
  public/screenings/{index,_card,_ticket,show}, public/bookings/{create,_summary,_attendee-fields,show,
         _eticket,ticket,lookup}, public/about
  components/ crumbs, stepper (3 gold bars), date-stub, poster, arrow, empty, booking-summary, date-badge
  staff/dashboard, staff/search, staff/partials/{booking-row,screening-table,genre-field}
  staff/screenings/{index,show,form,_fields,_party,_attendee-actions}, staff/reservations/{index,show}
  staff/reports/index, staff/movies/{index,form}, staff/users/{index,form}
database/seeders/  StaffUserSeeder, SeatSeeder (120 seats A–L × 10), CatalogSeeder (11 films + posters), DemoScreeningSeeder
public/images/about/ccd_facade.jpg    About opening photo (from the Centre's Instagram, credited on the page)
tests/Feature/  11 files, 85 tests
docs/           data-dictionary-mysql.md, schema-mysql.sql, erd-mysql.html (pwd_id_no included; NOT yet updated for
                fixed 120 seats / removed seat admin), system-evaluation-questionnaires.{md,docx}
SETUP.md        how to set the project up on another computer
```

## 4. DATABASE (MySQL/MariaDB, InnoDB, utf8mb4; 26 migrations, all applied)

| Table | Purpose / key fields | Notes |
|---|---|---|
| `users` | Staff only: names, `email` (unique), password, `position` ENUM(AVT,PDO) descriptive, `is_active` | |
| `movies` | Catalog: title, runtime_minutes, rating, release_year, synopsis, `poster_path` | |
| `actors`, `directors` | first/last name; created from names typed on the forms; orphans deleted | no admin pages |
| `genres` | `genre_name` unique; fixed list `Movie::GENRES` + custom "Other" genres | no admin page |
| `movie_actor`, `movie_director`, `movie_genre` | pivots | |
| `seats` | `seat_label` (A1…L10), `section` (Main A–H, Back I–L) | **Fixed at 120** (migration `2026_10_07_000002` added K1–L10) |
| `screenings` | event_title, movie_id (nullable), date, start/end, type free\|paid, price, `total_seats` (always 120), created_by | |
| `reservations` | booking_reference `CCD-XXXXXXXX`, status pending\|confirmed\|cancelled, cancellation_reason staff\|payment_expired, cancelled_at, lead_* (from seat 1) | |
| `reservation_seats` | released_at; generated `held_seat_id`; **UNIQUE(screening_id, held_seat_id)** | cancel releases seats |
| `reservation_attendees` | one per seat: names, age, sex, company_school, contact_no, email, senior_card_no, pwd_id_no, pwd_indicator, is_lead_reserver | logsheet fields |
| `payments` | amount, payment_channel, status pending\|verified\|rejected, provider_session_id, provider_payment_id, paid_at | `rejected` unused |
| `attendances` | reservation_seat_id (unique), remarks, checked_in_at, checked_in_by | |

- Seeded logins (password `password`): `avt@cinematheque.test`, `pdo@cinematheque.test`; `inactive@cinematheque.test` is refused.
- **Fresh-install note:** the 120-seat migration inserts K/L before the seeder inserts A–J, so seat ids differ from this machine; every view sorts seats by **label**, never by id.

## 5. FINALIZED BUSINESS RULES

1. Reservation, payment and attendance are separate records, never merged.
2. Every new reservation is **pending**. Free → **confirmed** only when staff approve (per booking or "Approve all pending"). Paid → confirmed **only** when PayMongo's API reports it paid (reference + exact amount). **Staff can never approve paid bookings.**
3. Unpaid paid-screening bookings **expire after 15 minutes** (`Reservation::PAYMENT_WINDOW_MINUTES`): cancelled with reason `payment_expired`, seats released, no email. Free pending bookings never expire.
4. Late payment: after the window/cancel → payment verified, booking stays cancelled, staff see **"Refund due"** (refund outside the system). Paid within the window but reported late → reinstated if seats are still free.
5. Cancel (staff or expiry) **releases seats**; records stay as history.
6. Max **10 seats** per booking, one declared attendee per seat. **The first seat picked is the primary booker** (lead_* come from that attendee; no separate "Your details"). Required per attendee: first/last name, age 1–120, sex, school/company, mobile, email. Optional: middle name, senior citizen ID, PWD ID.
7. **Mobile numbers:** fixed `+639` prefix + **9 digits** on the form; stored `+639XXXXXXXXX` (full `09…`/`+639…` still accepted).
8. **The venue has exactly 120 seats.** No seat admin, no per-screening capacity field.
9. **Admission:** only confirmed bookings; one attendance row per seat. Party of 1: **Admit**. Party of 2+: **Admit party** → all attendees ticked → untick anyone absent → confirm ("Admit 3"). Same ticks admit only some. Unticked = not admitted = no-show after the date. **Undo** and **Note** per admitted attendee.
10. **No-show** = seat of a confirmed booking without attendance after the screening date.
11. **The physical FDCP ticket and control number are outside the system.** `booking_reference` is the admission reference.
12. **Find my booking:** booking reference **OR** the booker's email (email → **upcoming** bookings only). Result = the **ticket page**.
13. Emails never block or undo a booking; failures logged and shown to staff.
14. Screening ↔ film: a film (catalog movie, matched by title case-insensitively, created if new; blank fields never erase catalog values) or a special programme (no movie). Genres: fixed chips + **Other** (typed).
15. Staff = AVT or PDO, identical access.

## 6. CUSTOMER WORKFLOW (implemented; mirrors the mobile app)

1. **Home** `/cinemathequecentredavao`: hero "NOW SHOWING" with overlapping search; All/Free/Paid tabs; **This week** = poster cards (whole poster, no hover movement) with **Book Now**; **Upcoming** = ticket cards (date stub).
2. **Screening page:** breadcrumbs; poster, tags (runtime, rating, year, genres), synopsis, director/cast; then the date ticket with **Choose seats**; other dates.
3. **Seats:** breadcrumbs + 3 gold step bars; **summary on the left**; 120-seat map (A–L) fits without scrolling; seats kept in click order.
4. **"Who's coming?":** one compact card per seat; seat 1 = "You · primary booker"; `+639` mobile field; guests may copy seat 1's mobile/email; senior/PWD IDs folded away.
5. **Submit:** free → booking page "Reservation received" (+ short popup); paid → PayMongo Checkout → back to the booking page.
6. **Booking page** (`/booking/{ref}`, nav **Screenings**): status panel + compact payment card (if unpaid) or the vertical e-ticket (ADMIT n, reference, copy).
7. **Find my booking** (`/booking`): ticket-shaped form (reference above the tear, **or** email below); "On this device" list (browser storage); "How booking works". Opens **`/booking/{ref}/ticket`** (ticket only, nav **Find my booking**).
8. **About** (`/about`): the mobile app's About story and motion (opening photo light-up + parallax + line-by-line title, scroll reveals, timeline drawn in gold to a reading line ending at 2022, pinned era strip, centres map by coordinates, Davao last). Centred 860px column, larger type; phone layout checked.
9. **No admin links anywhere on the customer side**, even for logged-in staff.

## 7. STAFF WORKFLOW (implemented)

- **Login** `/ccdadmin/login`. Top bar: global search ("/"), Public site, theme, account menu.
- **Nav:** Dashboard · Operations (**Attendance**, Reservations [badge = free bookings to approve]) · Insights (Reports) · Settings (Film catalog, Staff accounts).
- **Dashboard:** date + figures; **New screening**; Today and Next 7 days tables; Needs action (to approve, refunds due); Recent bookings.
- **Attendance** (routes still `staff.screenings.*`): screenings table by day (Upcoming/Past/All, search). **No create button here.**
- **Screening page:** figures; Approve all pending; Edit screening; More (public page, reservations list, delete). **Attendance table, one row per reservation:** Reservation ("Name · Party of N", reference) | Seats | Status | Admitted (n / N) | Actions; attendees expand beneath.
- **New / Edit screening:** a dedicated page (decision: enough fields to deserve focus): What's showing / Schedule / Admission + live **Summary** with Save. Entry points: Dashboard "New screening", Film catalog "Schedule" (pre-fills the film). No drawers.
- **Reservations:** table (Reservation, Screening, Party, Amount, Status, Booked, Actions = Approve); quick filters + search + screening picker. **Booking page:** Approve / Resend email / Cancel; the party table (expanded); attendee details; booker and payment ("Refresh from PayMongo").
- **Reports:** date presets + range; **Attendance** tab (reserved vs admitted table + CSV); **Demographics** tab (only people actually admitted; logsheet fields; totals by sex/senior/PWD; CSV). "Check-ins by staff" removed.
- **Film catalog:** table Film (poster, title, rating) | Runtime | Genre | Director | Year | Screenings (centred) | Actions (Schedule). Row opens the film; **Delete** is on the film's edit page.
- **Staff accounts:** table; row opens the account.

## 8. EMAIL (implemented; delivery tested against Mailtrap 2026-10-06)

- Sent synchronously to `reservations.lead_email` (= seat 1's email). Failures logged and shown to staff.
- **Pending** (paid: "Complete your payment" + 15-minute pay-by; free: "Reservation received") → **Approved = e-ticket** (once) → **Cancelled** (staff cancel; mentions refund if paid). **No email on expiry.** Staff "Resend email" sends the one matching the status.
- `MAIL_FROM_ADDRESS` is still `no-reply@example.com`. `[NEEDS CONFIRMATION]` real sender. Visual check of the emails in Mailtrap never reported back. `[NEEDS CONFIRMATION]`

## 9. PAYMENTS / INTEGRATIONS

- **PayMongo hosted Checkout (sandbox) is the only payment method.** Methods `card,gcash,paymaya`. Verified end-to-end 2026-10-06 by return page, webhook alone, and staff refresh.
- Webhooks deleted after testing; a deployment needs a public URL + a new **test-mode** webhook. `[NEEDS CONFIRMATION]` hosting.
- The `.env` holds the owner's Mailtrap and PayMongo **test** keys. Sharing the project with `.env` shares those sandbox accounts (see SETUP.md).

## 10. CORE LOGIC

- Double booking: `UNIQUE(screening_id, held_seat_id)` + screening row lock + validation. Availability = `total_seats` − held seats.
- Expiry: `UnpaidReservationExpiry` (locks payment first, same order as settle) → `Reservation::cancel('payment_expired')`.
- Booker: `StoreReservationRequest::prepareForValidation()` normalises phones (9 digits → `+639…`) and, when no lead_* is sent, fills lead_* from `attendees[seat_ids[0]]` and sets `lead_seat_id`.
- Film details: `Movie::syncDetails()`, `Movie::genreList()` (chips + typed Other, mapped case-insensitively to the fixed list), `customGenres()`.
- Staff state label: `Reservation::staffState()`.

## 11. CUSTOMER UI

- Mobile app design language: paper `#FAF9F7` / ink `#141219`, gold `#EBBC00` gradient buttons and eyebrows (`#8A5A00` on light), purple `#580076` links; **Poppins + Oswald** (uppercase display titles); ticket shapes (cream stub `#FFF7E1`, gold perforation).
- Layout: content max 1120px, side gutter `clamp(20px, 6.5vw, 96px)` (~1 inch on desktop). Light by default, optional dark (`localStorage` `ccd-theme`).
- Assets: `public/css/cinematheque.css`, `public/js/cinematheque.js` (seat order, digits-only phone, copy reference, "On this device", About motion with reduced-motion fallback).

## 12. ADMIN UI

- **Geist** (Geist Mono for booking references and seat labels). Neutral admin palette (gray surfaces, dark sidebar); purple only for primary buttons; red for destructive (solid red in delete confirmations via `data-confirm-danger`); semantic green/amber/red states.
- **Shapes = the v8 ones** (user request): 8px buttons/inputs, 10px panels/tables/dialogs, pill filter chips, pill genre chips and counts, 4px status labels with a dot, two-box Free/Paid and Film/Programme toggles.
- Tables for records with clear uppercase headers; **clicking a row opens its record; the Actions column holds only other actions**. Minimal copy: no page subtitles, no helper text that restates the UI.

## 13. ROUTES (48; `php artisan route:list --except-vendor`)

**Customer** (prefix `/cinemathequecentredavao`): `home`, `screenings.show`, `about`, `bookings.create` (GET), `bookings.store` (POST, throttle 10/min), `bookings.lookup` (`?reference=` or `?email=`), `bookings.show` (`/booking/{ref}`), `bookings.ticket` (`/booking/{ref}/ticket`), `bookings.pay`, `bookings.payment.return` (throttle 20/min). `POST /webhooks/paymongo` (no CSRF, throttle 60/min). `/` → redirect.

**Admin** (`/ccdadmin`, names `staff.*`, auth + active): dashboard, search; `screenings` resource + `approve-pending`; `reservation-seats/{id}/attendance` (POST), `reservations/{id}/admit` (POST, party), `attendances/{id}` (PATCH, DELETE); `reservations` index/show + confirm/cancel (PATCH) + resend/sync-payment (POST); `reports`, `reports/export` (`?view=demographics`); `movies` (except show); `users` (except show, destroy). **No seats, actors, directors or genres routes.**

## 14. AUTH

- Active `users` rows log in; moviegoers have no accounts; deactivated staff are refused and signed out.
- One "staff" role; `StaffPolicy` + record-state rules (screening deletable only without reservations; confirm only free+pending; cancel only if not cancelled; check-in only confirmed, not yet admitted; no self-deactivation; no user deletion).

## 15. CHANGES MADE IN THIS CHAT (2026-10-07, all complete)

1. **Docs** (`docs/`): `pwd_id_no` + one-form film workflow added to the data dictionary, schema SQL and ERD.
2. **Staff typography & copy (v7):** Geist; type scale; removed AI-sounding subtitles/helper text.
3. **Dashboard revision (v8):** film catalog list; neutral admin colours; **fixed 120 seats** (migration, no seat admin, no capacity field); genre **Other**; **demographic report**; actions on the right; Admit all with review; "Today" → "Dashboard".
4. **Senior UI/UX pass (v9):** admin.css rewritten; Screenings → **Attendance** (no create button); dedicated **New/Edit screening page** with summary (drawers removed); attendance by **party**; tables everywhere with clear headers; check-ins by staff removed. Then the **v8 shapes restored** at the user's request.
5. **Film catalog columns:** Film · Runtime · Genre · Director · Year · Screenings (centred) · Actions.
6. **Customer redesign (v10):** the mobile app's design; ~1-inch margins; poster fit + no hover motion; breadcrumbs; full film details before booking; 120-seat map with summary on the left; "Who's coming?" with seat 1 = booker; `+639` + 9 digits; compact payment page; mobile e-ticket; About page; no admin links on the customer side.
7. **About motion (v10.1):** the mobile app's effects + the centres map, centred and larger.
8. **v10.2:** About fixed on phones (map labels, title width, opening height, year sizes); timeline line ends at 2022; map parallels closer; booking-flow page under **Screenings**; Find my booking = reference **or** email (upcoming) → **ticket page**; name search removed.

## 16. UNFINISHED WORK

**MEDIUM**
1. **Docs** (`docs/`, `Downloads\chap_3_1006.docx`, `implementation_updated.md`): update for fixed 120 seats (no seat admin; `total_seats` always 120), seat 1 = booker, `+639` format, Find my booking by reference **or** email, ticket page, demographic report, Admit party, Attendance naming, About page. Use Case diagram (Figure 5) still shows proof upload / QR management — the group must redraw it.
2. **Email check in Mailtrap** (user) and the real `MAIL_FROM_ADDRESS`. `[NEEDS CONFIRMATION]`
3. **Privacy of email lookup:** anyone who knows a booker's email can open their upcoming tickets. Option offered: email the tickets instead of showing them. `[NEEDS CONFIRMATION]`

**LOW**
4. Seat map follows rows A–L × 10 (assumed arrangement); adjust visually only if a floor plan arrives.
5. Demo films have no runtime/genres; offered to add verified values.
6. Deployment / public webhook. `[NEEDS CONFIRMATION]` hosting.
7. `payments.status = 'rejected'` unused; `welcome.blade.php` unused; `components/booking-summary` and `date-badge` unused by current views.
8. Git not initialised (offered).
9. Dark mode of the new customer and admin designs not visually checked.

## 17. KNOWN BUGS / PROBLEMS

- No failing tests. No known functional bugs.
- XAMPP: MariaDB once stopped mid-migration and left an orphaned InnoDB tablespace (`Tablespace … exists`) — fixed by moving the `.ibd` file out of `C:\xampp\mysql\data\<db>\`. PHP has no GD (tests use real seed images).
- Tooling: the built-in browser pane can have a zero-size viewport (no scrolling, paused animation frames); use headless Chrome. `html { scroll-behavior: smooth }` makes `scrollTo()` asynchronous in scripts — pass `behavior: 'instant'` when testing. Headless Chrome won't go narrower than ~500px; test phone width inside a 390px iframe. In Git Bash prefix `/ccdadmin` paths with `MSYS_NO_PATHCONV=1`; write scripts to files instead of heredocs with mixed quotes.

## 18. LOCKED DECISIONS

- Laravel 12 + MySQL; server-rendered Blade; **no build step, no CSS framework**. Not Firebase (that's the mobile project).
- Customer at `/cinemathequecentredavao`, admin at `/ccdadmin`; **no admin links on the customer side**.
- Staff (AVT = PDO), customers without accounts, no roles tables.
- Reservation ≠ payment ≠ attendance; no-shows visible.
- PayMongo is the only payment method, confirmed only through PayMongo's API; staff can't approve paid bookings.
- Free bookings wait for staff; unpaid paid bookings expire after 15 minutes; cancel/expiry releases seats.
- Emails: Pending → Approved (= e-ticket) → Cancelled; none on expiry.
- Control number outside the system; no tickets table.
- **120 seats fixed.** Seat 1 = primary booker. `+639` + 9 digits.
- **Customer side follows the mobile app** (Poppins + Oswald, gold, ticket shapes). **Admin: Geist**, neutral palette, v8 shapes, tables with row-opens-record + Actions column, minimal copy, Attendance by party with Admit party (review by unticking).
- New/Edit screening = dedicated page (entry from Dashboard + catalog "Schedule"). Genres fixed + Other; no genre/people admin pages.
- Find my booking: reference OR email (upcoming) → ticket page.

## 19. REMOVED / REJECTED — do not bring back

- QR + screenshot proof + manual payment verification (and their tables); `attendances.control_number`; a `tickets` table; separate Approved and E-ticket emails; free bookings auto-confirming; roles tables / AVT≠PDO.
- Any admin/staff link on the customer site ("Staff login", "Staff view of …").
- Cancelled bookings holding seats; unpaid bookings without expiry.
- **Admin:** TailAdmin/SaaS look, Inter, gold accents, stat tiles; IBM Plex (replaced by Geist); seat layout page and per-screening capacity; separate Actors/Directors/Genres pages; create/edit **drawers**; "Add screening" on the Attendance page; "Check-ins by staff"; an Admit all **without** review; duplicate View/Edit buttons on rows; page subtitles and helper text that restates the UI.
- **Customer:** dark-by-default; ticket-count picker; date carousels; "Cinema 1" badge; star ratings; a separate "Your details" booker section; poster hover movement; name-based booking search; the booking-flow page shown as the result of Find my booking.

## 20. EXACT NEXT STEP

Nothing is in progress. Next, in order:
1. Ask the user whether the email lookup should keep showing tickets on screen or email them instead (§16.3).
2. Update the documentation for this chat's changes (§16.1). Do not edit the user's Word files unless asked.
- **Do NOT change:** the business rules in §5; PayMongo/settle/expiry logic; the email structure; the admin design decisions (§12); the customer design (mirrors the mobile app); existing migrations (add new ones).
- **Expected result:** updated docs, all tests passing (85), a short report.

---

## COPY THIS INTO THE NEW CHAT

> You are continuing an existing **Laravel 12 + MySQL (XAMPP MariaDB) + Blade, PayMongo sandbox + SMTP mail** project for **Cinematheque Centre Davao — Ticketing & Reservation Website** (working copy `C:\Users\User\Desktop\ccd-web`, no git). `HANDOFF.md` in the project root is the handoff from the previous chat. Treat the codebase as the source of truth for implementation status, and this handoff as the source of truth for decisions. (This is NOT the Flutter/Firebase project in `Desktop\ccd-mobile`; the customer site only borrows its design and About content.)
>
> Before changing anything:
> 1. Read this handoff thoroughly.
> 2. Inspect the relevant current files.
> 3. Do not reintroduce removed/rejected approaches (§19).
> 4. Do not invent missing requirements.
> 5. If the handoff conflicts with the code, identify the conflict before changing anything.
> 6. Continue from the exact next step (§20).
>
> **Current next task:** confirm with the user how the email-based "Find my booking" should behave (show tickets vs. email them), then update `docs/` for the 2026-10-07 changes (fixed 120 seats, seat 1 = booker, +639 numbers, reference-or-email lookup + ticket page, demographic report, Admit party, Attendance naming, About page). Keep all 85 tests passing.
>
> Begin by confirming your understanding of the current state and identifying the files you will inspect. Do not rewrite unrelated parts of the system.
