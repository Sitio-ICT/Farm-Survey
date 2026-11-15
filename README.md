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
   `functions/alter.sql` is the historical change log, including the original form-import seed rows. Some of those rows fail on a fresh database, so it is run with `--force`. It ends with the migrations in `sql/`. For an existing database, run only the new files in `sql/`.
3. Install dependencies. `vendor/` is not committed.
   ```sh
   composer install   # PhpSpreadsheet, PHPMailer
   npm ci             # if node-sass fails to build, use: npm ci --ignore-scripts
   npm run vendor     # copies jQuery, Bootstrap, Chart.js, DataTables and Font Awesome into vendor/
   ```
4. Configure: copy `functions/config.example.php` to `functions/config.php` and set:
   - `db`: database host, user, password and name. The app won't start without this file.
   - `app_url`: used in password-reset links.
   - `smtp`: optional. Without it, the app still works and shows the temporary password to the admin when a user is created.

   `config.php` is git-ignored, so credentials never end up in the repository. On a real server, use a dedicated MySQL user, not `root`.
5. Create the first administrator. There is no public sign-up; after this, admins add users from the Users page.
   ```sh
   php scripts/create_admin.php admin@example.com Ada Okafor   # prompts for the password
   ```
6. Open `http://localhost/Farm-Survey/login.php`.

## REST API

All endpoints speak JSON.

**Authentication.**
1. Log in with `POST api/login.php` and the body `{"email": "...", "password": "..."}`.
2. The response contains a `token` (valid for 30 days) and the user's `rights`.
3. Send the token on every other call as `Authorization: Bearer <token>`.

The user is always taken from the token. Any `userid` in the request is ignored.

| Method | Endpoint | Right required | Purpose |
|---|---|---|---|
| POST | `api/login.php` | none | Get a token |
| POST | `api/logout.php` | any | Revoke the current token |
| POST | `api/create_cluster.php` | `take_survey` | Submit cluster records |
| POST | `api/create_inventory.php` | `take_survey` | Submit warehouse inventory records |
| POST | `api/create_distribution.php` | `take_survey` | Submit input distribution records |
| GET | `api/get_unverified_cluster.php` | `approval` | List unverified clusters |
| GET | `api/get_unverified_cluster_single.php?cluster_id=` | `approval` | One unverified cluster |
| GET | `api/get_unverified_inventory.php` | `approval` | List unverified inventory records |
| GET | `api/get_unverified_inventory_single.php?inventory=` | `approval` | One inventory record |
| GET | `api/get_unverified_distribution.php` | `approval` | List unverified distributions |
| GET | `api/get_unverified_distribution_single.php?distribution=` | `approval` | One distribution record |
| POST | `api/update_{cluster,inventory,distribution}.php` | `approval` or `correct_survey` | Correct a record: `{"id": 1, "<column>": "<value>"}` |
| POST | `api/verify_{cluster,inventory,distribution}.php` | `approval` | Mark a record verified: `{"id": 1}` |

**Request rules.**
- `create_*` accepts one object or an array of objects. A batch is all-or-nothing, and new records start as `NOT VERIFIED`.
- `create_*` and `update_*` only accept known column names. Unknown fields, `id` changes and `status` changes are rejected with `422`. Records are verified only through `verify_*`.

**Errors** come back as `{"message": "..."}`:

| Status | Meaning |
|---|---|
| `400` | Bad input |
| `401` | Missing, invalid, expired or revoked token |
| `403` | Missing right |
| `404` | Record not found or already verified |
| `405` | Wrong HTTP method |
| `422` | Field not allowed |

**Migrating an older app build (November 2026 security update).**
- `GET api/get_user.php?email=&passkey=` was removed and now returns `410`, because it put passwords in URLs.
- `api/user_data.php` was removed.
- Old `?userid=` / `"userid"` parameters are no longer used for authorization.
- Successful reads now return `200` instead of `201`, and errors use the codes above.

`api/get_unverified_distrubition_single.php` stays as an alias of the correctly spelled endpoint, and it requires the same token.

## Security notes

- Passwords are stored with `password_hash()` and checked with `password_verify()`. Sessions are regenerated on login.
- There is no shared default password. New accounts get a random temporary password and are forced to change it on first login.
- Reset links are single-use, expire (1 hour for resets, 48 hours for invites) and are stored only as SHA-256 hashes. The forgot-password form responds the same way whether or not an email exists.
- SMTP credentials live in a git-ignored config file, and uploaded farm photos are runtime data that is never committed.
- Every form handler in `functions/` checks the session through `functions/auth.php`, and privileged actions also check the designation's rights. Pages and handlers enforce the same rules. Session cookies are `HttpOnly` and `SameSite=Lax`.
- Every state-changing request needs the session's CSRF token: forms send a hidden field, and jQuery AJAX sends an `X-CSRF-Token` header. The token rotates on login, and approvals are POST-only.
- API access uses random Bearer tokens that expire and can be revoked. Only their SHA-256 hashes are stored, and every endpoint checks the caller's role.
