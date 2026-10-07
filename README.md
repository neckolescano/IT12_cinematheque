# Cinematheque Centre Davao — Ticketing & Reservation System

Laravel 12 · PHP 8.2+ · MySQL 8 (or XAMPP MariaDB 10.4) · PayMongo (sandbox) · SMTP email.

| Area | URL |
|---|---|
| Customer site | `/cinemathequecentredavao` (`/` redirects here) |
| Staff admin | `/ccdadmin` (login at `/ccdadmin/login`). Not linked from the customer site, and every page requires a staff login |
| PayMongo webhook | `POST /webhooks/paymongo` |

---

## 1. Setup

You need PHP 8.2+, Composer and MySQL (XAMPP is fine).

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
Open http://127.0.0.1:8000. Staff: http://127.0.0.1:8000/ccdadmin (`avt@cinematheque.test` / `password`).

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
4. `PAYMONGO_PAYMENT_METHODS=card,gcash,paymaya` sets which methods the checkout offers (they must be enabled on your PayMongo account).
5. Use PayMongo's published test cards and test e-wallet flows. **Never put live keys (`sk_live_…`) in a development `.env`.**

After editing `.env`, run `php artisan config:clear`.

### 1c. Tests
```bash
php artisan test
```
Tests use the `cinematheque_test` database, and they fake email and PayMongo, so they never send mail or contact PayMongo.

---

## 2. How the main flows work

**Paid screening:** pick seats → enter attendees (booker email required) → reservation **Pending**, and the *"Complete your payment"* email is sent → the customer is sent straight to PayMongo checkout → on return, **the server asks PayMongo's API** whether the session is paid (the redirect alone proves nothing) → paid: payment **verified**, reservation **Approved**, **E-ticket email** sent. If the customer abandons checkout, the booking stays Pending and they can still pay from the booking page or the email link **within 15 minutes**. After that it **expires**: it becomes Cancelled (reason *payment expired*) and its seats are released. No cron job is needed, because every page request expires overdue bookings first (`ExpireUnpaidReservations` middleware); `php artisan reservations:expire-unpaid` does the same for the scheduler. A payment PayMongo reports after expiry is recorded, but the booking stays cancelled and staff see **Refund due**. If PayMongo shows it was paid *within* the window and the seats are still free, the booking is confirmed after all.

**Free screening:** pick seats → attendees → **Pending**, with the *"Reservation received"* email → staff approve (per booking, or **Approve all pending** on the screening page) → **Approved + E-ticket email**.

**Cancelled:** staff cancel → **Cancelled email** (it mentions a refund if the booking was paid). Cancelling **releases the seats** so others can book them; the booking, its attendees and any admissions stay on record. The database still blocks double booking: `UNIQUE(screening_id, held_seat_id)`, where `held_seat_id` is a generated column that becomes NULL once a seat is released.

**Adding a screening:** one form. Choose *A film* or *Special programme*. For a film, type the title (films already in the catalog are suggested and fill in their details), then runtime, rating, year, genre (chips from a fixed list in `Movie::GENRES`), director and actors (typed, comma-separated). On save the film is created or reused by title, and the names are stored in the existing `directors`/`actors`/`genres` tables behind the scenes, so there are no separate pages for people or genres. Blank fields never erase what the catalog already knows; edit the film under Film catalog to change or remove details.

**Posters:** staff upload a poster (JPG/PNG/WebP, max 2 MB) on Settings → Film catalog → Movie. Posters are stored on the `public` disk under `posters/` (needs `php artisan storage:link`). Screenings without a film, or films without a poster, show the generated gradient tile. The demo catalog uses posters of the FDCP Cinematheque Davao lineup from Wikipedia (fair use, low resolution; sources in `database/seeders/posters/sources.json`).

**Admission:** Admin → Screenings → open the screening. The **attendee checklist** lists every reserved seat for that screening, with separate *Reservation*, *Payment* and *Attendance* columns. Press **Admit** when a person actually arrives (one click, no page reload; **Undo** fixes mistakes). Only **Approved** bookings can be admitted. After the screening date, anyone reserved but not admitted shows as **No-show**. There is no control number.

