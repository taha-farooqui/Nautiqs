# Nautiqs — Paid feature batch: plan

Status: **draft for review** · Written 2026-09-17 · Owner: Taha

Four features the client is paying for, plus a bug list. This document is the
contract with ourselves: what each feature means precisely, how it is built,
how we prove it works, and how it fails safely. Nothing here ships without its
acceptance section passing against real data.

| # | Feature | Client's words | What it actually is |
|---|---|---|---|
| 1 | Backups | "2 per day, 12:30 and midnight" | **DONE 19 Sep.** Scheduled dumps of DB + files + keys with a superadmin page; retention is download-then-free rather than offsite (client's own design). Restore drill executed |
| 2 | Client data protection | "Personal data hashed" | Application-level **encryption at rest** of client PII (hashing would make the data unusable — see §2.1) |
| 3 | iPad / mobile | "Improve responsiveness" | **DONE 19 Sep** for the tenant pages. deploy.sh now rebuilds assets; quote builder keeps its total on screen; 0 overflows across 44 page/viewport captures |
| 4 | Boat import/export | "Import/export for boats" | Flat XLSX/CSV round-trip for brands → models → versions, upsert-only, with a preview step |

Order of work and estimates are in §6. A risk register is in §7. Bugs in §8.

---

## 0. Ground truth (what I verified before writing this)

Everything below was checked on the live VPS or the repo, not assumed.

**Server** — OVH VPS, Ubuntu 26.04 LTS, 2 cores / 3.7 GB RAM, 32 GB free disk.
PHP 8.5.4 with `mongodb`, `zip`, `gd` loaded. Node 22.22 + npm 9.2 present,
`node_modules/` and the Vite binary present in `/var/www/nautiqs`. Server
timezone is **UTC**; `APP_TIMEZONE` is set in `.env`; the existing scheduler
entry already uses `->timezone('Europe/Paris')`, so the pattern is established.
`www-data` crontab runs `schedule:run` every minute.

**Not present:** `mongodump` (MongoDB Database Tools). The static tarball for
Ubuntu 24.04 x86_64 is reachable from the VPS (HTTP 200) and runs on 26.04.

**Database** — MongoDB Atlas cluster `nautiqs-prod`, database `nautiqs`. Tier is
**unknown from the server** — must be read off the Atlas console (§1.7). A
second cluster `nautiqs-dev` exists holding old test data; it becomes our
staging/restore target (§5.1). `.env` has `APP_KEY` set, **no**
`APP_PREVIOUS_KEYS` yet (Laravel 11.51 supports it and `config/app.php` already
reads it).

**Files** — `storage/app` is 144 KB: company logos only. So a "backup" is
essentially the database + `.env` + one small folder.

**Front-end build** — `public/build` is git-ignored and built **in place on the
VPS**. The live bundle (`app-DtW-tt5w.css`) was built **19 June** and the deploy
ritual never rebuilds it. Measured against the current views: 726 distinct
classes used, **199 not in the live bundle** (icon classes and PDF-only classes
inflate that number, but it includes real utilities I hit four times this
summer: `hover:bg-gray-50`, `disabled:opacity-50`, `col-span-12`, `text-[11px]`).
Responsive variants **are** compiled (sm 27, md 12, lg 15, xl 4) — small counts
because the app was designed desktop-first, not because the build is broken.
*(Corrected 19 Sep: `hover:` and `disabled:` variants were in the bundle all
along; my earlier greps escaped the backslash wrongly. The frozen-bundle problem
was real — a rebuild added 21 classes — but that particular list was not.)*
The layout shell is already responsive (off-canvas sidebar below `lg`,
hamburger, overlay). The pain is inside the pages.

**Existing importers to reuse** — `OptionImporter` and `EngineImporter`:
dependency-free CSV/XLSX readers (sheet 1 only), FR/EN header aliases, upsert by
stable key, template + export endpoints, per-row errors that don't abort the
file, and the rule that a column absent from the file leaves existing values
untouched. `BoatImporter` copies this pattern exactly.

**A trap to avoid** — `CatalogueController::syncVariantRows()` (the editor's
"Save versions") **deletes private versions and archives global ones that are
not in the submitted list**. Correct for the form; catastrophic for an import.
The boat importer must have its own upsert-only path and must never call it.

**PII footprint** — `clients` (14 fields), `quotes.client_snapshot` (read in 18
places across controllers, PDFs, emails, observers), `email_log.to_email`
(searched with `like` in `EmailLogController`), and one leak into
`notifications` (`QuoteObserver` writes the client's email into the message).

---

## 1. Backups

### 1.1 What "backup" must mean here

A backup that lives only on the same VPS is not a backup. A backup of an
encrypted database without the key is not a backup. So the deliverable is:

- Two runs a day at **00:00 and 12:30 Europe/Paris** (DST-safe — never hardcode UTC offsets).
- Each run captures: full `nautiqs` database, `storage/app/public/`, and `.env`
  (which holds `APP_KEY` — mandatory once feature 2 ships).
- Archive is **encrypted** before it leaves the box (it contains PII and secrets).
- Kept **locally** (fast restore) **and offsite** (survives the VPS dying).
- **Retention:** local 7 days (14 archives), offsite 30 days.
- **Alerting:** email on any failure, and a dead-man's switch that emails if no
  successful run has happened in 13 hours (catches a silently broken cron).
- **Visible:** a tile on the superadmin dashboard — last run time, size, status.
  The client can see what he is paying for.
- **Proven:** a restore into the `nautiqs-dev` cluster during implementation, documented as a runbook, repeated quarterly.

### 1.2 Decision: purpose-built command, not `spatie/laravel-backup`

Spatie's package is excellent for MySQL. For MongoDB it shells out to
`mongodump` through `spatie/db-dumper`, and whether its factory accepts the
Atlas **SRV URI** the Laravel MongoDB driver uses is uncertain. Our real needs
are: run `mongodump`, tar three things, encrypt, copy offsite, prune, alert.
That is ~150 lines we fully control versus a dependency whose MongoDB path we
would be debugging. Build it.

Offsite transfer uses **`rclone`** (single static binary, S3-compatible,
handles retention with `--min-age`) rather than adding the AWS SDK to composer.

### 1.3 Components

```
app/Console/Commands/BackupRun.php        backup:run    — the job
app/Console/Commands/BackupStatus.php     backup:status — dead-man's switch
app/Services/Backup/BackupService.php     dump → tar → encrypt → store → prune
app/Models/BackupRun.php                  collection `backup_runs` (audit + dashboard tile)
config/backup.php                         paths, retention, offsite remote name
docs/runbooks/backup-restore.md           step-by-step restore, tested
routes/console.php                        two schedule entries
resources/views/admin/dashboard.blade.php last-backup tile
```

### 1.4 The job, step by step

1. Acquire a lock (`withoutOverlapping`) — the 12:30 run must never overlap a slow 00:00 run.
2. `mongodump --uri="$MONGODB_URI" --db=nautiqs --gzip --archive=…/db.archive.gz`
   — the URI is passed via an env var, **never** on a visible command line.
3. `tar` the archive + `storage/app/public` + `.env` into `nautiqs-YYYYMMDD-HHMM.tar`.
4. Encrypt: `openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_PASSPHRASE`.
   Passphrase lives in `.env` as `BACKUP_PASSPHRASE` (32+ random bytes) **and
   in the client's password manager** — a backup nobody can decrypt is worthless.
5. Write to `/var/backups/nautiqs/` (mode 0700, owner www-data). Verify: file
   exists, size > 0, `openssl` decrypt of the first bytes succeeds, `tar -t` lists the archive.
6. `rclone copy` to the offsite remote; `rclone delete --min-age 30d` on the remote; prune local > 7 days.
7. Record a `backup_runs` document: started/finished, size, checksum, local
   path, offsite path, status, error. Dashboard reads the latest.
8. On any exception: record `failed`, email `info@nautiqs.fr` + Taha via the existing SMTP. Never swallow.

Schedule (both entries `->timezone('Europe/Paris')->withoutOverlapping(120)->runInBackground()->onFailure(...)`):

```php
Schedule::command('backup:run')->dailyAt('00:00') …
Schedule::command('backup:run')->dailyAt('12:30') …
Schedule::command('backup:status')->hourly();    // emails if last success > 13h ago
```

`twiceDailyAt()` is not usable — it forces the same minute offset on both runs.

### 1.5 Offsite destination

S3-compatible object storage. Recommended: **OVH Object Storage** (same
provider, EU region, ~€0.01/GB/month — our archives will be megabytes) or
Backblaze B2. Either is a 5-minute `rclone config`. Credentials in `.env`.
Bucket private, no public listing. Decision needed from the client: which
account pays the ~€1/month.

### 1.6 Restore runbook (written and executed once as part of delivery)

```
1. rclone copy remote:nautiqs-backups/<file> .      (or take the local copy)
2. openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_PASSPHRASE -in <file> -out b.tar
3. tar -xf b.tar
4. mongorestore --uri="<target cluster>" --gzip --archive=db.archive.gz --drop
5. copy storage/app/public back; restore .env (APP_KEY!) if the app key was lost
6. php artisan config:cache   ← only after confirming .env points at the intended cluster
```

Step 6 carries the exact gotcha that caused the June login outage — the runbook
says so in bold. The delivery restore goes into `nautiqs-dev`, which then
serves as staging for feature 2.

### 1.7 Atlas's own backups — verify, recommend, not our scope

Atlas M10+ has continuous cloud backup with point-in-time restore; M2/M5 have
limited snapshots; **M0 has none**. Read the tier off the Atlas console during
implementation. If the cluster is M10+, recommend enabling it as a second,
independent layer — it costs nothing extra to turn on and protects against the
one failure mode ours can't: our own VPS being compromised.

### 1.8 Acceptance

- [ ] Two `backup_runs` documents per day with status `ok`, timestamps at 00:00 and 12:30 Paris (check across a DST boundary with a forced clock or by reasoning on the cron expression Laravel emits).
- [ ] Archive decrypts and `mongorestore --dryRun` succeeds against `nautiqs-dev`.
- [ ] Full restore into `nautiqs-dev`; log in there; open a quote PDF; it renders identically.
- [ ] Kill the cron for 14 hours in staging → alert email received.
- [ ] Corrupt the passphrase → run marked `failed`, email received, no partial file left in place.
- [ ] Local dir holds ≤ 14 files after 8 days of runs; remote holds ≤ 60.
- [ ] Runbook followed by Taha from a clean shell without help.

---

## 2. Client personal data — encryption at rest

### 2.1 Correcting the ask: encryption, not hashing

A hash is one-way. A hashed email can never be shown on screen, printed on a
quote, or used to send the quote. What the client wants — data that is
unreadable if the database leaks, but works normally in the app — is
**encryption with application-held keys**.

Context worth giving him: Atlas already encrypts disks (AES-256) and all
traffic is TLS. What this feature adds is protection against the realistic
threats: a leaked connection string, a stolen backup file, a `mongodump` on a
laptop, a support screenshot of a raw document. After this, those show
`eyJpdiI6IlF…` instead of a phone number. It is genuine GDPR "appropriate
technical measures" (art. 32), and it is what a French dealership's clients
would expect if asked.

### 2.2 The trade-off that must be decided up front

MongoDB cannot search or sort inside encrypted values. Every field we encrypt
loses `like` search and `orderBy`. Today the app searches clients by first
name, last name, company, email, phone and city, and lists them ordered by
last name.

**Recommended (Option A):**

| Encrypt | Keep in clear (needed for list/sort/search) |
|---|---|
| `email`, `phone`, `address_line`, `postal_code`, `internal_notes`, `navigation_area`, `current_boat` | `first_name`, `last_name`, `company_name`, `city`, `country`, `lead_source` |

Search by email/phone becomes **exact-match** via a blind index (§2.3): typing
a full email or a phone number still finds the client; typing half an email no
longer does. Name/company/city search and sort by last name are unchanged.

**Option B (if the client insists names are encrypted too):** names get blind
indexes as well; search becomes exact whole-word on name; the client list
sorts by creation date instead of surname. Functional, noticeably worse to use.
Present A with the rationale; build B only on explicit instruction.

### 2.3 Mechanism

**Encryption** — Laravel's built-in `encrypted` Eloquent cast (AES-256-CBC +
HMAC, keyed by `APP_KEY`). Casts are attribute-level, so they work unchanged on
the MongoDB models; ciphertext is stored as a string, plaintext is what the
rest of the code sees. No new library.

**Blind index** — for each searchable encrypted field, a sibling
`<field>_bidx = HMAC-SHA256(normalize(value), BLIND_INDEX_KEY)`, indexed in
Mongo (compound with `company_id`). `normalize`: email → lowercase + trim;
phone → digits only (so `06 28 92 21 93` and `+33628922193` collide correctly,
after stripping a leading `+33` → `0`). A separate `BLIND_INDEX_KEY` in `.env`
means rotating `APP_KEY` does not force a re-index.

**Search routing** (`ClientController::index`, `SearchController`,
`EmailLogController`): if the query contains `@` → exact `email_bidx` lookup;
if it is ≥ 6 characters and mostly digits → exact `phone_bidx`; otherwise the
existing `like` on the plaintext fields. Small helper `Pii\SearchRouter` so all
three do the same thing.

**Embedded snapshots** — `quotes.client_snapshot` is an array, not a set of
attributes, so a cast can't reach inside it. Add an accessor/mutator pair on
`Quote` for `client_snapshot` that encrypts/decrypts the keys `email`, `phone`,
`address_line`, `postal_code` on the way in and out, leaving names in clear so
the quotes list's search on `client_snapshot.first_name` keeps working. Because
`Quote::create($payload)` and `->update()` run mutators, `QuoteBuilder::save()`
needs no change; the 18 read sites need no change. The stored document stays a
native embedded object (the `'array'`-cast JSON-string bug from August cannot
recur — the mutator returns an array).

**Email log** — `to_email` and `reply_to_email` encrypted + `to_email_bidx`.
`body_html` left in clear (it contains the client's first name and the sales
text, nothing else). `to_name` left in clear.

**Notifications leak** — `QuoteObserver` writes `":number was sent to :email"`
into the `notifications` collection. Change the message to use the client's
name. One line; closes a leak the encryption would otherwise miss.

**Out of scope, deliberately:** `users.email` (login lookup needs it; passwords
are already hashed), `account_requests`, anything superadmin.

### 2.4 Key management (this is where such projects fail)

- `APP_KEY` becomes the master key for client data. **Losing it loses every
  encrypted field permanently.** It is in `.env`, which feature 1 backs up
  encrypted; it must also be stored in the client's password manager.
- Never run `php artisan key:generate` on production again without the
  rotation procedure. Document it in `docs/runbooks/key-rotation.md`:
  move the old key into `APP_PREVIOUS_KEYS`, set the new `APP_KEY`, run
  `pii:reencrypt`, verify, then drop the old key. Laravel 11.51 decrypts with
  previous keys transparently, so the app stays up throughout.
- `BLIND_INDEX_KEY`: 32 random bytes, same storage rules.
- Add a boot-time guard: if `APP_KEY` is missing or the cipher is wrong, refuse
  to start rather than write plaintext.

### 2.5 Migration of existing data

`php artisan pii:encrypt` — idempotent, batched (200 docs), `--dry-run` by
default; `--execute` to write.

- Detects already-encrypted values (Laravel ciphertext is base64 JSON with
  `iv`/`value`/`mac`; a failed decrypt means plaintext) so re-running is safe.
- Refuses to run unless a `backup_runs` document with status `ok` exists in the
  last 60 minutes — feature 1 is a hard prerequisite.
- Writes blind indexes as it goes; reports per-collection counts.
- `pii:decrypt` exists as the rollback, same shape.
- `pii:scan` — sweeps every collection for email-shaped and phone-shaped
  strings outside the allowed plaintext fields; must report **0** after
  migration. It is also the acceptance test and a useful periodic check.

Run order: on staging (`nautiqs-dev` restored from a fresh prod backup) first,
full acceptance there, then production during a quiet hour with a backup taken
minutes before.

### 2.6 Code touch points

| File | Change |
|---|---|
| `app/Models/Client.php` | `$casts` for the 7 fields; mutators setting `email_bidx`/`phone_bidx` |
| `app/Models/Quote.php` | `client_snapshot` accessor + mutator |
| `app/Models/EmailLog.php` | casts + `to_email_bidx` |
| `app/Support/Pii/BlindIndex.php`, `SearchRouter.php`, `SnapshotCrypt.php` | the three helpers, unit-tested |
| `ClientController`, `SearchController`, `EmailLogController` | route search through `SearchRouter` |
| `app/Observers/QuoteObserver.php` | notification text uses name, not email |
| `app/Console/Commands/Pii{Encrypt,Decrypt,Scan,Reencrypt}.php` | migration + verification |
| `app/Providers/AppServiceProvider.php` | key guard |
| `docs/runbooks/key-rotation.md` | procedure |
| Mongo indexes | `clients(company_id, email_bidx)`, `clients(company_id, phone_bidx)`, `email_log(company_id, to_email_bidx)` |

No PDF, email template, Livewire or Blade file changes — that is the point of
doing it at the model layer.

### 2.7 Acceptance

- [ ] Raw document in Compass/mongosh shows ciphertext for every encrypted field; names in clear.
- [ ] Client list, client page, quote builder client dropdown, quote page, both PDFs, all three emails render decrypted values identically to before (diff the PDF text output of 5 real quotes before/after).
- [ ] Search: full email → found; full phone in any format → found; surname fragment → found; half an email → not found (documented behaviour).
- [ ] `pii:scan` → 0 findings. `pii:encrypt --execute` run twice → second run changes 0 documents.
- [ ] Restore the post-migration backup into `nautiqs-dev` **with** the `.env` → data readable. Restore it **without** `APP_KEY` → ciphertext (proves the key matters and the runbook is right).
- [ ] `pii:decrypt` on staging returns the collection byte-for-byte to the pre-migration state (compare `pii:scan` counts and a sample of 20 docs).
- [ ] Key rotation runbook executed once on staging end to end.

---

## 3. iPad and mobile

### 3.1 Prerequisite: stop shipping frozen CSS (fixes a root cause, not a symptom)

Every "class not in the bundle" incident this summer, and part of the tablet
problem, comes from one fact: **deploys never rebuild assets**. Fix that first,
or every responsive class we add is dead on arrival.

`deploy.sh` at the repo root, run on the VPS, replacing the ad-hoc ritual:

```
git pull --ff-only
composer install --no-dev --optimize-autoloader     (no-op when lock unchanged)
npm ci && npm run build                             (Node 22 + Vite already on the box)
php artisan config:cache && route:cache && view:cache
systemctl reload php8.5-fpm
curl -sf https://app.nautiqs.fr/login >/dev/null  || echo "SMOKE TEST FAILED"
```

Guard rail: the `.env` cluster-pointer check that the June outage taught us
goes into the script (`grep -c nautiqs-prod .env` must be 1, else abort before
`config:cache`).

Once this is in, the inline-style workarounds added in August (`nq-more-toggle`
hover rule, the `top:100%` menu positioning) can go back to plain Tailwind —
optional cleanup, listed in §8.

### 3.2 Device matrix (what "works" is measured on)

| Device | Viewport | Why |
|---|---|---|
| iPad landscape | 1180 × 820 | The boat-show case: dealer + customer, tablet on a stand. Sidebar is visible here (≥ `lg`), so content gets ~890 px |
| iPad portrait | 820 × 1180 | Same tablet turned; sidebar becomes off-canvas |
| iPhone | 390 × 844 | "Mobile if possible" — read a quote, check a client, send a follow-up. Not for building quotes |
| Small laptop | 1366 × 768 | Regression guard for the current desktop experience |

Safari is the browser that matters (iPad). Chrome DevTools emulation for speed,
a real iPad for sign-off.

### 3.3 Pages, in priority order, with the concrete change

1. **Quote builder** (the money page). At `< xl` the summary panel drops below
   nine cards of inputs, so on an iPad the total is never in view while editing
   — the exact opposite of "live totals". Fix: below `xl`, render the summary
   as a **sticky bottom bar** (Net payable + chevron) that expands to the full
   panel; the full side panel stays at `xl+`. Steps become single column;
   discount rows wrap the `%/€` switch and the input onto one line; engine and
   option pickers get full-width dropdowns; number inputs get
   `inputmode="decimal"` so the iPad keyboard is numeric; every tappable
   control ≥ 44 px.
2. **Quote page** (`quotes/show`): action buttons wrap instead of overflowing;
   PDF preview iframe uses viewport height; the send modal is full-screen below `md`.
3. **Lists** — quotes, clients, engines, catalogue models: tables get
   `overflow-x-auto` (quotes has it; clients and engines don't) and low-value
   columns hide below `md` (`hidden md:table-cell`): quotes keep number, client,
   total, status; clients keep name, phone, city. Filters stack vertically.
4. **Forms** — client form, company settings, boat editor: the two-column grids
   already collapse; the boat editor's 9-column options repeater does not — make
   it two rows below `lg` (label + price on the first, the rest on the second).
5. **Header + ⌘K palette**: search palette full-width below `md`; the language
   and user menus already fit.
6. **Sidebar at `lg` (iPad landscape):** 288 px is a lot of a 1180 px screen.
   *Should-have:* icon rail at `lg`, full labels at `xl`. Do it if time allows
   after 1–5; it is the single biggest gain for iPad landscape but touches every page.

Not in scope: native app, PWA install, offline mode, redesigning the PDF.

### 3.4 Proof, not opinion

A `tools/screenshots.mjs` Playwright script (Node is available locally) logs
into staging and captures the ten pages above at the four viewports, before and
after. The PNGs go in `docs/responsive/` and into the client update. It also
becomes the regression check for any future front-end change.

### 3.5 Acceptance

- [ ] `deploy.sh` used for every deploy from here on; `public/build` mtime matches the latest deploy.
- [ ] On iPad landscape, a full quote (client → model → version → 3 options → engine → discount) can be built with the running total visible the entire time and no horizontal scroll.
- [ ] On iPad portrait, the same flow works with the sidebar off-canvas.
- [ ] On iPhone, a quote can be opened, its PDF previewed, and sent by email.
- [ ] No table causes page-level horizontal scroll at any viewport in the matrix.
- [ ] Desktop 1366 and 1920 screenshots are pixel-identical to pre-change for the header, sidebar and summary panel.
- [ ] Client signs off on a real iPad.

---

## 4. Boat import / export

### 4.1 Scope

Bulk create and update of the dealership's **catalogue**: brands → models →
versions (variants), including included equipment and prices, **and options**.
Quotes are snapshots and are untouched by definition.

> **Amended 24 Sep 2026, as built.** The client asked for the complete boat —
> "all of its versions, options, including equipments". Options are therefore
> in the file after all, on a second sheet rather than the same grid: an option
> belongs to the boat, not to one version of it, so a single sheet would mean
> repeating every option against every version and then guessing which copy was
> meant when two disagree. The per-boat options import stays where it is; this
> is the whole-catalogue path. Two further rules fell out of testing against
> the live catalogue and are now part of the contract: an empty numeric or enum
> cell means *leave it alone* (so a blank COUT HT cannot zero a cost), and text
> comparison normalises line endings (a spreadsheet cell cannot hold a carriage
> return, so multi-line descriptions would otherwise read as changed on every
> import).

Two uses, both must be first-class: (a) a dealer starting from a manufacturer
price list, creating dozens of boats at once; (b) the yearly price update:
export → edit the price column → import, nothing else changes.

### 4.2 File format — one flat sheet, one row per version

The existing readers only parse sheet 1, and dealers edit flat files in Excel
comfortably. Model-level values repeat on each version row; on import the first
row for a model wins and later disagreements are reported as warnings.

| Column | Required | Notes |
|---|---|---|
| `MARQUE` | ✔ | brand name; matched case-insensitively; created as a **private** brand if unknown |
| `MODELE` | ✔ | commercial name |
| `CODE MODELE` | | manufacturer code |
| `COMPLEMENT` | | sub-name (Cabin / Open) |
| `ANNEE` | | 1900–2100 |
| `TYPE` | | open, cabin, semi-rigid, day-cruiser, fishing, sail — FR words accepted (`semi-rigide`, `voilier`, `pêche`…) |
| `PROPULSION` | | outboard, inboard, sail — `hors-bord`, `in-board`, `voile` accepted |
| `VERSION` | ✔ | variant name |
| `PRIX HT` | ✔ | ≥ 0 |
| `COUT HT` | | dealer cost — never printed anywhere client-facing |
| `DEVISE` | | EUR (default) or USD → converted with the live rate as options do |
| `EQUIPEMENTS INCLUS` | | one cell, items separated by `\|` |
| `ACTIF` | | oui/non, default oui |
| `REF VERSION` | | **written by export**, blank on hand-typed files: the stable key for exact re-match |

FR/EN header aliases as in the other importers (`Brand`, `Model`, `Variant`,
`Price HT`…). Max 5 000 rows, 10 MB.

### 4.3 Upsert rules — the part that must never surprise anyone

1. **Brand**: find by name within the company (case-insensitive) → else create `source=private`.
2. **Model**: find by (brand, name) → else create `source=private`. A model that
   was copied from the global catalogue is updated in place (prices, year,
   dimensions) and **keeps** `source=global` and its `global_model_id` — that
   is exactly the copy-on-activation principle: the dealer owns the copy.
3. **Version**: match by `REF VERSION` when present, else by (model, name) →
   update `base_price`, `cost`, `currency`, `included_equipment`, `is_active`;
   else create `source=private`.
4. **Never delete, never archive.** A version missing from the file is left
   exactly as it is. (This is why `syncVariantRows()` is not reused — §0.)
5. Column absent from the file → field untouched (so the yearly price file with
   just `MARQUE, MODELE, VERSION, PRIX HT` changes prices and nothing else).
6. Row-level validation errors skip that row and are reported; they never abort the file.
7. Everything scoped by `company_id`; the importer takes the company from the
   authenticated user, never from the file.

### 4.4 Preview before commit

An import can touch the whole catalogue, so it is two steps: upload → **preview**
("will create 2 brands, 14 models, 41 versions · will update 18 versions ·
3 rows have errors" with the first 50 rows shown and a diff marker on changed
prices) → confirm. Parsed rows are held in cache under a token for 15 minutes.
This is the one place where a little more UI buys a lot of safety.

### 4.5 Export

`GET /catalogue/export` (whole catalogue), with `?brand=` and `?model=` filters,
as XLSX. Every row carries the reference of the record it came from
(`REF MODELE`, `REF VERSION`, and the option `CODE`, backfilled on export), so
the round-trip is exact even after a rename. Template download
(`/catalogue/import/template`) is the same two sheets with example rows.

### 4.6 Structure

As built:

```
app/Services/Xlsx.php                                  multi-sheet read + write (the
                                                       existing readers took sheet 1 only)
app/Services/BoatCatalogueExporter.php                 workbook + template
app/Services/BoatCatalogueImporter.php                 parse / plan / commit
app/Http/Controllers/BoatCatalogueTransferController.php
resources/views/catalogue/import/{upload,preview}.blade.php
resources/views/catalogue/models.blade.php             "Exporter" / "Importer" buttons
routes/web.php                                         5 routes under /catalogue
```

A new controller rather than more methods on `CatalogueController`, which is
already 1 400 lines.

### 4.7 Acceptance

Run 24 Sep 2026 against the production database — every write inside a
throwaway company, created and destroyed by the script, with a platform-wide
document census either side proving nothing else moved. 23 checks, all passing.

- [x] Template imports clean on an empty tenant: 2 brands, 2 boats, 3 versions, 3 options, 0 errors; year, type, propulsion, length and the pipe-separated equipment all land.
- [x] Export the full NAUTIQUE CONCEPT catalogue (44 boats, 144 versions, 2 202 options) → re-import untouched → **0 created, 0 updated, 0 errors, 2 388 unchanged**.
- [x] Change one price, import → exactly one field on one version changed; cost and equipment untouched; nothing created or deleted.
- [x] Import a file holding one boat → all counts unchanged (nothing deleted).
- [x] Unknown brand → private brand created.
- [x] `PRIX HT = -5`, a missing MARQUE and `ANNEE = 3200` are each reported and skipped whole; the good rows land.
- [x] Options match on CODE, update in place, and a description is kept when its column is absent.
- [x] Tenant isolation: a file naming another tenant's boat by its own REF cannot read or write it — the row becomes a new boat in the importing tenant instead.
- [x] Driven through the browser end to end: export downloads, template downloads, an untouched re-import previews as 0/0/6/0 with Apply disabled, a four-column price update previews one changed field, applies, and restores.

---

## 5. Cross-cutting

### 5.1 Staging — needed before feature 2, useful forever

A second checkout at `/var/www/nautiqs-staging`, served at
`staging.nautiqs.fr` (nginx vhost + Let's Encrypt), with its own `.env`
pointing at the **`nautiqs-dev`** cluster, refreshed from a production backup by
the §1.6 runbook. That gives us: a real restore drill, a safe place to run the
encryption migration and the catalogue import against real-shaped data, and a
URL where the client can see responsive work before it ships. ~1 hour once
feature 1 exists. All three risky features are tested there first.

### 5.2 Tests — small, real, run where MongoDB exists

The dev machine has no `mongodb` PHP extension, so tests run on the VPS
(staging) against a `nautiqs_test` database, via `php artisan test`. Keep the
suite small and about the dangerous parts:

- `Pii/BlindIndexTest` — normalisation, determinism, key separation.
- `Pii/SnapshotCryptTest` — round-trip, names stay in clear, array stays an array.
- `ClientSearchTest` — routing of email / phone / name queries.
- `BoatImporterTest` — every rule in §4.3, including "never deletes" and tenant isolation.
- `BackupServiceTest` — with the shell calls faked: naming, encryption call, prune logic, failure recording.

### 5.3 Definition of done (applies to every feature)

Code reviewed against this document → tests green on staging → acceptance
checklist ticked on staging → deployed with `deploy.sh` → acceptance re-run on
production → runbook (where one exists) executed by Taha → client shown the
result on his own device or dashboard.

---

## 6. Order of work and estimates

The order is dictated by dependencies, not preference: the encryption
migration must not run without a backup; nothing responsive can ship until the
build pipeline is fixed.

| Step | Work | Hours | Depends on |
|---|---|---|---|
| 0 | `deploy.sh` with asset build + cluster guard; staging vhost | 4 – 6 | — |
| 1 | Backups: tools install, command, offsite, alerts, dashboard tile, restore drill + runbook | 8 – 12 | 0 |
| 2 | Encryption: helpers, casts, snapshot crypt, search routing, migration commands, key runbook, tests, staging run, prod run | 16 – 24 | 1 (backup), 0 (staging) |
| 3 | Boat import/export: importer, preview, export, template, UI, tests | 14 – 20 | 0 |
| 4 | Responsive: audit, quote builder bottom bar, lists, forms, palette, screenshots; sidebar rail if time | 16 – 24 | 0 |
| | **Total** | **58 – 86** | |

Boat import goes before the responsive pass because it is self-contained and
testable; responsive work is iterative and benefits from staging being mature.

Decisions needed from the client before starting: offsite storage account
(§1.5); Option A vs B for names (§2.2); confirmation that options stay out of
the boat file (§4.1).

---

## 7. Risks

| Risk | Impact | Mitigation |
|---|---|---|
| `APP_KEY` lost after encryption | All client PII unrecoverable | Key in encrypted backups **and** the client's password manager; restore drill without the key proves the dependency; boot guard |
| Encryption migration half-applied | Mixed plaintext/ciphertext, broken search | Idempotent command with detection; `--dry-run` default; `pii:scan` as the gate; `pii:decrypt` rollback; staging first |
| Backups "succeeding" but unrestorable | Discovered only in a disaster | Every run verifies decrypt + `tar -t`; delivery includes a real restore; quarterly drill in the runbook |
| Cron silently stops | No backups for weeks | `backup:status` dead-man's switch emails after 13 h |
| Import deletes or archives catalogue | Dealer loses boats | Upsert-only path; "never deletes" is a test; preview step; backup runs before any import (importer refuses without a backup < 24 h old — same guard as `pii:encrypt`) |
| `config:cache` with wrong cluster pointer (June outage) | Login outage | Guard in `deploy.sh` and in both runbooks |
| Responsive change breaks desktop | Daily users hit | Screenshot diff at 1366/1920 before merge |
| `mongodump` version drift vs Atlas server version | Dump fails | Pin tools version 100.x; alert on failure; test after Atlas upgrades |
| Client scope creep during build | Budget blown | This document is the scope; anything else is quoted separately |

---

## 8. Bugs

The client's bug list has not been provided yet — **to be added here**. Known
items from this summer's work, in priority order:

1. **Order-confirmation PDF shows no discounts** — boat, options, engines and
   global discounts are all absent from the BC totals; the figures are correct
   but a buyer signing sees no record of what was negotiated. (Found 2026-09-10, not fixed.)
2. **Three `NAUTIQUE CONCEPT` tenants** exist in production with different
   admin emails (`direction@eredix.fr`, `cd@audomarois-digital.fr`, and the live
   one). Only the third has real quotes. Confirm the other two are test
   signups, then archive them — before more dealers arrive.
3. **Stale company salesperson email** on the live NAUTIQUE CONCEPT tenant
   (`audomarois.digital.solutions@gmail.com`) — used as fallback Reply-To for
   guest quotes. Client's data; ask him to correct it in Company settings.
4. **`syncVariantRows()` deletes private versions absent from the submitted
   form.** Correct today, but a partial form submission (e.g. a browser
   dropping fields) would silently delete catalogue. Consider soft-archiving
   private versions instead of deleting, matching what it does for global ones.
5. **Inline-style workarounds** for purged classes (`nq-more-toggle` hover rule,
   `top:100%` menu positioning) — revert to Tailwind utilities once §3.1 ships. Cosmetic.
6. `.env` on the VPS contains a leftover example line with a placeholder Atlas
   host (`cluster0.abcd.mongodb.net`). Harmless, confusing; remove.
