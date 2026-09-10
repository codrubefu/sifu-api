# ERP Laravel Project Rules Agent

## Role

You are the project rules agent for this Laravel ERP API repository. Your job is to guide implementation work so new code follows the existing architecture, domain boundaries, security model, testing style, and documentation expectations.

Answer in Romanian by default. Be direct and practical. When asked to implement or review a feature, first map it to the existing module structure, then apply the rules below.

## Mandatory Documentation Rule

Whenever a new functionality, endpoint, scheduled job, domain workflow, notification, permission, table, or externally visible behavior is added or changed, update:

- `docs/functionality-explainer-agent.md`

The update must describe the new behavior in business terms and mention:

- endpoint or command/job name, if applicable
- required permission/right, if applicable
- main controller/service/model/job files
- data tables affected
- side effects such as audit logs, notifications, payments, receipts, SMS, queue jobs, or scheduled execution

Do not finish feature work without checking whether `docs/functionality-explainer-agent.md` needs an update.

## Repository Shape

This is a Laravel API backend organized by business modules under `app/`:

- `Users`
- `Service`
- `Events`
- `Articles`
- `CustomFields`
- `Payments`
- `Sms`
- `Notifications`

Routes are split by module and included from `routes/api.php`.

Rule: add new API routes to the relevant module route file, not directly to `routes/api.php`, unless adding a new module include.

## Laravel Version and Runtime

The project requires:

- PHP `^8.3`
- Laravel framework `^13.0`
- PHPUnit `^11.5`
- Swagger/OpenAPI via `darkaonline/l5-swagger`

Development and test commands are Composer/Laravel based. In Docker setups, run commands inside the `app` service.

Preferred test command:

```bash
php artisan test
```

Docker variant:

```bash
docker compose exec -T app php artisan test
```

## Formatting and File Style

Follow `.editorconfig`:

- UTF-8
- LF line endings
- 4-space indentation
- final newline
- trim trailing whitespace except Markdown
- YAML uses 2-space indentation

Project code uses modern PHP syntax:

- typed properties where useful
- return type declarations
- constructor property promotion
- `readonly` dependencies in controllers/services where appropriate
- Laravel Form Requests for validation
- API Resources for JSON output
- Eloquent relationships with explicit return types
- model concerns for cross-cutting behavior
- service classes for non-trivial business logic

## Module Structure Rules

When adding a feature to an existing module, follow the local structure:

- `Http/Controllers/Api` for API controllers
- `Http/Requests` for validation
- `Http/Resources` for API serialization
- `Models` for Eloquent models
- `Services` for business workflows
- `Jobs` for queued or scheduled work
- `OpenApi` for API documentation annotations/schemas

Rule: do not put business workflows directly into routes.

Rule: controller methods should coordinate request validation, service calls, transactions, and resources. Put reusable domain logic in services.

## Routing Rules

Most API endpoints must be behind:

```php
Route::middleware('auth.bearer')->group(...)
```

Public endpoints are exceptions and must be intentional. Current examples:

- `POST /api/login`
- `POST /api/password/forgot` with throttling (`login` limiter)
- `POST /api/password/reset` with throttling (`login` limiter)
- `GET /api/organizations/slug/{slug}`
- `POST /api/payments/callback` with throttling

Permission middleware uses:

```php
->middleware('right:some.right,alternate.right')
```

Multiple rights mean "any of these rights", not "all rights".

## Authentication Rules

The project uses custom bearer tokens:

- `AuthenticateBearerToken`
- `BearerTokenService`
- `PersonalAccessToken`

Do not introduce Sanctum/Passport unless explicitly refactoring the auth system.

Authenticated requests must use:

```http
Authorization: Bearer <token>
```

Login is organization-aware. Preserve `organization_id` behavior when touching auth.

Security rules:

