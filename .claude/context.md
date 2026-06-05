# Nautiqs — Deep Codebase Context

> **Purpose of this file:** A complete, file-by-file mental model of the Nautiqs codebase so future sessions don't need to re-read every file. Generated from a full sweep of `app/`, `routes/`, `config/`, `resources/`, `database/`. Excludes `vendor/`, `node_modules/`, `public/build/`.
>
> **Last full sweep:** 2026-06-04. If you change architecture, update this file.

---

## 1. What Nautiqs is

Multi-tenant SaaS for boat dealerships to create/send professional **quotes** (`Q-YYYY-NNN`) and **order confirmations** (`BC-YYYY-NNN`) in under 2 minutes. Two tiers:

- **Platform / Superadmin** — owns the global catalogue (brands, models, variants, engines, equipment, options), provisions dealers, manages translations + platform settings, sees everything.
- **Dealership / Tenant** — its own workspace: clients, quotes, catalogue snapshot, team, email templates. Strictly isolated by `company_id`.

Canonical product spec: `technical_specification_boat_saas_v3.pdf`. Project rules: `CLAUDE.md`.

### Actual stack (verified from `composer.json` / `package.json`)
| Layer | Choice | Notes |
|---|---|---|
| Framework | **Laravel 11** (`^11.31`) | PHP `^8.2` |
| DB | **MongoDB** via `mongodb/laravel-mongodb` `^5.7` | Atlas cloud; connection `mongodb` |
| Reactive UI | **Livewire `^4.2`** + Alpine (shipped by Livewire — do NOT import Alpine manually) | spec said v3; actual is v4 |
| Auth | **Laravel Breeze** (Blade) + **Socialite** Google (`^5.26`) | |
| CSS | **Tailwind 3** + **daisyUI 5** (theme `nautiqs`) | |
| Font | **Geist** (Google Fonts) — note: CLAUDE.md says "Inter" but code uses Geist | |
| Primary colour | `#0e4f79` (navy, = `primary-800`) | |
| PDF | `barryvdh/laravel-dompdf` `^3.1` | table-based HTML (no flex/grid) |
| Email | **Postmark** (`symfony/postmark-mailer`) + **Brevo HTTPS API** (`symfony/brevo-mailer`) fallback | NOT generic SMTP; NOT Resend |
| Charts | `chart.js` `^4.5` | dashboard |
| Icons | **Remixicon 4.6** via CDN (`ri-*`) | |
| Excel/CSV | **custom importers** (no `maatwebsite/excel`) | XLSX read via ZipArchive+DOMDocument |

> ⚠️ **Drift from CLAUDE.md to remember:** Livewire is v4 (not 3); font is Geist (not Inter); mail is Postmark/Brevo (not SMTP/Resend); imports are hand-rolled (no maatwebsite/excel).

---

## 2. Roles & data isolation (the core invariant)

Three roles, constants on `app/Models/User.php`:
- `ROLE_SUPERADMIN = 'superadmin'` — `company_id` is `null`; platform-wide.
- `ROLE_TENANT_ADMIN = 'tenant_admin'` — dealership owner; can manage team.
- `ROLE_TENANT_USER = 'tenant_user'` — salesperson.

User helpers: `isActive()` (null = true, legacy), `isSuperadmin()`, `isTenantAdmin()`, `isTenantUser()`, `belongsToTenant()`.

### How tenant isolation works (memorise this)
- **`app/Models/Concerns/BelongsToTenant.php`** — trait. `bootBelongsToTenant()`:
  1. Adds `TenantScope` as a global scope.
  2. On `creating`, auto-fills `company_id` from `auth()->user()->company_id`.
- **`app/Models/Scopes/TenantScope.php`** — `apply(Builder, Model)`:
  - Superadmin → **no filter** (returns early, sees all).
  - Tenant with `company_id` → `where('company_id', $user->company_id)`.
  - Orphan (tenant, no company_id) → `whereRaw(['_id' => ['$exists' => false]])` (defensive: returns nothing).
  - Unauthenticated → no filter.
- Every tenant model uses `BelongsToTenant`. **No cross-tenant access is possible** under normal queries.

`Company` itself is NOT tenant-scoped (it IS the tenant root). `Notification` is scoped by **`user_id`** (not company), so each user only sees their own.

---

## 3. MongoDB collections (22)

