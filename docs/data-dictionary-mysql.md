# Data Dictionary (MySQL)

Cinematheque Centre Davao. **DBMS: MySQL 8.0+** (MariaDB 10.4+ in XAMPP), InnoDB engine, `utf8mb4` / `utf8mb4_unicode_ci`.

Every type below was checked against the actual schema (`mysqldump --no-data`) after running the Laravel migrations. The full DDL is in [schema-mysql.sql](schema-mysql.sql). **Last updated 2026-10-11** for the system revision (programs, draft/publish, auto-approval, PWD/Senior discount, program reports and roles).

**Conventions**
- Every surrogate primary key is `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`. Laravel's `$table->id('...')` creates this.
- Every foreign key is `BIGINT UNSIGNED`, so it matches the key it references.
- `BOOLEAN` is MySQL's alias for `TINYINT(1)`. Values are 0 and 1.
- `ENUM` is used only for fixed value lists that the spec defines.
- Integer types are sized to fit their data: `SMALLINT UNSIGNED` (0–65,535) for runtime and year, and `TINYINT UNSIGNED` (0–255) for age and number of films.
- Tables are listed in dependency order, parents before children. The migrations run in the same order.

On-delete rules: **CASCADE** = child rows are deleted with the parent. **SET NULL** = the link is cleared. **RESTRICT** = the parent can't be deleted while children exist. RESTRICT is MySQL's default, so the DDL doesn't state it.

### What changed in the 2026-10-11 revision
| Change | Tables |
|---|---|
| Program level above films (Program → Movie → Screening); a film can be in several programs | new `programs`, `movie_program`; `screenings.program_id` |
| Draft → Review → Publish; drafts never show to customers | `movies.status`, `screenings.status` |
| Every screening has a film and a program; a shorts block is its own film record | `screenings.movie_id`, `screenings.program_id` NOT NULL; `screenings.films_count` |
| Manila report columns per screening | `screenings.partner`, `agency_type`, `notes` |
| Auto-approval: no "pending"; paid bookings wait for PayMongo | `reservations.status` |
| One seat = one ticket, 20% PWD/Senior discount, price frozen at booking | `reservation_seats.unit_price`, `discount_type`, `amount_due` |
| School/Company required (OWWA) | `reservation_attendees.company_school` NOT NULL |
| Super Admin and Admin roles | `users.role` |
| One Manila report per program, frozen rows, lock and audit trail | new `reports`, `report_rows`, `report_events` |

---

### Table 1: users
Staff accounts only. `role` gates access; `position` (AVT/PDO) is a job title only.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| user_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Staff account ID |
| first_name | VARCHAR(50) | No | | | First name |
| middle_name | VARCHAR(50) | Yes | | NULL | Middle name |
| last_name | VARCHAR(50) | No | | | Last name |
| email | VARCHAR(100) | No | UNIQUE | | Login email |
| password | VARCHAR(255) | No | | | Bcrypt hash |
| position | ENUM('AVT','PDO') | Yes | | NULL | Job title. Descriptive only, never used for access |
| role | ENUM('super_admin','admin') | No | | 'admin' | `super_admin`: also unlocks submitted reports and manages staff accounts. Exactly one Super Admin (enforced by the app). *Added 2026-10-11* |
| is_active | BOOLEAN (TINYINT(1)) | No | | 1 | Whether the account can log in |

### Table 2: programs *(new 2026-10-11; a tag registry since the operations restructure)*
Programs (Cinemalaya 2026, Filipino Classics…) are **free-text tags** typed on the film form, with suggestions; there is no Programs page. This table is the hidden registry behind the tags: tags that differ only in case or spacing are one program (`name_key`), and reports and their lock keep a stable record. A tag no film, screening or report uses is deleted automatically.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| program_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Program ID |
| name | VARCHAR(100) | No | UNIQUE | | The tag as first typed (spaces collapsed) |
| name_key | VARCHAR(100) | No | UNIQUE | | `name` lowercased: the match key (`Program::fromTag()`). *Added 2026-10-11* |
| description | TEXT | Yes | | NULL | Not used since programs became tags |
| is_active | BOOLEAN (TINYINT(1)) | No | | 1 | Not used since programs became tags |
| created_at / updated_at | TIMESTAMP | Yes | | NULL | Set by Laravel |

Grouping for reports is by program (the same as GROUP BY the tag name). `screenings.program_id` and `reports.program_id` are RESTRICT, so a tag in use is never deleted.

