# Agent entry point

## Project overview

- Sifu is a multi-organization martial-arts / sports club ERP API.
- Composer requires Laravel `^13.0`, PHP `^8.3`; Docker uses PHP 8.3.
- Compose provides MySQL 8.0; `config/database.php` defaults to SQLite.
- Queue and cache default to database drivers; environment configuration can override them.
- Authentication uses custom bearer tokens, not Sanctum/Passport; rights come from groups.
- Integrations: SMSPortal, organization SMTP, configurable HTTP push, signed payment callbacks; optional ClamAV.
- PDFs use Dompdf; API documentation uses L5 Swagger. React/Vite frontend: separate `sifu-ui` repo.
- Tests use PHPUnit `^11.5.50`, mostly Laravel Feature tests with `RefreshDatabase`.

## Read only what the task needs

Start here, then use [AI_GUIDE](docs/AI_GUIDE.md) to select a domain and tests.
Search headings in `docs/project-rules-agent.md` and `docs/functionality-explainer-agent.md`;
read relevant sections, not both documents in full. Deployment/security work also needs
`docs/deployment-security.md`. Existing code and tests establish current behavior;
flag documentation disagreements. Update the behavior document and OpenAPI when contracts change.
Do not launch a subagent merely to reload these instructions.

## Repository map

- `app/{Users,Service,Events,CheckIns,Articles,CustomFields,Payments,Sms,Notifications,Campaigns,Reporting,Dashboard}`: existing business modules; preserve these names, including singular `Service`.
- Within a module: `Http/Controllers/Api` coordinates HTTP; `Http/Requests` validates; `Http/Resources` serializes; `Services` owns workflows/queries; `Models` owns persistence; `Jobs` runs background work; `OpenApi` documents contracts. Not every module needs every folder.
- `app/Users/Models/Concerns`, `Models/Scopes`, `Support`: tenant/location scoping, audit hooks, shared validation; `app/Users/Providers/AppServiceProvider.php`: listeners, limiters, scoped subscription service.
- `routes/api.php`: explicit module includes; `routes/*.php`: endpoints; `routes/console.php`: scheduler. Registration and middleware aliases: `bootstrap/app.php`.
- `app/Console/Commands`, `database/{migrations,seeders,factories}`: operations, schema history, seed data, test fixtures.
- `config`: environment-backed settings; `resources/views`: PDFs, account mail, Swagger; `public/index.php`: front controller.
- `tests/Feature`: endpoint/workflow behavior; `tests/Unit`: isolated checks; `docker`: runtime configuration.

## Where to make changes

- API: module route → controller → module FormRequest → service/model → Resource → matching Feature test.
- Database/query: module model/scopes → calling service → migrations → test; keep migration history intact.
- Validation: existing FormRequest and `Users/Support` first; retain non-HTTP invariants in services.
- External API: integration service listed in AI_GUIDE → caller/job → `config/services.php` → provider mocks.
- Async: module job → service → dispatch site / `routes/console.php` → `config/queue.php` → retries/failures.
- Authorization: route `right:*` → `RequireRight` → `User::hasAnyRight` / `OrganizationAccessService`; no established Policies layer.
- Payments/webhook: `routes/payment.php` → `PaymentController::callback` (raw-body HMAC before validation) → `PaymentService::processCallback` → receipt / service activation → payment tests.

## Architecture and invariants

- Keep controllers thin; extract substantial reusable workflows into cohesive module services or clearly named actions. Keep simple CRUD local. Do not mechanically introduce Actions, DTOs, interfaces, or Eloquent repositories.
- Keep HTTP validation in FormRequests where practical and existing response envelopes/pagination intact. Callback validation order is security-sensitive.
- Models retain relationships, casts, scopes, and persistence behavior; new unrelated workflows belong in services. Reuse focused helpers and existing status constants; avoid generic utility classes.
- Prefer constructor injection for services; isolate provider calls in cohesive services, with configuration under `config`. Avoid new service-locator dependencies or `env()` calls outside configuration. Bootstrap currently reads `TRUSTED_PROXIES` directly; changing bootstrap timing needs dedicated tests.
- Preserve tenant and location boundaries. The organization scope permits NULL/shared rows and is inactive without an authenticated organization; jobs/raw queries need explicit tenant constraints. `OrganizationScopedExistsRule` mirrors shared-row semantics, not strict ownership.
- `right:a,b` means either right. Preserve the `profile.view` fallback for users without group rights and organization-disabled rights.
- `service_user` is a lifecycle-bearing assignment with its own ID. Confirmed payments activate it only through `ServiceLifecycleService::activate`; preserve transactions, receipt numbering, after-commit audit/notifications, and callback idempotency.
- Notifications: `NotificationRequested` → `QueueNotificationDeliveries` → `SendNotificationDelivery`; stable `event_key` and consent checks matter. Mandatory account mail uses a separate flow.
- Preserve existing soft deletes, pivots, and organization-specific uniqueness. Do not assume email is globally unique or replace the dedicated password-setup token broker.

## Commands and context budget

```bash
docker compose up -d --build
docker compose exec -T app-sifu composer install
docker compose exec -T app-sifu php artisan test tests/Feature/ServiceLifecycleTest.php
docker compose exec -T app-sifu composer test
docker compose exec -T app-sifu vendor/bin/pint --test path/to/changed.php
```

Host equivalents work with PHP/dependencies installed. `composer test` clears config
then runs tests. SQLite in-memory, sync queue, array mail/cache/session are set in
`phpunit.xml`; no external services are needed for normal tests. The opt-in MySQL
concurrency test requires an isolated server; see AI_GUIDE. Test the affected behavior
first; broaden for shared infrastructure, schema, auth, or payment changes. Pint exists;
no configured PHPStan/Larastan/Psalm or Composer lint/analyse scripts were found.

Use `rg` on the selected module/tests. Skip `vendor`, `node_modules`, `storage`,
`bootstrap/cache`, `public/build`, coverage and generated Swagger output unless relevant
to debugging. Read lockfiles only for dependency questions; historical prompts/specs in
`docs` describe proposals, not necessarily implemented behavior. Never print secrets.