### Platform-level (no `company_id`)
| Collection | Model | Notes |
|---|---|---|
| `brands` | `GlobalBrand` | name, logo_path, display_order, is_active; `models()` hasMany |
| `global_boat_models` | `GlobalBoatModel` | brand_id, code, name, default_margin_pct, is_archived |
| `global_boat_variants` | `GlobalBoatVariant` | model_id, base_price, cost, currency, included_equipment[], is_archived |
| `global_options` | `GlobalOption` | model_id, category, label, price, cost, currency, position |
| `global_option_items` | `GlobalOptionItem` | standalone platform option library (category, label, price, vat_rate) |
| `global_engines` | `GlobalEngine` | brand, code, horsepower, fuel, cost, price, vat_rate; `priceTtc()` |
| `global_equipment` | `GlobalEquipment` | category (CATEGORIES enum), label, is_active |
| `translations` | `Translation` | key (English source), locale, value, updated_by; `$timestamps=false` |
| `platform_settings` | `PlatformSetting` | singleton: platform_name, logo_path, maintenance_mode, maintenance_message; `singleton()` |
| `audit_log` | `AuditLog` | append-only superadmin trail; before/after; `$timestamps=false` |
| `users` | `User` | mixed (superadmins + tenant users), scoped by role |

`GlobalEquipment::CATEGORIES = [exterior, interior, mooring, sails, electronics, electrical, other]`.

### Tenant-level (carry `company_id`, use `BelongsToTenant`)
| Collection | Model | Notes |
|---|---|---|
| `companies` | `Company` | NOT scoped (tenant root). margin_presets array; `marginForCategory($cat)` |
| `clients` | `Client` | accessors: `full_name`, `display_name`, `full_address` |
| `quotes` | `Quote` | the big one — see §6 |
| `quote_counters` | `QuoteCounter` | atomic `nextReference($companyId, $type, $year)` via Mongo `findOneAndUpdate`+`$inc` |
| `email_templates` | `EmailTemplate` | type, subject, body (HTML); `{{variables}}` |
| `email_log` | `EmailLog` | append-only; STATUS_SENT/FAILED; TYPE_QUOTE/ORDER_CONFIRMATION/FOLLOW_UP; denormalised quote_number + sent_by_user_name |
| `company_brands` | `CompanyBrand` | SOURCE_GLOBAL / SOURCE_PRIVATE; `isGlobal()/isPrivate()` |
| `company_boat_models` | `CompanyBoatModel` | TYPES + PROPULSIONS enums; capacity{}, included_equipment_refs[] |
| `company_boat_variants` | `CompanyBoatVariant` | per-variant dealer overrides of price/cost/equipment |
| `company_options` | `CompanyOption` | multi-currency snapshot fields (original_price, fx_rate_used, fx_rate_date); `code` = upsert key |
| `engines` | `Engine` | per-company private engine library; `priceTtc()` |
| `user_invitations` | `UserInvitation` | token + 7-day expiry; `isPending()`, `isExpired()` |

### User-scoped
| Collection | Model | Notes |
|---|---|---|
| `notifications` | `Notification` | scoped by `user_id`; `isUnread()`, `markRead()`, `displayTitle()`; type constants (quote.*, client.*, email_template.updated) |

> **Migrations** (`database/migrations/`) only create SQL auth/cache/jobs tables — **not** used in production. All app data is MongoDB; collections are created implicitly on first write.

### Margin resolution cascade (used everywhere margin is computed)
1. **Real margin** — actual cost entered on the line → `margin_type = 'real'`.
2. **Margin preset by category** — `Company.margin_presets` keyed `hull/engine/options/custom_items`.
3. **Model default** — `CompanyBoatModel.default_margin_pct`.
4. **Company default** — `Company.default_margin_pct`.
Each quote line records whether margin is `real` or `estimated`. **Margin/cost/internal notes NEVER appear in client PDFs/emails.**

---

## 4. Routing & middleware

Routes: `routes/web.php` (app), `routes/auth.php` (Breeze + Google + invitations), `routes/console.php`.
Middleware aliases registered in `bootstrap/app.php`:
- `role` → `EnsureUserHasRole` (403 on fail)
- `superadmin` → `RequireSuperadmin` (**404** on fail — hides admin panel existence)
- `maintenance` → `MaintenanceGate` (503 page; superadmin bypasses)
- Globally appended to web group: `SetLocale`, `SetCompanyTimezone`.