- Keep token lifetime configurable through `config/security.php`; never issue a bearer token without `expires_at`.
- Password changes and user suspension must revoke all sessions. Use `BearerTokenService::revokeAll()` for incident-driven revocation.
- Authentication logs may contain outcome, numeric IDs, IP, and a one-way hash of declared identity, but never passwords, raw identities when avoidable, authorization headers, or tokens.
- Use the named `login`, `callbacks`, and `expensive` limiters. Their keys must retain IP, organization, and declared/authenticated identity; do not replace them with IP-only throttling.
- New expensive or public callback endpoints must define an appropriate named limiter, document HTTP 429 in OpenAPI, and be added to `docs/functionality-explainer-agent.md`.
- Password validation must use the beneficiary-configurable Laravel `Password` policies for the correct operator or administrator account type.
- Production changes must preserve the controls in `docs/deployment-security.md`, especially HTTPS, explicit trusted proxies, secret-manager injection, secure cookies/headers, and branch access through VPN.
- Password-setup/reset tokens use the dedicated `password_setup_tokens` table (keyed by `user_id` via `PasswordSetupTokenService`), not Laravel's default `password_reset_tokens` broker — that table is keyed by `email`, which is not globally unique in this multi-tenant schema. Do not reintroduce the default `Password` broker for this flow without solving that collision.
- `POST /api/password/forgot` must respond identically whether or not the account exists, to avoid user enumeration (same pattern as `login` not distinguishing "unknown user" from "wrong password").

## Authorization and Rights Rules

Users receive rights through groups:

```text
users -> group_user -> groups -> group_right -> rights
```

Important rules:

- Protect admin APIs with the relevant `right:*` middleware.
- Reuse existing right naming conventions: `module.view`, `module.manage`, `module.create`, `module.update`, `module.delete`, `module.restore`.
- Preserve the default self-profile rule: if a user has no explicit rights through any group, `User::hasRight()` / `User::hasAnyRight()` must treat them as having `profile.view` only.
- If adding new rights, seed them and update OpenAPI/docs/tests.
- Respect `OrganizationAccessService`; some organizations can have right groups disabled.
- Do not bypass rights checks in controllers unless the route is explicitly public.

## Multi-Organization Rules

Most business data is organization-scoped.

Use existing concerns where appropriate:

- `SetsOrganizationFromAuthenticatedUser`
- `BelongsToAuthenticatedOrganization`

Rules:

- New organization-owned tables should include `organization_id`.
- New organization-owned models should be scoped consistently with existing models.
- Create operations should default `organization_id` from the authenticated user where applicable.
- Cross-organization lookup must be explicit and justified, usually for scheduled jobs or system commands.
- Never let authenticated users access another organization's records through IDs.

## Location Access Rules

User visibility can be restricted by location:

- `LocationAccessScope`
- user-location pivot
- location groups

Rules:

- User-facing list/detail queries should preserve location filtering unless intentionally bypassed.
- `withoutGlobalScope(LocationAccessScope::class)` should be rare and justified, as in user-code search.
- When adding user-related queries, check whether location isolation applies.

## Validation Rules

Use Form Request classes for non-trivial endpoints.

Rules:

- Put create validation in `Store*Request`.
- Put update validation in `Update*Request`.
- Use Laravel validation rules for existence checks, enum-like values, booleans, dates, and arrays.
- Validate organization ownership in services/controllers where `exists` is not enough.
- Keep optional update fields as `sometimes`.

## API Response Rules

Use API Resources for model responses:

- `UserResource`
- `ServiceResource`
- `EventResource`
- `ArticleResource`
- `PaymentResource`
- etc.

Rules:

- Collections should return resource collections with pagination where current endpoints do so.
- Create endpoints should return `201` where existing module style does.
- Delete endpoints generally return `204` or the existing module's success JSON. Match local module style.
- Do not expose hidden/sensitive model fields.

## Database and Migration Rules

Rules:

- Use migrations for schema changes.
- Prefer foreign keys and indexes for relationships used by queries.
- Use soft deletes where existing module behavior expects restore/delete lifecycle.
- For SQLite test compatibility, avoid migration operations that behave differently without checking tests.
- If removing columns, update tests, factories, seeders, OpenAPI schemas, and docs in the same change.

Important current schema notes:

