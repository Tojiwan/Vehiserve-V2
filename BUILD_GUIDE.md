# VEHISERVE v2 — Build Guide

Created: October 19, 2026  
Status: Phase 2 implemented and locally verified; Phase 3 is next. Hosted storage remains deferred to Phase 4.

## 1. Read this first

Build a portfolio-quality recreation of VEHISERVE, a university Motor Pool
Management System. The owner has no active users to migrate. The goal is a
convincing, publicly accessible demo with a complete working request-to-trip
workflow, not a production rollout for the university.

- New project directory: `C:\Project Files\Vehiserve-v2\vehiserve-v2`
- Legacy reference: `C:\Project Files\Vehiserve-v2\Prototype-Cap1`
- Progress tracker: [checklist.md](checklist.md)
- Other folders already exist: `Vehiserve_V2` and `vehiserve-laravel`. They are not
  this project's working directory. Inspect and ask before reusing their contents.

Keep development in this new folder and initialize a separate Git repository.
Use the old application to understand terminology, forms, and approval roles.
Create a new schema and fictional seed data; do not copy its database dump,
credentials, uploaded IDs, signatures, or personal records.

### Phase 0 recheck after folder relocation — October 19, 2026

The parent directory contains the legacy reference and the new project as sibling
folders. At the Phase 0 recheck the new project contained only the guide and checklist.
The owner now manages its separate Git repository inside `vehiserve-v2`.

Read-only legacy source review confirmed vehicle-request terminology (office/college,
purpose, destination, departure, passengers), Motor Pool availability, and Dean,
Vice President, and SUC President approval handlers. Sources:
`vehicle_request_submit.php`, `approve_vehicle_mp.php`, and
`approve_vehicle_req_{dean,vp,suc}.php` in the legacy folder.
`travel_order_submit.php` also exposes official station, inclusive date, requesting
for, vehicle request, and requested by. Confirm any additional travel-order fields
in Phase 4 before adding them to the unified trip record.

The legacy submission confirmation pages select the latest global request rather
than the signed-in user's request. The v2 ownership-scoped detail pages and tests
must avoid that behavior. Legacy code is a terminology reference, not a security
or workflow implementation to copy. No legacy data or credentials were copied.

### Instructions for the next coding session

1. Read this guide and the checklist before changing files.
2. Inspect this folder for work completed since this handoff.
3. Confirm the remaining decisions in section 4 with the owner.
4. Select mutually compatible current Laravel, Filament, Livewire, and PHP
   versions using their official requirements. Record exact versions here.
5. Work through the checklist in order. Check tasks only after implementation and
   verification; record blockers instead of silently skipping them.
6. Keep each phase usable. Run targeted tests for permissions, approval changes,
   scheduling, and files. Do not introduce abstractions for speculative features.
7. Never mark external setup complete based only on local code. Deployment,
   provider credentials, and inbox delivery require separate verification.

## 2. Target stack

| Component | Decision | Notes |
| --- | --- | --- |
| Application | Laravel | One application; use framework conventions and Eloquent |
| Admin/approvers | Filament | Staff management, approval queues, resources, dashboards |
| Requester portal | Livewire + Blade + Tailwind CSS | Focused request creation and tracking screens |
| Database | PostgreSQL | Local PostgreSQL during development; Neon Free online |
| Authentication | Laravel session authentication | Shared users across portal and panel |
| Authorization | Laravel policies and fixed role enum | Add a permission package only if requirements justify it |
| Public hosting | Render Free web service with Docker | Public HTTPS subdomain; service sleeps when idle |
| Email | Brevo HTTPS API | Render Free blocks ordinary SMTP ports |
| Files | Private S3-compatible object storage | Provider must be selected before attachments are built |
| Tests | Pest | Use Laravel's database and HTTP testing facilities |
| Development | Herd, Composer, npm, local PostgreSQL | Unlike the legacy project, Laravel builds frontend assets |
| Source control | Git and GitHub | Separate repository; automated checks before deployment |

### Version record — requirements reviewed October 19, 2026