### Middleware behaviour
- **`SetLocale`** — reads `locale` cookie (1-yr TTL), supported `['en','fr']`, silent fallback to `fr` (default locale is French). Set via `GET /locale/{lang}` (`locale.switch`).
- **`SetCompanyTimezone`** — applies `Company.timezone` (IANA) to PHP + Carbon; DB stays UTC, only display flips.
- **`MaintenanceGate`** — reads `PlatformSetting::singleton()->maintenance_mode`; superadmin always passes; tenants get `maintenance` view (503).
- **`RequireSuperadmin`** — `abort(404)` unless role is superadmin.
- **`EnsureUserHasRole`** — `role:tenant_admin` etc.; `abort(403)` if role not in list.

### Route groups
- **Public (no auth):** `/` (redirect), `/e/p/{token}` (`email.pixel`), `/locale/{lang}` (`locale.switch`), `/_diag/mail` (`diag.mail`).
- **Auth (`routes/auth.php`):** login/logout, forgot/reset password, Google OAuth (`auth.google.redirect/callback`), email verification (`verification.*`, signed + throttle), invitations (`invitations.accept`, `invitations.accept.store`). `GET /register` is a **404 stub** (self-registration removed; dealers created by superadmin).
- **Tenant (`auth` + `verified` + `maintenance`):**
  - `dashboard`
  - `clients.*` (resource)
  - `quotes.*` — index/create/show/edit/destroy + `trash`, `empty-trash`, `restore`, `force-delete`, `mark-sent`, `mark-won`, `mark-lost`, `duplicate`, `pdf`, `send-email`, `order-confirmation`
  - `company.settings(.update)`
  - `team.*` — wrapped in `role:tenant_admin` (invite/resend/revoke/deactivate/activate/role)
  - `notifications.*`, `search`
  - `catalogue.*` — models/brands/updates browse; private model+variant+option CRUD; variant activation (cherry-pick, bulk, toggle); per-boat options import/template; `brands.lookup` autocomplete. **Brand activation/deactivation routes are 404 stubs since 2026-05-23** (all global brands now auto-visible to all dealers; see §5).
  - `email-templates.*` (index/edit/update/reset), `email-log.*`
  - `engines.*` (CRUD + template + import)
  - `profile.*` (Breeze)
- **Superadmin (`prefix admin`, `name admin.`, `superadmin` middleware):**
  - `admin.dashboard`
  - `admin.dealers.*` (index/create/store/show/suspend/reactivate)
  - `admin.audit.index`
  - Global catalogue CRUD via `Admin\CatalogueController`: `admin.brands.*`, `admin.models.*`, `admin.variants.*`, `admin.equipment.*`, `admin.options.*`, `admin.engines.*` (each: index/create/store/edit/update + archive-or-toggle; engines also template+import)
  - `admin.settings.*` (platform branding + maintenance)
  - `admin.dictionary.*` (index/export/update/reset translations)

---

## 5. Superadmin controllers (`app/Http/Controllers/Admin/`)

- **`DashboardController`** — platform roll-up KPIs across all tenants: quotes this month vs last (+delta), total quoted HT, revenue won, active dealerships; 6-month sent/won/lost pipeline trend; recent quotes (decorated with company name); top 5 quoted models this month; recent audit entries.
- **`DealerController`** — dealer (Company) management. `index` (search name/siren/vat/salesperson_email; status all/active/suspended; decorates user count, quote count, primary contact). `store` creates a `tenant_admin` user (email pre-verified) + Company via `CompanyProvisioner`, audit-logs `dealer.create`. Password is **optional** (random one minted if blank); when "Send account setup email" is checked it mints a password-reset token and emails a secure **setup link** via `DealerWelcomeMail` — never a readable password. `show` (users + stats: quotes total/month, clients, last activity, revenue won YTD). `suspend`/`reactivate` flip `Company.status` + audit log.
- **`AuditController`** — paginated/searchable/filterable `audit_log` (search actor_email/target_label/action; filter by action + target_type; 40/page; facet dropdowns).
- **`SettingsController`** — `PlatformSetting` singleton edit (platform_name, logo upload to `storage/app/public/platform/`, maintenance_mode bool, maintenance_message). Audit-logged.
- **`DictionaryController`** — translation overrides. `index` merges `lang/{locale}.json` canonical + `Translation` DB overrides into rows (key/default/current/customised); search + filter (all/customised/defaults); 50/page. `update` validates placeholder parity (`:name` etc.) then upserts `Translation`; `reset` deletes the row (reverts to file); `export` CSV with BOM. All audit-logged.
- **`CatalogueController`** (global) — full CRUD for brands/models/variants/equipment/options/engines. Every action audit-logged with before/after. **Fan-out:** creating a global brand/model/variant/option calls `fanOutBrandToAllDealers()` / `fanOutModelToAllDealers()` / `fanOutChildOfModel()` to re-snapshot into every dealer workspace via `CatalogueService`. Engines: template (CSV+BOM) + import via `GlobalEngineImporter`.

