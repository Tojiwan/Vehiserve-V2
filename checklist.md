# VEHISERVE v2 — Build Checklist

Created: October 19, 2026  
Working directory: `C:\Project Files\Vehiserve-v2\vehiserve-v2`  
Specification: [BUILD_GUIDE.md](BUILD_GUIDE.md)

## How to track progress

- `[ ]` = not completed; `[x]` = implemented and verified.
- Add evidence such as a command result, test name, commit, or URL in the phase notes.
- Keep blocked tasks unchecked and record the blocker.
- Update this file as work progresses. Do not mark a whole phase done because its UI exists.

## Phase 0 — Confirm the plan

- [x] Create build guide and checklist in the new project directory.
- [x] Inspect this folder and confirm it is the intended v2 project.
- [x] Confirm role responsibilities and the approval sequence with the owner.
- [x] Confirm rejection, cancellation, and preliminary availability rules.
- [x] Confirm public demo access and restrictions; no unrestricted registration by default.
- [x] Confirm branding direction: retain DHVSU logo; typography/icons may change.
- [ ] Verify permission for public logo use before publishing the portfolio demo.
- [x] Confirm Render + Neon and acceptance of free-host cold starts.
- [x] Decide storage timing: owner deferred provider selection to Phase 4; use private local storage for development.
- [x] Review declared package compatibility and select target release lines.
- [x] Verify full Composer resolution and scaffold asset-tool compatibility during Phase 1.
- [x] Record target versions, local tool findings, and approved decisions in the build guide.

Phase 0 evidence (historical; see Phase 1 below for installed tooling): Owner approved workflow defaults and Render + Neon; requested
DHVSU logo retention with new typography/icons. Reviewed official requirements and
Packagist metadata for Laravel 13.37.0, Filament 5.10.2, Livewire 4.4.7, and Dompdf
integration 3.1.2. Local PHP 8.4.26 and Composer 2.10.3 are available with required
intl/pdo_pgsql extensions. Node is 26.11.1; target Node 24 LTS. PostgreSQL was not
found on PATH, in its standard installation directory, or as a Windows service.
Owner explicitly deferred hosted storage selection to Phase 4. No installation
performed. Phase 0 is complete for starting development; dependency resolution
belongs to Phase 1, storage selection to Phase 4, and logo permission to publication.

## Phase 1 — Project foundation

- [x] Initialize a separate Git repository without copying legacy secrets or data. (Owner-managed.)
- [x] Scaffold Laravel while preserving the handoff documents.
- [x] Install compatible Filament and the chosen Livewire authentication scaffolding.
- [x] Select Node 24 LTS for the project and verify Vite engine requirements.
- [x] Verify full Composer dependency resolution and record exact lockfile versions.
- [x] Link a distinct Herd domain, such as `vehiserve-v2.test`.
- [x] Install/configure local PostgreSQL and create development/test databases.
- [x] Add placeholder-only `.env.example` and appropriate `.gitignore`/`.dockerignore` rules.
- [x] Set timezone handling: store UTC, display/input Asia/Manila.
- [x] Implement users, fixed roles, and `is_demo` flags.
- [x] Configure shared authentication, password reset, sessions, and panel access.
- [x] Add policies for requester ownership and role-restricted actions.
- [x] Add domain migrations with foreign keys and relevant indexes.
- [x] Create initial fictional users, vehicles, and drivers.
- [x] Establish Pest and PostgreSQL-backed feature tests.
- [x] Add CI checks for tests, formatting, and asset build using the chosen versions.
- [x] Verify a fresh local install, migrations, login, and production asset build.

Evidence: `composer ci:check` passed (Pint, PHPStan, 61 tests / 305 assertions),
`composer validate --strict` passed, and `npm run build` passed under Node 24.22.0.
Composer/npm audits found no advisories. Initial migrations/seeding passed on local
PostgreSQL 18.6; tests migrated the separate disposable database. Herd HTTP login
and shared panel sessions passed, as did desktop/mobile browser smoke checks.
Policy tests cover all roles/stages, ownership, demo isolation, cancellation,
scheduling permissions, credential changes, reset-token bypass, and account history.
UTC/Manila roundtrip and PostgreSQL date/capacity checks passed. Git root verified
at the nested project path; owner handles repository setup/commits.
CI workflow is configured and its checks passed locally; hosted GitHub execution
is not yet verified. PostgreSQL needs manual startup after reboot (README).

