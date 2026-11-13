# Farm Survey

A web platform for collecting farm and warehouse records in the field and turning them into usable information for an agricultural input-financing programme. It was built for a real client running input distribution to farmer clusters across Nigerian states. Field agents register farmers and farms, record the inputs (fertiliser, micronutrients) received at warehouses and handed out to clusters, and managers verify, approve and report on that data.

## What it does

**Farmer onboarding**
- Register farmers with contact, personal and education details, assign them to a cluster, and attach geo-tagged farm photos.
- Bulk-import farmers and farms from Excel templates (`assets/Farmer_Template.xlsx`).
- Approval queue: new farmers stay pending until a user with approval rights signs them off.

**Clusters, farms and warehouses**
- Clusters with a cluster head, the LGAs they cover and their farm size in hectares.
- Farms with GPS coordinates, size and farm items, plus warehouses with location and capacity.
- Dashboard map with a heat map of activity by state (Google Maps).

**Input inventory and distribution**
- Track inputs received into warehouses (type, quantity, warehouse manager, bank representative, driver and vehicle) and distributed to clusters.
- Records submitted by the mobile app arrive *unverified* and are reviewed, corrected and verified before they count.

**Surveys and training**
- Configurable survey questions with answer options, survey corrections and training records linked to farmers.

**Loans and investments**
- Book input loans against a documented farm, approve them and record repayments, including arrears checks.
- Book fixed-deposit investments, approve them and pay investors out.
- Manage banks and branches.

**Reports**
- Cluster, farmer, farm, warehouse, survey and cluster-hectare reports with charts.
- Export report tables to Excel.

**Users and roles**
- Role-based access by designation: view reports, take survey, correct survey, approval, user management, register farmer.
- Admins create users. New users get a random temporary password and an emailed set-password link, and must change the password on first login. Users can also reset a forgotten password by email.

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8 (procedural), MySQL / MariaDB via mysqli prepared statements |
| UI | SB Admin 2 (Bootstrap 4), jQuery, Chart.js, DataTables, Font Awesome, SweetAlert |
| Spreadsheets | PhpSpreadsheet (Excel import) |
| Email | PHPMailer over SMTP |
| Maps | Google Maps JavaScript API (heat map) |
| Mobile | JSON REST endpoints in `api/` used by the field data-collection app |

## Project structure

```
├── *.php                     Pages (dashboard, farmers, clusters, reports, loans, ...)
├── header.php / footer.php   Layout, auth guard and role-based sidebar
├── api/                      JSON endpoints for the field app
├── functions/
│   ├── db.php                Database connection settings
│   ├── connect.php           Query helpers (selectOne, selectAll, insert, update, ...)
│   ├── mail.php              SMTP mail helper
│   ├── password_reset.php    Single-use, hashed reset tokens
│   ├── business/             Loans, fixed deposits, banks
│   ├── people/               Farmers (customers) and users
│   └── system/               Imports, uploads, AJAX helpers, cluster/question/training setup
├── sql/                      Incremental migrations
├── db_structure.sql          Base schema
├── states.sql, locals.sql    Nigerian states and LGAs reference data
├── tableExport/              Client-side table export
└── scripts/copy-vendor.js    Copies frontend libraries from node_modules into vendor/
```

## Getting started

Requirements: PHP 8 with mysqli (XAMPP works), MySQL or MariaDB, Composer and Node.js 18+.

1. Clone into your web root, for example `C:\xampp\htdocs\Farm-Survey`.
2. Create the database and load the schema in this order:
   ```sh
   mysql -u root -e "CREATE DATABASE farm_survey"
   mysql -u root farm_survey < db_structure.sql
   mysql -u root farm_survey < states.sql
   mysql -u root farm_survey < locals.sql
   mysql -u root --force farm_survey < functions/alter.sql
   ```
   `functions/alter.sql` is the historical change log, including the original form-import seed rows. Some of those rows fail on a fresh database, so it is run with `--force`. It ends with `sql/2026_11_password_reset.sql`. For an existing database, run only the new files in `sql/`.
3. Install dependencies. `vendor/` is not committed.
   ```sh
   composer install   # PhpSpreadsheet, PHPMailer
   npm ci             # if node-sass fails to build, use: npm ci --ignore-scripts
   npm run vendor     # copies jQuery, Bootstrap, Chart.js, DataTables and Font Awesome into vendor/
   ```
4. Configure:
   - Database credentials in `functions/db.php`.
   - Copy `functions/config.example.php` to `functions/config.php` and set `app_url` and your SMTP details. `config.php` is git-ignored. Without SMTP the app still works: the temporary password is shown to the admin when a user is created.
5. Open `http://localhost/Farm-Survey/login.php`.

## REST API

All endpoints return JSON. `create_*`, `update_*` and `verify_*` take a JSON request body.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `api/get_user.php` | Authenticate a field agent |
| GET | `api/user_data.php` | Agent profile data |
| POST | `api/create_cluster.php` | Submit a cluster record |
| POST | `api/create_inventory.php` | Submit a warehouse inventory record |
| POST | `api/create_distribution.php` | Submit an input distribution record |
| GET | `api/get_unverified_cluster.php?userid=` | List unverified clusters |
| GET | `api/get_unverified_cluster_single.php?userid=&cluster_id=` | One unverified cluster |
| GET | `api/get_unverified_inventory.php?userid=` | List unverified inventory records |
| GET | `api/get_unverified_inventory_single.php?userid=&inventory=` | One inventory record |
| GET | `api/get_unverified_distribution.php?userid=` | List unverified distributions |
| GET | `api/get_unverified_distribution_single.php?userid=&distribution=` | One distribution record |
| POST | `api/update_cluster.php`, `update_inventory.php`, `update_distribution.php` | Correct a record |
| POST | `api/verify_cluster.php`, `verify_inventory.php`, `verify_distribution.php` | Mark a record verified |

`api/get_unverified_distrubition_single.php` is kept as an alias of the correctly spelled endpoint, for older app builds.

## Security notes

- Passwords are stored with `password_hash()` and checked with `password_verify()`. Sessions are regenerated on login.
- There is no shared default password. New accounts get a random temporary password and are forced to change it on first login.
- Reset links are single-use, expire (1 hour for resets, 48 hours for invites) and are stored only as SHA-256 hashes. The forgot-password form responds the same way whether or not an email exists.
- SMTP credentials live in a git-ignored config file, and uploaded farm photos are runtime data that is never committed.