---

## 6. Tenant controllers + the Quote lifecycle (`app/Http/Controllers/`)

### Controllers (non-admin)
- **`DashboardController`** — tenant KPIs (cached 60s/company): quotes this month + delta, total quoted HT, revenue won, awaiting response + expiring; top models; conversion rate (won÷closed); avg days to close; 6-month pipeline; recent 5 quotes (always fresh).
- **`ClientController`** — resource CRUD. `index` paginated 20, searchable. `destroy` refuses if client has quotes. Client details get **snapshotted** into quotes at save time.
- **`CompanySettingsController`** — `edit`/`update` company profile, salesperson (used as email reply-to), defaults (vat, margin, display mode, timezone), margin presets. Superadmins redirected to admin panel.
- **`QuoteController`** — the hub. See lifecycle below.
- **`EmailTemplateController`** — index/edit/update/reset the 3 templates; delegates to `EmailTemplateService`.
- **`EmailLogController`** — read-only audit (25/page, filter type/status, search); `show` drawer.
- **`EmailPixelController`** — public `GET /e/p/{token}`. 1×1 GIF open tracking. `shouldCount()` filters bots (mailgun/sendgrid/brevo/curl/etc., but allows GoogleImageProxy), ignores hits <60s after `sent_at` (prefetch), per-IP dedup via `Cache::add()`. Updates `quote.tracking{open_count, first_opened_at, last_opened_at}`.
- **`EngineController`** — merged de-duped list (private shadows global by brand+code, case-insensitive); CRUD for private engines; CSV template + import via `EngineImporter`.
- **`NotificationController`** — per-user list (filter all/unread/read, 25/page); `markRead` (then redirect to `link`); `markAllRead`.
- **`CatalogueController`** (tenant) — workspace catalogue: brand activate/deactivate/reactivate (now mostly no-ops/404 stubs), private brand CRUD, brand autocomplete (`brandLookup` returns merged workspace+global namespaced `global:<id>`), model/variant/option CRUD, `importGlobalOptions`, bulk options import (`OptionImporter`, FX→EUR), reorder. De-dups private variants over global twins by (brand, code).
- **`SearchController`** (`__invoke`) — Cmd/Ctrl+K palette JSON: quotes/clients/models (8 each), min 2 chars, tenant-scoped.
- **`TeamController`** (admin-only) — `index` (active members + pending invites). `invite` (validates email globally-unique + no pending dup; mints 64-char token, +7d expiry; sends `TeamInvitation`). `resend`/`revoke`/`deactivate`/`activate`/`updateRole` (never self). `findTeammate()` re-checks company ownership.
- **`ProfileController`** — Breeze: edit/update (clears email_verified_at on email change)/destroy (password-confirmed).

### Form requests (`app/Http/Requests/`)
- `ClientRequest` — first_name*, last_name* + optional contact/address; authorize requires `company_id`.
- `ProfileUpdateRequest` — name*, email* (unique, ignore self), lowercased.
- `Auth/LoginRequest` — email/password; `authenticate()` rate-limits (5 tries, key `email|ip`), checks verification.

