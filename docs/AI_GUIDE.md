# AI task guide

Read [AGENTS.md](../AGENTS.md) first. This is a navigation index, not a second business
specification. Paths below are relative to the repository root. Service/controller
names belong to the indicated module unless qualified. Search a symbol before reading
its entire file, especially controllers with inline OpenAPI attributes.

## Change an API endpoint

1. Find its route in the table below; `bootstrap/app.php` registers `routes/api.php` with the `/api` prefix.
2. Read the controller method, its FormRequest and Resource, then the called service.
3. Inspect model scopes/relationships and only relevant migrations when persistence matters.
4. Read the matching Feature test and the relevant behavior/rules document section.
5. Preserve authorization, tenant constraints, response shape and side effects; update tests and OpenAPI for contract changes.

## Domain lookup

Test names below are in `tests/Feature` unless otherwise marked; use `rg --files tests`
to find adjacent cases. Do not load every test in a row by default.

| Task / module | Route file | Business logic and persistence | Starting tests |
| --- | --- | --- | --- |
| Members, groups, locations / `Users` | `routes/user.php` | `UserController`, `User`, `OrganizationAccessService`; FormRequests/Resources within `Users/Http` | `UserCrudTest`, `GroupCrudTest`, `LocationGroupRightsTest` |
| Login / password setup / `Users` | `routes/user.php` | `AuthController`, `PasswordResetController`, `BearerTokenService`, `PasswordSetupTokenService`, `PasswordSetupMail` | `BearerTokenAuthTest`, `PasswordResetTest`, `ApiSecurityTest` |
| Tenant quotas / `Users` | `routes/user.php` | `OrganizationSubscriptionService`, `EnforceOrganizationLimits`, organization plans/overrides; CLI in `app/Console/Commands` | `OrganizationSubscriptionTest`, `OrganizationSubscriptionMysqlTest` |
| Documents / privacy / `Users` | `routes/user.php` | `UserDocumentController`, `GdprController`, `AntivirusScanner`, `GdprErasureService`, `GeneratePersonalDataExport` | `UserDocumentTest`, `GdprWorkflowTest` |
| Membership services / `Service` | `routes/service.php`, assignment endpoints in `routes/user.php` | `ServiceController`, `Users/UserController` (under `Http/Controllers/Api`), `ServiceLifecycleService`, `ServiceUser`, document services | `ServiceCrudTest`, `ServiceLifecycleTest`, `PaymentLifecycleTest` |
| Calendar / `Events` | `routes/event.php` | `EventController`, `EventOccurrenceController`, `EventOccurrenceGeneratorService`, `Event`, `EventOccurrence` | `Events/EventCrudTest`, `Events/EventOccurrenceRollingWindowTest`, `Events/EventOccurrenceCancelTest` |
| Participation / `Events` | `routes/event.php` | `EventParticipantController`, `EventParticipantService`, `EventEligibilityService`, `ServiceLifecycleService` in `Service` | `EventParticipantCrudTest`, `Events/EventParticipantBulkFutureTest`, `Events/EventOrganizationScopedValidationTest` |
| Reception / `CheckIns` | `routes/event.php` | `CheckInController` → `CheckInService` → event eligibility + service access | `CheckInApiTest` |
| Payments / `Payments` | `routes/payment.php` | `PaymentService`, `ReceiptService`, `Payment`; `resources/views/payments` | `PaymentApiTest`, `PaymentLifecycleTest` |
| Announcements / `Articles` | `routes/article.php` | `ArticleController`, `Article::visibleTo`, `ArticleReceipt`, publication job; `Reporting/Services/SegmentService` | `ArticleSegmentationTest` |
| Campaigns / preferences / `Campaigns`, `Notifications` | `routes/campaign.php` | `CampaignService`, `DispatchCampaign`, `NotificationPreferenceController`, delivery pipeline below | `CampaignAudienceTest`, `EventNotificationLayerTest` |
| SMS / `Sms` | `routes/sms.php` | `SmsPortalService`, `SmsMessage`; legacy job in `Service/Jobs` | `SmsMessageIndexTest`, `SendExpiringServiceSmsTest`, `tests/Unit/SmsPortalServiceTest` |
| Custom fields / `CustomFields` | `routes/custom-fields.php` | `CustomFieldDefinitionService`, `CustomFieldValueService`, `HasCustomFieldValues`; definition cache invalidation | `CustomFieldApiTest` |
| Reports / `Reporting` | `routes/reporting.php` | Named report services, `SegmentService`, `GenerateReportExport`; `docs/reporting/member-lifecycle.md` | `FinancialReportingTest`, `AttendanceReportingTest`, `EventParticipationReportingTest` |
| Dashboard / `Dashboard` | `routes/dashboard.php` | `DashboardService` | `DashboardTest` |
| Demo reset / organization operations | `app/Console/Commands` | `DemoOrganizationResetService`, `database/seeders/DemoOrganizationSeeder.php` | `DemoOrganizationResetTest`, `DeleteOrganisationCommandTest`, `CreateOrganizationAdminCommandTest` |

## External APIs, webhooks, and documents