---

## 3. Where things are

| Path | What it does |
|---|---|
| `routes/web.php` | Customer (`/cinemathequecentredavao`), admin (`/ccdadmin`), webhook routes |
| `app/Http/Controllers/BookingController.php` | Seat picking, booking, PayMongo start and return |
| `app/Http/Controllers/PayMongoWebhookController.php` | Signature-checked webhook |
| `app/Services/PayMongo/PayMongoClient.php` | The two PayMongo API calls (create / retrieve Checkout Session) |
| `app/Services/ReservationPayments.php` | The only code that marks a payment paid, after verifying reference + amount with PayMongo |
| `app/Services/ReservationMailer.php` | Sends the 3 emails; failures are logged, never fatal |
| `app/Mail/*`, `resources/views/emails/*` | Pending / Approved (E-ticket) / Cancelled email classes and templates |
| `app/Http/Controllers/Staff/ScreeningController.php` | Screening list, drawers, workspace + checklist, "approve all pending" |
| `app/Http/Controllers/Staff/AttendanceController.php` | Admit / undo / remarks (JSON for the checklist) |
| `app/Http/Controllers/Staff/ReservationController.php` | Search, approve, cancel, resend email, refresh from PayMongo |
| `resources/views/layouts/app.blade.php` + `public/css/cinematheque.css` | Customer design |
| `resources/views/layouts/staff.blade.php` + `public/css/admin.css` + `public/js/admin.js` | Admin dashboard design |
| `database/migrations/2026_09_26_*` | Revision migrations (see below) |
| `docs/` | MySQL data dictionary, schema SQL, ERD |

### Database changes in the 2026-09-26 revision
| Migration | Change |
|---|---|
| `2026_09_26_000001_remove_control_number_from_attendances_table` | Drops `attendances.control_number` and its unique index |
| `2026_09_26_000002_add_paymongo_fields_to_payments_table` | Adds `payments.provider_session_id` (unique), `provider_payment_id`, `paid_at` |

### Database changes in the 2026-10-06 revision (decisions 5a–5d)
| Migration | Change |
|---|---|
| `2026_10_06_000001_release_seats_on_cancel_and_expire_unpaid` | Adds `reservations.cancellation_reason` (`staff` / `payment_expired`) and `cancelled_at`; adds `reservation_seats.released_at` and the generated `held_seat_id`; replaces `UNIQUE(screening_id, seat_id)` with `UNIQUE(screening_id, held_seat_id)`; releases the seats of bookings already cancelled |
| `2026_10_06_000002_drop_legacy_payment_proof_tables` | Drops `payment_proofs` and `payment_qr_codes` (the retired QR flow); their models are removed |
| `2026_10_06_000003_add_poster_path_to_movies_table` | Adds `movies.poster_path` |

The app's timezone is now **Asia/Manila** (`APP_TIMEZONE` in `.env` overrides it), so booking times, payment deadlines and "today" match Davao.

---

## 4. Design
- **Light theme by default** on both sites. A moon/sun button (customer header, admin top bar, admin login) switches to an optional **dark theme**. The choice is remembered per browser and never forced.
- Customer site (`public/css/cinematheque.css`): centred 1120 px container with responsive side padding. The booking flow uses a two-column layout (seat map or form + sticky **Booking summary**) that collapses to one column with a sticky bottom bar on phones. The confirmation page doubles as a printable e-ticket.
- Admin (`public/css/admin.css`): separate dense dashboard system (Inter font, white sidebar, task-based navigation).
- Both colour systems are token-based (`:root` light, `:root[data-theme="dark"]` dark). Change a token, not individual components.

## 5. Access control
Every `/ccdadmin/*` page requires a logged-in, **active** staff account (`auth` + `EnsureStaffIsActive`). AVT and PDO have identical access. Policies only add record-state rules (e.g. only Approved bookings can be admitted, and paid bookings can only be approved by PayMongo). Customers never log in; hiding the admin URL is not the protection.