### Quote lifecycle (statuses: DRAFT → SENT → WON / LOST)
- **Create:** `QuoteController@create` renders the Livewire `QuoteBuilder`. On `save()`: server recomputes totals via `QuoteCalculator`, persists snapshots (client/model/variant/equipment/options/custom_items), mints `number` via `QuoteCounter::nextReference`, status DRAFT, records `created_by_user_id`/`created_by_name`. Dispatches `navigate-to` → `quotes.show?preview=1`.
- **Edit:** only when `isEditable()` (DRAFT). `QuoteBuilder::loadFromQuote()` rehydrates all props.
- **Status transitions:** `markSent` (sets `sent_at`), `markWon` (`won_at`), `markLost` (`lost_at`). First successful email auto-promotes DRAFT→SENT.
- **Duplicate:** clone, reset to DRAFT, strip timestamps, set `duplicated_from`.
- **PDF:** `pdf()` renders `pdf.quote` via DomPDF; `?inline=1` streams for iframe preview, else downloads `{number}.pdf`.
- **Email (`sendEmail`):** validates recipient (guest name required if no client); persists guest into `client_snapshot` on first send; picks template (WON→order_confirmation; quote already sent→follow_up; else quote) via `EmailTemplateService`; allows subject/body override; mints 40-char `tracking_token`; appends pixel `<img>`; attaches quote.pdf or order-confirmation.pdf; reply-to = salesperson email; logs to `EmailLog` (even on failure).
- **Order confirmation (`orderConfirmation`):** requires WON; mints `order_confirmation_number` (`BC-YYYY-NNN`) via `QuoteCounter` if absent; sets `order_confirmation_at`; downloads `{order_confirmation_number}.pdf`.
- **Trash:** `destroy`→`trash()` (sets `trashed_at`); `trash` list (onlyTrashed); `restore`; `forceDelete`; `emptyTrash`.

`Quote` model helpers: `isTrashed/trash/untrash`, `isEditable`, `canGenerateOrderConfirmation`, `daysUntilExpiry/isExpired/isExpiringSoon`, `openCount/hasBeenOpened/firstOpenedAt/lastOpenedAt`, `creatorName`. Global `not_trashed` scope (`whereNull('trashed_at')`) + `withTrashed`/`onlyTrashed`.

### Quote `totals` array (computed by `QuoteCalculator`, stored on quote)
`base_price_gross/original/currency`, `boat_discount_amount/pct`, `options_rows[]`, `options_gross`, `options_discount_amount/pct`, `custom_items_rows[]`, `custom_items_ht`, `global_discount_amount/pct`, `total_ht`, `vat_breakdown{rate→amount}`, `vat_rate`, `vat_amount`, `total_ttc`, `trade_in_deduction`, `net_payable`, `margin_amount`, `margin_pct`, `margin_type` (real|estimated), `total_cost`, `fx_rate_used`.

---

## 7. Livewire Quote Builder (`app/Livewire/QuoteBuilder.php` + `resources/views/livewire/quote-builder.blade.php`)

Two-column: left = config steps 1–9; right = sticky live summary (vendor/client toggle).

**Key public props:** `quoteId/isEdit`; client: `client_mode` (existing|guest), `client_id`, quick-add fields (`quickClient*`); `brand_id/model_id/variant_id`; `selectedOptions{id→qty}`, `optionDiscounts{id→pct}`; `selectedEngines{namespaced→qty}` (`global:`/`private:`); `custom_items[]`; discounts (`boat_/options_/global_discount_pct`, `category_discounts`); `hasTradeIn`, `trade_in_value`; `view_mode`, `exchange_rate` (null=auto), `vat_rate`, `display_mode` (TTC|HT), `per_option_vat`; terms (`terms_payment/delivery/warranty/notes`), `internal_notes`.

**Lifecycle:** `mount($quoteId, $preselectedClientId)`, `loadFromQuote()`, `render()`.
**Computed (`#[Computed]`):** `clients`, `brands` (active), `models` (by brand), `variants` (active by model), `variant`, `options` (grouped by category), `engines` (de-duped, filtered by variant HP via `variantHpTargets()` regex), `totals` (delegates to `QuoteCalculator`).
**Updaters:** `updatedBrandId` (resets model/variant/options), `updatedModelId` (resets variant/options).
**Actions:** `openQuickClient/closeQuickClient/saveQuickClient`, `toggleOption`, `toggleEngine`, `addCustomItem/removeCustomItem`, `save()`.

- **Live totals < 100ms:** computed caching + debounced inputs (300–500ms). Engine dropdown is **client-side Alpine** (`engineDropdown()`) to avoid Livewire round-trips per keystroke.
- **FX:** if variant or any option currency ≠ EUR and no manual `exchange_rate`, fetch USD→EUR via `FxRateService` (fallback 1.0); stored immutably on the quote for PDF consistency.
- **Per-option VAT:** when on, each option uses its own `vat_rate`; summary shows "mixed" badge with breakdown tooltip.
- **Guest quotes:** `client_mode=guest` → snapshot filled on first email send.

