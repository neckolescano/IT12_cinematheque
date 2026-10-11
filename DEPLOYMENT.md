# Deploying the Cinematheque Centre Davao system (free demo setup)

This guide puts the system online for free with:

| Part | Service | Free plan | What it does |
|---|---|---|---|
| Website | **Render** (Web Service, Docker) | Free instance | Runs the Laravel app over HTTPS |
| Database | **Clever Cloud** (MySQL add-on, DEV plan) | Free | Stores all data |
| Email | **Brevo** (transactional email API) | 300 emails/day | Sends booking emails (Render's free tier blocks SMTP, so Gmail SMTP cannot be used there) |
| Payments | **PayMongo** (test mode) | Free | Online payment by card / GCash / Maya, no real money |

Time needed: about 1 hour the first time.

> **For the live defense itself**, running the system on your laptop with a Cloudflare Tunnel (Appendix A) is the most reliable option: no cold starts, Gmail SMTP works, uploaded posters are kept. Use this Render setup for a link that works without your laptop.

---

## 0. Before you start

You need accounts on: **GitHub**, **Render** (render.com), **Clever Cloud** (clever-cloud.com), **Brevo** (brevo.com) and **PayMongo** (dashboard.paymongo.com, test mode is enough), plus the **Gmail address** that will send the emails.

On your computer: the project folder `Desktop\IT12_cinematheque` with XAMPP's PHP (for one command in step 3).

The code that Render deploys must be on GitHub, including the deployment files in this folder (`Dockerfile`, `docker/`, `.dockerignore`, `.env.production.example`, this guide). They are not on GitHub yet.

---

## 1. Put the code on GitHub

1. Commit the current changes on your own branch and push it (for example `deploy/render`). Never push to `main`.
2. Render must be able to read the repository:
   - The group repository `neckolescano/IT12_cinematheque` is **public**, so Render can deploy it from its URL (step 5, *Public Git Repository*). Automatic redeploys then do not happen; you press **Manual Deploy** after each push.
   - For automatic redeploys on every push, the repository owner (Nicho) must connect it to Render, or you can fork it to your own GitHub account and connect the fork.

---

## 2. Create the MySQL database on Clever Cloud

1. Sign in to the Clever Cloud console → **Create** → **an add-on** → **MySQL**.
2. Choose the **DEV** plan (free). Pick a region (for example **Paris**). Name it `ccd-mysql`.
3. Open the add-on → **Add-on dashboard / Information**. Note these values:

   | Clever Cloud shows | Goes into Render as |
   |---|---|
   | Host (`…-mysql.services.clever-cloud.com`) | `DB_HOST` |
   | Port (usually `3306`) | `DB_PORT` |
   | Database name | `DB_DATABASE` |
   | User | `DB_USERNAME` |
   | Password | `DB_PASSWORD` |

4. Check the DEV plan's limits on that page (storage and maximum connections). The DEV plan is small; it is enough for a demo but not for real use.

> **Where to put the website:** choose the Render region closest to the database (for a Paris database: **Frankfurt**). Every page runs many database queries, and each one crosses the distance between the two; Singapore ↔ Paris would make pages take several seconds.

---

## 3. Set up Brevo for email

1. Sign up at brevo.com (free plan).
2. **Senders, Domains & Dedicated IPs → Senders → Add a sender**: enter the Gmail address and a sender name (*Cinematheque Centre Davao*). Brevo emails a code to that Gmail; enter it to verify.
3. **SMTP & API → API Keys → Generate a new API key**. Name it `render`. Copy the key (starts with `xkeysib-`); it is shown only once.
4. If Brevo asks you to complete your profile or activate transactional sending, do it; new accounts may need a short review before sending.

Notes: 300 emails per day on the free plan. Emails "from" a Gmail address sent through Brevo may land in **Spam**; tell recipients to check it.

---

## 4. Generate the application key

In `Desktop\IT12_cinematheque`, run:

```powershell
php artisan key:generate --show
```

Copy the whole output (`base64:…`). This is `APP_KEY`. Keep it secret and keep it the same for every deploy (changing it logs everyone out and breaks encrypted data).

---

## 5. Create the Web Service on Render

1. Render dashboard → **New** → **Web Service**.
2. Source: connect the GitHub repository, or choose **Public Git Repository** and paste `https://github.com/neckolescano/IT12_cinematheque`.
3. Settings:

   | Field | Value |
   |---|---|
   | Name | `cinematheque-davao` (becomes `https://cinematheque-davao.onrender.com`) |
   | Language / Runtime | **Docker** |
   | Branch | the branch you pushed in step 1 |
   | Region | **Frankfurt** (near the Clever Cloud database) |
   | Dockerfile path | `./Dockerfile` |
   | Instance type | **Free** |
   | Health check path | `/up` |

4. **Environment variables** (*Environment* → *Add Environment Variable*). Copy them from `.env.production.example` and fill in your values:

   | Key | Value |
   |---|---|
   | `APP_NAME` | `Cinematheque Centre Davao` |
   | `APP_ENV` | `production` |
   | `APP_KEY` | from step 4 |
   | `APP_DEBUG` | `false` |
   | `APP_URL` | `https://cinematheque-davao.onrender.com` (your service's URL) |
   | `APP_TIMEZONE` | `Asia/Manila` |
   | `LOG_CHANNEL` | `stderr` |
   | `LOG_LEVEL` | `warning` |
   | `DB_CONNECTION` | `mysql` |
   | `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | from step 2 |
   | `SESSION_DRIVER` | `database` |
   | `SESSION_SECURE_COOKIE` | `true` |
   | `CACHE_STORE` | `database` |
   | `QUEUE_CONNECTION` | `sync` |
   | `MAIL_MAILER` | `brevo` |
   | `BREVO_API_KEY` | from step 3 |
   | `MAIL_FROM_ADDRESS` | the Gmail address verified in Brevo |
   | `MAIL_FROM_NAME` | `Cinematheque Centre Davao` |
   | `PAYMONGO_SECRET_KEY` | your PayMongo **test** secret key (`sk_test_…`) |
   | `PAYMONGO_PAYMENT_METHODS` | `card,gcash,paymaya` |
   | `PAYMONGO_WEBHOOK_SECRET` | leave empty for now (step 8) |
   | `INITIAL_ADMIN_EMAIL` | the email the Super Admin will sign in with |
   | `INITIAL_ADMIN_PASSWORD` | a strong password, at least 12 characters |

   Only if your database provider requires TLS (Aiven does; Clever Cloud normally does not): add its CA certificate under *Secret Files* as `ca.pem` and set `MYSQL_ATTR_SSL_CA=/etc/secrets/ca.pem`.

5. Click **Create Web Service**.

---

## 6. First deploy: what happens and how to check it

Render builds the Docker image (5–10 minutes the first time), then starts it. On every start, `docker/entrypoint.sh` runs:

1. `php artisan config:cache`, `route:cache`, `view:cache`
2. `php artisan migrate --force` — creates every table
3. `php artisan db:seed --class=EssentialSeeder --force` — the venue's 120 seats only (no demo data)
4. `php artisan staff:create-admin --if-none` — creates the Super Admin from `INITIAL_ADMIN_EMAIL` / `INITIAL_ADMIN_PASSWORD`, only if none exists yet
5. starts Apache

Open **Logs** in Render and check for `Created super_admin account …` and no errors. When the status turns **Live**, open the URL.

---

## 7. First sign-in and real accounts

1. Open `https://<your-app>.onrender.com/ccdadmin` and sign in with the Super Admin email and password.
2. **Staff accounts → Add account**: create the AVT and PDO accounts with their own emails and strong passwords.
3. In Render → **Environment**, **delete** `INITIAL_ADMIN_EMAIL` and `INITIAL_ADMIN_PASSWORD`, and save (Render redeploys; nothing is recreated because the Super Admin exists).
4. Add the films (Film catalog), then the screenings.

---

## 8. Connect PayMongo (test mode)

1. PayMongo dashboard, **test mode** → **Developers** → **Webhooks** → **Create webhook**.
2. URL: `https://<your-app>.onrender.com/webhooks/paymongo` — Event: `checkout_session.payment.paid`.
3. Copy the webhook's **secret key** (`whsk_…`) into Render as `PAYMONGO_WEBHOOK_SECRET` and save (Render redeploys).

---

## 9. End-to-end check

1. Book a **free** screening on the public site with your own email → the e-ticket email arrives (check Spam).
2. Book a **paid** screening → pay on PayMongo with the test GCash / test card `4343 4343 4343 4345` → the booking turns **Confirmed** and the e-ticket arrives.
3. Staff side: admit the attendees, open **Reports**, export the **.xlsx** and **.csv**.
4. Run a test email any time from Render's **Shell** (paid plans) or locally against the same settings: `php artisan mail:test you@example.com`.

---

## 10. Limitations of the free setup (tell the panel if asked)

- **Sleeps after 15 minutes without visitors**; the first visit then takes about a minute. Open the site 2–3 minutes before presenting. Optional: a free cron-job.org job that opens `https://<your-app>.onrender.com/up` every 10 minutes keeps it awake (one always-on service fits within Render's 750 free hours a month).
- **Uploaded posters disappear** whenever the service restarts or redeploys (the free disk is not permanent). Re-upload them before the demo, or use a paid disk / object storage for real use.
- **Small database** (Clever Cloud DEV plan): fine for a demo, not for production.
- **Email via Brevo**: 300 per day; may land in Spam when sent from a Gmail address.
- **PayMongo test mode**: no real money; accepting real payments requires the Centre's own verified PayMongo account and live keys.

---

## 11. Updating the deployment

Push new commits to the deployed branch. With a connected repository Render redeploys automatically; with *Public Git Repository*, press **Manual Deploy → Deploy latest commit**. Migrations run automatically on start; existing data is kept.

---

## 12. Troubleshooting

| Symptom (Render logs) | Cause and fix |
|---|---|
| `SQLSTATE[HY000] [2002]` / connection refused or timed out | Wrong `DB_HOST` / `DB_PORT`, or the database is not running. Re-copy the values from the Clever Cloud dashboard. |
| `Access denied for user` | Wrong `DB_USERNAME` / `DB_PASSWORD`. |
| `3750 Unable to create or change a table without a primary key` | The MySQL server enforces `sql_require_primary_key`. Turn it off in the database provider's settings (Aiven: advanced configuration), or use another provider. |
| `Too many connections` | The free DEV plan allows only a few connections. Wait and retry; avoid many people using it at once. |
| 500 error on every page | Missing or wrong `APP_KEY`, or `APP_DEBUG=false` hiding the error: read the Render logs. |
| Links or redirects use `http://` | `APP_URL` must start with `https://`. |
| Emails do not arrive | Check Spam; check that `MAIL_FROM_ADDRESS` is the sender verified in Brevo; check Brevo → *Transactional → Logs*. `Key not found (401)` means a wrong `BREVO_API_KEY`. |
| Paid booking stays "Awaiting payment" | Webhook URL or `PAYMONGO_WEBHOOK_SECRET` wrong; use **Refresh from PayMongo** on the booking page meanwhile. |
| Super Admin not created | `INITIAL_ADMIN_PASSWORD` shorter than 12 characters, or the email already exists — see the log line from `staff:create-admin`. |

---

## Appendix A — Live demo from your laptop (Cloudflare Tunnel)

1. Start MySQL in XAMPP, then in `IT12_cinematheque`: `php artisan serve`
2. In a second terminal: `cloudflared tunnel --url http://127.0.0.1:8000` and copy the `https://….trycloudflare.com` address.
3. Set `APP_URL` in `.env` to that address, then `php artisan config:clear`.
4. Create a PayMongo test webhook for `https://<tunnel-address>/webhooks/paymongo` and put its secret in `PAYMONGO_WEBHOOK_SECRET`, then `php artisan config:clear`.

Gmail SMTP works here. The tunnel address changes every time you start it: repeat steps 3–4.

## Appendix B — Clean database commands (any server)

```bash
php artisan migrate --force                              # create the tables
php artisan db:seed --class=EssentialSeeder --force       # 120 seats only, safe to repeat
php artisan staff:create-admin                            # first Super Admin (asks for the password)
```

Development only (never production): `php artisan migrate:fresh --seed` loads the demo accounts (password `password`), films and bookings.
