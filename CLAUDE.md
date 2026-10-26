# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

**Oasis Canvassing** — Laravel 13 (PHP ^8.3) + Inertia + Vue 3 app for PT Oasis Waters International's canvassing (van-sales) business: depot warehouse → daily van load → sales/returns/empty-gallon collection at outlets via the mobile API → unload → cash settlement & reconciliation → monthly targets and incentives. Rebuilt from scratch (Oct 2026) with its own data model; old Marketama/Enesis master data is imported with `oasis:impor-master`. Branding: Oasis logo (`public/images/`, atom `BrandLogo`) and corporate colors navy `#293694` / teal `#18a6b1` (`@theme` in `resources/css/app.css`). UI text and code comments are in Indonesian — match that. Code identifiers, tables and columns are English. Business background: `README.md`, data model: `docs/DATA_MODEL.md`, mobile API: `docs/API.md`.

## Commands

```bash
composer setup                 # install, .env, key, migrate, npm install + build
composer dev                   # php artisan dev (server + vite etc.)
composer test                  # config:clear + php artisan test
php artisan test --filter=NamaTest          # single test class/method
vendor/bin/pint                # code style (Laravel Pint)
php artisan migrate --seed     # schema + reference data (roles, admin, channels, categories, schemes)
php artisan db:seed --class=DemoSeeder      # demo data (2 depots, full day of transactions)
php artisan db:seed --class=PondokKopiSeeder # sample check-in today: 3 motoris, 24 outlets in Pondok Kopi (Depo Bekasi)
php artisan oasis:impor-master [--csv=dir] [--only=depo,area,...]   # import master data from the old app
php artisan oasis:buat-admin admin          # create/reset super admin
```

Tests use in-memory SQLite (`tests/Concerns/BuildsOasis.php` has fixture builders; `TestCase` calls `withoutVite()`). `tests/Feature/Web/AccessTest` opens every sidebar page with `DemoSeeder` data and asserts the Vue page file exists — keep it green when adding menus. Frontend can only be built where npm works (GitHub Actions `build-deploy.yaml` on push to `main` builds `public/build` into branch `deploy`).

## Architecture

- **Domain logic lives in `app/Services`**, controllers stay thin. Key services: `Inventory` (the only way to change stock: writes `stock_movements` + updates `stock_balances`, rejects negative stock), `Pricing` (depot price overrides national price, latest `effective_from` ≤ date), `TripService` (load/cancel/depart/finish/unload), `SalesService` (mobile sale/return/empty-gallon, void), `SettlementService` (live summary until approved, then frozen in `settlements`; approval writes off what is still in the van), `TargetService`, `IncentiveService` (estimate live; approved/paid frozen in `incentive_payouts`), `FieldService` (attendance, visits, GPS, new outlets with auto territory from polygon), `StockDocumentService`.
- Business-rule violations throw `App\Exceptions\BusinessRuleException` → web: `back()->with('galat', …)` (+ field error), API: 422 `{"error": {"code", "message", "field"}}` (see `bootstrap/app.php`).
- **Stock is location-based**: `stock_locations` = depot warehouse, depot damaged-goods, and one vehicle per salesman. A movement moves qty from → to (null = outside: supplier/customer).
- **Enums** in `app/Enums` (string-backed, `label()` in Indonesian, `options()` for dropdowns). Date-only columns are stored as datetime strings in SQLite — always query them with `whereDate()`, never `where('date', '…')` / `firstOrCreate` on a date.
- **Authorization**: permissions are keys in `config/permissions.php`; a role stores a list of keys (`*` = all). `Gate::before` maps `can('x.y')` to the role. **Depot scope**: `User::depotIds()` (null = all depots; else `depot_user` pivot, falling back to the employee's depot); models with `depot_id` use `ScopedToDepot` (`visibleTo($user)`), employee-bound models use `ScopedThroughEmployee`. Always load records through `visibleTo()` in controllers so other depots 404.
- **Navigation**: `config/navigation.php` (sections → items with `slug`, `icon`, `permission`; section `posisi: footer` holds Master Data / Pengaturan sub-groups shown in the user menu + side panel). `App\Support\Navigation` filters by permission; shared via `HandleInertiaRequests` (`menu`, `searchMenu`, `currentSlug` = request path, `auth.user.permissions`).
- **Generic master pages**: extend `App\Http\Controllers\ResourceController` (declare `columns()`, `fields()` with validation rules, `row()`, optional `query()/filters()/save()/rowActions()`), register with the `$resource()` helper in `routes/web.php`; rendered by `Pages/Resource/Index.vue`. Custom pages get their own controller + page under `resources/js/Pages/<Area>/`.
- **Mobile API** (`routes/api.php`, `/api/v1`): bearer tokens in `api_tokens` (SHA-256 hash, `App\Support\Auth\ApiTokens`, guard `mobile`), middleware `salesman` (account linked to an active salesman with a depot). Every create endpoint is idempotent on `client_uuid` (201 new, 200 resend).
- Lookup lists sent to Vue are arrays of `{value, label}` (`Controller::options()`), not `{id: label}` objects (JS reorders numeric keys). Money = integer rupiah; quantities = integer base units.
- Maps (`Field/Tracking.vue`, `Master/TerritoryMap.vue`) use Leaflet from `public/vendor/leaflet` via `Composables/useLeafletMap.js`; never bind `:class` on the Leaflet container element (it wipes Leaflet's classes) — put state classes on a wrapper.

## Komponen UI (atomic design) — WAJIB dipakai

Semua markup UI **harus** disusun dari komponen reusable di `resources/js/Components/ui/` (atoms → molecules → organisms), berdiri di atas class CSS di `resources/css/app.css`:

| Layer | Komponen |
| --- | --- |
| atoms | `BrandLogo`, `Button`, `Badge`, `Icon`, `Label`, `Input`, `Select`, `Textarea`, `Checkbox`, `Radio`, `FieldError`, `Tooltip` |
| molecules | `Card`, `Alert`, `Modal`, `Pagination`, `PageHeader`, `PasswordInput`, `Flash`, `Breadcrumb`, `Tabs`, `Combobox`, `FilterBar`, `FormField`, `StatCard` |
| organisms | `Sidebar` (dengan `partials/`), `Topbar`, `DataTable`, `SettingsSideNav` |

Aturan:
- **Jangan** menulis ulang markup `<div class="card">`, `<button class="btn …">`, `<span class="badge …">`; pakai `<Card>`, `<Button>`, `<Badge>`, dst.
- Tabel daftar memakai organism `DataTable` (props `columns` {key: label}, `rows`, `total`, `links`, `numeric`, `showFooter`, `rowKey`; slot `#cell-<key>`, `#aksi`, `#header`, `#tfoot`). Filter halaman memakai `FilterBar` (bawaan memasang ulang halaman saat filter berubah supaya form/peta dari props ikut segar).
- Atom/molecule yang dipakai ≥ 2 tempat wajib diekstrak ke folder yang sesuai. Partial khusus satu halaman boleh di `Pages/<Area>/partials/`.
- Variasi kecil = prop baru pada komponen yang ada, bukan salinan komponen.
- Styling elemen berulang lewat class komponen pendek di `app.css` (`@layer components`); utility Tailwind langsung hanya untuk tata letak satu halaman, sesuai `docs/STYLE_GUIDE.md`.
- Hanya ikon yang ada di `public/vendor/lucide/` yang bisa dipakai.