---

## 8. Services (`app/Services/`)

- **`QuoteCalculator::compute(array $input, Company $company): array`** — central money engine. Discounts (boat/options/global/category), per-line or quote-wide VAT (`vat_breakdown`), FX via private `toEur()`, and the **margin cascade**: any real cost → `margin_type=real` (`total_ht − real_cost`); else estimated via `company->marginForCategory(hull|options|custom_items)`. Used by builder, save, PDF, email — single source of truth.
- **`CatalogueService`** — copy-on-activation. `activateGlobalBrand($companyId, $brand)` (idempotent; re-activation only flips is_active + activated_at, never overwrites dealer edits) → `snapshotGlobalModel()` copies models+variants+options. `createPrivateBrand()`. `deactivateGlobalBrand()` is a **no-op since 2026-05-23**.
- **`CompanyProvisioner::forNewUser($user): Company`** — creates Company with defaults (vat 20, margin 10, display TTC, presets hull12/engine8/options15/custom10, status active) and links `user.company_id`.
- **`DbOverlayTranslator`** — extends Laravel translator; layers `translations` collection over `lang/*.json`. `overridesFor($locale)` cached 24h (key `translations:overlay:{locale}`); `forget($locale)` busted by `TranslationObserver`.
- **`FxRateService`** — `rate($base,$target)` / `convert()` via frankfurter.app (ECB rates, no key); cached 1h, 4s timeout; null on failure.
- **`NotificationService`** — `record()` (no-op if no auth — safe in seeders/jobs), `unreadCount()`, `recentForUser()`, `markAllRead()`.
- **`EmailTemplateService`** — 3 types (TYPE_QUOTE/ORDER_CONFIRMATION/FOLLOW_UP) with EN+FR DEFAULTS. `getOrCreate`/`getAll`/`reset`/`render` (substitutes `{{vars}}`, prepends text-only branded header via `wrapWithLogo` — no `<img>` because cid:/data: are rejected by Brevo/Gmail). Variables: `client_name, client_first_name, quote_number, order_number, boat_model, total_ttc, salesperson_name, company_name, date`.
- **`AuditLogger::record($action, $target, $before, $after, $targetLabel)`** — static; redacts password/token fields; derives label from name/label/title/email/code.
- **`EngineImporter` / `GlobalEngineImporter`** — XLSX/CSV (auto delimiter; ZipArchive+DOMDocument for xlsx). Columns: Brand, Model(code), PA HT, PV HT, TVA. Upsert by (company_id,brand,code) (global: no company). Max 5000 rows. Returns created/updated/skipped/errors.
- **`OptionImporter`** — 7 cols (FAMILLE, DESIGNATION, PA HT, PA CURRENCY, PV HT, PV CURRENCY, TVA). FX→EUR at import (stores originals + rate). Upsert key auto-slug `slug(category)__slug(label)`.

---

## 9. Console commands (`app/Console/Commands/`)

- **`catalogue:bootstrap [--force]`** (`BootstrapCompanyCatalogues`) — activate every global brand into every company; skips companies that already have brands unless `--force`. Idempotent.
- **`mongo:ensure-indexes`** (`EnsureMongoIndexes`) — creates ~23 indexes (quotes by company+created_at/status/sent_at/won_at/lost_at, number; email_log; notifications by user; company_* catalogue; global_* catalogue; clients; email_templates; users). Run after deploy.
- **`mail:diagnose {to}`** — prints mail config table + attempts a real send.
- **`library:seed`** (`SeedGlobalLibrary`) — idempotent: 27 engines, 35 equipment, 24 options. Run every deploy.
- **`brands:import-tsv [--file= --wipe --dry-run]`** — import boat brands from TSV (`database/data/brands-bandofboats.tsv`); smart title-casing.

## 10. Mail + seeders + provider