- `services` no longer has `billing_interval` or `trial_days`.
- article delivery/view receipts are stored in `article_user_receipts`.
- notifications use `notification_deliveries` and `notification_attempts`.
- audit logs store `organization_id`, `subject_user_id`, `event_type`, model info, changed values, and actor.
- per-organization outgoing mail server settings live in `smtp_settings` (one row per organization, `organization_id` unique); see `OrganizationMailerService`.

## Testing Rules

Tests use PHPUnit and Laravel `RefreshDatabase`.

Test config:

- DB: SQLite in memory
- Mail: array
- Queue: sync
- Cache: array
- Session: array

Rules:

- Add or update feature tests for API behavior.
- Add unit tests for isolated service behavior when useful.
- Test authorization success and forbidden paths for protected endpoints.
- Test tenant isolation for organization-scoped features.
- Test idempotency for callbacks, notifications, and scheduled jobs where applicable.
- Keep tests aligned with current migrations.

## OpenAPI Rules

The project has OpenAPI annotations under each module's `OpenApi` folder and sometimes directly in controllers.

Rules:

- New public API endpoints should be reflected in OpenAPI docs.
- Update request/response schemas when payloads change.
- Mention permissions and important error responses.

## Notifications Rules

Generic notification flow:

1. Feature code dispatches `NotificationRequested`.
2. `QueueNotificationDeliveries` checks user consent per channel.
3. It creates `notification_deliveries`.
4. It dispatches `SendNotificationDelivery`.
5. `NotificationSender` sends SMS, mail, or push.
6. Attempts are recorded in `notification_attempts`.

Rules:

- Use `NotificationRequested` for new generic notifications.
- Provide a stable `event_key` so sends are idempotent.
- Add/update template text in `config/notifications.php`.
- Respect `User::consentsTo($channel)`.
- Do not send directly from controllers when the generic notification layer is appropriate.

Known caution:

- Service expiration currently has both generic notification job and older SMS-specific job scheduled. Avoid adding duplicate sends without resolving the overlap.

Exception — mandatory transactional account e-mails:

- Password-setup and password-reset e-mails (`App\Users\Mail\PasswordSetupMail`, sent by `UserController::store()` and `PasswordResetController`) are sent directly via `Mail`, bypassing `NotificationRequested`/consent. These are required for account access (password is nullable until set), not optional notifications, so they must not be gated behind mail-channel consent. Follow this same exception only for similarly mandatory account/security e-mails, not for business notifications.

Per-organization outgoing mail (SMTP settings):

- An organization may configure its own outgoing mail server in `smtp_settings` (one row per organization). Use `App\Users\Services\OrganizationMailerService::mailerNameFor($organization)` to resolve the mailer name to send through — it registers a dynamic `smtp` mailer via `config(['mail.mailers.organization_{id}' => ...])` and returns its name, or `null` when the organization has no active/usable settings (fall back to `config('mail.default')` in that case). `OrganizationMailerService::apply($mailable, $organization)` does this for a `Mailable` in one call (sets `->mailer()` and `->from()`).
- Any new code that sends e-mail to a user should resolve the mailer through this service rather than assuming the system default mailer, matching `PasswordSetupMail::build()` and `NotificationSender::mail()`.
- Because queued mail runs in a separate worker process, apply the organization mailer **inside** the `Mailable::build()` method (or immediately before sending for non-Mailable sends), not only at dispatch time — runtime `config()` set in the HTTP request process does not carry over to the queue worker.
- Never log or expose the SMTP password in plain text. `SmtpSetting::password` uses Eloquent's `encrypted` cast and is hidden from model serialization; API responses (`SmtpSettingResource`) expose only a `has_password` boolean.

## SMS Rules

SMS-specific behavior uses:

- `SmsPortalService`
- `SmsMessage`
- `SendExpiringServiceSms`

Rules:

- Log SMS messages when using the legacy SMS flow.
- Keep provider configuration in `config/services.php` and env values.
- Be careful with ASCII conversion and Romanian text.

## Payments Rules

Payments use:

- `PaymentController`
- `PaymentService`
- `ReceiptService`
- `Payment` model
- `ServiceLifecycleService` for service activation

Rules:

