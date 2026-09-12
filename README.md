# Sifu API

Multi-organization Laravel backend for a martial-arts / sports club ERP: members,
services, events, attendance, payments, communications, and reporting. The React/Vite
frontend lives in the separate `sifu-ui` repository; local Vite assets are minimal.

## Start here

- [AGENTS.md](AGENTS.md): compact architecture rules and working commands.
- [AI task guide](docs/AI_GUIDE.md): domain, entry point, integration, and test lookup.
- [Business behavior](docs/functionality-explainer-agent.md): search for the relevant feature.
- [Detailed conventions](docs/project-rules-agent.md): consult relevant sections.
- [Production security](docs/deployment-security.md): deployment requirements.

## Local development

Requires PHP `^8.3` and Laravel `^13.0` (Composer constraints). Docker supplies
PHP 8.3, Composer, SQLite support, MySQL 8.0, and nginx.

```bash
docker compose up -d --build
docker compose exec -T app-sifu composer install
docker compose exec -T app-sifu php artisan migrate
docker compose exec -T app-sifu php artisan test
```

The container entrypoint creates `.env` from `.env.example` when absent and generates
an application key when missing. Configure database access in your local `.env`
before migrating: MySQL inside Compose uses host `db`, port `3306`. Configuration
otherwise defaults to SQLite. nginx exposes the API on port `8090`.
`queue-sifu` runs the queue worker; run the scheduler separately with
`docker compose exec -T app-sifu php artisan schedule:work` in development.
See the AI guide for test setup and formatting commands.

Local asset commands are `npm install`, `npm run dev`, and `npm run build`.
`composer dev` requires host PHP and Node and starts the server, queue listener,
log viewer, and Vite. Avoid starting duplicate workers unintentionally.

## Multi-organization support

The API now includes an organization layer for tenant isolation:

- Core domain tables include an `organization_id` foreign key.
- Records are automatically assigned to the authenticated user's organization on create when `organization_id` is not explicitly provided.
- This behavior is centralized in `App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser`.

To apply the schema changes, run:

```bash
php artisan migrate
```

## Organization subscriptions

Organizations inherit Start (30 EUR/month), Plus (60 EUR/month), or Pro (100 EUR/month), with database-backed limits for active members, locations, monthly event occurrences and active administrators. Configure subscriptions only through CLI or SQL; API clients can read usage and check capacity.

```bash
php artisan organization:subscription:show 12
php artisan organization:subscription:set 12 --plan=plus
php artisan organization:subscription:set 12 --members=200 --administrators=unlimited
php artisan organization:subscription:set 12 --reset=members
php artisan create:organisation --plan=plus
```

Plan changes preserve overrides. `--reset-all` returns every limit to plan inheritance. SQL `NULL` means unlimited; an absent override means inheritance. Example for changing a plan while preserving overrides:

```sql
START TRANSACTION;
SELECT id FROM organizations WHERE id = 12 FOR UPDATE;
UPDATE organizations SET plan_id = (SELECT id FROM organization_plans WHERE code = 'plus') WHERE id = 12;
DELETE FROM organization_limit_overrides WHERE organization_id = 12 AND resource = 'members';
INSERT INTO organization_limit_overrides (organization_id, resource, value, created_at, updated_at)
VALUES (12, 'members', 200, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
COMMIT;
```

Remove the override row to inherit the plan again. Direct SQL bypasses application audit; use the documented organization locking order for concurrent writes. Existing over-limit data is retained; only quota-increasing operations are blocked with HTTP 409. Pricing is informational, without subscription billing.

Authenticated APIs (right: `organization_subscription.view`): `GET /api/organization/subscription?month=2026-09` and `POST /api/organization/subscription/check` with `{"resource":"members","quantity":1}`. Event checks also require `month`. Checks do not reserve capacity. See [functional documentation](docs/functionality-explainer-agent.md#abonamente-și-limite-pentru-organizații) for counting rules, SQL examples, error contracts, migration and recurrence-block handling. Regenerate Swagger with `php artisan l5-swagger:generate` after deployment.
