# Data Dictionary (MySQL)

Cinematheque Centre Davao. **DBMS: MySQL 8.0+**, InnoDB engine, `utf8mb4` / `utf8mb4_unicode_ci`.

Every type below was checked against the actual `SHOW CREATE TABLE` output after running the Laravel migrations on MySQL. The full DDL is in [schema-mysql.sql](schema-mysql.sql).

**Conventions**
- Every surrogate primary key is `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`. Laravel's `$table->id('...')` creates this.
- Every foreign key is `BIGINT UNSIGNED`, so it matches the key it references.
- `BOOLEAN` is MySQL's alias for `TINYINT(1)`. Values are 0 and 1.
- `ENUM` is used only for fixed value lists that the spec defines.
- Integer types are sized to fit their data: `SMALLINT UNSIGNED` (0–65,535) for runtime and year, and `TINYINT UNSIGNED` (0–255) for age. Nothing gets more precision than it needs.
- Tables are listed in dependency order, parents before children. The migrations run in the same order.

On-delete rules: **CASCADE** = child rows are deleted with the parent. **SET NULL** = the link is cleared. **RESTRICT** = the parent can't be deleted while children exist. RESTRICT is MySQL's default, so the DDL doesn't state it.

---

### Table 1: users
Staff accounts only. AVT and PDO have identical access.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| user_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Staff account ID |
| first_name | VARCHAR(50) | No | | | First name |
| middle_name | VARCHAR(50) | Yes | | NULL | Middle name |
| last_name | VARCHAR(50) | No | | | Last name |
| email | VARCHAR(100) | No | UNIQUE | | Login email |
| password | VARCHAR(255) | No | | | Bcrypt hash |
| position | ENUM('AVT','PDO') | Yes | | NULL | Job title. Descriptive only, never used for access |
| is_active | BOOLEAN (TINYINT(1)) | No | | 1 | Whether the account can log in |

### Table 2: movies
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| movie_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Film ID |
| title | VARCHAR(150) | No | | | Film title |
| runtime_minutes | SMALLINT UNSIGNED | Yes | | NULL | Running time in minutes |
| rating | VARCHAR(10) | Yes | | NULL | Content rating, e.g. PG-13 |
| release_year | SMALLINT UNSIGNED | Yes | | NULL | Year of release. `YEAR` is not used because it only covers 1901–2155 |
| synopsis | TEXT | Yes | | NULL | Plot description |

### Table 3: actors
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| actor_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Actor ID |
| first_name | VARCHAR(50) | No | | | First name |
| middle_name | VARCHAR(50) | Yes | | NULL | Middle name |
| last_name | VARCHAR(50) | No | | | Last name |

### Table 4: movie_actor (pivot)
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| movie_id | BIGINT UNSIGNED | No | PK, FK → movies.movie_id (CASCADE) | | Film |
| actor_id | BIGINT UNSIGNED | No | PK, FK → actors.actor_id (CASCADE) | | Actor |

Composite primary key: `(movie_id, actor_id)`.

### Table 5: directors
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| director_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Director ID |
| first_name | VARCHAR(50) | No | | | First name |
| middle_name | VARCHAR(50) | Yes | | NULL | Middle name |
| last_name | VARCHAR(50) | No | | | Last name |

### Table 6: movie_director (pivot)
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| movie_id | BIGINT UNSIGNED | No | PK, FK → movies.movie_id (CASCADE) | | Film |
| director_id | BIGINT UNSIGNED | No | PK, FK → directors.director_id (CASCADE) | | Director |

Composite primary key: `(movie_id, director_id)`.

### Table 7: genres
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| genre_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Genre ID |
| genre_name | VARCHAR(50) | No | UNIQUE | | Genre label |

### Table 8: movie_genre (pivot)
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| movie_id | BIGINT UNSIGNED | No | PK, FK → movies.movie_id (CASCADE) | | Film |
| genre_id | BIGINT UNSIGNED | No | PK, FK → genres.genre_id (CASCADE) | | Genre |

Composite primary key: `(movie_id, genre_id)`.