| Item | Selected version |
| --- | --- |
| PHP | 8.4; local CLI is 8.4.26, with intl and pdo_pgsql enabled |
| Laravel | Installed 13.37.0; project PHP requirement ^8.4 |
| Filament | Installed 5.10.2 |
| Livewire | Installed 4.4.7; Fortify 1.41.0 and Flux 2.20.1 |
| PostgreSQL | Installed EDB 18.6-5 binaries; PostgreSQL 18.6, local-only listener on 127.0.0.1:5432 |
| Node.js | Installed 24.22.0 via Herd's nvm; `.nvmrc` selects it; other shells may still default to Node 26 |
| npm / Vite | npm 11.19.1 bundled with Node 24; Vite 8.3.4; production build verified |
| Tests | Pest 4.7.8; PostgreSQL-backed feature tests |
| PDF package | barryvdh/laravel-dompdf 3.1.2 supports Laravel 13; install during document phase |
| Object-storage provider | Owner deferred selection to Phase 4; private local storage during development |

The legacy app used PHP 8.2 because of its old lock file. Do not carry that lock
file or PHP restriction into this new project.

Composer 2.10.3 resolved the installed stack; exact versions are in `composer.lock`
and `package-lock.json`. The current official Livewire starter source was used
(commit `0f62a26c4e4b401c1300930f47d72a497add8cce`), because its old tagged release
still targeted Laravel 12/Livewire 3. Public registration, passkeys, and two-factor
features were removed through its installer; password reset, confirmation, and
email verification remain. Standard Vite replaces the starter's Vite+ tooling,
removing vulnerable transitive dependencies and 72 unnecessary npm packages.
Both package audits reported no advisories after the change.

The earlier Phase 0 relocation recheck reproduced PHP 8.4.26, Composer 2.10.3, Node 26.11.1,
and enabled intl/pdo_pgsql extensions. Herd and Git are on PATH; psql and Docker
are not. No PostgreSQL Windows service or `C:\Program Files\PostgreSQL` directory
was found. Fresh Packagist metadata confirms the package versions above:
Filament support 5.10.2 accepts Illuminate 13 and requires Livewire ^4.4.7;
Livewire 4.4.7 accepts Illuminate 13. Phase 1 subsequently verified full resolution.

Requirements sources:
- https://laravel.com/docs/13.x/releases
- https://filamentphp.com/docs/5.x/introduction/installation
- Packagist package metadata for laravel/framework, filament/support,
  livewire/livewire, and barryvdh/laravel-dompdf
- https://nodejs.org/dist/index.json
- https://neon.com/docs/postgresql/postgres-version-support

## 3. Hosting constraints that shape the build

Provider details were reviewed on October 19, 2026; check them again before
deploying because free plans change.

### Render Free

- Sleeps after approximately 15 minutes without inbound traffic; waking can take
  about a minute. Include this notice beside the portfolio's live-demo link.
- Local filesystem changes disappear on restarts, redeployments, and spin-down.
  Uploaded documents must live in external object storage, not container storage.
- Use PostgreSQL-backed sessions. Do not depend on local session files.
- Free web services block outbound SMTP ports 25, 465, and 587. Use Brevo's API
  over HTTPS with an API key, not the SMTP key from the legacy application.
- Do not rely on an always-running queue worker or scheduler. Initial email sends
  happen during the request, with timeouts and visible failure handling.
- Keep background reminders and scheduled cleanup out of the initial scope.
- Use environment variables in Render; never copy a real `.env` into an image.
- Render's free Postgres expires after 30 days. Use Neon instead for this demo.

### Neon Free

- PostgreSQL compute can sleep and wake on a connection. Unlike a manually paused
  demo project, a normal idle sleep should not require the owner to restore it.
- The reviewed free plan has 1 GB database storage per project and compute/egress
  limits. Reconfirm actual quotas in the owner's account.
- Use TLS and the connection configuration Neon recommends for Laravel/PDO.
- Review pooled versus direct connections; use the appropriate endpoint for app
  queries and migrations. Do not assume every pooling mode supports every command.
