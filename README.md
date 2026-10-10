# Cinematheque Centre Davao — Ticketing & Reservation System

Laravel 12 · PHP 8.2+ · MySQL 8 (or XAMPP MariaDB 10.4) · PayMongo (sandbox) · SMTP email.

| Area | URL |
|---|---|
| Customer site | `/cinemathequecentredavao` (`/` redirects here) |
| Staff admin | `/ccdadmin` (login at `/ccdadmin/login`). Not linked from the customer site, and every page requires a staff login |
| PayMongo webhook | `POST /webhooks/paymongo` |

The **2026-10-11 system revision** (client evaluation) added programs above films, draft/review/publish, automatic approval of bookings, a compulsory Review step, a 20% PWD/Senior discount, the per-program Manila report with a Super Admin lock, and the Super Admin role. Full step-by-step setup for a new computer is in [SETUP.md](SETUP.md); decisions and project state are in [HANDOFF.md](HANDOFF.md).

---

## 1. Setup

You need PHP 8.2+, Composer and MySQL (XAMPP is fine). No GD extension and no Node/npm are needed.

```bash
composer install
```
```bash
copy .env.example .env
```
```bash
php artisan key:generate
```
In phpMyAdmin, create the databases `cinematheque` (the app) and `cinematheque_test` (automated tests), both with collation `utf8mb4_unicode_ci`. Then:
```bash
php artisan migrate:fresh --seed
```
```bash
php artisan storage:link
```
```bash
php artisan serve
```
Open http://127.0.0.1:8000. Staff: http://127.0.0.1:8000/ccdadmin. Seeded logins (password `password`): `avt@cinematheque.test` and `pdo@cinematheque.test` (Admins), `manila@cinematheque.test` (the Super Admin).

**Updating an existing install:** run `php artisan migrate`. The revision's migrations keep all data (see §3).

### 1a. Email (required for e-tickets)
Fill these in `.env` with your SMTP provider. For testing without emailing real people, use a free **Mailtrap** sandbox inbox.
```
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io   # Gmail: smtp.gmail.com
MAIL_PORT=2525                       # Gmail: 587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password          # Gmail: an App Password, not your normal password
MAIL_FROM_ADDRESS="no-reply@yourdomain"
```
If email isn't configured, **bookings still work**. The failure is logged to `storage/logs/laravel.log`, the customer and staff see a notice, and staff can press **Resend email** later.

### 1b. PayMongo sandbox (required for paid screenings)
1. Sign in to the PayMongo dashboard and stay in **Test mode**.
2. Go to **Developers → API keys** and copy the **secret key** (`sk_test_…`) into `.env` as `PAYMONGO_SECRET_KEY=`.
3. *(Optional, recommended)* Go to **Developers → Webhooks**, add `https://YOUR-PUBLIC-URL/webhooks/paymongo` with the event `checkout_session.payment.paid`, and copy its secret (`whsk_…`) into `PAYMONGO_WEBHOOK_SECRET=`. Locally you need a tunnel (e.g. ngrok) for PayMongo to reach you. **Without a webhook, payments are still confirmed when the customer returns from PayMongo**; the webhook only covers customers who close the tab.
4. `PAYMONGO_PAYMENT_METHODS=gcash,paymaya` sets which methods the checkout offers (they must be enabled on your PayMongo account). `card` is never offered, even if listed (`ReservationPayments::paymentMethods()`). The booker's phone is sent without +63, because PayMongo's phone field adds the country code itself.
5. Use PayMongo's published test cards and test e-wallet flows. **Never put live keys (`sk_live_…`) in a development `.env`.**

After editing `.env`, run `php artisan config:clear`.

### 1c. Tests
```bash
php artisan test
```
129 tests as of 2026-10-11. They use the `cinematheque_test` database, and they fake email and PayMongo, so they never send mail or contact PayMongo.

### 1d. Acceptance check against the Manila sheets
```bash
php artisan reports:acceptance
```
Adds an acceptance program with the four example lines of Manila's February 2026 Free and Paid Screening Report sheets (past dates, never shown to customers), generates its report and prints each value next to the sheet's value. Expected last line: *"All 4 rows and the program total match the Manila sheets."*

---

## 2. How the main flows work

**Booking (both kinds):** pick seats on the floor plan (rows J at the top to A at the bottom, screen at the bottom, exits beside row B, entrance at the top) → **Who's coming?**: one attendee per seat, every field required except middle name and the IDs (red asterisks; School/Company is required by OWWA) → **Review booking** (compulsory: every detail read-only, each ticket's price, **Edit details** goes back with everything kept) → **Confirm**. Submitting without the Review step only shows the Review page.