## Phase 2 — Trip requests and approvals

- [x] Implement request form: purpose, destination, office, dates/times, passengers.
- [x] Validate date ordering, passenger input, and required fields server-side.
- [x] Implement requester list/detail screens scoped to ownership.
- [x] Implement stable workflow states with readable labels.
- [x] Create one shared transactional action for state transitions and audit events.
- [x] Implement Motor Pool preliminary availability check.
- [x] Implement Dean approval/rejection queue.
- [x] Implement VP approval/rejection queue.
- [x] Implement President approval/rejection queue.
- [x] Implement Motor Pool final approval and ready-to-schedule state.
- [x] Implement cancellation rules and required rejection/cancellation reasons.
- [x] Display the complete approval timeline.
- [x] Prevent editing approved request details and repeated/skipped approvals.
- [x] Test the happy path from submission through final approval.
- [x] Test ownership violations and every role's forbidden actions.
- [x] Test stale/concurrent decisions cannot overwrite newer state.
- [x] Test cancellation and rejection at their permitted stages.

Evidence: `composer ci:check` passed (Pint, PHPStan, 112 tests / 675 assertions).
Production Vite build passed. RequestWorkflowTest covers submission validation,
ownership, demo isolation, role/stage authorization, cancellation/rejection,
availability, rollback, immutable history, locked properties, and submission limits.
TransitionConcurrencyTest starts a second PHP process against PostgreSQL, verifies
it waits on the request row lock, and confirms its stale approval is rejected.
Desktop/mobile browser checks passed for submission, all five approval steps,
the Filament queue, and cancellation, without JS errors or horizontal overflow.
Two fictional walkthrough requests remain in the development database (ready to
schedule and cancelled). The actor-name migration was applied locally.
Date validation checks format and return-after-departure; past departure dates
are not prohibited. Scheduling and attachments remain Phase 3/4 work.

## Phase 3 — Fleet and scheduling

- [x] Implement vehicle management with unique plates and passenger capacity.
- [x] Implement driver management and operational availability.
- [x] Implement booking assignments through one shared transaction path.
- [x] Lock/revalidate resources and check overlapping intervals before assigning.
- [x] Reject unavailable vehicles/drivers and insufficient vehicle capacity.
- [x] Implement schedule list and a usable calendar view.
- [x] Implement scheduled-trip cancellation and resource release.
- [x] Implement trip completion with an audit event.
- [x] Define and enforce rescheduling rules if rescheduling is exposed.
- [x] Test vehicle conflicts, driver conflicts, and cancelled-booking exclusion.
- [x] Test adjacent bookings, timezone conversion, and invalid time ranges.
- [x] Verify simultaneous booking attempts cannot double-book a resource.

Evidence: `composer ci:check` passed (Pint, PHPStan, 141 tests / 813 assertions).
Production Vite build passed. `AssignTrip` locks request → vehicle → driver in one
transaction, rechecks half-open overlaps for active bookings only, and rejects
unavailable, maintenance, missing-capacity, or conflicting resources with field
errors. `CompleteTrip` records the actual completion time in history and closes the
booking. Filament queues expose Schedule and Complete actions, offer only resources
that are free for the trip window, and reject a stale modal submission when another
staff member books first. Demo staff can only assign demo resources; created fleet
records inherit the creator's demo status. Vehicle and driver pages manage plates,
capacity, contact details, and availability. `Booking` + `BookingState` centralize
overlap queries. The Schedule page shows a month calendar plus a bookings list in
Manila time and filters demo bookings from demo staff. Rescheduling is deliberately
not exposed; cancel and resubmit is the supported path. SchedulingConcurrencyTest
starts a second PHP process, holds the vehicle row lock, and proves the concurrent
assignment waits and then loses as a conflict (one booking, loser unchanged).
Browser checks covered approvals, the schedule modal, calendar/list, completion,
requester assignment views, fleet pages, and the mobile calendar without JS errors.

