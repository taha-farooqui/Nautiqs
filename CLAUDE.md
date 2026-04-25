# Nautiqs — Boat Quotation SaaS

Multi-tenant SaaS that lets boat dealerships create, manage, and send professional quotations and order confirmations in under 2 minutes.

Reference document: [technical_specification_boat_saas_v3.pdf](technical_specification_boat_saas_v3.pdf) (V3.0, April 2026).

## Product model

Two-tier architecture:

| Tier | Who | What they manage |
|---|---|---|
| **Platform (Superadmin)** | Software owner | Global catalogue: brands, models, variants, equipment, options |
| **Dealership (Tenant)** | Subscribing dealership | Their own workspace: clients, quotes, settings, private brands |

Key principles:
- Dealerships **activate** brands from the global catalogue — a **copy-on-activation** snapshot is made into their workspace so they can customise prices/margins without being affected by global updates.
- Dealerships can also create **private brands** not in the global catalogue.
- Platform catalogue updates are pushed as **notifications** — the dealer chooses to apply, review, or dismiss.
- **Quotes are snapshots** — catalogue updates never break existing quotes.
- **Strict data isolation**: every tenant query is scoped by `company_id`. No cross-tenant access, ever.

Out of scope (V1): accounting/invoicing/inventory, workshop management, marketplace, native mobile, brands managing their own catalogue directly.

## Stack (as adapted for this implementation)

The original spec recommends Next.js + Prisma + PostgreSQL + Resend. This project uses:

| Layer | Choice | Rationale |
|---|---|---|
| Framework | **Laravel 11** | User-selected |
| Database | **MongoDB Atlas** (cloud) | User-selected — document model fits the nested catalogue/quote snapshots well |
| MongoDB driver | `mongodb/laravel-mongodb` | Official Laravel MongoDB package |
| Auth scaffolding | **Laravel Breeze (Blade stack)** | Email+password, password reset, email verification out of the box |
| OAuth | **Laravel Socialite** (Google provider) | "Continue with Google" — creds in `.env` |
| Reactive UI | **Livewire 3 + Alpine.js** | For no-reload quote builder (replaces Zustand from spec) |
| CSS | **Tailwind CSS + daisyUI** | Matches the clean card/tile UI in the login screenshot |
| Font | **Inter** | User requirement |
| Primary colour | **`#0e4f79`** | User requirement — deep navy blue |
| Email | **SMTP** (Laravel Mail) | User-selected (replaces Resend) — creds in `.env` |
| PDF generation | `barryvdh/laravel-dompdf` | Server-side PDF from Blade templates |
| Excel/CSV import | `maatwebsite/excel` | Catalogue + private brand imports |

### Required local PHP extensions
- `mongodb` (PECL) — **not installed by default on Windows**. Download `php_mongodb.dll` matching PHP 8.2 TS x64 from https://pecl.php.net/package/mongodb, drop into `ext/`, add `extension=mongodb` to `php.ini`, restart. Without this, Laravel cannot talk to Atlas.

## User roles

| Role | Scope | Access |
|---|---|---|
| `superadmin` | Platform-wide | `/admin/*` routes only — manages global catalogue, all tenants, platform settings |
| `tenant_admin` | Company-scoped | Dealership owner/manager — company settings, brand activation, team, quotes |
| `tenant_user` | Company-scoped | Salesperson — creates and manages quotes inside their dealership |

No dealership user can ever reach `/admin` routes. Role is persisted on the `User` document and enforced by middleware at the route level, plus a `company_id` query scope at the model level.

## Data model (MongoDB collections)

**Platform-level** (no `company_id` — global):
- `brands`, `global_boat_models`, `global_boat_variants`
- `global_equipment_items`, `global_variant_equipment`, `global_options`
- `catalogue_update_log`

**Tenant-level** (every document carries `company_id`):
- `companies`, `users`
- `company_brands`, `margin_presets`, `email_templates`, `clients`
- `company_boat_models`, `company_boat_variants`
- `company_equipment_items`, `company_variant_equipment`, `company_options`
- `import_log`, `documents`, `catalogue_update_notifications`
- `quotes`, `quote_options`, `quote_custom_items`
- `order_confirmations`, `email_log`

See spec §3 for the complete field-level schema.

### Margin resolution cascade
When calculating margin for a line, the system tries these sources in order and uses the first that has data:
1. Real margin (cost price entered for this line)
2. `MARGIN_PRESET` by category (hull / engine / options / custom_items)
3. `COMPANY_BOAT_MODEL.default_margin_pct`
4. `COMPANY.default_margin_pct` (global fallback)

Every quote line shows whether its margin is REAL (cost provided) or ESTIMATED (cascade) — visible in the builder, never in the client PDF.

## Implementation phases

Mirrors spec §19 but adapted to Laravel:

1. **Foundation** — Laravel install, MongoDB driver, Tailwind + daisyUI, Inter font, theme
2. **Auth** (current task) — Breeze + Socialite Google + SMTP reset, role middleware, tenant scoping scaffold
3. **Onboarding flow** — create account → company profile → logo → salesperson → activate brands → catalogue copy → dashboard
4. **Superadmin panel** (`/admin`) — global catalogue CRUD, tenant management, update notifications
5. **Tenant catalogue** — private brands, Excel/CSV import (3 methods: file, copy-paste, manual)
6. **Quote builder** — Livewire, live totals, no page reloads, <100ms updates
7. **Quote PDF** — DomPDF template per spec §12 (margin/cost/internal notes must NEVER appear)
8. **Order confirmation PDF** — separate immutable doc with signature block (spec §13)
9. **Email module** — three templates (quote sending, order confirmation, follow-up), variable replacement, SMTP send
10. **Dashboard + lists** — clients page, quotes page, KPIs, update alerts
11. **Company settings** — profile, salesperson, defaults, margin presets, brand management, email templates
12. **Integration + deploy**

## Performance targets (spec §18)
- Initial page load: **< 2s**
- Live summary update in quote builder: **< 100ms**
- PDF generation: **< 5s**
- Catalogue import (500 rows): **< 10s**
- Brand activation (copy catalogue): **< 15s**
- Email send with attachment: **< 5s**

## Naming conventions
- Quote number: `Q-YYYY-NNN` (e.g. `Q-2026-001`) — per-dealership counter, resets yearly
- Order confirmation: `BC-YYYY-NNN` — per-dealership counter, resets yearly

## Critical security invariants
- HTTPS on all routes in production
- Superadmin routes fully separated and role-gated
- Strict `company_id` scoping at the query level (global scope on tenant models)
- No cross-tenant data access possible under any circumstance
- Margin, cost prices, and internal notes must never appear in any client-facing PDF or email

## External configuration (all via `.env`)
- `DB_CONNECTION=mongodb` + `DB_URI=<Atlas connection string>`
- `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI`
- `MAIL_*` (SMTP host, port, username, password, from address)

## Reference assets
- `logo.jpeg` — Nautiqs logo (blue sailboat on square background)
- `technical_specification_boat_saas_v3.pdf` — full V1 spec