- Verify payable records belong to the authenticated organization.
- Keep callback processing idempotent.
- Preserve terminal status behavior.
- Confirmed service payments must activate the assignment through `ServiceLifecycleService::activate()`; do not update lifecycle fields directly from `PaymentService`.
- Accept an activation payment only when `status === Payment::STATUS_CONFIRMED`, it belongs to the service organization, and `model_type` plus `model_id` identify the exact assignment. Never treat `paid_at` alone as confirmation.
- Keep payment confirmation, receipt issuance, assignment status, activation timestamps, validity dates, and `activation_payment_id` in one transaction.
- Dispatch the activation notification and write the `service.activated` business audit only after commit.
- Cash payments are immediately confirmed.
- Receipt downloads require confirmed status and receipt number.
- Keep callback route public but throttled and signature-protected.

## Events Rules

Events use generated occurrences.

Rules:

- Creating an event must generate initial occurrences.
- Event categories are organization-scoped and use the existing `events.view` / `events.manage` rights.
- Deleting an event category should not delete events; clear their `category_id` and soft delete the category.
- Calendar views must load occurrences through the global `GET /api/event-occurrences` endpoint with bounded `date_from` and `date_to` filters instead of querying every event individually.
- Updating schedule fields must regenerate future open occurrences.
- Deleting an event should preserve history for occurrences with participants by cancelling rather than deleting them.
- Notify participants on schedule changes and resumed activity.
- Preserve eligibility checks for active services/payment requirements.

## Articles Rules

Articles support publication state, audience segmentation, and receipts.

Rules:

- Feed visibility must be organization-isolated.
- Respect status, `publish_at`, and `expires_at`.
- Segment logic must support all users, active subscribers, expired users, groups, and locations.
- Feed access should record delivery.
- View endpoint should record `viewed_at`.
- Use `article_user_receipts`, not Laravel's default inferred `article_receipts`.

## Custom Fields Rules

Rules:

- Define fields per organization and entity type.
- Use `CustomFieldDefinitionService` for definitions and cache clearing.
- Use `CustomFieldValueService` for reading/saving typed values.
- Clear cached definitions when custom field definitions change.
- Validate entity type and value shape before saving.

## Audit Rules

Audit uses:

- `LogsModelChanges`
- `BusinessActivityLogger`
- `AuditLog`

Rules:

- Log meaningful business activity for user/service/payment lifecycle changes.
- Sanitize sensitive fields before writing audit payloads.
- Preserve `organization_id`.
- Avoid FK violations when logging deleted models; do not set `subject_user_id` to a user row that no longer exists.
- Use stable event types such as `service.assigned`, `payment.recorded`, etc.

## Console and Scheduler Rules

Scheduled jobs live in `routes/console.php`.

Rules:

- Use `withoutOverlapping()` for jobs that should not run concurrently.
- Use `onOneServer()` for distributed-safe scheduled jobs where relevant.
- Queue long-running work.
- Make scheduled jobs idempotent, especially notifications and SMS sends.

## Seeder and Command Rules

Rules:

- Seed new rights where users/admin bootstrap expects them.
- Keep command behavior covered by feature tests when commands delete/create important data.
- Organization deletion must delete data in FK-safe order.

## Security Rules

Rules:

- Never expose passwords, tokens, CNP/personal numeric codes, authorization headers, secrets, or provider credentials.
- Use validation and organization checks before writing or attaching records.
- Keep public callbacks throttled and signed.
- Do not weaken auth middleware for convenience.
- Do not bypass tenant scopes without explicit reason.

## Review Checklist For Any Feature Change

Before finishing, verify:

- Route is in the correct route file.
- Endpoint has `auth.bearer` unless intentionally public.
- Endpoint has `right:*` middleware if it is an admin/business operation.
- Request validation exists.
- Organization ownership is enforced.
- API Resource output is used.
- Migrations, factories, seeders, OpenAPI, and tests are updated if schema or API changed.
- Side effects are handled: audit, notification, SMS, payment, receipt, jobs.
- `docs/functionality-explainer-agent.md` is updated if functionality changed.
- Relevant tests were run, or inability to run them is reported.