- Pick regions near the Render deployment where possible.
- Free-tier limits can suspend availability. Maintain a downloadable DB backup.

### Object storage

Select a provider offering private buckets, signed downloads, Laravel-compatible
S3 access, and a suitable free allowance. Check card requirements, egress charges,
request limits, and whether inactivity affects access. Cloudflare R2 is a candidate,
not an approved final choice; verify its current terms before signing up.

Phase 0 clarification: this storage is for uploaded files, not PostgreSQL records.
Private local storage works during development; external storage is required before
hosted attachments can persist across Render restarts. On October 19, 2026, the
owner explicitly deferred provider selection to Phase 4. Use private local storage
until then; do not create a billing account or treat hosted uploads as ready.
R2's reviewed Standard allowance is 10 GB-month storage, 1 million Class A and
10 million Class B operations monthly, with free direct egress. Usage above these
allowances is billed. Its setup requires an R2 subscription checkout; confirm the
owner's account/payment requirements before choosing it.

Sources: https://developers.cloudflare.com/r2/pricing/ and
https://developers.cloudflare.com/r2/get-started/

Supabase was considered earlier for database and files, but its free project
inactivity pause makes it less convenient for an unattended portfolio. Neon is
the current database choice.

Official references:

- https://render.com/docs/free
- https://neon.com/pricing
- https://laravel.com/docs
- https://filamentphp.com/docs
- https://developers.brevo.com/

## 4. Decisions and confirmation status

Use the defaults below unless the owner changes them; record any agreed change.

Owner confirmation, October 19, 2026: approved the default workflow, seven roles,
rejection/cancellation/availability rules, restricted demo access, and Render + Neon
with the documented cold starts. Branding direction: **retain the DHVSU logo;
typography and icons may change**. Keep the portfolio-recreation description;
logo publication permissions have not been verified. Hosted file storage remains
undecided and explicitly deferred to Phase 4. Proposed file types/limits still
need confirmation before implementing attachments.

1. **Roles:** retain User, Admin, Motor Pool Staff, Dean, Vice President, SUC
   President, and Super Admin, with the permissions in section 6.
2. **Routing:** one demo university approval chain. All demo deans see the same
   queue; department-specific routing is deferred until requested.
3. **Rejection:** a rejected request is terminal. A requester can create a new
   request rather than revising an already approved document.
4. **Cancellation:** requester cancellation is allowed before scheduling. Staff
   can cancel a scheduled trip with a reason and release its assignment.
5. **Availability:** the early Motor Pool check is preliminary. Resource
   reservations happen only when scheduling, with a fresh conflict check.
6. **Authentication:** portfolio access uses role-specific demo entry points;
   unrestricted public registration is not needed for the first release.
7. **Files:** approve the external provider and upload types/size limits before
   implementing storage. Initial proposed types are PDF, JPEG, and PNG.
8. **Branding:** retain the DHVSU logo as requested, with refreshed typography and
   icons. Present the app as an independent portfolio recreation, not an official
   university service. Verify permission for public use before publication.

## 5. Product scope

### Public landing page

- Explain the transport-request problem and what the application demonstrates.
- Show technology choices, screenshots, and a short workflow walkthrough.
- Provide a clear demo entry point and link to the source repository.
- Include a brief Render cold-start note and fictional-data notice.
- Supply a video or screenshots outside the sleeping app on the owner's portfolio.

### Requester portal

- Submit purpose, destination, departure/return time, passengers, and supporting memo.
- View only the requester's own requests and attachments.
- See an approval timeline with decisions and rejection/cancellation reasons.
- Cancel eligible requests and download finalized documents.
- Receive in-app status notifications.

### Filament panel

- Role-specific dashboard and actionable request queues.
- Vehicle and driver records, availability, and schedules.
- Approval/rejection actions with required reasons where appropriate.
- Vehicle/driver assignment with conflict checks.
- Calendar and list views using the same booking data.
- Completion/cancellation actions and a read-only audit history.
- Real administrative actions limited to authorized non-demo accounts.

### Deferred until the core workflow works