## Phase 4 — Files, documents, and notifications

- [ ] Select private object-storage provider after reviewing free limits and billing requirements with the owner.
- [ ] Confirm permitted upload types and size limits.
- [ ] Configure approved private object storage and local development storage.
- [ ] Implement file-type/size validation and opaque storage paths.
- [ ] Implement authorized uploads and short-lived private downloads.
- [ ] Add attachment ownership and document categories to request screens.
- [ ] Generate vehicle-request and travel-order PDFs from the shared trip record.
- [ ] Include approver names/timestamps and demo-document labeling.
- [ ] Block arbitrary remote resources in PDF generation.
- [ ] Add in-app notifications for meaningful workflow changes.
- [ ] Obtain a new Brevo API key and verified sender in provider settings.
- [ ] Implement Brevo HTTPS delivery with timeout/error handling.
- [ ] Wire framework password-reset/verification links to the public APP_URL.
- [ ] Keep public demo actions from sending email to arbitrary recipients.
- [ ] Test unauthorized file access, disallowed types, and oversized uploads.
- [ ] Test generated PDFs and notification-recipient selection.
- [ ] Test mail failures without false success or lost approval decisions.
- [ ] Verify real inbox delivery and password reset with an owner-controlled account.

Evidence / blockers: Pending. Local legacy SMTP credentials are not v2 API credentials.

## Phase 5 — Portfolio UX and demo experience

- [ ] Build landing page: problem, features, stack, screenshots, demo entry, source link.
- [ ] Build clear requester dashboard with statuses and next actions.
- [ ] Build useful role-specific Filament dashboards and queues.
- [ ] Polish forms, timeline, schedule, empty states, and error messages.
- [ ] Verify responsive behavior on mobile and desktop.
- [ ] Verify keyboard navigation, labels, focus states, contrast, and validation feedback.
- [ ] Seed realistic fictional requests at every stage, including rejected/completed examples.
- [ ] Provide safe entry points for all seven demo roles.
- [ ] Enforce demo restrictions on password/email/role changes and account management.
- [ ] Rate-limit demo entry, request creation, and uploads; add modest data caps.
- [ ] Implement an owner-only CLI reset that touches demo-owned records only.
- [ ] Test demo restrictions by direct HTTP/Livewire requests.
- [ ] Document shared-demo behavior and fictional-data limitations.
- [ ] Capture screenshots and record a short walkthrough.
- [ ] Add screenshots/video and cold-start note to the owner's external portfolio.

Evidence / blockers: Pending.

## Phase 6 — Hosting and deployment

- [ ] Recheck Render, Neon, storage, and Brevo current free-plan limits.
- [ ] Create a private GitHub repository initially; inspect tracked files before publication.
- [ ] Create Neon PostgreSQL project and choose suitable region/connection endpoints.
- [ ] Configure private object-storage bucket and narrowly scoped credentials.
- [ ] Build production Docker image with compiled assets and production dependencies.
- [ ] Ensure Docker image contains no `.env`, personal files, or development database.
- [ ] Serve only Laravel's public directory with an appropriate PHP runtime.
- [ ] Configure PORT binding, health endpoint, runtime permissions, and stdout logging.
- [ ] Configure stable APP_KEY, APP_URL, production/debug settings, DB TLS, and sessions.
- [ ] Configure HTTPS proxy handling and secure cookies.
- [ ] Establish controlled migrations compatible with Render Free's restrictions.
- [ ] Deploy app to Render Free and confirm public HTTPS access.
- [ ] Seed hosted demo data explicitly; do not reset on every restart.
- [ ] Check every demo role and the full workflow against the deployed database.
- [ ] Verify private uploads and generated document downloads online.
- [ ] Verify Brevo API operation from Render with an owner-controlled recipient.
- [ ] Verify first visit after sleep and record observed cold-start behavior.
- [ ] Verify data and uploaded files survive redeployment.
- [ ] Verify `.env`, source/config secrets, and logs are not publicly downloadable.
- [ ] Verify demo reset leaves non-demo records intact.
- [ ] Document and test database export/restore using a disposable target.

Evidence / blockers: Pending. No v2 provider resources have been created.

## Phase 7 — Release and handoff