### Table 9: seats
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| seat_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Physical seat ID |
| seat_label | VARCHAR(10) | No | UNIQUE | | Seat code, e.g. A1 |
| section | VARCHAR(30) | Yes | | NULL | Grouping, e.g. Main |

### Table 10: screenings
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| screening_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Screening ID |
| event_title | VARCHAR(150) | No | | | Event title (logsheet "EVENT") |
| movie_id | BIGINT UNSIGNED | Yes | FK → movies.movie_id (SET NULL) | NULL | Cataloged film, if any |
| event_date | DATE | No | INDEX | | Screening date |
| start_time | TIME | No | | | Start time |
| end_time | TIME | No | | | End time |
| type | ENUM('free','paid') | No | | 'free' | Admission type |
| price | DECIMAL(8,2) | Yes | | NULL | Price per seat. NULL for free screenings |
| total_seats | INT UNSIGNED | No | | 100 | Capacity |
| created_by | BIGINT UNSIGNED | No | FK → users.user_id (RESTRICT) | | Staff member who posted it |
| created_at | TIMESTAMP | Yes | | NULL | Set by Laravel |
| updated_at | TIMESTAMP | Yes | | NULL | Set by Laravel |

### Table 11: reservations
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| reservation_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Booking ID |
| screening_id | BIGINT UNSIGNED | No | FK → screenings.screening_id (RESTRICT) | | Screening booked |
| booking_reference | VARCHAR(20) | No | UNIQUE | | e.g. CCD-7K2QX9AB |
| status | ENUM('pending','confirmed','cancelled') | No | INDEX | 'pending' | Reservation state |
| reservation_datetime | DATETIME | No | | CURRENT_TIMESTAMP | When the booking was submitted |
| lead_first_name | VARCHAR(50) | No | | | Booker's first name |
| lead_middle_name | VARCHAR(50) | Yes | | NULL | Booker's middle name |
| lead_last_name | VARCHAR(50) | No | | | Booker's last name |
| lead_contact_no | VARCHAR(20) | No | | | Booker's phone number |
| lead_email | VARCHAR(100) | Yes | | NULL | Booker's email |

### Table 12: reservation_seats
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| reservation_seat_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | One held seat |
| reservation_id | BIGINT UNSIGNED | No | FK → reservations.reservation_id (CASCADE) | | Owning reservation |
| screening_id | BIGINT UNSIGNED | No | FK → screenings.screening_id (RESTRICT) | | Stored here as well so the unique key below can use it |
| seat_id | BIGINT UNSIGNED | No | FK → seats.seat_id (RESTRICT) | | Physical seat |

`UNIQUE (screening_id, seat_id)` means the same seat can't be booked twice for the same screening.

### Table 13: reservation_attendees
| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| reservation_attendee_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Attendee ID |
| reservation_seat_id | BIGINT UNSIGNED | No | UNIQUE, FK → reservation_seats (CASCADE) | | Seat this person occupies (1:1) |
| is_lead_reserver | BOOLEAN (TINYINT(1)) | No | | 0 | Is this the booker? |
| first_name | VARCHAR(50) | No | | | First name |
| middle_name | VARCHAR(50) | Yes | | NULL | Middle name |
| last_name | VARCHAR(50) | No | | | Last name |
| age | TINYINT UNSIGNED | Yes | | NULL | Age (0–255) |
| sex | ENUM('M','F') | Yes | | NULL | Sex |
| company_school | VARCHAR(150) | Yes | | NULL | Company or school |
| contact_no | VARCHAR(20) | Yes | | NULL | Phone number |
| email | VARCHAR(100) | Yes | | NULL | Email |
| senior_card_no | VARCHAR(30) | Yes | | NULL | Senior citizen ID |
| pwd_indicator | BOOLEAN (TINYINT(1)) | No | | 0 | Person with disability |