Driver attendance, fuel/mileage tracking, maintenance work orders, GPS, advanced
analytics, multi-campus routing, real-time sockets, dynamic permission editors,
mobile apps, and a separate REST API/frontend are not required for the first demo.

## 6. Roles and authorization

Use a fixed role enum and Laravel policies. Navigation visibility is convenience;
every HTTP request and Livewire/Filament action must enforce authorization.

| Role | Initial responsibilities |
| --- | --- |
| User | Create and track own requests; cancel eligible own requests |
| Motor Pool Staff | Check availability, finalize transport approval, schedule, complete/cancel trips, manage fleet/drivers |
| Dean | Approve/reject requests currently awaiting Dean decision |
| Vice President | Approve/reject requests currently awaiting VP decision |
| SUC President | Approve/reject requests currently awaiting President decision |
| Admin | Operational oversight, dashboards, read-only request/audit access; no implicit authority to sign for approvers |
| Super Admin | Manage real user accounts and roles; no automatic bypass of the documented approval order |

Add an `is_demo` marker to demo accounts. Their role labels must not allow password,
email, role, account-management, destructive global operations, or real email actions.
Keep those restrictions server-side. Automated tests must cover direct requests,
not just hidden controls.

## 7. Workflow and domain rules

Use stable machine-readable states and display labels separately. Do not store
long approval sentences as the state, as the legacy app did.

```text
submitted
  -> awaiting_dean          (Motor Pool availability check)
  -> awaiting_vp            (Dean approves)
  -> awaiting_president     (VP approves)
  -> awaiting_motor_pool    (President approves)
  -> ready_to_schedule      (Motor Pool final approval)
  -> scheduled              (vehicle and driver assigned)
  -> completed
```

- An authorized reviewer can reject at their stage; require a reason.
- Failed availability can reject with a transport-unavailable reason.
- Cancellation follows the agreed rules in section 4 and is recorded in history.
- Do not allow skipped stages, edits to approved trip details, or repeated decisions.
- Require departure before return and valid passenger information.
- Reject assignments above the vehicle's passenger capacity. Define capacity as
  passenger seats excluding the driver consistently in forms and data.
- Disable unavailable/maintenance vehicles and unavailable drivers for assignment.
- Complete a request only after scheduling; use actual completion time in history.

### Transactions and concurrent changes

Put transitions in one small domain action/service shared by all interfaces.
Authorize the actor, lock/re-read the request in a transaction, validate its
current state, and persist both state and audit event together.

When scheduling, lock the request and selected vehicle/driver records in a
consistent order, then check active overlapping bookings and create the assignment
in the same transaction. Every booking/rescheduling entry point must use this
same path; two simultaneous requests must not reserve the same resource.

Use half-open intervals: a booking ending at 10:00 can be followed by one starting
at 10:00. Overlap is `existing_start < new_end AND existing_end > new_start`.
Cancelled bookings do not block availability. Preserve historical records.

Save timestamps consistently in UTC and display/input university times in
`Asia/Manila`. Test date parsing and timezone conversion.

## 8. Database design

Create Laravel migrations with foreign keys and indexes. Use PostgreSQL locally
for feature tests involving dates, constraints, transactions, and scheduling.

| Table | Main contents |
| --- | --- |
| users | Name, unique email, hashed password, fixed role, email verification, is_demo |
| vehicles | Unique asset/plate identifier, name, type, passenger capacity, operational status |
| drivers | Name, fictional demo contact/license info, availability status |
| trip_requests | Request number, requester, office label, purpose, destination, departure/return timestamps, workflow state |
| passengers | Trip request, passenger name |
| request_events | Request, actor, action/stage, from/to states, reason, timestamp |
| bookings | Request, vehicle, driver, departure/return timestamps, active/completed/cancelled state |
| attachments | Request, uploader, private disk/path, original name, MIME type, size, document category |
| notifications | Laravel database notifications |
| sessions | Laravel database sessions |

Use a single trip request as the source for both vehicle-request and travel-order
PDFs. Avoid duplicating passengers, dates, and purpose across unrelated records.
If travel orders need distinct fields, add only those confirmed requirements.

