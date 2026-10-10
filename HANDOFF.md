# HANDOFF — Cinematheque Centre Davao: Ticketing & Reservation **Website** (Laravel)

Prepared 2026-10-11, after the **system revision** (client evaluation, five phases, all complete). This replaces the 2026-10-07 handoff.

- **The codebase is the source of truth for what is implemented. This document is the source of truth for decisions.**
- Items marked `[NEEDS CONFIRMATION]` are unsettled.
- **Do not confuse this project with `Desktop\ccd-mobile`**, a separate Flutter + Firebase app for another course. The customer site borrows the mobile app's design language and its About content; nothing else.
- Setting the project up on another computer: **`SETUP.md`**. Overview and flows: **`README.md`**. Schema: **`docs/`**.
- The revision specification (with the client's answers) is a Claude Doc: https://claude.ai/code/artifact/6e4042aa-f016-4b29-ae45-bc59115b5a9e

---

## 1. PROJECT OVERVIEW

- **Project:** web ticketing and reservation system for **Cinematheque Centre Davao**, an FDCP Cinematheque Centre on Palma Gil St., Davao City. Student group project (IT12/L, "System Integration and Architecture").
  - Group: Alexandra Dela Cruz, Jaica-an Mansaludo, Nicho Lescano.
- **Purpose:**
  - Moviegoers browse screenings, reserve specific seats (one declared attendee per seat), review the booking, pay online for paid screenings (20% off for PWD/Senior IDs), and get an e-ticket.
  - Staff manage programs, films and screenings (draft → review → publish), see bookings and payments, admit people at the door, and produce the per-program Manila report, which the Super Admin (FDCP Manila) locks and unlocks.
- **Location:** `C:\Users\Nicho Lescano\Desktop\ccd-web`. **Git:** repository `https://github.com/neckolescano/IT12_cinematheque.git`, working branch `ui/version-5`. The revision work is **not committed yet**; the user commits and pushes themselves (they asked for steps, not for Claude to run git).
- **Stack:** Laravel 12, PHP 8.2 (XAMPP, **no GD extension**); MySQL via XAMPP MariaDB 10.4 (`cinematheque`, test DB `cinematheque_test`); Blade with hand-written CSS/JS (**no npm build, no Tailwind, no Bootstrap**); PayMongo hosted Checkout (sandbox); Laravel Mail over SMTP (Mailtrap sandbox).
- **Timezone:** `Asia/Manila`.
- **Users:** staff at `/ccdadmin` with two roles, **Admin** and one **Super Admin**. **Moviegoers have no accounts.**
- **Run:** start MySQL in XAMPP → `php artisan serve` → `http://127.0.0.1:8000/cinemathequecentredavao` (staff: `/ccdadmin`).
- **Tests:** `php artisan test` → **129 pass (771 assertions)** as of 2026-10-11. Acceptance: `php artisan reports:acceptance` → all 4 Manila sheet rows and the total match.

## 2. ARCHITECTURE (server-rendered MVC)

```
Browser ─▶ public/index.php ─▶ routes/web.php
   web group: session, CSRF, + ExpireUnpaidReservations (expires overdue unpaid bookings on every request)
   staff routes: + auth + EnsureStaffIsActive
 ─▶ Controller ─▶ FormRequest (validation) / Policy (authorization)
              ─▶ Services: ReservationPayments (only code that marks a payment paid), PayMongoClient,
                           ReservationMailer, UnpaidReservationExpiry, ProgramReports, ProgramReportSheet
              ─▶ Eloquent models ─▶ MySQL
 ◀─ Blade view, redirect or .xlsx download    assets: public/css/{cinematheque,admin}.css, public/js/{cinematheque,admin}.js
PayMongo ─▶ POST /webhooks/paymongo (signature-checked, no CSRF) ─▶ ReservationPayments::sync
```

- No SPA/JSON API, except staff admission endpoints which answer JSON with the re-rendered party.
- Session auth (`SESSION_DRIVER=database`). Posters on the `public` disk under `posters/` (needs `php artisan storage:link`).
- Console: `reservations:expire-unpaid` (optional; the middleware does it), `reports:acceptance` (§15).

## 3. PROJECT STRUCTURE (important files)

```
routes/web.php                 customer (/cinemathequecentredavao), admin (/ccdadmin), webhook, "/" → redirect
routes/console.php             reservations:expire-unpaid, reports:acceptance
app/Http/Controllers/
  PublicScreeningController    home (spotlight + showcase tabs), film page; page() also renders staff previews
  BookingController            seats → details → review() → store() (auto-approval, per-ticket pricing), pay,
                               paymentReturn, show, lookup (reference OR email), ticket
  PayMongoWebhookController    signature check → sync
  Staff/DashboardController    Dashboard: today, next 7 days, needs action (refunds), recent bookings
  Staff/ScreeningController    screenings list, roster (bookings + check-in), create incl. batch / edit, preview, publish
  Staff/MovieController        poster-tile catalog by program, film panel (show), form, preview, publish
  Staff/ProgramReportController program reports, Super Admin inbox, update, submit, request-unlock, unlock, export
  Staff/AttendanceController   admit one / admit party / remarks / undo (time-locked by AttendancePolicy)
  Staff/ReservationController  reservations table, booking page, cancel, resend, syncPayment
  Staff/ReportController       "Summary": date-range Attendance + Demographics tabs (+ CSV)
  Staff/{User,Search}Controller
app/Http/Requests/StoreReservationRequest.php   seat 1 = booker; +63 digits; required attendee fields; max 10 seats
app/Http/Requests/ScreeningRequest.php          program + film required, films_count, report columns, intent
app/Services/ProgramReports.php                 report rows, totals, workflow (generate/edit/submit/unlock)
app/Services/ProgramReportSheet.php             Manila .xlsx layout
app/Support/XlsxWriter.php                      minimal .xlsx writer (ext-zip only)
app/Models/  Program, Movie (status, programs), Screening (status, program, films_count, scopePublished),
             Reservation (STATUSES awaiting_payment/confirmed/cancelled), ReservationSeat (priceFor, DISCOUNT_RATE),
             ReservationAttendee, Payment, Attendance, Report, ReportRow, ReportEvent, User (role, isSuperAdmin),
             Seat (CAPACITY = 120), Actor, Director, Genre
app/Policies/  StaffPolicy base; ProgramPolicy, ReportPolicy, UserPolicy add the revision's rules
resources/views/
  public/screenings/{index,_spotlight,_showcase,_film-card,show,...}, public/bookings/{create,_summary,
         _attendee-fields,review,show,_eticket,ticket,lookup}, partials/preview-bar
  staff/{dashboard,search}, staff/screenings/*, staff/movies/{index,show,form}, staff/programs/{index,form},
  staff/program-reports/{index,show}, staff/reports/index, staff/reservations/*, staff/users/*
database/seeders/  StaffUserSeeder (Admins + Super Admin), SeatSeeder (A–J × 12), CatalogSeeder (4 programs,
                   11 films + posters), DemoScreeningSeeder, ManilaAcceptanceSeeder (not in DatabaseSeeder)
tests/Feature/  19 files, 129 tests (RevisionPhase1–4Test, RevisionAcceptanceTest cover the revision)
docs/           data-dictionary-mysql.md, schema-mysql.sql, erd-mysql.html (all updated 2026-10-11)
```

## 4. DATABASE (MySQL/MariaDB, InnoDB, utf8mb4; 38 migrations, all applied)

Full detail in `docs/data-dictionary-mysql.md`. Hierarchy: **Program → Movie → Screening → Reservation → Reservation seat (ticket) → attendee / attendance**.

| Table | Purpose / key fields |
|---|---|
| `users` | Staff: names, email, password, `position` (AVT/PDO, title only), **`role` super_admin\|admin**, is_active |
| `programs` | tag registry: name, **name_key** (unique, lowercased); description / is_active unused |
| `movies` | title, runtime, rating, year, synopsis, curator_note, trailer_url, poster_path, **status draft\|published** |
| `movie_program` | many-to-many: a film can be in several programs |
| `actors`, `directors`, `genres` + pivots | created from names typed on the forms; no admin pages |
| `seats` | 120 seats, rows A–J × 12 |
| `screenings` | **movie_id and program_id required**, films_count, date/times, type free\|paid, price, total_seats (120), **status**, partner, agency_type, notes |
| `reservations` | booking_reference, **status awaiting_payment\|confirmed\|cancelled**, cancellation_reason, lead_* |
| `reservation_seats` | one ticket: **unit_price, discount_type none\|pwd\|senior, amount_due**, released_at, generated held_seat_id, UNIQUE(screening_id, held_seat_id) |
| `reservation_attendees` | one per seat; **company_school NOT NULL**; senior_card_no, pwd_id_no |
| `payments` | amount (= sum of amount_due), status pending\|verified\|rejected, PayMongo ids |
| `attendances` | one per admitted seat |
| `reports` | one per program; status draft\|submitted\|unlocked; generated/submitted/unlocked by + at |
| `report_rows` | frozen line per screening (M/F, PWD, Senior, audience, occupancy, regular/discount, sales, partner, agency, notes) |
| `report_events` | audit: generated, edited, submitted, unlock_requested, unlocked, resubmitted (+ reason) |

- Seeded logins (password `password`): `avt@cinematheque.test`, `pdo@cinematheque.test` (Admins), `manila@cinematheque.test` (Super Admin); `inactive@cinematheque.test` is refused.
- The demo database on this machine also holds the acceptance program "Acceptance: World Cinema, February 2026" and its report (from `reports:acceptance`).

## 5. FINALIZED BUSINESS RULES

1. Reservation, payment and attendance are separate records, never merged.
2. **Auto-approval (2026-10-11):** a free booking is **confirmed on submit** and the e-ticket is emailed at once. A paid booking is **awaiting_payment** (seats held) and becomes confirmed **only** when PayMongo's API reports it paid (reference + exact amount). There is no staff approval and no "pending" status.
3. **Review booking is compulsory:** the booking form posts to the Review page; `store()` refuses (shows Review) without `reviewed=1`.
4. **Pricing:** one seat = one ticket. Paid screening: the screening price, or **20% off** with the attendee's own PWD ID or Senior Citizen ID (PWD counted first; one discount per ticket, never stacked). Free: ₱0. Prices are stored per ticket at booking time; the PayMongo amount is their sum (4 regular + 1 PWD at ₱50 = ₱240). Staff type each screening's price (no default price). A PWD/Senior attendee without an ID at the door pays the difference (handled at the door, outside the system).
5. Unpaid paid-screening bookings **expire after 15 minutes** (cancelled, reason `payment_expired`, seats released, no email). Late payment: booking stays cancelled, staff see **Refund due**; paid within the window but reported late → reinstated if seats are still free.
6. Cancel (staff or expiry) **releases seats**; records stay as history.
7. Max **10 seats** per booking, one declared attendee per seat. **The first seat picked is the primary booker.** Required: first/last name, age, sex, **school/company (OWWA)**, mobile, email — marked with red asterisks. Optional: middle name, senior citizen ID, PWD ID.
8. Mobile numbers: fixed `+63` prefix + the 10 digits starting with 9; stored `+639XXXXXXXXX`.
9. **The venue has exactly 120 seats**, rows A–J × 12. Customer floor plan: entrance at the top, row J at the top down to row A, **screen at the bottom**, emergency **exits beside row B** (2nd row from the screen, client answer).
10. **Admission:** only confirmed bookings; one attendance row per seat; Admit / Admit party (untick absentees) / Undo / Note. No-show = confirmed seat without attendance after the date.
11. **Programs are free-text tags** on the film (no Programs page): a film can carry several; each screening is reported under one (typed on the schedule form, defaulting to the film's only tag). Tags matching ignoring case and spacing are one program (hidden registry, `name_key`); unused tags are deleted automatically. Reports group by program.
11b. **Door check-in time lock:** Admit / Admit party / Undo / Note only from **20 minutes before the start until 60 minutes after the end** (server-enforced); before that the roster is a reservation list with disabled Admit buttons and the opening time. The **Super Admin** can admit or correct at any time.
11c. **Batch scheduling:** daily/weekly repeat and extra showtimes, same length as the first, max 30, no overlaps with any screening (all or nothing).
11d. **Exports:** one click per program, .xlsx (Manila layout) or .csv; the **locked snapshot** once submitted, otherwise live figures.
12. **Every screening has a film.** A shorts block or a talk is its own film record; `films_count` gives the report's "No. of films". A film with screenings can't be deleted (save it as a draft).
13. **Draft → Review → Publish** for films and screenings. Customers see a screening only when it and its film are published; drafts 404. Publishing a screening publishes its film. Review on a published item saves live (offered to change; see §16).
14. **Manila report: one per program** (client answer: per program, not per month), free and paid together. Viewers = people **admitted at the door** (client answer). Occupancy = audience ÷ 120 × 100. Sales = amount_due of PayMongo-verified tickets. Rows are frozen when generated. **No ticket control number** in the report (client answer; a future recommendation). Type of Agency is **free text** (client answer).
15. **Report lock:** Admins generate, edit (Partner / Agency / Notes), regenerate (typed columns kept) and submit. Submitted = read-only for everyone. Admins can only **request an unlock with a reason**; **only the Super Admin unlocks** (with a reason). Every step goes to `report_events`.
16. **Roles:** exactly **one Super Admin** (FDCP Manila, client answer). Only the Super Admin sees Staff accounts, creates accounts, sets roles and deactivates. Everyone edits their own name, email and password; nobody deactivates or demotes themselves. Position (AVT/PDO) is never used for access.
17. **Find my booking:** booking reference **OR** the booker's email (upcoming bookings only) → the ticket page.
18. Emails never block or undo a booking; failures are logged and shown to staff.

## 6. CUSTOMER WORKFLOW (implemented)

1. **Home:** looping spotlight carousel under the header; heading + search; poster showcase with **Now Showing / Advance Booking / Coming Soon / Special Screenings** tabs and an admission filter. Published screenings only.
2. **Film page:** centred hero (poster on a yellow offset block, title, tags, curator's quote, synopsis, at-a-glance facts, cast), then showtime "ticket" rows each with **Reserve seats** (free) or **Get tickets** (paid).
3. **Stepper (4 steps):** Seats → Details → Review → Pay / Confirmed.
4. **Seats:** floor plan per §5.9 on the left; summary card (large poster banner, seats, price, total) on the right.
5. **Who's coming?:** one card per seat; seat 1 = "You · primary booker"; red asterisks; OWWA hint under School or company; Senior/PWD IDs folded away ("20% off on paid screenings"). The summary splits **Regular × n** and **PWD / Senior × n** live.
6. **Review booking:** one read-only card per ticket with its price and discount badge; total; **Confirm and pay** / **Confirm reservation**; **Edit details** returns to the form with everything kept.
7. **After submitting:** free → booking page "Booking confirmed" + e-ticket; paid → PayMongo → booking page.
8. **Booking page** (`/booking/{ref}`): status panel ("Awaiting payment" / confirmed / cancelled / expired) + payment card or the e-ticket.
9. **Find my booking** (`/booking`) → ticket page. **About** page. Footer: link columns, address, FDCP badge (the CINEMATHEQUE photo wordmark was removed).
10. No admin links anywhere on the customer side.

## 7. STAFF WORKFLOW (implemented)

- **Login** `/ccdadmin/login`: minimal split page with the photo-filled CINEMA/THEQUE wordmark; no customer links.
- **Nav:** Dashboard · **Operations** (Films & schedule, Screenings & check-in) · **Insights** (Program reports [Super Admin: count of submitted reports], Summary) · **Settings** (Staff accounts, Super Admin only).
- **Dashboard:** today, next 7 days, Needs action (refunds due), recent bookings.
- **Screenings & check-in:** screenings by day (Draft badge) → the **roster**: check-in status bar (opens / open until / closed), figures, film and program, Edit / Review / Publish; one row per booking (payment status, seats, status, Admit / Admit party, ⋯ menu: Resend email, Refresh from PayMongo, Cancel booking); expanded guests with age, sex, School/Company, PWD / Senior ID, discount, contact; search and filters. The old Reservations list and booking pages redirect here. **No Approve buttons anywhere.**
- **Film catalog:** poster tiles grouped by program + filter chips → **film panel** (details, programs, Edit movie, Add screening schedule, Review, Publish, every screening with booked/admitted).
- **Film and screening forms:** red asterisks; **Publish**, **Save as draft**, **Review**. The screening form has Program, film details, Screening title, No. of films, Schedule, Admission, and Report details (Partner, Type of agency, Notes).
- **Program reports:** Generate per program → the Manila table (editable Partner/Agency/Notes while not locked) → Submit; locked panel with Request unlock (Admin) or Unlock (Super Admin) and a reason; activity log; Export .xlsx. Super Admin inbox at the top of the list.
- **Summary:** totals for a date range (admitted, Male, Female, PWD, Senior, verified income), then the Attendance and Demographics tabs with CSV.
- **Staff accounts:** Super Admin only (role and active status on the form); others reach their own account from the account menu.

## 8. EMAIL

- Sent synchronously to `reservations.lead_email` (seat 1's email). Failures logged and shown to staff.
- **Awaiting payment** ("Complete your payment", paid bookings only) → **E-ticket** (free: on submit; paid: when PayMongo confirms) → **Cancelled** (staff cancel; mentions a refund if paid). No email on expiry. "Resend email" sends the one matching the status.
- `MAIL_FROM_ADDRESS` is still `no-reply@example.com`. `[NEEDS CONFIRMATION]` real sender.

## 9. PAYMENTS / INTEGRATIONS

- **PayMongo hosted Checkout (sandbox) is the only payment method**, one line item for the discounted total. Methods `gcash,paymaya`; **card is never offered**. The booker's phone goes to PayMongo without +63 (its phone field has its own country code).
- A deployment needs a public URL + a new test-mode webhook. `[NEEDS CONFIRMATION]` hosting.
- `.env` holds the owner's Mailtrap and PayMongo **test** keys (see SETUP.md).

## 10. CORE LOGIC

- Double booking: `UNIQUE(screening_id, held_seat_id)` + screening row lock + validation.
- Pricing: `ReservationSeat::priceFor($screening, $attendee)`; `DISCOUNT_RATE = 0.20`.
- Booking: `BookingController::review()` (validated data re-emitted as hidden fields) → `store()` (requires `reviewed`; status by screening type; mailer `approved` for free, `pending` for paid).
- Visibility: `Screening::scopePublished()` (screening and film published); `isDraft()` → 404 on the film page and booking routes.
- Reports: `ProgramReports::generate()` (locks the report row; refuses when submitted; keeps typed Partner/Agency/Notes by screening), `rowFor()`, `totals()`, `submit()`, `requestUnlock()`, `unlock()`; `Report::pendingUnlockRequest()`.
- Roles: `User::isSuperAdmin()`; a model saving hook refuses a second Super Admin; `ReportPolicy` and `UserPolicy`.

## 11. CUSTOMER UI

- Mobile-app design language: paper `#FAF9F7` / ink `#141219`, gold `#EBBC00` gradient buttons, **Poppins + Oswald**, ticket shapes. Light by default, optional dark.
- Assets: `public/css/cinematheque.css`, `public/js/cinematheque.js` (carousel, seat order, live discount summary, digits-only phone, copy reference, About motion). Staff previews add `partials/preview-bar` above the customer page.

## 12. ADMIN UI

- **Geist** (Geist Mono for references and seat labels). **Yellow / black / white**: black sidebar with the gold serpent mark, gold gradient primary buttons, gold active states; red for destructive actions (`data-confirm-danger`).
- Tables: clicking a row opens its record; the Actions column holds only other actions. Poster tiles for the catalog. The Manila report table uses the sheet's yellow header and borders.

## 13. ROUTES (60; `php artisan route:list --except-vendor`)

**Customer** (`/cinemathequecentredavao`): `home`, `screenings.show`, `about`, `bookings.create`, **`bookings.review`** (POST), `bookings.store` (POST), `bookings.lookup`, `bookings.show`, `bookings.ticket`, `bookings.pay`, `bookings.payment.return`. `POST /webhooks/paymongo`.

**Admin** (`/ccdadmin`, `staff.*`): dashboard, search; `screenings` resource + `preview`, `publish`; attendance routes; `reservations` index/show (redirects to the roster) + cancel/resend/sync-payment; **`program-reports`** index/store/show/update + submit, request-unlock, unlock, export, **export-program** (one click, .xlsx/.csv); `reports` (Summary) + export; `programs` (redirect to the catalog); `movies` resource (show = film panel) + `preview`, `publish`; `users` (except show, destroy). The approve routes are gone.

## 14. AUTH

- Active `users` rows log in; deactivated staff are refused and signed out.
- `StaffPolicy` + role and record-state rules: screening deletable only without reservations; cancel only if not cancelled; check-in only confirmed; report edits only when not submitted; unlock only by the Super Admin; staff accounts only by the Super Admin; no self-deactivation or self-demotion; no user deletion.

## 15. CHANGES MADE IN THIS CHAT (2026-10-08 → 2026-10-11, all complete)

1. **UI redesigns:** spotlight carousel, poster showcase with tabs, centred film page with showtime rows, booking pages (action left, summary right, `+63` prefix), 120-seat hall as rows A–J × 12, footer link columns, transparent serpent logo, staff area restyled yellow/black/white in Geist, minimal staff sign-in page.
2. **Revision specification** written as a Claude Doc (link at the top) from the client's evaluation and the Manila report photos; the client's answers were added to it.
3. **Phase 1, data foundation:** programs, statuses, report columns, ticket pricing, required company_school, roles, report tables; seeders with 4 programs and the Super Admin.
4. **Phase 2, customer booking:** Review step, auto-approval, 20% PWD/Senior discount, required-field asterisks + OWWA hint, flipped floor plan, footer wordmark removed, approval UI removed.
5. **Phase 3, admin content:** Programs CRUD, poster-tile catalog, film panel, Save as draft / Review / Publish, program and film required on screenings, report columns on the screening form, drafts hidden from customers.
6. **Phase 4, reports and roles:** program reports in the Manila format, submit / request unlock / unlock with the audit trail, Super Admin inbox, `.xlsx` export (own writer, no GD), Super-Admin-only staff accounts.
7. **Phase 5, acceptance and docs:** `ManilaAcceptanceSeeder` + `php artisan reports:acceptance` (all 4 sheet rows and the total match); data dictionary, schema SQL, ERD, SETUP.md, README.md and this handoff updated.
8. **Refinements:** PayMongo without card and without a doubled +63; home banner (6 soonest films, else 5 recent screenings per program; logline, director, programme – rating – genres; See full schedule); phone mask; PWD ID (16 digits) and Senior ID rules; copy trimmed.
9. **Operations restructure:** Operations / Insights / Settings navigation; programs as free-text tags (Programs page removed); Reservations merged into the screening roster; time-locked door check-in with a Super Admin override; batch scheduling; Program reports list with live totals and one-click .xlsx / .csv; Summary totals.

## 16. UNFINISHED WORK

**MEDIUM**
1. **Commit and push** the revision (branch `ui/version-5`); the user does this themselves.
2. **Open an exported `.xlsx` in Excel** to confirm the layout (only checked as well-formed XML and by tests). `[NEEDS CONFIRMATION]`
3. **Paid rows of the acceptance check:** the photos don't show their dates, titles, program name or PWD/Senior counts, so the seeder uses placeholders (Feb 20, "Paid example 1/2"). Ask the client for the real lines and update `ManilaAcceptanceSeeder::SHEET_ROWS`. `[NEEDS CONFIRMATION]`
4. **Review on a published film/screening saves live.** Offered: make Review never touch the live version. `[NEEDS CONFIRMATION]`
5. **Program tags on the film form are optional** (a film gets a tag when screened under it). Offered: require at least one. `[NEEDS CONFIRMATION]`
6. The spec's Super Admin **"System settings"** page (default price, discount rate, capacity) was not built: the client said staff type each price; the 20% rate and 120 seats are constants (`ReservationSeat::DISCOUNT_RATE`, `Seat::CAPACITY`).
7. Group documents outside the repo (Word chapters, use case diagram) still describe staff approval and the old flow; the group updates them.
8. Email check in Mailtrap and the real `MAIL_FROM_ADDRESS`; privacy of the email lookup (shows upcoming tickets to anyone who knows the email). `[NEEDS CONFIRMATION]`

**LOW**
9. Deployment / public webhook. `[NEEDS CONFIRMATION]` hosting.
10. `payments.status = 'rejected'` unused; `screenings.total_seats` column default is 100 although the app always stores 120.
11. Future recommendations listed in the spec (QR e-tickets, ticket control numbers, online refunds, etc.) are out of scope.

## 17. KNOWN BUGS / PROBLEMS

- No failing tests. No known functional bugs.
- XAMPP: MariaDB once stopped mid-migration and left an orphaned InnoDB tablespace — fix in SETUP.md §9. PHP has no GD (tests use real seed images; the `.xlsx` writer avoids it).
- Tooling: in Git Bash, `/ccdadmin/...` arguments get rewritten as Windows paths (use `MSYS_NO_PATHCONV=1` or drop the leading slash); heredocs with mixed quotes fail (write scripts to files); `sed` on CRLF files is unreliable (use an editor or a Node script that preserves line endings).

## 18. LOCKED DECISIONS

- Laravel 12 + MySQL; server-rendered Blade; **no build step, no CSS framework**.
- Customer at `/cinemathequecentredavao`, admin at `/ccdadmin`; no admin links on the customer side.
- Program → Movie → Screening; film↔program many-to-many; screening in one program; every screening has a film.
- Auto-approval (free confirmed on submit; paid confirmed only by PayMongo); compulsory Review step; 20% PWD/Senior discount per ticket; prices stored per ticket.
- Draft → Review → Publish; drafts never reach customers.
- Manila report per program, viewers = admitted at the door, frozen rows, Super Admin lock, `.xlsx` in the Manila layout, no control number.
- Two roles: Admin and exactly one Super Admin; customers without accounts.
- 120 seats (A–J × 12), screen at the bottom, exits beside row B. Seat 1 = primary booker. `+63` numbers.
- Customer design follows the mobile app; admin is Geist, yellow/black/white.

## 19. REMOVED / REJECTED — do not bring back

- A **Programs settings page** (programs are tags); separate **Reservations** list and booking pages (merged into the roster); admitting outside the check-in window (except the Super Admin).
- **Staff approval of bookings**, the "pending" status, Approve / Approve all buttons, the "To approve" filter and badge, the free "reservation received, awaiting approval" email.
- Special programmes without a film; screenings without a program; films and screenings that publish on save without a draft option.
- The large CINEMATHEQUE photo wordmark in the customer footer.
- Ticket control numbers in the report; a monthly report period.
- QR + screenshot payment proofs; `attendances.control_number`; a `tickets` table; AVT≠PDO access; roles tables.
- Any admin/staff link on the customer site; cancelled bookings holding seats; unpaid bookings without expiry.
- PhpSpreadsheet (needs GD on this XAMPP).

## 20. EXACT NEXT STEP

Nothing is in progress. Next, in order:
1. Ask the user to commit the revision and to open one exported `.xlsx` in Excel (§16.1–2).
2. Ask the client for the real paid rows of the February 2026 sheet and the decisions in §16.4–5.
- **Do NOT change:** the business rules in §5; PayMongo/settle/expiry logic; the report lock rules; existing migrations (add new ones).
- **Expected result:** all 129 tests and `php artisan reports:acceptance` passing.

---

## COPY THIS INTO THE NEW CHAT

> You are continuing an existing **Laravel 12 + MySQL (XAMPP MariaDB) + Blade, PayMongo sandbox + SMTP mail** project for **Cinematheque Centre Davao — Ticketing & Reservation Website** (working copy `C:\Users\Nicho Lescano\Desktop\ccd-web`, git branch `ui/version-5`). `HANDOFF.md` in the project root is the handoff from the previous chat. Treat the codebase as the source of truth for implementation status, and this handoff as the source of truth for decisions. The 2026-10-11 system revision (programs, draft/publish, auto-approval, Review step, PWD/Senior discount, per-program Manila report with a Super Admin lock, roles) is complete.
>
> Before changing anything:
> 1. Read this handoff thoroughly.
> 2. Inspect the relevant current files.
> 3. Do not reintroduce removed/rejected approaches (§19).
> 4. Do not invent missing requirements.
> 5. If the handoff conflicts with the code, identify the conflict before changing anything.
> 6. Continue from the exact next step (§20).
>
> Keep all 129 tests and `php artisan reports:acceptance` passing.