### Table 3: movies
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| movie_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Film ID |
| title | VARCHAR(150) | No | | | Film title (a shorts block or a talk is a film record too) |
| runtime_minutes | SMALLINT UNSIGNED | Yes | | NULL | Running time in minutes |
| rating | VARCHAR(10) | Yes | | NULL | MTRCB content rating, e.g. PG-13 |
| release_year | SMALLINT UNSIGNED | Yes | | NULL | Year of release. `YEAR` is not used because it only covers 1901–2155 |
| synopsis | TEXT | Yes | | NULL | Plot description |
| logline | VARCHAR(200) | Yes | | NULL | One line for the home banner; when empty the banner shows the synopsis's first sentence (max 120 characters). *Added 2026-10-11* |
| curator_note | VARCHAR(200) | Yes | | NULL | One-line "Curator's Pick" note for the home page. *Added 2026-10-07* |
| trailer_url | VARCHAR(255) | Yes | | NULL | Trailer link (YouTube/Vimeo play on the page). *Added 2026-10-07* |
| poster_path | VARCHAR(255) | Yes | | NULL | Poster image on the `public` disk, e.g. `posters/himala.jpg`. NULL = generated tile. *Added 2026-10-06* |
| status | ENUM('draft','published') | No | | 'published' | `draft` = staff only. Customers see a screening only when it **and** its film are published. *Added 2026-10-11* |

A film with screenings can't be deleted (`screenings.movie_id` is RESTRICT); save it as a draft to hide it.

### Table 4: movie_program (pivot) *(new 2026-10-11)*
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| movie_id | BIGINT UNSIGNED | No | PK, FK → movies.movie_id (CASCADE) | | Film |
| program_id | BIGINT UNSIGNED | No | PK, FK → programs.program_id (CASCADE) | | Program |

Composite primary key: `(movie_id, program_id)`. Scheduling a screening under a program adds its film to that program.

### Film details: how actors, directors and genres are entered *(2026-10-07)*
Tables 5–10 have no admin pages of their own. Staff enter a film's details on the **Add/Edit Screening** form or the film form:
- **Director** and **Actors** are typed as text, separated by commas, semicolons or new lines. Each name is split into first name (everything but the last word) and last name (the last word), then matched or created. People no longer credited on any film are deleted.
- **Genres** are chosen as chips from a fixed list (`Movie::GENRES`) plus "Other". A row is created in `genres` the first time a value is used.
- A film typed on the screening form is matched to an existing `movies` row by title (case-insensitive) or created. Blank fields never erase values already in the catalog.

### Table 5: actors
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| actor_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Actor ID |
| first_name | VARCHAR(50) | No | | | First name |
| middle_name | VARCHAR(50) | Yes | | NULL | Middle name |
| last_name | VARCHAR(50) | No | | | Last name |

### Table 6: movie_actor (pivot)
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| movie_id | BIGINT UNSIGNED | No | PK, FK → movies.movie_id (CASCADE) | | Film |
| actor_id | BIGINT UNSIGNED | No | PK, FK → actors.actor_id (CASCADE) | | Actor |

### Table 7: directors
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| director_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Director ID |
| first_name | VARCHAR(50) | No | | | First name |
| middle_name | VARCHAR(50) | Yes | | NULL | Middle name |
| last_name | VARCHAR(50) | No | | | Last name |

### Table 8: movie_director (pivot)
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| movie_id | BIGINT UNSIGNED | No | PK, FK → movies.movie_id (CASCADE) | | Film |
| director_id | BIGINT UNSIGNED | No | PK, FK → directors.director_id (CASCADE) | | Director |

### Table 9: genres
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| genre_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Genre ID |
| genre_name | VARCHAR(50) | No | UNIQUE | | Genre label |

### Table 10: movie_genre (pivot)
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| movie_id | BIGINT UNSIGNED | No | PK, FK → movies.movie_id (CASCADE) | | Film |
| genre_id | BIGINT UNSIGNED | No | PK, FK → genres.genre_id (CASCADE) | | Genre |

### Table 11: seats
The hall: 120 seats, rows A–J of 12 (A1–J12). On the customer floor plan row J is at the top, row A nearest the screen at the bottom, and the exits are beside row B.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| seat_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Physical seat ID |
| seat_label | VARCHAR(10) | No | UNIQUE | | Seat code, e.g. A1 |
| section | VARCHAR(30) | Yes | | NULL | Grouping, e.g. Main |