Use foreign keys to preserve relationships and indexes for approval queues,
requester history, and booking overlap queries. Approval history is immutable
through the application. Do not use blanket hard deletes for approved requests.

## 9. Authentication, files, and email

### Authentication

Choose the Laravel authentication scaffolding that fits the selected Livewire
version. Use framework password hashing, CSRF protection, rate limiting, password
resets, and sessions. Share authentication between the requester portal and
Filament. Use framework migrations instead of the legacy `otp` design.

### Documents and signatures

- Store uploads in private buckets; generate opaque server-controlled paths.
- Validate MIME/type, file size, and request ownership on upload and download.
- Issue short-lived download URLs only after authorization.
- Initial PDF generation should use trusted templates and local/approved assets.
- Do not accept arbitrary remote URLs for the PDF renderer to fetch.
- Display recorded approver name and approval time. Do not present an uploaded
  signature image as a cryptographic digital signature.
- Mark demo-generated documents as sample/not official.

### Email

Use Brevo's transactional HTTPS API through a maintained compatible mail transport
or a small Laravel HTTP integration. Select the least complex supported approach.
Use a new **API key** stored only in environment configuration. The old SMTP key
is not an API credential and must not be copied into the repository.

For the free demo, send synchronously with a bounded timeout. Report send failures
accurately and avoid marking messages as delivered merely because the API accepted
them. Do not roll back an already committed approval because notification delivery
failed. Provide an appropriate retry path without duplicate approval actions.

Public demo activity uses in-app notifications and does not send messages to
arbitrary recipients. Test real email only through controlled non-demo accounts
and inboxes owned by the project owner.

## 10. Demo data and portfolio safeguards

- Seed all seven roles with documented local demo credentials.
- Add enough fictional vehicles, drivers, requests, passengers, bookings, and
  decisions to populate every dashboard and workflow stage.
- Include empty/error states as well as populated screens.
- On the hosted site, demo access must authenticate only predefined demo identities;
  never accept an arbitrary role or user ID supplied by the client.
- Rate-limit demo entry, request creation, and uploads. Set modest creation/storage
  caps so an unattended public demo cannot grow without bound.
- Keep privileged account management and real email sending unavailable to demo users.
- Provide an explicit owner-run reset command that touches demo-owned records only.
  Do not auto-reset on deploy or rely on a free-host scheduler.
- Generate dates relative to reset time; reset data before recording a walkthrough.
- Document that shared demo users can see other visitors' fictional demo activity.

## 11. Implementation phases

### Phase 1 — Foundation

Confirm versions and decisions. Initialize Laravel, Filament, authentication,
PostgreSQL, roles, policies, a base layout, and a small seed. Configure local Herd.
Commit the lockfiles and a placeholder-only `.env.example`.

This folder already contains documentation. If the installer needs an empty
directory, scaffold into a temporary sibling and move the generated project files
here after checking conflicts. Preserve this guide and checklist.

### Phase 2 — Requests and approvals

Build requester forms, ownership-scoped pages, the shared transition action,
approver queues, event timeline, cancellation/rejection, and policy tests.

### Phase 3 — Fleet and scheduling

Build resource management and assignments, then calendar/list views. Use an
existing compatible calendar solution if suitable; otherwise start with a clear
schedule list before adding calendar UI. Verify concurrent conflict prevention.

### Phase 4 — Documents and notifications

Choose storage, build private upload/download flows and PDFs, then in-app
notifications and controlled Brevo API email. Test failure paths and authorization.

### Phase 5 — Portfolio experience

Polish the landing page, requester portal, Filament theme, responsive behavior,
accessibility, seeded dashboards, demo restrictions, and walkthrough assets.

### Phase 6 — Deployment and handoff

Build/test the Docker image, deploy to Render, connect Neon and object storage,
verify email and links, document operations, and publish the portfolio link.

## 12. Deployment implementation details

- Use a multi-stage Docker build for Composer dependencies and npm-built assets.
  Do not install development dependencies in the final production image.
