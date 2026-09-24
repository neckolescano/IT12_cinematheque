# Cinematheque Centre Davao — Laravel + MySQL

A complete Laravel 12 project built from `erd.html` and `implementation.md`.

- **Framework:** Laravel 12, PHP 8.2+
- **Database:** MySQL 8.0+ (InnoDB, utf8mb4). Also verified on MariaDB 10.4 (XAMPP).
- **Front end:** Blade with a hand-written design system (`public/css/cinematheque.css` + `public/js/cinematheque.js`), styled after fdcp.ph. There is no npm or Vite step.

Tested on MySQL: all migrations and seeders ran, and all 34 feature tests passed (174 assertions).

---

## 1. Setup

You need PHP 8.2+, Composer, and a running MySQL server. On XAMPP, start MySQL from the XAMPP Control Panel.

**1. Open the folder in VS Code and open a terminal (Ctrl+`).**

**2. Install the PHP dependencies.** The `vendor/` folder isn't included in the download.
```bash
composer install
```

**3. Create your `.env` file.** `.env.example` already has MySQL settings (`127.0.0.1:3306`, database `cinematheque`, user `root`, empty password).
```bash
cp .env.example .env
```
On Windows Command Prompt, use `copy .env.example .env` instead. If your MySQL root user has a password, set `DB_PASSWORD=` in `.env`.

**4. Generate the app key.**
```bash
php artisan key:generate
```

**5. Create the databases.** Create `cinematheque` for the app and `cinematheque_test` for the tests, either in phpMyAdmin (collation `utf8mb4_unicode_ci`) or from the command line:
```bash
mysql -u root -p -e "CREATE DATABASE cinematheque CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE cinematheque_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
On XAMPP the `mysql` command is at `C:\xampp\mysql\bin\mysql.exe`.

**6. Build the tables and load the demo data.**
```bash
php artisan migrate:fresh --seed
```

**7. Make uploaded images viewable** (payment proofs and QR codes).
```bash
php artisan storage:link
```

**8. Run the tests.** They use `cinematheque_test` (set in `phpunit.xml`) and wipe it on every run. They never touch `cinematheque`.
```bash
php artisan test
```

**9. Start the app.**
```bash
php artisan serve
```
Open http://127.0.0.1:8000. Staff login is at http://127.0.0.1:8000/login.

### Seeded logins (password: `password`)
| Email | Position | Notes |
|---|---|---|
| avt@cinematheque.test | AVT | full staff access |
| pdo@cinematheque.test | PDO | identical access |
| inactive@cinematheque.test | PDO | `is_active = 0`; login is refused |

Change these passwords after the first login (Staff → Edit).

---

## 2. Where every file is

Every path below is relative to the project root, the folder that contains `artisan`. The rest of the project is standard Laravel 12.

### Database
| Path | What it is |
|---|---|
| `database/migrations/0001_01_01_000000_create_users_table.php` | `users` (+ Laravel's `password_reset_tokens`, `sessions`) |
| `database/migrations/2026_09_24_000001_create_movies_table.php` | `movies` |
| `database/migrations/2026_09_24_000002_create_actors_table.php` | `actors` |
| `database/migrations/2026_09_24_000003_create_directors_table.php` | `directors` |
| `database/migrations/2026_09_24_000004_create_genres_table.php` | `genres` |
| `database/migrations/2026_09_24_000005_create_movie_actor_table.php` | `movie_actor` |
| `database/migrations/2026_09_24_000006_create_movie_director_table.php` | `movie_director` |
| `database/migrations/2026_09_24_000007_create_movie_genre_table.php` | `movie_genre` |
| `database/migrations/2026_09_24_000008_create_seats_table.php` | `seats` |
| `database/migrations/2026_09_24_000009_create_screenings_table.php` | `screenings` |
| `database/migrations/2026_09_24_000010_create_reservations_table.php` | `reservations` |
| `database/migrations/2026_09_24_000011_create_reservation_seats_table.php` | `reservation_seats` |
| `database/migrations/2026_09_24_000012_create_reservation_attendees_table.php` | `reservation_attendees` |
| `database/migrations/2026_09_24_000013_create_payments_table.php` | `payments` |
| `database/migrations/2026_09_24_000014_create_payment_proofs_table.php` | `payment_proofs` |
| `database/migrations/2026_09_24_000015_create_payment_qr_codes_table.php` | `payment_qr_codes` |
| `database/migrations/2026_09_24_000016_create_attendances_table.php` | `attendances` |
| `database/seeders/DatabaseSeeder.php` | Runs the four seeders below |
| `database/seeders/StaffUserSeeder.php` | **RBAC seeder**: default AVT and PDO accounts, plus one inactive account |
| `database/seeders/SeatSeeder.php` | 100 venue seats, A1–J10 |
| `database/seeders/CatalogSeeder.php` | Demo genres, movies, actors, directors |
| `database/seeders/DemoScreeningSeeder.php` | Demo screenings, reservations, payments, proofs, QR code, attendance |
| `database/factories/` | `UserFactory`, `MovieFactory`, `ActorFactory`, `DirectorFactory`, `GenreFactory`, `ScreeningFactory`, `ReservationFactory` |

### Models (`app/Models/`)
`User.php`, `Movie.php`, `Actor.php`, `Director.php`, `Genre.php`, `Seat.php`, `Screening.php`, `Reservation.php`, `ReservationSeat.php`, `ReservationAttendee.php`, `Payment.php`, `PaymentProof.php`, `PaymentQrCode.php`, `Attendance.php`. There is one model per table; the pivot tables don't need models.

### Access control
| Path | What it is |
|---|---|
| `routes/web.php` | Public routes, plus the `/staff` group protected by `auth` and `EnsureStaffIsActive` |
| `app/Http/Middleware/EnsureStaffIsActive.php` | Logs out deactivated staff |
| `app/Policies/StaffPolicy.php` | Base policy: any active staff account is allowed, `position` is never checked |
| `app/Policies/ScreeningPolicy.php` | Can't delete a screening that has reservations |
| `app/Policies/ReservationPolicy.php` | Confirm / cancel rules |
| `app/Policies/PaymentProofPolicy.php` | A proof is reviewed once |
| `app/Policies/AttendancePolicy.php` | A seat is checked in once, never for a cancelled reservation |
| `app/Policies/SeatPolicy.php` | Can't delete a seat that has been reserved |
| `app/Policies/UserPolicy.php` | Staff can't deactivate themselves |
| `app/Policies/MoviePolicy.php`, `ActorPolicy.php`, `DirectorPolicy.php`, `GenrePolicy.php`, `PaymentQrCodePolicy.php` | Staff-only access |

### Controllers and validation
| Path | What it handles |
|---|---|
| `app/Http/Controllers/PublicScreeningController.php` | Public screening list and detail |
| `app/Http/Controllers/BookingController.php` | Seat picking, reservation submission, booking lookup, Payment Screen |
| `app/Http/Controllers/PaymentProofUploadController.php` | Public proof upload |
| `app/Http/Controllers/Auth/LoginController.php` | Staff login and logout |
| `app/Http/Controllers/Staff/DashboardController.php` | Staff dashboard |
| `app/Http/Controllers/Staff/ScreeningController.php` | Screening management |
| `app/Http/Controllers/Staff/MovieController.php` | Movie catalog |
| `app/Http/Controllers/Staff/PersonController.php` | Shared base for actors and directors |
| `app/Http/Controllers/Staff/ActorController.php`, `DirectorController.php`, `GenreController.php` | Catalog lists |
| `app/Http/Controllers/Staff/SeatController.php` | Venue seats |
| `app/Http/Controllers/Staff/ReservationController.php` | Reservation search, confirm, cancel |
| `app/Http/Controllers/Staff/PaymentProofController.php` | Accept or reject proofs |
| `app/Http/Controllers/Staff/PaymentQrCodeController.php` | Replace the payment QR code |
| `app/Http/Controllers/Staff/AttendanceController.php` | Check-in and control numbers |
| `app/Http/Controllers/Staff/UserController.php` | Staff accounts |
| `app/Http/Controllers/Staff/ReportController.php` | Reports |
| `app/Http/Requests/StoreReservationRequest.php` | Reservation validation |
| `app/Http/Requests/ScreeningRequest.php` | Screening validation |

### Views (`resources/views/`)
| Path | Page |
|---|---|
| `layouts/app.blade.php` | Public layout: header, hero slot, footer |
| `layouts/staff.blade.php` | Staff layout: sidebar, top bar |
| `partials/` | `head`, `brand`, `skyline`, `flash`, `confirm-modal` |
| `components/` | `<x-status>`, `<x-date-badge>`, `<x-poster>`, `<x-stepper>`, `<x-empty>` |
| `auth/login.blade.php` | Staff login |
| `public/screenings/index.blade.php`, `show.blade.php`, `_card.blade.php` | Landing page, screening detail, screening card |
| `public/bookings/create.blade.php` | Seat picker and attendee form |
| `public/bookings/show.blade.php` | Booking summary and Payment Screen |
| `public/bookings/lookup.blade.php` | Find a booking by reference |
| `staff/dashboard.blade.php` | Dashboard |
| `staff/screenings/index.blade.php`, `form.blade.php`, `show.blade.php` | Screenings |
| `staff/reservations/index.blade.php`, `show.blade.php` | Reservations and check-in |
| `staff/payment-proofs/index.blade.php`, `_table.blade.php` | Proof review queue |
| `staff/qr-codes/index.blade.php` | QR code |
| `staff/movies/index.blade.php`, `form.blade.php` | Movies |
| `staff/people/index.blade.php`, `edit.blade.php` | Actors and directors (shared) |
| `staff/genres/index.blade.php`, `edit.blade.php` | Genres |
| `staff/seats/index.blade.php` | Seats |
| `staff/users/index.blade.php`, `form.blade.php` | Staff accounts |
| `staff/reports/index.blade.php` | Reports, with CSV export (`/staff/reports/export`) |

### Design system (`public/`)
| Path | What it is |
|---|---|
| `public/css/cinematheque.css` | Colour, type, spacing, shadow and motion tokens, plus every component style |
| `public/js/cinematheque.js` | Optional enhancements: header on scroll, mobile menu, reveal on scroll, confirmation modal, submit loading state, live seat count, image preview |

The whole app also works with JavaScript turned off. Every animation turns off when the device's "reduce motion" setting is on.

### Tests and configuration
| Path | What it is |
|---|---|
| `tests/Feature/AccessControlTest.php` | Login, deactivation, staff vs public access, AVT = PDO |
| `tests/Feature/ReservationFlowTest.php` | Booking, attendees, double-booking, capacity |
| `tests/Feature/PaymentReviewTest.php` | Proof upload, accept/reject, QR replacement |
| `tests/Feature/StaffManagementTest.php` | Screenings, catalog, check-in, every staff page renders |
| `tests/Feature/ExampleTest.php` | Home page check |
| `phpunit.xml` | Tests run on the MySQL database `cinematheque_test` |
| `.env.example` | MySQL connection defaults |

### Documentation (`docs/`): reference only, Laravel doesn't load these
| Path | What it is |
|---|---|
| `docs/data-dictionary-mysql.md` | Data dictionary with MySQL types, NULL, keys, defaults and on-delete rules |
| `docs/schema-mysql.sql` | `CREATE TABLE` DDL for all 17 tables, taken from the real MySQL result |
| `docs/erd-mysql.html` | ERD with MySQL column types (open in a browser) |

---

## 3. RBAC, as the spec defines it

`implementation.md` §9 (revised) says: **no roles or permissions tables and no Spatie**. AVT and PDO have identical access, and moviegoers never log in.

- **Route boundary:** every `/staff/*` route runs `auth` and then `EnsureStaffIsActive`. Public routes (browse, reserve, look up a booking, upload proof) have no middleware.
- **Policies** extend `StaffPolicy`. They allow any active staff account and never read `position`. They add only record-state rules, such as a proof being reviewed once.
- **Conditional UI:** views use `@can`. Guests always fail policies, so staff-only links and buttons never render for them.
- **RBAC seeder:** `StaffUserSeeder` seeds the default staff accounts, since those are the only access tier.

## 4. Assumptions and open points

1. **Free reservations are `confirmed` on submission**, because there is no payment step to confirm them. Staff can still confirm a pending free reservation.
2. **Cancelling does not free the seats.** The rows are kept as history, so `UNIQUE(screening_id, seat_id)` still holds them. Freeing them means either deleting the seat rows (losing attendee history) or moving the uniqueness rule into application code.
3. **`payments.status = 'rejected'` is never set.** The spec says a rejected proof keeps the payment `pending`.
4. **Timestamps** exist only where the data dictionary lists them: `screenings.created_at`/`updated_at` and `payments.created_at`.
5. **At most 10 seats per reservation.** This limit is mine, not from the spec (`StoreReservationRequest::MAX_SEATS_PER_RESERVATION`).
6. **The booking reference is the moviegoer's only credential** for their booking page. Anyone who has it can open the page.