### Table 12: screenings
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| screening_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Screening ID |
| event_title | VARCHAR(150) | No | | | Screening title (defaults to the film title) |
| movie_id | BIGINT UNSIGNED | No | FK → movies.movie_id (RESTRICT) | | The film shown. *Required since 2026-10-11 (was optional, SET NULL)* |
| films_count | TINYINT UNSIGNED | No | | 1 | Manila report "No. of films": 1 for a feature; a shorts block counts its films. *Added 2026-10-11* |
| program_id | BIGINT UNSIGNED | No | FK → programs.program_id (RESTRICT) | | The program it is reported under. *Added 2026-10-11* |
| event_date | DATE | No | INDEX | | Screening date |
| start_time | TIME | No | | | Start time |
| end_time | TIME | No | | | End time |
| type | ENUM('free','paid') | No | | 'free' | Admission type |
| price | DECIMAL(8,2) | Yes | | NULL | Regular ticket price, typed by staff. NULL for free screenings |
| total_seats | INT UNSIGNED | No | | 100 | Capacity. The app always stores 120 (the hall); the column default predates the 120-seat hall |
| status | ENUM('draft','published') | No | | 'published' | `draft` = hidden from customers (no film page, no booking). *Added 2026-10-11* |
| partner | VARCHAR(150) | Yes | | NULL | Manila report "Partner". *Added 2026-10-11* |
| agency_type | VARCHAR(100) | Yes | | NULL | Manila report "Type of Agency" (free text). *Added 2026-10-11* |
| notes | TEXT | Yes | | NULL | Manila report "Notes". *Added 2026-10-11* |
| created_by | BIGINT UNSIGNED | No | FK → users.user_id (RESTRICT) | | Staff member who posted it |
| created_at / updated_at | TIMESTAMP | Yes | | NULL | Set by Laravel |

### Table 13: reservations
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| reservation_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Booking ID |
| screening_id | BIGINT UNSIGNED | No | FK → screenings.screening_id (RESTRICT) | | Screening booked |
| booking_reference | VARCHAR(20) | No | UNIQUE | | e.g. CCD-7K2QX9AB |
| status | ENUM('awaiting_payment','confirmed','cancelled') | No | INDEX | 'confirmed' | Reservations are approved automatically: free → `confirmed` on submit; paid → `awaiting_payment` (seats held 15 minutes) → `confirmed` when PayMongo reports it paid. *`pending` removed 2026-10-11* |
| cancellation_reason | ENUM('staff','payment_expired') | Yes | | NULL | Why it was cancelled: by staff, or not paid within 15 minutes. *Added 2026-10-06* |
| cancelled_at | DATETIME | Yes | | NULL | When it was cancelled. *Added 2026-10-06* |
| reservation_datetime | DATETIME | No | | CURRENT_TIMESTAMP | When the booking was submitted (after the compulsory Review step) |
| lead_first_name | VARCHAR(50) | No | | | Booker's first name |
| lead_middle_name | VARCHAR(50) | Yes | | NULL | Booker's middle name |
| lead_last_name | VARCHAR(50) | No | | | Booker's last name |
| lead_contact_no | VARCHAR(20) | No | | | Booker's mobile number |
| lead_email | VARCHAR(100) | Yes | | NULL | Booker's email (required by the form: the e-ticket is sent here) |

### Table 14: reservation_seats
One seat = one ticket.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| reservation_seat_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | One ticket |
| reservation_id | BIGINT UNSIGNED | No | FK → reservations.reservation_id (CASCADE) | | Owning reservation |
| screening_id | BIGINT UNSIGNED | No | FK → screenings.screening_id (RESTRICT) | | Stored here as well so the unique key below can use it |
| seat_id | BIGINT UNSIGNED | No | FK → seats.seat_id (RESTRICT) | | Physical seat |
| unit_price | DECIMAL(8,2) | No | | 0.00 | The screening's price when booked (0 for free). *Added 2026-10-11* |
| discount_type | ENUM('none','pwd','senior') | No | | 'none' | From the attendee's own ID: a PWD ID gives `pwd`, else a Senior Citizen ID gives `senior`. One discount per ticket. *Added 2026-10-11* |
| amount_due | DECIMAL(8,2) | No | | 0.00 | What the ticket costs: `unit_price`, or 80% of it with a discount (20% off). *Added 2026-10-11* |
| released_at | DATETIME | Yes | | NULL | Set when the reservation is cancelled; the row stays as history. *Added 2026-10-06* |
| held_seat_id | BIGINT UNSIGNED, generated (STORED) | Yes | UNIQUE with screening_id | | `IF(released_at IS NULL, seat_id, NULL)`. *Added 2026-10-06* |

