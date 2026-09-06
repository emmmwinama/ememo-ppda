# PPDA e-Services (rebuilt)

Native module inside the ememo app. Uses the ememo database (`config/database.php`),
the ememo SSO session, and the ememo theme (`assets/css/theme.css`). **None** of the
legacy PHPRunner code or theme is carried over — this is a fresh implementation of the
*functionality*.

Reached from the Digital Hub: the "e-Service" card → `app/eservice/index.php`.

## Install

1. Load the schema and seed data into the ememo database:

   ```
   mysql -u ppda_u375699389_ememo -p ppda_u375699389_ememo < app/eservice/sql/01_schema.sql
   mysql -u ppda_u375699389_ememo -p ppda_u375699389_ememo < app/eservice/sql/02_seed.sql
   ```

2. Grant e-Services roles. Open `app/eservice/admin.php` as an ememo **admin**
   (ememo `users.role = 'admin'` counts as e-Services admin automatically), or:

   ```sql
   INSERT INTO es_user_role (user_id, role) VALUES
     (<uid>, 'registry'), (<uid>, 'officer'),
     (<uid>, 'supervisor'), (<uid>, 'director'), (<uid>, 'dg');
   ```

## What's built (v1 — core happy path)

| Area | Status |
|------|--------|
| App shell, SSO guard, DB bootstrap, role gate | ✅ `inc/bootstrap.php`, `inc/layout.php` |
| Dashboard (KPIs + recent activity) | ✅ `index.php` |
| **Bid registry** — list / add / edit / view | ✅ `bid_registry*.php` |
| **Bid analysis** — start from registry, list, workspace | ✅ `bid_analysis*.php` |
| Compliance checklist (officer, editable in draft) | ✅ `bid_analysis_save.php` |
| Workflow: officer → supervisor → director → DG (submit / endorse / return / approve / reject) | ✅ `bid_analysis_action.php` |
| Routing trail + discussion thread | ✅ |
| **Suppliers** — read-only lookup register | ✅ `suppliers.php` (empty until data imported) |
| e-Services role admin | ✅ `admin.php` |

## Legacy data import

Brings the old e-Services data into the `es_*` tables. Every importable table has a
UNIQUE `legacy_id`, so the import is **idempotent** — re-running updates rows in place.

### One click (no setup)

Open **`app/eservice/import_legacy.php`** (sidebar → *Legacy import*, ememo admin only) and
press **Import now**. It parses the bundled dump `sql/legacy_suppliers.sql` in PHP and writes
straight into the `es_*` tables — no legacy database, no config file. *Preview* = dry-run.
`sql/legacy_suppliers.sql` is the assembled supplier subset (structure from
`ppda_e_services-online.sql` + data from `ppda_e_services-online (1).sql`, ~13k suppliers,
git-ignored, contains PII). Large imports take a minute or two; safe to re-click.

### CLI

- From the dump file:
  `php app/eservice/import/run.php suppliers --file=app/eservice/sql/legacy_suppliers.sql`
- From a live legacy DB: `cp app/eservice/import/config.sample.php app/eservice/import/config.php`,
  set the creds, then `php app/eservice/import/run.php all [--dry-run]`.

Note: the supplier bundle has no PDE / category / country catalogue tables, so `country_id`
and supplier categories import as NULL — fine for a lookup register.

Phases, in order:

| Phase | Legacy → new |
|-------|--------------|
| `lookups`   | `_bid_ppdes` → `es_pde`; `_bid_ppda_officers` → `es_legacy_officer_map` (matched to ememo users by email, then full name); any `country` / `currency` / `*_category` catalogue tables it can find → `es_country` / `es_currency` / `es_category` |
| `suppliers` | `_supplier_details` (+ `_supplier_business_operations`) → `es_supplier`; shareholders, bank details, categories, certificates, attachments (one row per file column) |
| `bids`      | `_bid_registry` → `es_bid_registry`; `_bid_analysis` → `es_bid_analysis`; `_bids_lots`, `_bid_assessment` → `es_bid_bidder`, `_bid_financial_evaluation`, `_bid_techncial_list`, the 6 criteria/observation/recommendation tables → `es_bid_list_item`, `_bid_routing`, `_bid_analysis_messaging`, `_bid_pde_response` |

**Status codes:** the legacy DB shipped no `_parameters` lookup data, so
`es_bid_analysis.stage` is derived structurally (`archive` → approved; `dg_approval`
changed from default → dg_review; `board_status` → board_review; `validation_status` →
director_review; a supervisor set → supervisor_review; else draft) and the raw legacy
integers are preserved in `es_bid_analysis.legacy_status_note`. Supplier status maps to
`expired` when `expire_date` is past, else `active`; the original int is kept in
`es_supplier.legacy_status_int`. Officers/routing users that don't match an ememo user
are left NULL and counted as warnings. Review over the warnings after the first run.

## Not yet built (next steps)

- Bidder capture + financial + technical evaluation screens (`es_bid_bidder`,
  `es_bid_financial_eval`, `es_bid_technical_eval`, `es_bid_list_item`).
- Lots.
- PDE feedback letter (`es_pde_response`) + publish.
- Board review stage (`board_review` — table/enum exist, no UI).
- Attachments upload (`es_bid_attachment`, `es_supplier_attachment` — tables exist).
- Supplier detail view + certificate/QR.
- Notifications (hook into ememo's notifier).
- Reference-data admin screens (PDEs, categories, methods) — seed only for now.

## Schema notes

- All tables `es_`-prefixed, InnoDB + utf8mb4, real FKs.
- Legacy magic status integers replaced with readable `ENUM`s
  (`es_bid_analysis.stage`, `es_bid_registry.status`, …).
- `legacy_id` on importable tables for one-to-one mapping back to the old rows.