- **`app/Mail/DealerWelcomeMail`** — new dealer welcome with a single-use **account-setup link** (password-reset token via the standard broker) — never a readable password (view `emails.dealer-welcome`). Constructor: `(Company, User, string $setupUrl)`.
- **`app/Mail/TeamInvitation`** — teammate invite (view `emails.team-invitation`, lean for Brevo API).
- **`database/seeders/`** — `DatabaseSeeder` → `GlobalCatalogueSeeder` (Brig/Jeanneau/Quicksilver, 6 models, variants+options; variants/options wiped+reseeded each run) + `DemoDataSeeder` ("Demo Marine" company, user `demo@nautiqs.test`/`demo123!` tenant_admin, 15 clients, 25 quotes across statuses, backdated 90d).
- **`app/Providers/AppServiceProvider`** — (register) swaps translator for `DbOverlayTranslator`; (boot) forces HTTPS in prod, registers observers (Quote/Client/EmailTemplate/Translation), registers `brevo` mail transport (`Mail::extend`, needs `BREVO_KEY`), view-composer shares notifications + unread count into header/sidebar.

## 11. Observers (`app/Observers/`)
- **`QuoteObserver`** — created→quote.created; updated→quote.sent/won/lost (only on status change); deleted→quote.deleted. (via NotificationService)
- **`ClientObserver`** — created/updated (skips updated_at-only)/deleted notifications.
- **`EmailTemplateObserver`** — updated (only real subject/body changes) → email_template.updated.
- **`TranslationObserver`** — saved/deleted → `DbOverlayTranslator::forget($locale)` (cache bust).

---

## 12. Frontend / views (`resources/views/`)

### Two shells
- **`components/app-layout.blade.php`** (tenant) — white header, `bg-base-200`, navy sidebar `w-72`, ⌘K palette, SweetAlert2 confirms. User menu: Profile / Company settings / Logout.
- **`components/admin-layout.blade.php`** (superadmin) — **slate-900** top bar + amber "PLATFORM" badge (deliberate visual distinction). No notifications/company-settings.

### Navigation (`components/app/sidebar.blade.php`)
- Tenant groups: **Workspace** (Dashboard, Quotes, Clients), **Catalogue** (Catalogue, Engines), **Settings** (Company, Team[admin], Email templates + Email log collapsible), **Activity** (Notifications + unread badge).
- Superadmin groups: **Platform** (Overview, Dealers), **Global catalogue** (Brands/Models/Variants/Engines/Equipment/Options), **Customisation** (Settings, Dictionary), **Activity** (Activity log). Active state via `request()->routeIs('quotes.*')` etc.
- `components/app/header.blade.php` — mobile toggle, page title, ⌘K search trigger, FR/EN switcher, notifications bell, user dropdown.
- `components/app/search-palette.blade.php` — ⌘K/Ctrl+K, debounced (200ms), arrow-nav, queries SearchController.
- Other components: `app/stat-card`, `app/status-pill`, `app/empty-state`, `auth-brand-panel` (nautical mosaic), plus Breeze form components (modal/dropdown/buttons/inputs).

### View folders (purpose)
`admin/` (catalogue brands/models/variants/engines/equipment/options each form+index; dealers create/index/show; dictionary; settings; audit; dashboard) · `auth/` (login/register/forgot/reset/verify/confirm/accept-invitation) · `catalogue/` (brands, models, model-edit, variant-create, updates, partials `_boat-fields`/`_equipment-checkboxes`) · `clients/` · `company/settings` · `dashboard` · `email-log/` · `email-templates/` · `emails/` (dealer-welcome, team-invitation) · `engines/` · `livewire/quote-builder` · `notifications/` · `pdf/` · `profile/` · `quotes/` (index/create/edit/show/trash) · `team/` · `layouts/guest` · `maintenance`.

### PDF templates (`resources/views/pdf/`)
- **`_styles.blade.php`** — shared CSS. Table-based (DomPDF: no flex/grid). Colors `.nv-navy #0d3d5c`, `.nv-accent #2ab0e8`, `.nv-green #16a34a`, `.nv-orange #ea580c`. DejaVu Sans ~9.5pt, A4.
- **`quote.blade.php`** — header (company + "Quotation" + number + date), client/salesperson meta, navy boat band, included-equipment checklist, options table (base + boat discount + options by category + custom items), conditions+totals (terms left, trade-in box, totals card: subtotal HT → global discount → total HT → VAT → **Total TTC** → **Net payable** headline), buyer/seller signatures, footer with page number.
- **`order-confirmation.blade.php`** — same shape; "Order confirmation" title, `order_confirmation_number`, "Bill to", "Confirmed configuration", confirmation date. Generated only after WON.
- **NEVER rendered in PDFs:** margin, cost, internal_notes, internal IDs.

