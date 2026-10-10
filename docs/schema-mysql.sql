-- =====================================================================
-- Cinematheque Centre Davao: MySQL schema (reference only)
--
-- Generated from the Laravel migrations in database/migrations/ after
-- running them (mysqldump --no-data). The migrations are the source of
-- truth; run `php artisan migrate` rather than this file. It is here so
-- the schema can be reviewed, or imported by hand, without Laravel.
--
-- Target: MySQL 8.0+ (also runs on MariaDB 10.4+, as in XAMPP), InnoDB,
-- utf8mb4. Tables are in dependency order: parents before children.
-- Laravel's own support tables (password_reset_tokens, sessions, cache,
-- jobs, migrations) are not included.
-- Updated 2026-10-06: seat release on cancel + unpaid expiry columns,
-- movies.poster_path; payment_proofs / payment_qr_codes dropped.
-- Updated 2026-10-07: reservation_attendees.pwd_id_no added.
-- Updated 2026-10-11 (system revision): programs + movie_program;
-- movies/screenings status (draft/published); screenings program_id,
-- films_count, partner, agency_type, notes, movie_id required;
-- reservation_seats unit_price/discount_type/amount_due; attendee
-- company_school required; users.role; reservations status
-- awaiting_payment/confirmed/cancelled; reports, report_rows,
-- report_events.
-- =====================================================================

SET NAMES utf8mb4;