- Serve Laravel through its `public` directory using a production-capable PHP web
  runtime. Bind the HTTP listener to Render's `PORT` and `0.0.0.0` as required.
- Run as a non-root user where practical; allow writes only to framework runtime
  directories. Send logs to stdout/stderr.
- Set `APP_ENV=production`, `APP_DEBUG=false`, a stable secret `APP_KEY`, public
  `APP_URL`, PostgreSQL/TLS, database sessions, storage, and Brevo API credentials.
- Configure trusted proxies and secure session cookies for Render's HTTPS proxy.
- Build caches only when the required runtime environment is available. Do not
  bake local credentials or stale cached configuration into the image.
- Add a lightweight health endpoint. Verify the selected Docker runtime's health
  checks and Render startup timeout.
- Free Render lacks ordinary shell/one-off-job facilities. Define a deliberate
  migration strategy before launch: for the single-instance demo, an entrypoint
  may run `php artisan migrate --force` and stop on failure before starting HTTP.
  Review this strategy before adding multiple instances.
- Run demo seeding/reset explicitly from an authorized owner environment using
  appropriate Neon credentials. Never run `migrate:fresh` or an unconditional reset
  during production startup.
- Verify a redeployment preserves DB records and object-storage uploads.
- Document backup/export and restore steps, external account ownership, and how to
  rotate secrets without losing the Laravel encryption key.

## 13. Definition of done

The portfolio release is ready when:

1. Every role has a usable, authorized demo experience.
2. A request can progress from submission through approvals, scheduling, and completion.
3. Invalid stages, unauthorized access, and double bookings are rejected server-side.
4. Private files cannot be read by unrelated users or anonymous visitors.
5. PDFs reflect the approved request and its audit history.
6. Demo visitors cannot modify credentials, manage real accounts, or send arbitrary email.
7. The public deployment survives a restart/redeploy without losing requests or uploads.
8. Configuration, feature tests, and deployment smoke checks pass.
9. A new developer can follow the README to install locally without hidden secrets.
10. The owner's portfolio has screenshots/video and a clear cold-start note.

## 14. Handoff status

Phases 1 and 2 are implemented and locally verified. Open `http://vehiserve-v2.test` with
Herd and local PostgreSQL running. See README.md for startup, installation, and
all seven fictional demo logins. The owner handles Git; no agent commit was made.

Implemented: shared Fortify/Filament sessions, disabled public registration,
verified-role panel access, fixed role enum and demo flags, ownership/role policies,
demo credential protection, UTC/Manila configuration, domain migrations with
foreign keys/indexes/checks, and repeatable fictional user/vehicle/driver seeds.
Phase 2 adds request creation, ownership-scoped list/detail screens, Filament
approver queues, preliminary availability checks, and the full approval chain to
ready-to-schedule. Shared transition logic locks/re-reads requests, rejects stale
decisions, and saves state and history together. Rejection/cancellation require a
reason; scheduled cancellation releases the booking. Actor names are snapshotted
in history. Submitted details are read-only. Date validation enforces valid dates
and return-after-departure, but does not prohibit past departures.
The retained DHVSU logo was the only copied legacy asset; no legacy data or secrets
were copied. Two fictional browser-walkthrough requests remain as local examples.

Local PostgreSQL binaries and data are under `%LOCALAPPDATA%\Vehiserve`, outside the
repository. Development uses `vehiserve`; tests use `vehiserve_test`, with a separate
non-superuser application role. PostgreSQL is not a Windows service and must be
started after reboot using the README commands. `.env` contains generated local
credentials and is ignored; `.env.example` contains no secret values.

Verification: 112 tests / 675 assertions passed, including a real two-process
PostgreSQL row-lock/stale-decision test. Pint, PHPStan, and the production Vite build
passed. Desktop/mobile browser checks covered submission, all five approval steps,
Filament queues, and cancellation without JS errors or horizontal overflow.
Composer validation and package audits passed in Phase 1; Phase 2 added no dependencies.
CI configuration is written; GitHub execution, hosted services, inbox delivery,
and public logo permission are not verified. Next: Phase 3 fleet management,
transactional scheduling, and trip completion.
