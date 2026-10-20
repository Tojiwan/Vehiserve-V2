# VEHISERVE

An independent portfolio recreation of a university motor pool system. All demo
accounts and fleet records are fictional. This is not an official university service.

## Current build

Phases 1–4 provide shared authentication, fixed roles, demo safeguards, PostgreSQL
schema and fictional seeds, request submission/tracking, staff approval queues, a
recorded decision timeline, fleet management, transactional scheduling, trip
completion, private uploads with signed downloads, generated PDFs, and in-app
notifications.

Installed: PHP 8.4, Laravel 13.37.0, Filament 5.10.2, Livewire 4.4.7,
Fortify 1.41.0, Pest 4.7.8, PostgreSQL 18.6, Node 24.22.0, npm 11.19.1,
Vite 8.3.4, laravel-dompdf 3.1.2, and symfony/brevo-mailer 8.1.0. Exact
dependency versions are in the lockfiles.

## Open this local installation

Start Herd, ensure PostgreSQL is running, then open **http://vehiserve-v2.test**.

PostgreSQL was installed as local EDB binaries rather than a Windows service.
Its data and administrator credential file are outside the repository, under
`%LOCALAPPDATA%\Vehiserve`. The application uses a separate non-superuser database
role; its generated password is in the ignored `.env`.

To start PostgreSQL after a reboot, run in PowerShell:

```powershell
$pg = "$env:LOCALAPPDATA\Vehiserve"
& "$pg\postgresql-18.6\pgsql\bin\pg_isready.exe" -h 127.0.0.1 -p 5432
```

If it reports `accepting connections`, PostgreSQL is already running. Otherwise:

```powershell
& "$pg\postgresql-18.6\pgsql\bin\pg_ctl.exe" -D "$pg\data" -l "$pg\postgres.log" -o '-h 127.0.0.1 -p 5432' -w start
```

To stop it cleanly:

```powershell
& "$env:LOCALAPPDATA\Vehiserve\postgresql-18.6\pgsql\bin\pg_ctl.exe" -D "$env:LOCALAPPDATA\Vehiserve\data" -m fast -w stop
```

## Fresh installation

Requirements: PHP 8.4 with `intl`, `pdo_pgsql`, `mbstring`, `dom`, `fileinfo`,
`curl`, and `zip`; Composer 2; Node 24; PostgreSQL 18. Herd is optional.

1. Install dependencies from the project directory:

   ```powershell
   nvm install 24.22.0
   nvm use 24.22.0
   composer install
   npm ci
   Copy-Item .env.example .env
   ```

   Copy `.env.example` only on a fresh installation; retain an existing `.env` and key.

2. With PostgreSQL running and its `bin` directory on PATH, create a local role and
   two separate databases. The password command prompts for your chosen password:

   ```powershell
   psql -U postgres -h 127.0.0.1 -c "CREATE ROLE vehiserve LOGIN;"
   psql -U postgres -h 127.0.0.1 -c '\password vehiserve'
   createdb -U postgres -h 127.0.0.1 -O vehiserve vehiserve
   createdb -U postgres -h 127.0.0.1 -O vehiserve vehiserve_test
   ```

3. Set `DB_PASSWORD` in `.env` to that password. Adjust host/port if necessary.
   Keep `DB_DATABASE=vehiserve`, `SESSION_DRIVER=database`, and `MAIL_MAILER=log`.
   Add a `BREVO_API_KEY` only when testing real email (see below). Then run:

   ```powershell
   php artisan key:generate
   php artisan migrate --seed
   npm run build
   ```

4. With Herd running, link the project:

   ```powershell
   herd link vehiserve-v2
   ```

   Without Herd, set `APP_URL=http://127.0.0.1:8000` and run `php artisan serve`.
   Use `npm run dev` while editing assets, or rebuild them with `npm run build`.

On Windows, stop the Vite dev server before running `npm ci`; native asset-tool
files are locked while it runs. The lockfile's clean install was verified in an
isolated temporary directory as well as the project's successful asset build.