| Capability | Implementation / configuration | Verification |
| --- | --- | --- |
| SMSPortal HTTP | `app/Sms/Services/SmsPortalService.php`; `config/services.php` → `smsportal` holds endpoint/auth/timeout/encoding | `tests/Unit/SmsPortalServiceTest.php` |
| SMTP per organization | `app/Users/Services/OrganizationMailerService.php`, encrypted `SmtpSetting`; `config/mail.php`, account mail views | `SmtpSettingCrudTest`, `PasswordResetTest` |
| HTTP push | `app/Notifications/Services/NotificationSender.php::push`; `config/services.php` → `push`; device invalidation and legacy token fallback live here | Start at `EventNotificationLayerTest`; check provider-fake coverage before changing transport |
| Payment callback | `routes/payment.php` → `PaymentController::callback` → `PaymentService::processCallback`; `config/services.php` → `payments` | `PaymentApiTest`, `PaymentLifecycleTest` |
| File scanning/storage | `app/Users/Services/AntivirusScanner.php` invokes configured ClamAV binary; document/export controllers/jobs use Laravel Storage; `config/filesystems.php` | `UserDocumentTest`, `GdprWorkflowTest` |
| Local PDF rendering | `ReceiptService`, `ServiceInvoiceService`, `PaymentNoteService`, `OccurrenceAttendancePdfService`; templates in `resources/views` | `PaymentApiTest`, `ServiceCrudTest`, `EventAttendancePdfDownloadTest` |

No Stripe/PayPal SDK integration was found. Mail-provider/S3 configuration entries
are available defaults, not proof of deployed integrations. Keep existing small
integration services in their modules; introduce a dedicated integration folder only
when it makes a growing provider implementation easier to navigate.

Payment callback: public, `throttle:callbacks`, HMAC SHA-256 over the raw body using
`X-Payment-Signature`, then JSON validation. Processing locks the payment in a
transaction and protects terminal states; service activation and receipt issuance
are downstream. A FormRequest extraction must retain signature-before-validation
ordering and JSON-only input. Never change this flow without payment tests.

## Async and event flows

- `routes/console.php` is the schedule: due campaigns, lifecycle notifications, recurring occurrence extension, legacy expiration SMS, article publication transitions. Compose runs a worker, not a scheduler.
- `NotificationRequested` → listener registered in `app/Users/Providers/AppServiceProvider.php` → delivery records → `SendNotificationDelivery` → `NotificationSender` → attempt records. Preserve stable event keys and consent checks. Delivery jobs use 4 tries and backoff `[60, 300, 900]` seconds.
- Recurrence extension and lifecycle notification jobs use 3 tries with `[60, 300]` backoff. Inspect job-specific failure handling before changing retries; Compose's worker default is `--tries=3`.
- Both generic lifecycle notifications and legacy `Service/Jobs/SendExpiringServiceSms` are scheduled. Consider their overlap before adding another expiration send.
- Account setup/reset mail intentionally bypasses optional notification consent. Organization mailer setup must occur in the sending worker, not just at dispatch time.

## Database and hidden dependencies

- Start with `Users/Models/Concerns/{SetsOrganizationFromAuthenticatedUser,BelongsToAuthenticatedOrganization,LogsModelChanges}.php` and `Users/Models/Scopes/LocationAccessScope.php` for cross-cutting queries. Raw `DB::table` calls do not inherit model scopes.
- `User::activeServices()` calls `ServiceLifecycleService::refresh()` before building a relationship; reads can mutate lifecycle state. Read both files before optimizing eager loading or eligibility queries.
- `Article::visibleTo()` resolves `Reporting/Services/SegmentService`; campaigns and reports also consume segment membership. Similar service-date queries do not necessarily have identical lifecycle semantics.
- Rights checks depend on `User`, `RequireRight`, `OrganizationAccessService`, and `database/seeders/ApplicationRights.php`. Preserve explicit ownership checks; there is no established policy/repository layer to migrate to mechanically.
- Quota protection uses a scoped `OrganizationSubscriptionService`, HTTP middleware, and explicit guards in recurrence jobs/services. Read its locking/reentrancy behavior before changing transaction boundaries.
- `service_user` has an assignment ID and lifecycle/document fields; `event_occurrence_user` tracks attendance. Payment `model_type`/`model_id` is a supported-target contract. Rights are global; email/user-code uniqueness is organization-specific. Check migration history rather than guessing inferred tables or universal soft-delete/UUID conventions.

## Run checks

```bash
# Targeted test; use the file from the table above.
docker compose exec -T app-sifu php artisan test tests/Feature/CheckInApiTest.php
# Full suite (also clears cached configuration).
docker compose exec -T app-sifu composer test
# Check formatting only for touched PHP files; omit --test to format them.
docker compose exec -T app-sifu vendor/bin/pint --test path/to/changed.php
# Inspect contracts / regenerate after changing OpenAPI.
docker compose exec -T app-sifu php artisan route:list --path=api
docker compose exec -T app-sifu php artisan l5-swagger:generate
```

`phpunit.xml` supplies SQLite `:memory:`, sync queue, array mail/cache/session.
Normal tests need Composer dependencies and SQLite PHP support, not a running MySQL
server or live providers. `OrganizationSubscriptionMysqlTest` is opt-in through
`SUBSCRIPTION_TEST_MYSQL_DSN`, `SUBSCRIPTION_TEST_MYSQL_USER`, and
`SUBSCRIPTION_TEST_MYSQL_PASSWORD`, plus `pcntl`; use an isolated server with permission
to create/drop its temporary database. Never point it at production.

Existing formatting tool: Pint. No configured static-analysis tool was found.
For documentation-only changes, verify links, paths, command service names, and
`git diff --check`; runtime tests are unnecessary when runtime files are unchanged.

## Documentation ownership

- `AGENTS.md`: short default context and shared invariants.
- This guide: task-to-code/test navigation; update relevant rows when moving responsibilities.
- `project-rules-agent.md`: detailed conventions; search headings first.
- `functionality-explainer-agent.md`: implemented business behavior; update touched sections for behavior changes.
- `deployment-security.md`: production controls.
- `ai-optimization-audit.md`: dated findings/deferred work; read only for architecture work.
- `codex-prompts/` and `superpowers/specs/`: proposed/historical work; verify implementation before using as requirements.