**Pricing:** each seat is one ticket. On a paid screening a ticket is the screening price, or **20% off** when that attendee gives a PWD ID or a Senior Citizen ID (PWD counted first; one discount per ticket, never stacked). Example: 4 regular + 1 PWD at ₱50 = ₱240. The price, discount and amount are stored on `reservation_seats`, and the PayMongo amount is their sum. The summary card shows Regular × n and PWD/Senior × n as IDs are typed; the server prices it again.

**Free screening:** confirmed **as soon as it is submitted** (no staff approval) → **E-ticket email** straight away.

**Paid screening:** saved as **Awaiting payment**, the *"Complete your payment"* email is sent, and the customer goes straight to PayMongo checkout → on return, **the server asks PayMongo's API** whether the session is paid (the redirect alone proves nothing) → paid: payment **verified**, reservation **Confirmed**, **E-ticket email** sent. If the customer abandons checkout, they can still pay from the booking page or the email link **within 15 minutes**. After that the booking **expires**: it becomes Cancelled (reason *payment expired*) and its seats are released. No cron job is needed, because every page request expires overdue bookings first (`ExpireUnpaidReservations` middleware); `php artisan reservations:expire-unpaid` does the same for the scheduler. A payment PayMongo reports after expiry is recorded, but the booking stays cancelled and staff see **Refund due**. If PayMongo shows it was paid *within* the window and the seats are still free, the booking is confirmed after all.

**Cancelled:** staff cancel → **Cancelled email** (it mentions a refund if the booking was paid). Cancelling **releases the seats** so others can book them; the booking, its attendees and any admissions stay on record. The database still blocks double booking: `UNIQUE(screening_id, held_seat_id)`, where `held_seat_id` is a generated column that becomes NULL once a seat is released.

**Admin navigation:** Dashboard · **Operations** (Films & schedule, Screenings & check-in) · **Insights** (Program reports, Summary) · **Settings** (Staff accounts, Super Admin only).

