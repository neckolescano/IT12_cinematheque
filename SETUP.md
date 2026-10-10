# Setting up the Cinematheque Centre Davao website

Step-by-step instructions for running the project on your own Windows computer.
It takes about 15 minutes. No GitHub, Node or npm is needed.

---

## 1. What you need

| Tool | Why | Get it |
|---|---|---|
| **XAMPP** with **PHP 8.2 or newer** | runs PHP and the MySQL (MariaDB) database | https://www.apachefriends.org |
| A browser | to open the site | Chrome, Edge or Firefox |
| *(Only if `vendor` is missing from the zip)* **Composer** | installs Laravel's PHP packages | https://getcomposer.org |

After installing XAMPP, check PHP from a terminal (PowerShell or Command Prompt):

```bash
php -v
```

If you see "php is not recognized", add `C:\xampp\php` to Windows' **PATH** (Start → "Edit the system environment variables" → Environment Variables → Path → New), then open a new terminal.

---

## 2. Unzip the project

Unzip the zip somewhere with a **short path**, for example your Desktop (you get `C:\Users\<you>\Desktop\ccd-web`).
Avoid deep folders: some Laravel files have long names, and Windows can't unzip paths longer than 260 characters (you'd see "path too long" or missing files).
All commands below are run **inside that folder**:

```bash
cd C:\Users\<you>\Desktop\ccd-web
```

If the `vendor` folder is missing, run this once:

```bash
composer install
```

---

## 3. Start MySQL and create the databases