## Local demo accounts

Every account below uses **`Vehiserve-demo-2026!`**. These are public fictional
credentials, not production administrator credentials.

| Role | Email |
| --- | --- |
| User | `user@demo.vehiserve.test` |
| Admin | `admin@demo.vehiserve.test` |
| Motor Pool Staff | `motor_pool@demo.vehiserve.test` |
| Dean | `dean@demo.vehiserve.test` |
| Vice President | `vice_president@demo.vehiserve.test` |
| SUC President | `president@demo.vehiserve.test` |
| Super Admin | `super_admin@demo.vehiserve.test` |

All roles use `/login` and `/dashboard`; staff roles can also use `/admin` with the
same session. Requesters cannot enter the panel. Public registration is disabled.
Demo accounts cannot change credentials, delete accounts, manage real users, or
send password-reset/verification mail. Demo staff policies exclude real records.
Super Admin does not bypass the approval sequence. Account-management screens and
public role-entry buttons belong to later phases.

The seed is additive and repeatable: `php artisan db:seed` creates missing fictional
accounts and fleet records, preserving existing ones. It is not a demo reset command.

## Checks

The latest local verification passed 170 tests / 939 assertions, formatting,
static analysis, and a fresh production asset build. Composer validation and
Composer/npm audits passed with no reported vulnerabilities. Two-process PostgreSQL tests verify concurrent approvals
wait on a lock and cannot overwrite a newer decision, and concurrent assignments
cannot double-book a vehicle or a driver shared by different vehicles. Concurrent
uploads also respect the three-attachment cap. A
headless-browser pass verified the dashboard notification list and mark-as-read,
both generated PDFs, attachment upload, signed download, and deletion, the staff
read-only view, and the Filament notification bell.

```powershell
composer ci:check
npm run build
composer validate --strict
composer audit
npm audit
```

`composer ci:check` runs Pint, PHPStan, and Pest. Tests force PostgreSQL and the
`vehiserve_test` database, using the local host/port/credentials from `.env`.
They migrate and reset that disposable database, not the development database.
Never point test credentials at a hosted production database.

CI is configured in `.github/workflows/tests.yml`, with PostgreSQL 18, PHP 8.4,
and Node from `.nvmrc`. GitHub execution remains to be verified after pushing.

## Conventions and next steps

### Request workflow

1. Sign in as User and open **Transport requests → New request**. Enter office,
   destination, purpose, departure/return in Manila time, and one passenger per line.
2. Sign in as Motor Pool Staff and open **Request queue** in the staff panel.
   View the request and confirm preliminary availability.
3. Review in order as Dean, Vice President, SUC President, then Motor Pool Staff
   for final approval. The request becomes **Ready to schedule**.

The queue defaults to **Awaiting my decision** for approvers. Admin/Super Admin
can inspect requests but cannot sign for another role. The detail screen is shared
between the portal and staff queue; every decision is authorized server-side.

Submitted trip details cannot be edited. Requesters may cancel before scheduling;
Motor Pool Staff may cancel scheduled trips and release their booking. Rejection
and cancellation require a reason and are terminal. A stale browser tab must reload
before recording another decision. History retains the actor's name at decision time.

Submissions allow 1–60 passengers (excluding the driver) and at most five requests
per user per minute. Dates must be valid with return after departure; past departures
are currently permitted. Preliminary availability checks do not reserve vehicles or
drivers; scheduling performs the fresh transactional check.

Four fictional browser-walkthrough requests remain in the development database as
examples at every stage (ready to schedule, scheduled, completed, cancelled); they
are not part of the repeatable seed yet. After pulling these changes elsewhere,
run `php artisan migrate` to add the history actor-name snapshot column.

### Fleet and scheduling

1. Stay signed in as Motor Pool Staff and use **Request queue**. Requests ready for
   transport appear with a **Schedule** action; pick a vehicle and driver from the
   options that are free for the trip window, then submit.
2. Open **Schedule** in the staff panel for the month calendar and bookings list.
   Both show Manila times and include active and completed trips. Completed trips
   leave the request queue.
