# AI navigation audit — 2026-09-12

## Scope and decision

Reviewed manifests, file inventory and sizes across app/routes/config/database/resources/
tests/bootstrap/public, existing agent/business documentation, and Docker configuration.
Inspected controller hotspots, model scopes, provider calls, validation sites, service
resolution, listener registration, scheduling, and test setup. This is a structural
review, not proof of production route usage or a complete dynamic dependency analysis.

Retain the existing 12 domain modules and explicit route includes. There are no
500+ line controllers or giant route files: the largest route file is `routes/user.php`
(133 lines). No generic Helper/Manager/Utils layer or repository/DTO hierarchy was
found that warrants reorganization. Existing services generally have cohesive names;
`EventParticipantEligibilityResult` already provides a focused typed result.

The implemented optimization is documentation-only: a compact shared entry point,
a task-to-code/test index, a project README, consistent Claude loading instructions,
and corrected Docker service names. No runtime, migration, dependency, route, API,
authorization, payment, or queue behavior changed.

## Concrete follow-up candidates

Sizes below are the baseline at review time, not thresholds requiring extraction.

| Finding | Evidence / likely next step | Required protection |
| --- | --- | --- |
| Participant workflow inside controller | `EventParticipantController` (441 lines, including substantial OpenAPI attributes): `bulkStore` and `applyToFutureOccurrences` coordinate eligibility, capacity, access consumption, persistence and per-user outcomes. Extract a cohesive bulk-registration workflow when modifying this feature. | `EventParticipantCrudTest`, `Events/EventParticipantBulkFutureTest`, `CheckInApiTest`; preserve atomic target vs best-effort future behavior |
| Assignment lifecycle inside user controller | `UserController` (431 lines): service synchronization, initial status and expiry calculations alongside member CRUD. A service-assignment operation is a clearer boundary than splitting every controller method. | `UserCrudTest`, `ServiceLifecycleTest`, `PaymentLifecycleTest`; document numbering, quota and audit effects |
| Model read with writes | `User` (230 lines): `activeServices()` refreshes assignments through a resolved lifecycle service. `Article` (128 lines) resolves `SegmentService` inside a scope. | Documented in AI_GUIDE; characterize query/lifecycle behavior before extraction |
| Repeated status values / validation | Participant status lists occur in three FormRequests, controller branches, `EventOccurrence::activeParticipants`, and PDF counts. A module status constant/enum could centralize the contract. | Preserve accepted values, active subset, errors and serialization; payment/audit constants already exist and should be reused |
| Similar queries | Eligibility and active-service/date queries span check-ins, participants, articles, segments and reports. `SegmentService::members` and `EventParticipantService` already share important logic. | Similar text does not prove equivalent business meaning; compare status/date/tenant semantics before consolidating |
| Inline validation | Auth, dashboard, GDPR, notification preferences, campaign scheduling, service operations and payment callback still validate inline. | Extract substantial HTTP contracts when touched; small one-off rules can stay. Callback must authenticate raw bytes before validating JSON |
| Response transformations | Model APIs mostly use Resources; reporting/dashboard and bulk results use purpose-specific arrays. | No broad duplicate payload contract established; avoid wrapping every aggregate in a Resource/DTO |
| Authorization repetition | Explicit tenant ownership checks coexist with group-right middleware and scopes. | These may protect different boundaries; do not remove as duplicate checks. Reuse `OrganizationScopedExistsRule` only where shared NULL rows are intended |
| Service location dependencies | `app(...)` resolution in model scopes, custom-field resources, notification sending, recurrence quota guards and demo reset. | Prefer injection for future service changes; do not blindly inject services into Eloquent models |
| Provider boundaries | SMS has `SmsPortalService`; mail has `OrganizationMailerService`; push transport is inside the small `NotificationSender` (84 lines). Payment controller contains signature verification, not a scattered SDK integration. | Keep boundaries; extract push transport only if it grows. Retries are partly job-owned, so do not centralize retries without considering duplicate delivery |
| Environment access | No direct `env()` calls found under `app`; `bootstrap/app.php` reads `TRUSTED_PROXIES`. | Leave bootstrap unchanged here; a config-cache fix requires testing initialization timing and proxy/security behavior |
| Scheduled overlap | Generic service lifecycle notifications and legacy expiration SMS are both scheduled. | Existing documentation notes this; resolve intended audiences/idempotency before changing sends |
| Larger cohesive query service | `FinancialReportService` (320 lines) owns aggregate/detail/receivable queries and database-specific period expressions. | Keep discoverable under Reporting; split only along independently reusable report responsibilities |

## Unproven / deliberately retained

- No circular service dependency was established from inspected injection and service-resolution sites. Container lookups, Eloquent hooks and runtime dispatch mean this is not a proof of absence.
- No route/class was proven unused. Search absence is insufficient with Laravel conventions, jobs, reflection and external frontend clients; deleting routes needs consumer evidence.
- Framework example tests, welcome page and `inspire` command are recognizable defaults, not automatically dead code. They were retained.
- No duplicated DTO architecture or large generic utility class was found. Shared validation helpers are already small and focused.
- `OpenApi` files, migrations, seeders and historical specifications can be large; they are now excluded from default reading by task guidance, not hidden from source search.

## Validation

Documentation checks: local Markdown link targets and literal source paths, Compose
service names via `docker compose config --services`, and `git diff --check`.
Application tests were not run because no runtime files changed. Host PHP is unavailable
in this workspace; documented Docker commands use the actual `app-sifu` service.
No static-analysis configuration was found; consider a scoped PHPStan/Larastan baseline
in separate work if ongoing refactors justify it. No tools were installed.
