# Deploying Nautiqs

This is a **Laravel 11 + MongoDB Atlas + Livewire 3** app. It needs a host that runs PHP-FPM (or `php artisan serve`) as a long-lived process. **Vercel will not work** — it's serverless, and Livewire's stateful component model + DomPDF's filesystem use are incompatible. Use one of the platforms below.

## Recommended: Railway

Railway gives you GitHub auto-deploy with native PHP support and a free starter plan. ~10 minutes.

### 1. Push the repo to GitHub

```bash
cd c:/Users/T4905/Desktop/Nautiqs
git init
git add .
git commit -m "Initial commit — auth, dashboard, clients, quotes, PDFs"
git branch -M main
git remote add origin https://github.com/taha-farooqui/Nautiqs.git
git push -u origin main
```

> Verify `.env` is **not** in the staged files before you push — `git status` should never list `.env`. The `.gitignore` already excludes it.

### 2. Create the Railway project

1. Go to https://railway.app → **New Project** → **Deploy from GitHub repo** → pick `Nautiqs`.
2. Railway auto-detects PHP via `composer.json`. The first build will run `composer install` and `npm install && npm run build`.
3. Once the build is up, click the service → **Settings** → **Networking** → **Generate Domain**. You'll get something like `nautiqs-production.up.railway.app`.

### 3. Set environment variables

In the Railway service **Variables** tab, add (copy-paste from your local `.env`):

```env
APP_NAME=Nautiqs
APP_ENV=production
APP_KEY=<generate via `php artisan key:generate --show` locally and paste>
APP_DEBUG=false
APP_URL=https://YOUR-RAILWAY-DOMAIN.up.railway.app

DB_CONNECTION=mongodb
MONGODB_URI=<your Atlas connection string>
MONGODB_DATABASE=nautiqs

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr

MAIL_MAILER=postmark
POSTMARK_TOKEN=<your Postmark server token>
POSTMARK_MESSAGE_STREAM=outbound
MAIL_FROM_ADDRESS=<your verified sender email>
MAIL_FROM_NAME=Nautiqs

GOOGLE_CLIENT_ID=<your Google OAuth client id>
GOOGLE_CLIENT_SECRET=<your Google OAuth client secret>
GOOGLE_REDIRECT_URI=https://YOUR-RAILWAY-DOMAIN.up.railway.app/auth/google/callback
```

> **Get the actual values from your local `.env` file** — never paste them into committed docs.

### 4. Tell Railway how to start the app

In **Settings** → **Deploy** → **Custom Start Command**:

```
php -S 0.0.0.0:$PORT -t public public/index.php
```

(For a heavier load, swap to nginx + PHP-FPM via a custom Dockerfile — not needed for a demo.)

### 5. Install MongoDB PHP extension on Railway

PHP on Railway doesn't ship with the MongoDB extension. Add a **`nixpacks.toml`** at the project root *(I'll commit this for you when you confirm Railway)*:

```toml
[phases.setup]
nixPkgs = ['php82', 'php82Extensions.mongodb', 'nodejs']
```

### 6. Run migrations + seeders (one-time)

In the Railway service → **deployments** → latest → **shell**:

```bash
php artisan key:generate --force      # if APP_KEY is blank
php artisan db:seed --class=GlobalCatalogueSeeder --force
php artisan db:seed --class=DemoDataSeeder --force
```

> Skip `DemoDataSeeder` in production once you have real users — it overwrites the demo company's data on every run.

### 7. Update Google OAuth redirect

In https://console.cloud.google.com → Credentials → your OAuth client → add the new redirect URI:

```
https://YOUR-RAILWAY-DOMAIN.up.railway.app/auth/google/callback
```

### 8. Whitelist Railway in MongoDB Atlas

In Atlas → **Network Access** → **Add IP** → **Allow access from anywhere** (`0.0.0.0/0`). Less secure but fine for a demo. For real production, get Railway's egress IPs and pin them.

## Alternatives

| Host | Difficulty | Free tier | Notes |
|---|---|---|---|
| **Render** | easy | yes (sleeps after 15 min idle) | Use a "Web Service" with a Dockerfile; same Mongo extension issue |
| **Fly.io** | medium | yes ($5 credit/mo) | Dockerfile-based, full control |
| **DigitalOcean App Platform** | easy | $5/mo | Native PHP support, no sleep |
| **Hetzner Cloud + Forge** | medium | $4/mo + $12/mo Forge | Best for real production — full VPS, easy Laravel deploys |

## Why not Vercel

- Serverless: 10s-60s execution caps break PDF generation
- No persistent filesystem: DomPDF font cache, Livewire snapshots, and file sessions all break
- Long-lived Livewire connections need a stateful backend
- Community PHP runtime exists (`vercel-php`) but is unmaintained and has open issues with Laravel 11

## Going live (post-demo)

Before showing this to actual paying customers:
- Set `APP_DEBUG=false` (already in this guide)
- Move sessions to **Redis** if you scale beyond one instance
- Pin Atlas to specific IPs instead of `0.0.0.0/0`
- Set up scheduled `php artisan queue:work` if you start using async jobs
- Add Sentry or Bugsnag for error tracking
- Get Postmark **out of test mode** so you can email real customers (not just `taha@alphaventure.com`)

## What I committed for the deploy

- `.gitignore` covers `.env`, `vendor/`, `node_modules/`, build artefacts, IDE folders, log files
- `.env.example` is sanitised — no secrets, just placeholders
- `nixpacks.toml` (Railway) — adds the MongoDB PHP extension to the build image
- This document, `DEPLOY.md`