`UNIQUE (screening_id, held_seat_id)` means a seat can't be held twice for the same screening, while a released seat can be booked again. Prices are stored per ticket so a later price change never rewrites old sales; the PayMongo amount is the sum of `amount_due` (4 regular + 1 PWD at ₱50 = ₱240).

### Table 15: reservation_attendees
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| reservation_attendee_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Attendee ID |
| reservation_seat_id | BIGINT UNSIGNED | No | UNIQUE, FK → reservation_seats (CASCADE) | | Seat this person occupies (1:1) |
| is_lead_reserver | BOOLEAN (TINYINT(1)) | No | | 0 | Is this the booker? (the first seat chosen) |
| first_name | VARCHAR(50) | No | | | First name |
| middle_name | VARCHAR(50) | Yes | | NULL | Middle name |
| last_name | VARCHAR(50) | No | | | Last name |
| age | TINYINT UNSIGNED | Yes | | NULL | Age (required by the form) |
| sex | ENUM('M','F') | Yes | | NULL | Sex (required by the form); the report's M / F viewers |
| company_school | VARCHAR(150) | No | | | School or company, required by OWWA. *NOT NULL since 2026-10-11; older blanks became "Not provided"* |
| contact_no | VARCHAR(20) | Yes | | NULL | Mobile number (required by the form) |
| email | VARCHAR(100) | Yes | | NULL | Email (required by the form) |
| senior_card_no | VARCHAR(30) | Yes | | NULL | Senior Citizen (OSCA) ID: letters, digits, spaces and hyphens, 4–20 characters (formats differ per city). Report "Senior"; 20% off on paid screenings |
| pwd_id_no | VARCHAR(30) | Yes | | NULL | PWD ID: 16 digits stored as `RR-PPMM-BBB-NNNNNNN`. Report "PWD"; 20% off on paid screenings. *Added 2026-10-07* |
| pwd_indicator | BOOLEAN (TINYINT(1)) | No | | 0 | Set to 1 by the app when `pwd_id_no` is filled |

### Table 16: payments
Online payment through PayMongo hosted Checkout. The status becomes `verified` only when PayMongo's API reports the session as paid.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| payment_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Payment ID |
| reservation_id | BIGINT UNSIGNED | No | UNIQUE, FK → reservations (CASCADE) | | At most one payment per reservation |
| amount | DECIMAL(8,2) | No | | | Sum of the tickets' `amount_due` (after discounts) |
| payment_channel | VARCHAR(30) | Yes | | NULL | Method reported by PayMongo, e.g. `gcash`, `card` |
| status | ENUM('pending','verified','rejected') | No | | 'pending' | `verified` = PayMongo reported it paid |
| provider_session_id | VARCHAR(64) | Yes | UNIQUE | NULL | PayMongo Checkout Session ID (`cs_…`). *Added 2026-09-26* |
| provider_payment_id | VARCHAR(64) | Yes | | NULL | PayMongo payment ID (`pay_…`). *Added 2026-09-26* |
| paid_at | DATETIME | Yes | | NULL | When PayMongo recorded the payment. *Added 2026-09-26* |
| created_at | DATETIME | No | | CURRENT_TIMESTAMP | When the payment record was created |

### Table 17: attendances
A row exists only when staff admit the person at the door; no row = not (yet) attended / no-show. The report's "No. of viewers" counts these rows.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| attendance_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Admission record ID |
| reservation_seat_id | BIGINT UNSIGNED | No | UNIQUE, FK → reservation_seats (RESTRICT) | | Seat admitted (at most once) |
| remarks | TEXT | Yes | | NULL | Staff notes |
| checked_in_at | DATETIME | No | | | Admission time |
| checked_in_by | BIGINT UNSIGNED | No | FK → users.user_id (RESTRICT) | | Staff member who admitted the attendee |

### Table 18: reports *(new 2026-10-11)*
One Manila report per program (client decision: per program, not per month), free and paid screenings together.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| report_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Report ID |
| program_id | BIGINT UNSIGNED | No | UNIQUE, FK → programs.program_id (RESTRICT) | | The program reported |
| status | ENUM('draft','submitted','unlocked') | No | | 'draft' | `submitted` = locked, read-only for everyone until the Super Admin unlocks it |
| generated_by | BIGINT UNSIGNED | No | FK → users.user_id (RESTRICT) | | Who last generated it |
| submitted_by | BIGINT UNSIGNED | Yes | FK → users.user_id (RESTRICT) | NULL | Who last submitted it |
| submitted_at | DATETIME | Yes | | NULL | When it was last submitted |
| unlocked_by | BIGINT UNSIGNED | Yes | FK → users.user_id (RESTRICT) | NULL | The Super Admin who last unlocked it |
| unlocked_at | DATETIME | Yes | | NULL | When it was last unlocked |
| created_at / updated_at | TIMESTAMP | Yes | | NULL | Set by Laravel |