### Table 14: payments
Online payment through PayMongo hosted Checkout. The status becomes `verified` only when PayMongo's API reports the session as paid.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| payment_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Payment ID |
| reservation_id | BIGINT UNSIGNED | No | UNIQUE, FK → reservations (CASCADE) | | At most one payment per reservation |
| amount | DECIMAL(8,2) | No | | | Price × seat count |
| payment_channel | VARCHAR(30) | Yes | | NULL | Method reported by PayMongo, e.g. `gcash`, `card` |
| status | ENUM('pending','verified','rejected') | No | | 'pending' | `verified` = PayMongo reported it paid |
| provider_session_id | VARCHAR(64) | Yes | UNIQUE | NULL | PayMongo Checkout Session ID (`cs_…`). *Added 2026-09-26* |
| provider_payment_id | VARCHAR(64) | Yes | | NULL | PayMongo payment ID (`pay_…`), quoted for refunds or disputes. *Added 2026-09-26* |
| paid_at | DATETIME | Yes | | NULL | When PayMongo recorded the payment. *Added 2026-09-26* |
| created_at | DATETIME | No | | CURRENT_TIMESTAMP | When the payment record was created (reservation submitted) |

### Table 15: payment_proofs *(retired, kept as history)*
Used by the old QR + screenshot flow, which PayMongo replaced on 2026-09-26. The app no longer writes to this table. Any existing rows are shown read-only on the staff reservation page.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| proof_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Proof submission ID |
| payment_id | BIGINT UNSIGNED | No | FK → payments.payment_id (CASCADE) | | Payment it supports |
| proof_image | VARCHAR(255) | No | | | Stored file path |
| submitted_at | DATETIME | No | | CURRENT_TIMESTAMP | Upload time |
| status | ENUM('pending','accepted','rejected') | No | INDEX | 'pending' | Review outcome |
| reviewed_by | BIGINT UNSIGNED | Yes | FK → users.user_id (RESTRICT) | NULL | Reviewer |
| reviewed_at | DATETIME | Yes | | NULL | Review time |
| rejection_reason | VARCHAR(255) | Yes | | NULL | Staff note when rejected |

### Table 16: payment_qr_codes *(retired, kept as history)*
Replaced by PayMongo on 2026-09-26. The app no longer reads or writes this table.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| qr_code_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | QR record ID |
| qr_image | VARCHAR(255) | No | | | Stored file path |
| is_active | BOOLEAN (TINYINT(1)) | No | | 1 | Currently shown. The app keeps only one active at a time |
| uploaded_by | BIGINT UNSIGNED | No | FK → users.user_id (RESTRICT) | | Uploader |
| uploaded_at | DATETIME | No | | CURRENT_TIMESTAMP | Upload time |

### Table 17: attendances
A row exists only when staff admit the person at the door; no row = not (yet) attended / no-show. `control_number` was **removed** on 2026-09-26 (migration `2026_09_26_000001`): the official physical-ticket number is outside this system, and the booking reference is the system's own admission reference.

| Field | MySQL type | Null | Key | Default | Description |
|---|---|---|---|---|---|
| attendance_id | BIGINT UNSIGNED AUTO_INCREMENT | No | PK | | Admission record ID |
| reservation_seat_id | BIGINT UNSIGNED | No | UNIQUE, FK → reservation_seats (RESTRICT) | | Seat admitted (at most once) |
| remarks | TEXT | Yes | | NULL | Staff notes |
| checked_in_at | DATETIME | No | | | Admission time |
| checked_in_by | BIGINT UNSIGNED | No | FK → users.user_id (RESTRICT) | | Staff member who admitted the attendee |

---

### Indexes beyond the data dictionary
These three are plain indexes for speed. They add no constraints:
- `screenings.event_date`: upcoming-screening lists
- `reservations.status`: status filters
- `payment_proofs.status`: the pending-review queue

MySQL also creates an index on every foreign-key column automatically.

### DATETIME vs TIMESTAMP
Business times (`reservation_datetime`, `submitted_at`, `reviewed_at`, `uploaded_at`, `checked_in_at`, `payments.created_at`) use `DATETIME`, as the source dictionary specifies. MySQL stores them without time-zone conversion and they don't run out in 2038. Only the Laravel bookkeeping columns `screenings.created_at` and `screenings.updated_at` use `TIMESTAMP`, which the source dictionary also specifies.
