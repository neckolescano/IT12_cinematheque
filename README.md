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

**Paid screening:** pick seats → enter attendees (booker email required) → reservation **Pending**, and the *"Complete your payment"* email is sent → the customer is sent straight to PayMongo checkout → on return, **the server asks PayMongo's API** whether the session is paid (the redirect alone proves nothing) → paid: payment **verified**, reservation **Approved**, **E-ticket email** sent. If the customer abandons checkout, the booking stays Pending and they can pay later from the booking page or the email link.

**Free screening:** pick seats → attendees → **Pending**, with the *"Reservation received"* email → staff approve (per booking, or **Approve all pending** on the screening page) → **Approved + E-ticket email**.

**Cancelled:** staff cancel → **Cancelled email** (it mentions a refund if the booking was paid).

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

`payment_proofs` and `payment_qr_codes` (the old QR flow) are **kept, unchanged, as history**. The app no longer writes to them.

---

## 4. Design
- **Light theme by default** on both sites. A moon/sun button (customer header, admin top bar, admin login) switches to an optional **dark theme**. The choice is remembered per browser and never forced.
- Customer site (`public/css/cinematheque.css`): centred 1120 px container with responsive side padding. The booking flow uses a two-column layout (seat map or form + sticky **Booking summary**) that collapses to one column with a sticky bottom bar on phones. The confirmation page doubles as a printable e-ticket.
- Admin (`public/css/admin.css`): separate dense dashboard system (Inter font, white sidebar, task-based navigation).
- Both colour systems are token-based (`:root` light, `:root[data-theme="dark"]` dark). Change a token, not individual components.

## 5. Access control
Every `/ccdadmin/*` page requires a logged-in, **active** staff account (`auth` + `EnsureStaffIsActive`). AVT and PDO have identical access. Policies only add record-state rules (e.g. only Approved bookings can be admitted, and paid bookings can only be approved by PayMongo). Customers never log in; hiding the admin URL is not the protection.
