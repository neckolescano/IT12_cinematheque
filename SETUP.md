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

- `migrate --seed` creates all tables and fills them with demo data: 120 seats, 11 films with posters, screenings from tomorrow onward, sample bookings in every state, and staff accounts.
- `storage:link` makes the film posters visible. If it says the link already exists, that's fine.

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

**Staff logins** (password `password` for all):
- `avt@cinematheque.test`
- `pdo@cinematheque.test`
- `inactive@cinematheque.test` — refused on purpose (a deactivated account)

Stop the site with **Ctrl + C** in that terminal. Next time you only need to start MySQL in XAMPP and run `php artisan serve`.

---

## 7. Trying a test payment

1. On the customer site pick a **paid** screening (₱150), choose seats, fill in "Who's coming?" and continue to payment.
2. On PayMongo's test checkout, choose **GCash** (or Maya) and click **Authorize Test Payment**, or use the test card **4343 4343 4343 4345**, any future expiry date and any 3-digit CVC.
3. You return to the booking page, which shows "Booking confirmed" and the e-ticket.

Unpaid bookings for paid screenings expire after **15 minutes** and their seats are released.

---

## 8. Running the automated tests (optional)

```bash
php artisan test
```

All tests should pass (85 as of 7 October 2026). They use the separate `cinematheque_test` database, so your demo data is not touched.

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
| Emails don't arrive | They go to the Mailtrap test inbox, not real inboxes. Check the inbox of whoever's Mailtrap keys are in `.env`. |

---

## 10. Where things are

- `HANDOFF.md` — full project state, decisions and rules (read this before changing code).
- `README.md` — overview and flows.
- `docs/` — data dictionary, SQL schema, ERD, evaluation questionnaires.
- `public/css`, `public/js` — the site's styles and scripts (edited by hand; no build step).
- `resources/views` — the pages (Blade templates).