- [ ] Finish README with exact local installation commands and required versions.
- [ ] Document deployment, environment variables, migrations, backup, and demo reset.
- [ ] Document known limits, cold starts, and optional future enhancements.
- [ ] Document sample accounts without including real credentials.
- [ ] Run the final targeted test suite, formatting checks, and production build.
- [ ] Manually verify the full request-to-completion workflow online.
- [ ] Confirm all definition-of-done items in BUILD_GUIDE.md.
- [ ] Review Git diff/status for secrets and unintended files.
- [ ] Publish repository if desired and add final live/source/video portfolio links.
- [ ] Record release date, commit, deployed URL, and outstanding issues below.

## Deferred backlog

Only start these if requested after the core release:

- [ ] Driver attendance and attendance reports.
- [ ] Fuel, mileage, and trip-ticket records.
- [ ] Maintenance work orders and reminders.
- [ ] Department/campus-specific approval routing.
- [ ] Dynamic roles/permissions or multiple simultaneous roles per user.
- [ ] Dedicated queue worker and scheduled reminders on paid hosting.
- [ ] Advanced analytics and exports.

## Session handoff log

### October 19, 2026 — Planning handoff

- Completed: build guide and phased checklist.
- Application status: not scaffolded.
- Next action: confirm Phase 0 decisions, record compatible versions, begin Phase 1.
- Blockers: storage provider and external account setup are pending.
- Tests: documentation file references checked; no application exists to test yet.

### October 19, 2026 — Phase 0 started

- Session moved to `C:\Project Files\Vehiserve-v2`; inspected both handoff files.
- Workflow and demo defaults approved by owner.
- Branding: retain DHVSU logo, permit typography and icon changes.
- Target: PHP 8.4, Laravel 13, Filament 5, Livewire 4, PostgreSQL 18, Node 24 LTS.
- Open: hosted object-storage selection and public logo permission verification.
- Next: resolve storage preference or explicitly defer it to Phase 4, then scaffold
  the foundation and set up PostgreSQL in Phase 1 when requested.
- Checks: official requirements/package metadata and read-only local tool checks;
  no application tests or dependency installation yet.

### October 19, 2026 — Phase 0 handoff confirmed

- Re-read existing progress rather than restarting previously approved decisions.
- Rechecked official Laravel 13/Filament 5 requirements and Cloudflare R2 pricing.
- Owner chose to defer hosted file storage selection until Phase 4.
- Development will use private local storage; persistent hosted uploads remain pending.
- Phase 0 complete for development. Next: Phase 1 scaffold and PostgreSQL setup.
- No dependencies installed or external accounts created in this step.

### October 19, 2026 — Phase 0 checklist reconciled

- Read both documents and inspected the working directory; only the guide and checklist exist.
- Checked completed planning tasks against the prior confirmations and requirements
  review recorded in BUILD_GUIDE.md sections 2 and 4 and the handoff entries above.
- Left public logo permission and Phase 1 dependency/build verification unchecked.
- No new owner decisions, tool-version checks, or package installations performed.
- Next: Phase 1 foundation; hosted storage selection remains deferred to Phase 4.

### October 19, 2026 — Phase 0 relocation and source recheck

- Confirmed the new project is `C:\Project Files\Vehiserve-v2\vehiserve-v2`;
  legacy reference is `C:\Project Files\Vehiserve-v2\Prototype-Cap1`.
- Corrected current paths in both documents; earlier handoff paths are historical.
- Reviewed legacy vehicle/travel submission fields and vehicle approval handlers.
  Recorded terminology, additional travel-order fields to confirm in Phase 4, and
  the legacy global-latest-request ownership issue in the guide.
- Re-ran `php -v`, `composer --version`, `node --version`, `php -m`, PATH lookup,
  PostgreSQL service lookup, and standard installation-directory check.
  Existing tool findings were reproduced; Herd and Git are available.
- Re-fetched Packagist metadata for Laravel, Filament, Filament support, Livewire,
  and Dompdf integration; recorded versions and declared compatibility still match.
- Existing owner approvals and storage deferral remain in effect. Phase 0 is
  complete for development; next is Phase 1 in the corrected project directory.