**Programs, films and screenings:** a **program** is a **free-text tag** on the film (Operations → Films & schedule → the film form): type it, pick a suggestion, Enter adds it. A film can carry several tags; each screening is reported under one of them (typed on the schedule form, defaulting to the film's only tag). Tags that differ only in case or spacing are one program (a hidden registry, `programs.name_key`); unused tags disappear; there is no Programs page. The catalog shows poster tiles grouped by program (filter chips, a "Draft" badge on unpublished films); a tile opens the **film panel**: details, **Edit movie**, **Add screening schedule**, and every screening of the film. A screening row opens its roster.

**Adding a screening:** one form: program tag (required), film title (films already in the catalog are suggested and fill in their details), runtime, rating, year, genre (chips from `Movie::GENRES` + Other), director and actors (typed, comma-separated), number of films (a shorts block counts its films), schedule, admission and price, and the optional report columns Partner / Type of agency / Notes. Every screening has a film: a shorts block or a talk is entered as its own film record. On save the film is created or reused by title and tagged with the program. **Batch scheduling** (new screenings only): **Repeat** daily or weekly until a date, and/or **Add showtime** rows (date + start). Every showtime gets the first one's length, at most 30 at once, and none may overlap another screening in the hall (all or nothing).

**Draft → Review → Publish:** the film and screening forms save with **Save as draft** (staff only), **Review** (saves, then shows the customer film page and home page card exactly as customers will see them, with **Back to edit** and **Publish**) or **Publish**. Customers see a screening only when it **and** its film are published; a draft has no film page and can't be booked (404). Publishing a screening also publishes its film. Review on something already published saves the changes live.

**Posters:** staff upload a poster (JPG/PNG/WebP, max 2 MB) on the film form. Posters are stored on the `public` disk under `posters/` (needs `php artisan storage:link`). Films without a poster show the generated gradient tile. The demo catalog uses posters of the FDCP Cinematheque Davao lineup from Wikipedia (fair use, low resolution; sources in `database/seeders/posters/sources.json`).

**Screening roster & door check-in** (Operations → Screenings & check-in → a screening): one screen for bookings and admission (the separate Reservations pages were merged into it and now redirect here). One row per booking: booker, reference, payment (₱ paid / unpaid / refund due), seats, status; expanding it shows each guest's age, sex, School/Company, PWD / Senior ID and discount, and contact. The ⋯ menu has **Resend email**, **Refresh from PayMongo** and **Cancel booking**. Search and filters (To admit, Admitted, Awaiting payment, Cancelled) are on top.
**Time lock:** until **20 minutes before the start** the page is a reservation roster and the Admit buttons are disabled with "Check-in opens at 2:40 PM"; check-in then stays open until **60 minutes after the end** (late arrivals, corrections). The page reloads itself when the window opens or closes (timed by the server). The rule is enforced on the server (`AttendancePolicy`, `Screening::checkInState()`); the **Super Admin** can admit or correct at any time. **Admit** (one person) or **Admit party** (untick anyone absent); **Undo** and **Note** while open. Only confirmed bookings can be admitted; anyone not admitted after the date is a **No-show**.

**Program reports (the Manila report):** the list shows every program tag with live totals from one grouped query (`ProgramReports::overview()`: screenings free/paid, admitted M / F / PWD / Senior, verified revenue) and one-click **.xlsx** (Manila layout) and **.csv** exports: the **locked snapshot** once the report is submitted, otherwise live figures. Choose a program → **Generate**. One frozen row per published screening of the program, free and paid together: viewers M / F (people admitted at the door), PWD and Senior (from their IDs), total audience, occupancy (audience ÷ 120 × 100), and for paid rows regular / discount tickets and sales (payments PayMongo verified), plus a program total. Staff type Partner, Type of agency and Notes in the table, then **Submit to Super Admin**: the report is **locked** for everyone. An Admin can **Request unlock** with a reason; only the **Super Admin** can **Unlock** (with a reason). Then it can be edited, regenerated (typed columns are kept) and resubmitted. Every step is logged (`report_events`) and shown on the report. **Export .xlsx** gives the Manila layout: two-row header (NO. OF VIEWERS over M / F, TICKETS SOLD over REGULAR / DISCOUNT), Program merged down its rows, Date / Day merged per day. The export is written by `App\Support\XlsxWriter` (ext-zip only; PhpSpreadsheet was not used because it needs GD). **Summary** (Insights) shows totals for a date range (admitted, Male, Female, PWD, Senior, verified income) above the Attendance and Demographics tables (CSV).

---

## 3. Where things are

| Path | What it does |
|---|---|
| `routes/web.php` | Customer (`/cinemathequecentredavao`), admin (`/ccdadmin`), webhook routes |
| `routes/console.php` | `reservations:expire-unpaid`, `reports:acceptance` |
| `app/Http/Controllers/BookingController.php` | Seat picking, details, Review step (`review`), booking (`store`), PayMongo start and return |
| `app/Http/Controllers/PublicScreeningController.php` | Home showcase and film page; `page()` also renders the staff Review preview |
| `app/Http/Controllers/PayMongoWebhookController.php` | Signature-checked webhook |
| `app/Services/PayMongo/PayMongoClient.php` | The two PayMongo API calls (create / retrieve Checkout Session) |
| `app/Services/ReservationPayments.php` | The only code that marks a payment paid, after verifying reference + amount with PayMongo |
| `app/Services/ReservationMailer.php` | Sends the 3 emails; failures are logged, never fatal |
| `app/Services/ProgramReports.php` | Report rows and totals, and the generate / edit / submit / unlock workflow |
| `app/Services/ProgramReportSheet.php`, `app/Support/XlsxWriter.php` | The Manila `.xlsx` layout and the minimal `.xlsx` writer |
| `app/Models/ReservationSeat.php` | `priceFor()`: regular or 20% off per ticket |
| `app/Mail/*`, `resources/views/emails/*` | Awaiting payment / E-ticket / Cancelled email classes and templates |
| `app/Http/Controllers/Staff/ScreeningController.php` | Screenings list, roster page, create (incl. batch) / edit, Review preview, Publish |
| `app/Http/Controllers/Staff/MovieController.php` | Poster-tile catalog, film panel, film form, Review preview, Publish |
| `app/Http/Controllers/Staff/ProgramReportController.php` | Program reports, Super Admin inbox, submit / unlock, `.xlsx` export |
| `app/Http/Controllers/Staff/AttendanceController.php` | Admit / undo / remarks (JSON for the roster); time lock in `AttendancePolicy` |
| `app/Http/Controllers/Staff/ReservationController.php` | Cancel, resend email, refresh from PayMongo (from the roster); old Reservations URLs redirect to the roster |
| `app/Http/Controllers/Staff/UserController.php` | Staff accounts (Super Admin only; everyone edits their own account) |
| `app/Policies/*` | `StaffPolicy` base + record-state rules; `ReportPolicy`, `UserPolicy` add the Super Admin rules |
| `database/seeders/ManilaAcceptanceSeeder.php` | The Manila sheet examples and their expected values |
| `resources/views/layouts/app.blade.php` + `public/css/cinematheque.css` + `public/js/cinematheque.js` | Customer design and scripts |
| `resources/views/layouts/staff.blade.php` + `public/css/admin.css` + `public/js/admin.js` | Admin design and scripts |
| `docs/` | MySQL data dictionary, schema SQL, ERD (updated 2026-10-11) |

### Database changes in the 2026-10-11 system revision
| Migration | Change |
|---|---|
| `2026_10_11_000001_create_programs_tables` | `programs`, `movie_program`; `screenings.program_id`. Existing films/screenings go into "Unassigned" |
| `2026_10_11_000002_add_publishing_and_report_fields` | `movies.status`, `screenings.status` (draft/published, existing rows published); `screenings.partner`, `agency_type`, `notes` |
| `2026_10_11_000003_add_ticket_pricing_to_reservation_seats` | `reservation_seats.unit_price`, `discount_type`, `amount_due` (backfilled from screening prices) |
| `2026_10_11_000004_require_attendee_company_school` | `reservation_attendees.company_school` NOT NULL (blanks become "Not provided") |
| `2026_10_11_000005_add_roles_and_awaiting_payment_status` | `users.role` (super_admin/admin); `awaiting_payment` status |
| `2026_10_11_000006_create_reports_tables` | `reports`, `report_rows`, `report_events` |
| `2026_10_11_000007_auto_approve_reservations` | `pending` removed: paid pending → `awaiting_payment`, free pending → `confirmed` |
| `2026_10_11_000008_require_program_and_film_on_screenings` | `screenings.films_count`; a screening without a film gets its own film record; `movie_id` and `program_id` NOT NULL; a film with screenings can't be deleted |
| `2026_10_11_000009_add_logline_to_movies_table` | `movies.logline` for the home banner |
| `2026_10_11_000010_make_programs_a_tag_registry` | `programs.name_key` (unique, lowercased); programs that differ only in case or spacing are merged |

Earlier revisions: 2026-09-26 (control number removed, PayMongo fields), 2026-10-06 (seat release on cancel, unpaid expiry, posters, old payment-proof tables dropped), 2026-10-07/08 (PWD ID, 120 seats as rows A–J × 12). The app's timezone is **Asia/Manila** (`APP_TIMEZONE` in `.env` overrides it).

---

## 4. Design
- **Light theme by default** on both sites. A moon/sun button (customer header, admin top bar) switches to an optional **dark theme**. The choice is remembered per browser and never forced.
- **Customer site** (`public/css/cinematheque.css`, Poppins + Oswald, gold, ticket shapes): looping spotlight carousel, poster showcase with Now Showing / Advance Booking / Coming Soon / Special Screenings tabs, film page with a centred hero and showtime "ticket" rows, a four-step booking flow (Seats → Details → Review → Pay/Confirmed) with the action on the left and a summary card with a large poster on the right. The footer keeps its link columns, address and FDCP badge (the large CINEMATHEQUE photo wordmark was removed in the revision).
- **Admin** (`public/css/admin.css`, Geist): yellow / black / white, black sidebar with the serpent mark, gold primary buttons, tables where clicking a row opens the record, poster tiles for the film catalog, the Manila table for reports. Minimal split sign-in page.
- Both colour systems are token-based (`:root` light, `:root[data-theme="dark"]` dark). Change a token, not individual components.

## 5. Access control
Every `/ccdadmin/*` page requires a logged-in, **active** staff account (`auth` + `EnsureStaffIsActive`). There are two roles (`users.role`):
- **Admin:** films, program tags, schedules, bookings, door check-in (inside the check-in window), reports (generate, edit, submit, request unlock); edits their own account. Position (AVT/PDO) is a job title only.
- **Super Admin** (exactly one, FDCP Manila): everything an Admin does, plus the report inbox, **unlocking** submitted reports, check-in corrections **at any time**, and creating, editing, deactivating and setting the role of staff accounts.

Policies also add record-state rules (only confirmed bookings can be admitted; a submitted report is read-only; a film or program with screenings can't be deleted; nobody deactivates or demotes their own account). Customers never log in; hiding the admin URL is not the protection.