1. Open the **XAMPP Control Panel** and click **Start** next to **MySQL** (Apache is not needed).
2. Click **Admin** next to MySQL (opens phpMyAdmin), or use the terminal, and create two empty databases with collation `utf8mb4_unicode_ci`:
   - `cinematheque` (the site)
   - `cinematheque_test` (only for the automated tests)

   From the terminal instead:

   ```bash
   C:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE cinematheque CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE cinematheque_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

---

## 4. The `.env` settings file

The project reads its settings (database, email, PayMongo) from a file called **`.env`** in the project folder.

**If the zip already contains `.env`** (it does if it was shared by the owner): keep it. It already has:
- the database settings for XAMPP (`root`, no password),
- the owner's **Mailtrap** (test email inbox) and **PayMongo** (test payments) keys.

You do **not** need to sign in to Mailtrap or PayMongo. Bookings you make send their test emails to the owner's Mailtrap inbox and their test payments appear in the owner's PayMongo test dashboard. No real money is involved. **Keep this file private** — it contains passwords.

**If there is no `.env`:**

```bash
copy .env.example .env
php artisan key:generate
```

Then open `.env` in a text editor and check the database lines:

```
DB_DATABASE=cinematheque
DB_USERNAME=root
DB_PASSWORD=
```

Without Mailtrap/PayMongo keys the site still works, but the staff dashboard shows "Email is not configured" / "PayMongo is not configured", and paid screenings can't be paid. To add them, ask the owner for the keys, or create your own free accounts:
- **Mailtrap** (Email Testing → your inbox → SMTP credentials) → `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`
- **PayMongo** (dashboard in **Test mode** → Developers → API keys) → `PAYMONGO_SECRET_KEY` (starts with `sk_test_`)

After any change to `.env`, run `php artisan config:clear`.

---

## 5. Create the tables and the demo data

```bash
php artisan migrate --seed
php artisan storage:link
```

- `migrate --seed` creates all tables and fills them with demo data: 120 seats, 4 programs, 11 films with posters, screenings from tomorrow onward, sample bookings in every state, and staff accounts (Admins and the one Super Admin).
- `storage:link` makes the film posters visible. If it says the link already exists, that's fine.

**Already had the project running before 11 October 2026?** Just run `php artisan migrate`. It adds the revision's tables and columns without losing data: films and screenings go into an "Unassigned" program for you to re-file, pending free bookings become confirmed, pending paid bookings become "awaiting payment", and a screening without a film gets its own film record.

To start over with clean demo data at any time (**this deletes everything in the `cinematheque` database**):

```bash
php artisan migrate:fresh --seed
```

---

## 6. Run the site

```bash
php artisan serve
```

Leave that terminal open, then open:

| Page | Address |
|---|---|
| Customer site | http://127.0.0.1:8000/cinemathequecentredavao |
| Staff (admin) | http://127.0.0.1:8000/ccdadmin |

**Staff logins** (password `password` for all; change them after the first login):
- `avt@cinematheque.test`: Admin
- `pdo@cinematheque.test`: Admin
- `manila@cinematheque.test`: **Super Admin** (FDCP Manila). The only account that unlocks submitted reports and manages staff accounts
- `inactive@cinematheque.test`: refused on purpose (a deactivated account)

**Roles:** Admins run films, program tags, schedules, bookings, door check-in (from 20 minutes before a screening until 60 minutes after it ends) and reports, and edit their own account. The one Super Admin can do all of that, and also creates, edits and deactivates staff accounts, unlocks submitted program reports, and can correct a check-in roster at any time. Position (AVT/PDO) is only a job title.

Stop the site with **Ctrl + C** in that terminal. Next time you only need to start MySQL in XAMPP and run `php artisan serve`.

---

## 7. Trying a test payment

1. On the customer site pick a **paid** screening (₱150), choose seats, fill in "Who's coming?", check everything on **Review booking**, then **Confirm and pay**. Typing a PWD or Senior Citizen ID for a person makes their ticket 20% off.
2. On PayMongo's test checkout, choose **GCash** or **Maya** and click **Authorize Test Payment**. Card payments are not offered.
3. You return to the booking page, which shows "Booking confirmed" and the e-ticket.

Unpaid bookings for paid screenings stay "awaiting payment" for **15 minutes**, then expire and their seats are released. Free screenings need no payment: the booking is confirmed and the e-ticket emailed as soon as it is submitted.

---

## 7b. Program reports and the acceptance check

Staff make the Manila report under **Program reports**: choose a program → **Generate** → check the table (type Partner, Type of agency and Notes in it) → **Submit to Super Admin**. A submitted report is locked; an Admin can **Request unlock** with a reason, and only the Super Admin can **Unlock** it. **Export .xlsx** downloads it in the Manila layout (two-row header, merged Program and Date cells). No extra PHP extension is needed for the export.

To check the report against Manila's February 2026 sheets, run:

```bash
php artisan reports:acceptance
```

The first time, it adds a program called "Acceptance: World Cinema, February 2026" with the sheets' four example screenings (past dates, so customers never see them), generates its report and prints every value next to the sheet's value. It ends with "All 4 rows and the program total match the Manila sheets." The report then also appears under Program reports.

---

## 8. Running the automated tests (optional)

```bash
php artisan test
```

All tests should pass (129 as of 11 October 2026). They use the separate `cinematheque_test` database, so your demo data is not touched.

---

## 9. Common problems

| Problem | Fix |
|---|---|
| `SQLSTATE[HY000] [2002] No connection could be made` | MySQL isn't running — start it in the XAMPP Control Panel. |
| `Unknown database 'cinematheque'` | Create the database (step 3). |
| `No application encryption key has been specified` | Run `php artisan key:generate`. |
| Film posters don't show | Run `php artisan storage:link`. |
| `Tablespace for table ... exists` while migrating | MySQL stopped mid-migration once before. Stop MySQL, delete the leftover `.ibd` file named in the error from `C:\xampp\mysql\data\cinematheque\`, start MySQL, run `php artisan migrate:fresh --seed`. |
| Port 8000 is busy | `php artisan serve --port=8080` and use `:8080` in the addresses. |
| Changed `.env` but nothing happened | `php artisan config:clear` |
| `Data truncated for column 'status'` or a missing column after updating the code | Run `php artisan migrate` (step 5). |
| A screening or film doesn't show on the customer site | It is a draft. Open it in the staff area and press **Publish** (both the screening and its film must be published). |
| "This report is submitted and locked" | Ask the Super Admin to unlock it (Program reports → the report → Request unlock). |
| Emails don't arrive | They go to the Mailtrap test inbox, not real inboxes. Check the inbox of whoever's Mailtrap keys are in `.env`. |

---

## 10. Where things are

- `HANDOFF.md` — full project state, decisions and rules (read this before changing code).
- `README.md` — overview and flows.
- `docs/` — data dictionary, SQL schema and ERD (updated 11 October 2026 for the system revision), evaluation questionnaires.
- `public/css`, `public/js` — the site's styles and scripts (edited by hand; no build step).
- `resources/views` — the pages (Blade templates).