- No scaffold, dependency installation, application tests, or provider setup run.
  Public logo permission and full Composer/build verification remain unchecked.

### October 19, 2026 — Phase 1 foundation verified

- Owner took responsibility for Git; agent made no commits or repository initialization.
- Installed the current official Laravel/Livewire scaffold and compatible Filament,
  Fortify, and Pest dependencies. Lockfiles record exact versions.
- Installed Node 24.22.0 and PostgreSQL 18.6 binaries; created separate local
  development/test databases and a non-superuser application role. Linked Herd.
- Added the domain schema, roles, demo flags, ownership and role policies, fictional
  seeds, shared authenticated workspaces, and server-side demo account protections.
- Fixed PostgreSQL session timezone after a conversion test exposed the machine's
  default timezone. Replaced Vite+ with standard Vite after npm audit flagged its
  transitive dependencies; the final dependency audits are clean.
- Verified 61 tests / 305 assertions, Pint, PHPStan, Composer validation, production
  assets, real Herd login, and desktop/mobile browser navigation without JS errors.
- Added README installation/startup/demo credentials and a PostgreSQL-backed CI workflow.
- A final in-place `npm ci` encountered Windows native-module locks from a running
  Vite server. Restored dependencies with `npm install` and rebuilt successfully;
  isolated `npm ci` from the same lockfile passed. Stop Vite before future clean installs.
- Next: Phase 2 request creation, ownership-scoped pages, and transactional approvals.
- Still external/unverified: GitHub CI run, hosted services, real inbox delivery,
  hosted storage selection, and public DHVSU logo permission.

### October 19, 2026 — Phase 2 requests and approvals verified

- Added Livewire request submission, ownership-scoped lists/details, readable states,
  a decision timeline, and Filament queues filtered to each approver's current stage.
- Added shared transactional submission/transition actions, row-lock and stale-state
  checks, actor-name snapshots, required rejection/cancellation reasons, and booking
  release when staff cancel an already scheduled trip.
- Preliminary availability checks capacity, operational status, and overlapping
  active bookings without reserving resources. Scheduling must recheck in Phase 3.
- Verified 112 tests / 675 assertions, formatting, static analysis, production assets,
  and the complete approval chain plus mobile cancellation in a real browser.
- Confirmed the reported PostgreSQL startup failure was a duplicate startup attempt;
  the existing server was accepting connections. README now includes a readiness check.
- Updated guide, checklist, and README. No agent commit made.
- Next: Phase 3 fleet management, vehicle/driver assignment, scheduling, and completion.

### October 19, 2026 — Phase 3 fleet and scheduling verified

- Added `Booking`/`BookingState`, `AssignTrip`, and `CompleteTrip`; scheduling is
  the only reservation path and every entry point shares it.
- Added Filament vehicle/driver management, Schedule/Complete queue actions, and a
  Schedule page with a month calendar and bookings list (Manila time). The mobile
  calendar scrolls horizontally instead of collapsing.
- Added 16 feature tests, a two-process double-booking concurrency test, and browser
  walkthrough checks (desktop + mobile). No schema changes were needed.
- Rescheduling is not exposed; cancel and resubmit is the supported path for now.
- Two existing demo requests were scheduled during the browser walkthrough; one was
  completed and one remains scheduled. A ready-to-schedule example also remains.
  No agent commit made.
- Next: Phase 4 files, documents, and notifications.

### October 19, 2026 — Phase 3 review fixes

- Fixed empty/malformed calendar month handling and converted month query bounds
  to UTC so early-morning Manila bookings appear in the correct month.
- Scoped demo fleet tables to demo records, including driver contact/license data;
  restored mass-assignment protection for fleet demo flags.
- Matched capacity validation to PostgreSQL's signed smallint limit (32767).
- Added regression tests, assignment/completion audit-failure rollback checks, and
  a second concurrency scenario with different vehicles sharing one driver.
- Full local gate passed: Pint, PHPStan, 141 tests / 813 assertions. Browser and
  asset-build checks were not repeated for these PHP-only changes.

## Release record

- Release date: pending
- Commit: pending
- Live URL: pending
- Repository URL: pending
- Walkthrough URL: pending
- Outstanding issues: pending implementation
