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
- [ ] Verify full Composer resolution and scaffold asset-tool compatibility during Phase 1.
- [x] Record target versions, local tool findings, and approved decisions in the build guide.

Evidence / blockers: Owner approved workflow defaults and Render + Neon; requested
DHVSU logo retention with new typography/icons. Reviewed official requirements and
Packagist metadata for Laravel 13.37.0, Filament 5.10.2, Livewire 4.4.7, and Dompdf
integration 3.1.2. Local PHP 8.4.26 and Composer 2.10.3 are available with required
intl/pdo_pgsql extensions. Node is 26.11.1; target Node 24 LTS. PostgreSQL was not
found on PATH, in its standard installation directory, or as a Windows service.
Owner explicitly deferred hosted storage selection to Phase 4. No installation
performed. Phase 0 is complete for starting development; dependency resolution
belongs to Phase 1, storage selection to Phase 4, and logo permission to publication.

## Phase 1 — Project foundation

- [ ] Initialize a separate Git repository without copying legacy secrets or data.
- [ ] Scaffold Laravel while preserving the handoff documents.
- [ ] Install compatible Filament and the chosen Livewire authentication scaffolding.
- [ ] Select Node 24 LTS for the project and verify Vite engine requirements.
- [ ] Verify full Composer dependency resolution and record exact lockfile versions.
- [ ] Link a distinct Herd domain, such as `vehiserve-v2.test`.
- [ ] Install/configure local PostgreSQL and create development/test databases.
- [ ] Add placeholder-only `.env.example` and appropriate `.gitignore`/`.dockerignore` rules.
- [ ] Set timezone handling: store UTC, display/input Asia/Manila.
- [ ] Implement users, fixed roles, and `is_demo` flags.
- [ ] Configure shared authentication, password reset, sessions, and panel access.
- [ ] Add policies for requester ownership and role-restricted actions.
- [ ] Add domain migrations with foreign keys and relevant indexes.
- [ ] Create initial fictional users, vehicles, and drivers.
- [ ] Establish Pest and PostgreSQL-backed feature tests.
- [ ] Add CI checks for tests, formatting, and asset build using the chosen versions.
- [ ] Verify a fresh local install, migrations, login, and production asset build.

Evidence / blockers: Pending.

## Phase 2 — Trip requests and approvals

- [ ] Implement request form: purpose, destination, office, dates/times, passengers.
- [ ] Validate date ordering, passenger input, and required fields server-side.
- [ ] Implement requester list/detail screens scoped to ownership.
- [ ] Implement stable workflow states with readable labels.
- [ ] Create one shared transactional action for state transitions and audit events.
- [ ] Implement Motor Pool preliminary availability check.
- [ ] Implement Dean approval/rejection queue.
- [ ] Implement VP approval/rejection queue.
- [ ] Implement President approval/rejection queue.
- [ ] Implement Motor Pool final approval and ready-to-schedule state.
- [ ] Implement cancellation rules and required rejection/cancellation reasons.
- [ ] Display the complete approval timeline.
- [ ] Prevent editing approved request details and repeated/skipped approvals.
- [ ] Test the happy path from submission through final approval.
- [ ] Test ownership violations and every role's forbidden actions.
- [ ] Test stale/concurrent decisions cannot overwrite newer state.
- [ ] Test cancellation and rejection at their permitted stages.

Evidence / blockers: Pending.

## Phase 3 — Fleet and scheduling

- [ ] Implement vehicle management with unique plates and passenger capacity.
- [ ] Implement driver management and operational availability.
- [ ] Implement booking assignments through one shared transaction path.
- [ ] Lock/revalidate resources and check overlapping intervals before assigning.
- [ ] Reject unavailable vehicles/drivers and insufficient vehicle capacity.
- [ ] Implement schedule list and a usable calendar view.
- [ ] Implement scheduled-trip cancellation and resource release.
- [ ] Implement trip completion with an audit event.
- [ ] Define and enforce rescheduling rules if rescheduling is exposed.
- [ ] Test vehicle conflicts, driver conflicts, and cancelled-booking exclusion.
- [ ] Test adjacent bookings, timezone conversion, and invalid time ranges.
- [ ] Verify simultaneous booking attempts cannot double-book a resource.

Evidence / blockers: Pending.

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

## Release record

- Release date: pending
- Commit: pending
- Live URL: pending
- Repository URL: pending
- Walkthrough URL: pending
- Outstanding issues: pending implementation