3. Use **Complete trip** on a scheduled request to close it. The actual completion
   time is recorded in history and the vehicle/driver are released.
4. Manage **Vehicles** (plate, capacity, status) and **Drivers** (contact, license,
   availability) from the staff panel. Records with bookings are kept for history.

Scheduling locks the request, vehicle, and driver, rechecks half-open overlaps for
active bookings, and writes the booking, state change, and audit event in one
transaction. Cancelled and completed bookings do not block. Rescheduling is not
exposed yet: cancel and submit a new request when plans change. Demo staff only see
and assign demo resources, and fleet records created by demo accounts stay demo-owned.

### Attachments and documents

1. As the requester, open a request and use **Add an attachment** to upload an
   endorsement letter, supporting document, or other file in a fixed category.
   PDF, JPEG, and PNG up to 5 MB each; at most 3 attachments per request.
2. Every request page offers **Vehicle request** and **Travel order** PDFs built
   from the shared trip record, with recorded approver names and times.

Uploads are requester-only and stored on the private `attachments` disk
(`storage/app/private/attachments`) under server-generated opaque paths; staff
see them read-only. Downloads use 15-minute signed links and re-check
authorization on every request, so a shared link still fails for anyone without
access. Only the uploader can delete, and only while the request is not in a
terminal state. Demo-owned requests are labeled as samples in generated PDFs; the
renderer fetches no remote resources.

PHP must allow the advertised upload limit: set `upload_max_filesize=5M` (or
higher), `post_max_size=8M` (or higher), and a writable `upload_tmp_dir` in the
PHP configuration used by your web server, then restart it. The reviewed local
CLI default was only 2 MB. A writable system temp directory is also required;
generated PDFs use `storage/app/private/dompdf` for their temporary font files.
The browser review uploaded and downloaded an exact 5 MB PDF with these settings.

### Notifications and email

Workflow decisions send in-app notifications after the decision commits: the next
reviewer is alerted when a request reaches their stage, the requester when all
approvals complete, when the trip is scheduled or completed, and on rejection or
cancellation (with the reason), and motor pool staff when their queue work
changes. Staff see them in the Filament bell; everyone sees a **Recent updates**
list on the dashboard with mark-as-read. Demo accounts receive in-app
notifications only.

Non-demo accounts additionally receive mail through the `brevo` mailer
(symfony/brevo-mailer HTTPS API, 10-second timeout). To test real delivery, set
`MAIL_MAILER=brevo`, `BREVO_API_KEY`, and a verified `MAIL_FROM_ADDRESS`; local
development keeps `MAIL_MAILER=log`. Failed sends are logged, never roll back a
decision, and are not reported as delivered.

### Development conventions

- Store timestamps in UTC, including the PostgreSQL connection timezone. Forms
  and displays must convert through `config('vehiserve.display_timezone')`
  (`Asia/Manila`). Conversion and database date ordering are covered by tests.
- Apply policies in every action. Ownership query scopes do not replace authorization.
- All approval/rejection/cancellation entry points use `TransitionTripRequest`,
  and all assignment/completion entry points use `AssignTrip`/`CompleteTrip`, which
  lock/re-read rows and atomically append history. Never write bookings outside
  `AssignTrip`.
- Local uploads stay private on the `attachments` disk under server-generated
  paths; hosted object storage is deferred to Phase 6. Never copy the legacy SMTP
  key: Brevo API credentials live only in `.env`.
- Password-reset and verification links use the request host, or `APP_URL` when
  generated outside a request. Verify the public HTTPS host at deployment. Real
  inbox delivery through Laravel's Brevo HTTPS mailer was confirmed by the owner
  on October 20, 2026. The owner also confirmed password-reset email delivery,
  password change, and login. Sending from Render remains unverified. Keep logs private.
- DHVSU logo publication permission remains pending before any public release.
- Follow [BUILD_GUIDE.md](BUILD_GUIDE.md) and [checklist.md](checklist.md) for scope.