CREATE TABLE `users` (
  `user_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `position` enum('AVT','PDO') DEFAULT NULL,
  `role` enum('super_admin','admin') NOT NULL DEFAULT 'admin',
  `is_active` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `programs` (
  `program_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `name_key` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`program_id`),
  UNIQUE KEY `programs_name_unique` (`name`),
  UNIQUE KEY `programs_name_key_unique` (`name_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `movies` (
  `movie_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `runtime_minutes` smallint unsigned DEFAULT NULL,
  `rating` varchar(10) DEFAULT NULL,
  `release_year` smallint unsigned DEFAULT NULL,
  `synopsis` text DEFAULT NULL,
  `logline` varchar(200) DEFAULT NULL,
  `curator_note` varchar(200) DEFAULT NULL,
  `trailer_url` varchar(255) DEFAULT NULL,
  `poster_path` varchar(255) DEFAULT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'published',
  PRIMARY KEY (`movie_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `movie_program` (
  `movie_id` bigint unsigned NOT NULL,
  `program_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`movie_id`,`program_id`),
  KEY `movie_program_program_id_foreign` (`program_id`),
  CONSTRAINT `movie_program_movie_id_foreign` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`movie_id`) ON DELETE CASCADE,
  CONSTRAINT `movie_program_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `actors` (
  `actor_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  PRIMARY KEY (`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `movie_actor` (
  `movie_id` bigint unsigned NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`movie_id`,`actor_id`),
  KEY `movie_actor_actor_id_foreign` (`actor_id`),
  CONSTRAINT `movie_actor_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `actors` (`actor_id`) ON DELETE CASCADE,
  CONSTRAINT `movie_actor_movie_id_foreign` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`movie_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `directors` (
  `director_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  PRIMARY KEY (`director_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `movie_director` (
  `movie_id` bigint unsigned NOT NULL,
  `director_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`movie_id`,`director_id`),
  KEY `movie_director_director_id_foreign` (`director_id`),
  CONSTRAINT `movie_director_director_id_foreign` FOREIGN KEY (`director_id`) REFERENCES `directors` (`director_id`) ON DELETE CASCADE,
  CONSTRAINT `movie_director_movie_id_foreign` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`movie_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `genres` (
  `genre_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `genre_name` varchar(50) NOT NULL,
  PRIMARY KEY (`genre_id`),
  UNIQUE KEY `genres_genre_name_unique` (`genre_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `movie_genre` (
  `movie_id` bigint unsigned NOT NULL,
  `genre_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`movie_id`,`genre_id`),
  KEY `movie_genre_genre_id_foreign` (`genre_id`),
  CONSTRAINT `movie_genre_genre_id_foreign` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`genre_id`) ON DELETE CASCADE,
  CONSTRAINT `movie_genre_movie_id_foreign` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`movie_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `seats` (
  `seat_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seat_label` varchar(10) NOT NULL,
  `section` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`seat_id`),
  UNIQUE KEY `seats_seat_label_unique` (`seat_label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `screenings` (
  `screening_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_title` varchar(150) NOT NULL,
  `movie_id` bigint unsigned NOT NULL,
  `films_count` tinyint unsigned NOT NULL DEFAULT 1,
  `program_id` bigint unsigned NOT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `type` enum('free','paid') NOT NULL DEFAULT 'free',
  `price` decimal(8,2) DEFAULT NULL,
  `total_seats` int unsigned NOT NULL DEFAULT 100,
  `status` enum('draft','published') NOT NULL DEFAULT 'published',
  `partner` varchar(150) DEFAULT NULL,
  `agency_type` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`screening_id`),
  KEY `screenings_created_by_foreign` (`created_by`),
  KEY `screenings_event_date_index` (`event_date`),
  KEY `screenings_program_id_foreign` (`program_id`),
  KEY `screenings_movie_id_foreign` (`movie_id`),
  CONSTRAINT `screenings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `screenings_movie_id_foreign` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`movie_id`),
  CONSTRAINT `screenings_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reservations` (
  `reservation_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `screening_id` bigint unsigned NOT NULL,
  `booking_reference` varchar(20) NOT NULL,
  `status` enum('awaiting_payment','confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  `cancellation_reason` enum('staff','payment_expired') DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `reservation_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `lead_first_name` varchar(50) NOT NULL,
  `lead_middle_name` varchar(50) DEFAULT NULL,
  `lead_last_name` varchar(50) NOT NULL,
  `lead_contact_no` varchar(20) NOT NULL,
  `lead_email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`reservation_id`),
  UNIQUE KEY `reservations_booking_reference_unique` (`booking_reference`),
  KEY `reservations_screening_id_foreign` (`screening_id`),
  KEY `reservations_status_index` (`status`),
  CONSTRAINT `reservations_screening_id_foreign` FOREIGN KEY (`screening_id`) REFERENCES `screenings` (`screening_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reservation_seats` (
  `reservation_seat_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` bigint unsigned NOT NULL,
  `screening_id` bigint unsigned NOT NULL,
  `seat_id` bigint unsigned NOT NULL,
  `unit_price` decimal(8,2) NOT NULL DEFAULT 0.00,
  `discount_type` enum('none','pwd','senior') NOT NULL DEFAULT 'none',
  `amount_due` decimal(8,2) NOT NULL DEFAULT 0.00,
  `released_at` datetime DEFAULT NULL,
  `held_seat_id` bigint unsigned GENERATED ALWAYS AS (if(`released_at` is null,`seat_id`,NULL)) STORED,
  PRIMARY KEY (`reservation_seat_id`),
  UNIQUE KEY `reservation_seats_held_unique` (`screening_id`,`held_seat_id`),
  KEY `reservation_seats_reservation_id_foreign` (`reservation_id`),
  KEY `reservation_seats_seat_id_foreign` (`seat_id`),
  CONSTRAINT `reservation_seats_reservation_id_foreign` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE,
  CONSTRAINT `reservation_seats_screening_id_foreign` FOREIGN KEY (`screening_id`) REFERENCES `screenings` (`screening_id`),
  CONSTRAINT `reservation_seats_seat_id_foreign` FOREIGN KEY (`seat_id`) REFERENCES `seats` (`seat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reservation_attendees` (
  `reservation_attendee_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reservation_seat_id` bigint unsigned NOT NULL,
  `is_lead_reserver` tinyint NOT NULL DEFAULT 0,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `age` tinyint unsigned DEFAULT NULL,
  `sex` enum('M','F') DEFAULT NULL,
  `company_school` varchar(150) NOT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `senior_card_no` varchar(30) DEFAULT NULL,
  `pwd_id_no` varchar(30) DEFAULT NULL,
  `pwd_indicator` tinyint NOT NULL DEFAULT 0,
  PRIMARY KEY (`reservation_attendee_id`),
  UNIQUE KEY `reservation_attendees_reservation_seat_id_unique` (`reservation_seat_id`),
  CONSTRAINT `reservation_attendees_reservation_seat_id_foreign` FOREIGN KEY (`reservation_seat_id`) REFERENCES `reservation_seats` (`reservation_seat_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `payment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` bigint unsigned NOT NULL,
  `amount` decimal(8,2) NOT NULL,
  `payment_channel` varchar(30) DEFAULT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `provider_session_id` varchar(64) DEFAULT NULL,
  `provider_payment_id` varchar(64) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `payments_reservation_id_unique` (`reservation_id`),
  UNIQUE KEY `payments_provider_session_id_unique` (`provider_session_id`),
  CONSTRAINT `payments_reservation_id_foreign` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attendances` (
  `attendance_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reservation_seat_id` bigint unsigned NOT NULL,
  `remarks` text DEFAULT NULL,
  `checked_in_at` datetime NOT NULL,
  `checked_in_by` bigint unsigned NOT NULL,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `attendances_reservation_seat_id_unique` (`reservation_seat_id`),
  KEY `attendances_checked_in_by_foreign` (`checked_in_by`),
  CONSTRAINT `attendances_checked_in_by_foreign` FOREIGN KEY (`checked_in_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `attendances_reservation_seat_id_foreign` FOREIGN KEY (`reservation_seat_id`) REFERENCES `reservation_seats` (`reservation_seat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reports` (
  `report_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `program_id` bigint unsigned NOT NULL,
  `status` enum('draft','submitted','unlocked') NOT NULL DEFAULT 'draft',
  `generated_by` bigint unsigned NOT NULL,
  `submitted_by` bigint unsigned DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `unlocked_by` bigint unsigned DEFAULT NULL,
  `unlocked_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`report_id`),
  UNIQUE KEY `reports_program_id_unique` (`program_id`),
  KEY `reports_generated_by_foreign` (`generated_by`),
  KEY `reports_submitted_by_foreign` (`submitted_by`),
  KEY `reports_unlocked_by_foreign` (`unlocked_by`),
  CONSTRAINT `reports_generated_by_foreign` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `reports_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`),
  CONSTRAINT `reports_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `reports_unlocked_by_foreign` FOREIGN KEY (`unlocked_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `report_rows` (
  `report_row_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint unsigned NOT NULL,
  `screening_id` bigint unsigned DEFAULT NULL,
  `type` enum('free','paid') NOT NULL,
  `screening_date` date NOT NULL,
  `start_time` time NOT NULL,
  `film_title` varchar(150) NOT NULL,
  `films_count` smallint unsigned NOT NULL DEFAULT 1,
  `male` int unsigned NOT NULL DEFAULT 0,
  `female` int unsigned NOT NULL DEFAULT 0,
  `pwd` int unsigned NOT NULL DEFAULT 0,
  `senior` int unsigned NOT NULL DEFAULT 0,
  `total_audience` int unsigned NOT NULL DEFAULT 0,
  `occupancy_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `regular_count` int unsigned NOT NULL DEFAULT 0,
  `discount_count` int unsigned NOT NULL DEFAULT 0,
  `total_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
  `partner` varchar(150) DEFAULT NULL,
  `agency_type` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`report_row_id`),
  KEY `report_rows_screening_id_foreign` (`screening_id`),
  KEY `report_rows_report_id_screening_date_index` (`report_id`,`screening_date`),
  CONSTRAINT `report_rows_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `reports` (`report_id`) ON DELETE CASCADE,
  CONSTRAINT `report_rows_screening_id_foreign` FOREIGN KEY (`screening_id`) REFERENCES `screenings` (`screening_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `report_events` (
  `event_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `action` enum('generated','edited','submitted','unlock_requested','unlocked','resubmitted') NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`event_id`),
  KEY `report_events_report_id_foreign` (`report_id`),
  KEY `report_events_user_id_foreign` (`user_id`),
  CONSTRAINT `report_events_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `reports` (`report_id`) ON DELETE CASCADE,
  CONSTRAINT `report_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