---

## 13. i18n (EN/FR)

- Source strings in Blade are **English verbatim**, used as keys: `{{ __('Dashboard') }}`.
- `lang/fr.json` (~904 entries) is the real dictionary; `lang/en.json` is a stub comment (English = the key itself).
- Default locale **`fr`**, fallback `en` (`config/app.php`). Selection: `locale` cookie → `SetLocale` middleware → `App::setLocale()`. Switch via `route('locale.switch', 'fr'|'en')`.
- **DB overlay:** `DbOverlayTranslator` lets superadmin override any string at runtime via `/admin/dictionary` (writes `translations` collection; reset = delete row → falls back to file). Cached 24h, busted by `TranslationObserver`.

---

## 14. Theme / build / config

- **Tailwind** (`tailwind.config.js`): daisyUI theme `nautiqs` — `primary #0e4f79`, secondary `#185482`, accent `#266390`, success `#16a34a`, warning `#f59e0b`, error `#dc2626`, base-100 `#fff`, base-200 `#f5f7fa`. Font `Geist`. Plugins: `@tailwindcss/forms`, `daisyui`. Custom primary scale 50→900 (800 = `#0e4f79`).
- **Vite** (`vite.config.js`): inputs `resources/css/app.css`, `resources/js/app.js`, refresh on. `app.js` imports bootstrap + chart.js (`window.Chart`). **Do not import Alpine** (Livewire 4 ships it).
- **Config:** `config/database.php` mongodb connection (`MONGODB_URI`, `MONGODB_DATABASE` default `nautiqs`); `config/app.php` locale `fr`/fallback `en`, custom `tracking_base_url` (lets local dev use prod tracking pixel base); `config/services.php` postmark + google + (aws/slack unused); `config/mail.php` postmark default + brevo registered in provider; `config/auth.php` web/session guard.
- **`.env` keys:** `APP_*`, `DB_CONNECTION=mongodb` + `MONGODB_URI` + `MONGODB_DATABASE`, `MAIL_MAILER` (+`POSTMARK_TOKEN`/`POSTMARK_MESSAGE_STREAM` or `BREVO_KEY`), `MAIL_FROM_*`, `GOOGLE_CLIENT_ID/SECRET/REDIRECT_URI`, `EMAIL_TRACKING_BASE_URL`, session/cache/queue, optional AWS/Slack.

### Required local extension
`mongodb` PECL extension (`php_mongodb.dll` for PHP 8.2 TS x64). Without it Laravel cannot reach Atlas.

---

## 15. Quick reference — "where do I look for X?"

| Task | File(s) |
|---|---|
| Add a quote calculation rule | `app/Services/QuoteCalculator.php` |
| Change quote builder UX | `app/Livewire/QuoteBuilder.php` + `resources/views/livewire/quote-builder.blade.php` |
| Tenant isolation logic | `app/Models/Scopes/TenantScope.php`, `app/Models/Concerns/BelongsToTenant.php` |
| Global catalogue admin | `app/Http/Controllers/Admin/CatalogueController.php` + `resources/views/admin/catalogue/**` |
| Copy-on-activation snapshot | `app/Services/CatalogueService.php` |
| Quote numbering | `app/Models/QuoteCounter.php` |
| PDF layout | `resources/views/pdf/{quote,order-confirmation,_styles}.blade.php` |
| Email sending + tracking | `QuoteController@sendEmail`, `EmailPixelController`, `EmailTemplateService` |
| Translations override | `app/Services/DbOverlayTranslator.php`, `Admin/DictionaryController.php`, `lang/*.json` |
| FX rates | `app/Services/FxRateService.php` |
| Roles/middleware | `bootstrap/app.php`, `app/Http/Middleware/*` |
| Routes | `routes/web.php`, `routes/auth.php` |
| Dealer provisioning | `Admin/DealerController@store`, `app/Services/CompanyProvisioner.php` |
| Mongo indexes | `php artisan mongo:ensure-indexes` (`app/Console/Commands/EnsureMongoIndexes.php`) |
| Seed data | `database/seeders/*`, `php artisan library:seed` |

---

*If the codebase diverges from this doc, the code wins — re-verify and update this file.*