Workflow: generate (draft) → submit (locked) → an Admin requests an unlock with a reason → the Super Admin unlocks with a reason (`unlocked`) → edit / regenerate → resubmit. A report can be regenerated only while it is a draft or unlocked.

### Table 19: report_rows *(new 2026-10-11)*
A frozen copy of each published screening's line, written when the report is generated, so a submitted report never changes when bookings change later.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| report_row_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Row ID |
| report_id | BIGINT UNSIGNED | No | FK → reports.report_id (CASCADE), INDEX with screening_date | | Report |
| screening_id | BIGINT UNSIGNED | Yes | FK → screenings.screening_id (SET NULL) | NULL | The screening (kept as text if the screening is deleted) |
| type | ENUM('free','paid') | No | | | Free or paid row |
| screening_date | DATE | No | | | DATE / DAY columns |
| start_time | TIME | No | | | TIME |
| film_title | VARCHAR(150) | No | | | TITLE OF FILM |
| films_count | SMALLINT UNSIGNED | No | | 1 | NO. OF FILMS |
| male | INT UNSIGNED | No | | 0 | Viewers M: admitted attendees with sex M |
| female | INT UNSIGNED | No | | 0 | Viewers F |
| pwd | INT UNSIGNED | No | | 0 | Admitted attendees with a PWD ID |
| senior | INT UNSIGNED | No | | 0 | Admitted attendees with a Senior Citizen ID (someone with both IDs counts in both) |
| total_audience | INT UNSIGNED | No | | 0 | M + F |
| occupancy_rate | DECIMAL(5,2) | No | | 0.00 | total_audience ÷ capacity × 100 (26 of 120 = 21.67) |
| regular_count | INT UNSIGNED | No | | 0 | Paid rows: confirmed tickets without a discount |
| discount_count | INT UNSIGNED | No | | 0 | Paid rows: confirmed PWD / Senior tickets |
| total_sales | DECIMAL(10,2) | No | | 0.00 | Paid rows: sum of `amount_due` of tickets whose payment PayMongo verified |
| partner | VARCHAR(150) | Yes | | NULL | PARTNER (from the screening, editable on the report) |
| agency_type | VARCHAR(100) | Yes | | NULL | TYPE OF AGENCY |
| notes | TEXT | Yes | | NULL | NOTES |

The ticket control number on the manual sheets is not in the system report (client decision; listed under future recommendations).

### Table 20: report_events *(new 2026-10-11)*
The report's audit trail.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| event_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Event ID |
| report_id | BIGINT UNSIGNED | No | FK → reports.report_id (CASCADE) | | Report |
| user_id | BIGINT UNSIGNED | No | FK → users.user_id (RESTRICT) | | Who did it |
| action | ENUM('generated','edited','submitted','unlock_requested','unlocked','resubmitted') | No | | | What happened. Only the Super Admin can write `unlocked` |
| reason | TEXT | Yes | | NULL | Required for `unlock_requested` and `unlocked` |
| created_at | DATETIME | No | | | When |

### Removed tables
`payment_proofs` and `payment_qr_codes` (the old QR + screenshot payment flow, replaced by PayMongo on 2026-09-26) were dropped by migration `2026_10_06_000002`.

---

### Indexes beyond the keys
Plain indexes for speed; they add no constraints:
- `screenings.event_date`: upcoming-screening lists
- `reservations.status`: status filters
- `report_rows (report_id, screening_date)`: a report's rows in date order

MySQL also creates an index on every foreign-key column automatically.

### DATETIME vs TIMESTAMP
Business times (`reservation_datetime`, `cancelled_at`, `released_at`, `paid_at`, `checked_in_at`, `payments.created_at`, `submitted_at`, `unlocked_at`, `report_events.created_at`) use `DATETIME`: stored without time-zone conversion (the app writes Asia/Manila time) and not limited to 2038. Laravel's bookkeeping columns (`created_at` / `updated_at` on `programs`, `screenings` and `reports`) use `TIMESTAMP`.
