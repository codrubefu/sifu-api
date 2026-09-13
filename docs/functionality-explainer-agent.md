# ERP Laravel Functionality Explainer Agent

## Role

You are the functionality explainer agent for this Laravel ERP API project. Your job is to explain what the system does, how features connect, what permissions are required, and where the relevant code lives.

Answer in Romanian by default, unless the user asks for another language. Be concrete and source-grounded: mention controllers, services, routes, jobs, migrations, and tests when useful. Do not invent frontend behavior; this repository is primarily a Laravel API backend.

## Maintenance Rule

This file must be updated every time a new functionality, endpoint, scheduled job, domain workflow, notification, permission, table, or externally visible behavior is added or changed. The implementation agent must keep this explainer aligned with the current code before finishing the feature.

For project-wide implementation rules, use `docs/project-rules-agent.md`.

## Project Overview

This project is a modular Laravel ERP-style API with:

- multi-organization tenant isolation
- bearer-token authentication
- rights and groups based authorization
- users, administrators, clients, locations, and location groups
- services and service assignment lifecycle
- events, generated occurrences, and participants
- articles/announcements with audience segmentation and read receipts
- custom fields per entity type
- payments with provider callbacks and receipts
- SMS logging and SMS portal integration
- generic notification delivery layer for SMS, mail, and push
- audit/business activity logging
- scheduled jobs and console commands
- OpenAPI annotations for API documentation

## Core Architecture

### Routing

API routes are split by module and included from `routes/api.php`:

- `routes/user.php`
- `routes/service.php`
- `routes/event.php`
- `routes/article.php`
- `routes/custom-fields.php`
- `routes/sms.php`
- `routes/payment.php`
- `routes/reporting.php`
- `routes/dashboard.php`
- `routes/campaign.php`

Most endpoints are protected by `auth.bearer`. Permission checks use the custom middleware `right:...`.

### Authentication

Authentication uses custom bearer tokens, not Laravel Sanctum.

Main files:

- `app/Users/Http/Controllers/Api/AuthController.php`
- `app/Users/Http/Middleware/AuthenticateBearerToken.php`
- `app/Users/Services/BearerTokenService.php`
- `app/Users/Models/PersonalAccessToken.php`
- `database/migrations/2026_05_15_000000_create_personal_access_tokens_table.php`

Flow:

1. `POST /api/login` validates email, organization, and password.
2. A personal access token is created and returned.
3. Authenticated endpoints require `Authorization: Bearer <token>`.
4. `POST /api/logout` revokes the current token.

Security behavior:

- `POST /api/login` has a dedicated configurable per-minute limiter keyed by IP, organization, and a hash of the declared email.
- Successful and failed attempts are logged with outcome, user ID when known, organization, IP, and a hash of the declared identity. Passwords and bearer tokens are never logged.
- Tokens receive `expires_at` from `BEARER_TOKEN_EXPIRATION_MINUTES`; expired tokens and tokens belonging to inactive users are rejected.
- Changing a password or changing `active` to false deletes every bearer session for that user. Incident-response code can explicitly call `BearerTokenService::revokeAll()`.
- `PATCH /api/me/password` applies separate configurable Laravel `Password` policies for operators and administrators, including the compromised-password check, and revokes the calling session on success.
- Main configuration and tests are `config/security.php` and `tests/Feature/ApiSecurityTest.php`.

### API abuse protection and deployment security

- Payment callbacks use the `callbacks` limiter, keyed by IP, declared `X-Organization-Id`, and `X-Provider-Id` or the callback `external_reference`.
- Financial aggregation/export and dynamic segment member evaluation use the stricter `expensive` limiter, keyed by IP, authenticated organization, and authenticated user ID. The public `GET /api/organizations/by-url` lookup reuses the same `expensive` limiter; since it is called unauthenticated, it is effectively keyed by IP alone (organization and identity fall back to `none`/`anonymous`).
- A rate-limit rejection returns HTTP `429`; it does not create payments, exports, audit records, notifications, or other business side effects.
- `SecurityHeaders` adds HSTS on HTTPS plus content-type, frame, referrer, and permissions protections. Explicit proxy addresses are read from `TRUSTED_PROXIES` so HTTPS and client-IP detection remain trustworthy.
- Production requirements for HTTPS-only ingress, secure cookies, secret management and branch access through VPN are mandatory and documented in `docs/deployment-security.md`.
- Relevant implementation files are `AppServiceProvider`, `SecurityHeaders`, `bootstrap/app.php`, `routes/payment.php`, and `routes/reporting.php`; no new database table is introduced by these controls.

The project also supports `GET /api/organizations/slug/{slug}` and `GET /api/organizations/by-url?url=...` to resolve organization details before login — the former by slug, the latter by the full frontend origin the request came from (case-insensitive, tolerant of a trailing slash, matched via `LOWER()` comparisons against both the trailing-slash and no-trailing-slash forms so it stays portable between SQLite tests and MySQL). `by-url` is throttled with the `expensive` limiter since the frontend calls it on every unauthenticated page load; `slug` is not throttled.

### Authorization

Authorization is based on rights attached to groups, and groups attached to users.

Main files:

- `app/Users/Models/User.php`
- `app/Users/Models/Group.php`
- `app/Users/Models/Right.php`
- `app/Users/Http/Middleware/RequireRight.php`
- `app/Users/Services/OrganizationAccessService.php`
- `config/organization-access.php`

Important behavior:

- `right:a,b` means the user needs at least one of the listed rights.
- A user with no explicit rights through any group is treated by `User::hasRight()` / `User::hasAnyRight()` as having `profile.view` by default. This only grants self-profile access; it does not grant admin/module rights.
- Rights can be disabled per organization through `OrganizationAccessService`.
- Admin-style APIs generally use rights such as `users.view`, `users.manage`, `services.manage`, `events.manage`, etc.

### Multi-Organization Isolation

Most business records have `organization_id`.

Main files:

- `database/migrations/2026_05_26_000010_add_organizations_layer.php`
- `app/Users/Models/Concerns/SetsOrganizationFromAuthenticatedUser.php`
- `app/Users/Models/Concerns/BelongsToAuthenticatedOrganization.php`
- `app/Users/Models/Organization.php`

Behavior:

- New records often inherit the authenticated user's organization automatically.
- Queries are scoped to the authenticated organization where models use the organization concern.
- `BelongsToAuthenticatedOrganization`'s global scope treats a row with `organization_id IS NULL` as a shared/global record, visible to (and selectable by) every organization — not just to the organization that happens to own it, since there is none. This applies to every model listed in the trait's usages (`Service`, `LocationGroup`, `Location`, `User`, `Grade`, `UserGrade`, `UserDocument`, `Group`, `CustomFieldValue`, `EventCategory`, `SmtpSetting`, `Article`, `CustomField`, `Event`, `EventOccurrence`, `Campaign`, `Segment`).
- Because of that, Form Request `exists` validation for a foreign key into one of those models must accept both the authenticated organization's own rows and shared/global (`NULL`-organization) rows — otherwise a value that was legitimately offered by a list endpoint (e.g. a shared `Location`/`Group`) would be rejected as invalid when submitted back. `App\Users\Support\OrganizationScopedExistsRule::make($table, $column, $organizationId)` is the shared helper for this (used by Events, Articles, Campaigns, and Users Form Requests); it never allows another organization's non-null rows, only adds the `NULL`-organization case.
- Some internal jobs use `withoutGlobalScopes()` when they intentionally need cross-scope lookup.

## Functional Modules

### Users, Administrators, Clients

Main controller:

- `app/Users/Http/Controllers/Api/UserController.php`

Main routes:

- `GET /api/users`
- `GET /api/administrators`
- `GET /api/clients`
- `GET /api/users/search/user-code`
- `POST /api/users`
- `GET /api/users/{user}`
- `PUT/PATCH /api/users/{user}`
- `DELETE /api/users/{user}`
- `PATCH /api/users/service/{user}`
- `GET /api/users/{user}/activity`

Functional behavior:

- Users can be listed, filtered by search, viewed, created, updated, and deleted.
- Administrators are users with groups, excluding users who only have `profile.view`.
- Clients are users with no groups or only `profile.view`.
- Users can have groups, locations, services, notification consents, push tokens, and user codes.
- User list/detail responses include the loaded `parent` relation when present, so clients can display the guardian/tutor name without extra per-row requests.
- User e-mail, user code, and phone are unique within the organization when present; the same values may be reused in another organization.
- Users cannot delete their own account.
- User visibility is affected by location access scope.
- Service sync detaches old services, attaches new ones, calculates expiration, logs activity, and dispatches service activation notifications.

### Profile: `/me`

Main controller:

- `app/Users/Http/Controllers/Api/MeController.php`

Routes:

- `GET /api/me`
- `PATCH /api/me/password`
- `GET /api/me/custom-fields`
- `GET /api/me/events`
- `GET /api/me/services`

Functional behavior:

- Authenticated users can inspect their own profile.
- They can update their password.
- They can retrieve their custom fields, registered events, and services.

### Groups and Rights

Main controllers:

- `app/Users/Http/Controllers/Api/GroupController.php`
- `app/Users/Http/Controllers/Api/RightController.php`

Routes:

- `/api/groups`
- `/api/rights`

Functional behavior:

- Groups are organization-scoped and can contain rights.
- Rights are global after later migrations.
- Group create/update rejects disabled rights for the current organization.
- Rights can be listed, created, updated, and deleted by authorized users.

### Locations and Location Groups

Main controllers:

- `app/Users/Http/Controllers/Api/LocationController.php`
- `app/Users/Http/Controllers/Api/LocationGroupController.php`

Routes:

- `/api/locations`
- `/api/location-groups`

Functional behavior:

- Locations are organization-scoped.
- Location groups organize locations.
- Users can be attached to locations.
- Location access can restrict which users/data are visible.

### Services

Main controller:

- `app/Service/Http/Controllers/Api/ServiceController.php`

Main model:

- `app/Service/Models/Service.php`

Routes:

- `GET /api/services`
- `POST /api/services`
- `GET /api/services/{service}`
- `PUT/PATCH /api/services/{service}`
- `DELETE /api/services/{service}`
- `POST /api/services/{service}/restore`
- `PATCH /api/services/{service}/toggle-active`
- `GET /api/service-assignments/{assignment}/payment-note`

Functional behavior:

- Services are organization-scoped.
- Services have name, description, price, currency, duration in days, maximum users, active flag, timestamps, and soft delete.
- `billing_interval` and `trial_days` were removed by migration `2026_05_18_000002_remove_billing_interval_and_trial_days_from_services_table.php`.
- Users are attached to services through `service_user`.
- `service_user` stores `start_date` and `expires_at`.
- Active user services are determined by active service status and date range.
- Payment notes for service assignments are generated as PDFs from `storage/note-plata.html` or `storage/nota-plata.html` by `PaymentNoteService`; access is restricted to the authenticated organization.
- Assigning a service creates a bill number on `service_user`. Invoice numbers are not created automatically; `POST /api/service-assignments/{assignment}/invoice` assigns the next organization invoice number to the assignment.

### Events and Occurrences

Main controllers:

- `app/Events/Http/Controllers/Api/EventController.php`
- `app/Events/Http/Controllers/Api/EventCategoryController.php`
- `app/Events/Http/Controllers/Api/EventOccurrenceController.php`
- `app/Events/Http/Controllers/Api/EventParticipantController.php`

Main services:

- `app/Events/Services/EventOccurrenceGeneratorService.php`
- `app/Events/Services/EventEligibilityService.php`
- `app/Events/Services/EventParticipantService.php`

Routes:

- `GET /api/event-categories`
- `POST /api/event-categories`
- `GET /api/event-categories/{eventCategory}`
- `PUT/PATCH /api/event-categories/{eventCategory}`
- `DELETE /api/event-categories/{eventCategory}`
- `GET /api/event-occurrences`
- `GET /api/events`
- `POST /api/events`
- `GET /api/events/{event}`
- `PUT/PATCH /api/events/{event}`
- `DELETE /api/events/{event}`
- `GET /api/events/{event}/occurrences`
- `GET /api/event-occurrences/{occurrence}`
- `PATCH /api/event-occurrences/{occurrence}/cancel`
- `GET /api/event-occurrences/{occurrence}/eligible-participants`
- `GET /api/event-occurrences/{occurrence}/participants`

Event payment fields (`requires_payment`, `payment_amount`, `payment_type`): when `requires_payment` is `true`, `payment_amount` (numeric) and `payment_type` are both required (`StoreEventRequest`/`UpdateEventRequest`). `payment_type` holds a **currency code** (e.g. `EUR`, `RON`), validated as `string`, `max:3`, with no fixed enum — the same convention as `Service.currency` (`app/Service/Http/Requests/StoreServiceRequest.php`). It is unrelated to the Payments module's `Payment::PAYMENT_TYPES` (`cash`/`card`/`bank_transfer`), which models the payment method of an actual `Payment` record; `Event::payment_type` is never read to derive a `Payment::payment_type_id` and the two concepts are intentionally decoupled. When `requires_payment` is toggled off (or absent on create), both `payment_amount` and `payment_type` are nulled out in `prepareForValidation()`.

When an event has a required service with a finite `max_accesses` limit, adding a participant consumes exactly one access from that user's active assignment. Single and bulk participant additions consume inside the same database transaction as the participant pivot insert, so a failed addition rolls back the consumption. Duplicate registrations remain rejected before consumption. The current participant schema does not restore an access when a participant is removed; restoration would require a usage ledger linking the participant to the consumed service assignment.

Event reporting dimensions (`location_id`, `instructor_id`, `group_id`):

- `Event` (`app/Events/Models/Event.php`) has three optional foreign keys added for reporting/filtering purposes: `location_id` (→ `App\Users\Models\Location`, nullable FK on `locations`), `instructor_id` (→ `App\Users\Models\User`, nullable FK on `users`), and `group_id` (→ `App\Users\Models\Group`, nullable FK on `groups`). All three `nullOnDelete()`. The model exposes them as Eloquent relations: `location()`, `instructor()`, `group()`.
- These are distinct from the pre-existing free-text `location` string column on `events`, which remains unchanged and is still used as a display fallback for ad-hoc events that don't have a structured `Location` record. `location_id` is the structured/primary source when set; the two are never auto-synced.
- `EventResource` (`app/Events/Http/Resources/EventResource.php`) exposes, alongside the existing raw `location_id`/`instructor_id`/`group_id` IDs:
  - `location_text`: the free-text fallback string (formerly serialized under the `location` key — renamed to avoid colliding with the new resolved-object `location` key below).
  - `location`: `{"id": ..., "name": ...}` resolved from `location_id`, or `null` when `location_id` is not set.
  - `instructor`: `{"id": ..., "name": ...}` — a minimal projection of the instructor `User` (full name only), not a full `UserResource`, to avoid leaking sensitive user fields. `null` when `instructor_id` is not set.
  - `group`: `{"id": ..., "name": ...}` resolved from `group_id`, or `null` when `group_id` is not set.
  - All three resolved fields use `whenLoaded()`; if a caller forgets to eager-load `location`/`instructor`/`group`, the keys are simply omitted from the response instead of triggering N+1 queries.
- `EventController@index/store/show/update` eager-load `location`, `instructor`, and `group` alongside `category` and `requiredService`.
- `StoreEventRequest`/`UpdateEventRequest` validate `location_id`, `instructor_id`, and `group_id` as nullable and, when present, must reference a `locations`/`users`/`groups` row belonging to the authenticated user's organization or a shared/global row (`organization_id IS NULL`) — same rule already used for `category_id`. A value from another, non-null organization is rejected with `422`. This is built with `App\Users\Support\OrganizationScopedExistsRule::make()`, a small shared helper used by every Form Request that validates a foreign key against a model using `BelongsToAuthenticatedOrganization`, so the `exists` check always matches that trait's own visibility scope (own-organization rows plus shared/global `NULL`-organization rows) instead of rejecting legitimately selectable shared records.

### Grades

Grades are organization-scoped definitions and a separate historical award record for each user.

Main files:

- `app/Users/Models/Grade.php`
- `app/Users/Models/UserGrade.php`
- `app/Users/Http/Controllers/Api/GradeController.php`
- `database/migrations/2026_08_28_000002_create_grades_table.php`
- `database/migrations/2026_08_28_000003_create_user_grades_table.php`

Routes:

- `GET/POST /api/grades`
- `GET/PUT/PATCH/DELETE /api/grades/{grade}`
- `GET /api/grades/{grade}/users`
- `GET/POST /api/users/{user}/grades`
- `GET/PUT/PATCH/DELETE /api/users/{user}/grades/{userGrade}`

Grade definitions contain a name, description and active flag. User awards contain the grade, obtained date and description. Future obtained dates are rejected. The active grade is the most recent non-deleted award, ordered by obtained date and then record ID. Deleting a definition is a soft delete and preserves historical awards.

The `grades.view` right allows reading definitions, history and active-grade user lists. `grades.manage` allows creating, updating and deleting definitions and awards, and implies `grades.view`. All queries use the authenticated organization and existing user visibility rules.
- `POST /api/event-occurrences/{occurrence}/participants/bulk`
- `POST /api/event-occurrences/{occurrence}/participants`
- `PUT/PATCH /api/event-occurrences/{occurrence}/participants/{user}`
- `DELETE /api/event-occurrences/{occurrence}/participants/{user}`

Functional behavior:

- Events can be one-time, weekly, or monthly.
- Events can be assigned to organization-scoped categories and filtered by `category_id`.
- Calendar UIs can load all occurrences through `GET /api/event-occurrences` using `date_from`, `date_to`, `status`, and `category_id`. This read-only calendar endpoint, `GET /api/event-occurrences/{occurrence}`, `GET /api/events/{event}`, and `GET /api/events/{event}/occurrences` require bearer authentication but no `events.view` right, so every logged-in user can inspect the schedule and event details.
- Administrative event lists, categories, mutations, participants, check-in, and attendance exports still require their existing `events.*`, `event_participants.*`, or `checkins.*` rights.
- Deleting a category clears `category_id` on related events before soft deleting the category.
- Creating an event generates initial occurrences. A `once` event still creates exactly one occurrence on `start_date`. Weekly and monthly events are materialized only through a rolling two-month horizon, calculated from the current date and capped by `end_date` when it exists.
- `events.occurrences_generated_until` records the date through which a recurring event has been materialized. `null` identifies one-time events and legacy recurring events that predate the rolling-window feature; legacy events are intentionally not backfilled automatically.
- The queued `App\\Events\\Jobs\\ExtendRecurringEventOccurrences` job runs daily at 03:00. It extends only active weekly/monthly events with a non-null `occurrences_generated_until`, preserving roughly two months of scheduled occurrences ahead without exceeding `end_date`; its date uniqueness handling makes repeated runs idempotent.
- Updating schedule-related fields regenerates future open occurrences through the same rolling horizon. Editing a legacy recurring event adopts it into this workflow by setting `occurrences_generated_until`; changing it to `once` clears the field. Reducing `end_date` also reduces the recorded horizon, while occurrences with participants retain the existing preservation behavior.
- Deleting an event removes future occurrences without participants and cancels future occurrences with participants.
- Events can require active services and/or payment.
- Events can require a specific service.
- Participants are attached through `event_occurrence_user`.
- Quick add lists eligible users for a selected occurrence through `GET /api/event-occurrences/{occurrence}/eligible-participants`; the list excludes existing participants and applies active-service requirements before returning results. Multiple selected users can be attached atomically through `POST /api/event-occurrences/{occurrence}/participants/bulk`.
- Participant status can be managed.
- When schedule changes or an inactive event resumes, notifications are dispatched to affected participants.
- `PATCH /api/event-occurrences/{occurrence}/cancel` cancels a single occurrence (requires `events.manage`). It rejects with `400` when the occurrence is already `cancelled` or `completed`. On success it sets `status` to `cancelled` and, after the transaction commits, dispatches `NotificationRequested` with type `occurrence.cancelled` to every participant whose pivot status on that occurrence is `registered` or `attended`, using an idempotent `event_key` of `occurrence.cancelled:{occurrence_id}:{updated_at_timestamp}`. The endpoint explicitly checks that the occurrence belongs to the caller's organization (`404` otherwise) rather than relying solely on the model's global scope, since route-model binding resolves before the bearer-token auth middleware runs and can't rely on `Auth::user()` for scoping.
- `GET/PATCH/DELETE /api/events/{event}` (show, update, destroy) likewise perform an explicit organization check on the bound `Event` before acting, for the same route-model-binding-before-auth reason; cross-organization access returns `404`.

Quick add API details:

- `GET /api/event-occurrences/{occurrence}/eligible-participants` accepts `search`, `page`, and `per_page`. Search matches `first_name`, `last_name`, `email`, `phone`, and `user_code`.
- Eligible-participants responses are paginated user resources with `active_services` loaded, `has_active_service`, and no users already attached to the occurrence.
- `POST /api/event-occurrences/{occurrence}/participants/bulk` body: `user_ids` required array of distinct user IDs, optional `status`, optional `registered_at`, optional `notes`, optional `apply_to_future_occurrences` boolean (default `false`).
- Bulk add defaults `status` to `registered` and `registered_at` to the current timestamp.
- Bulk add on the target occurrence is atomic: duplicates, ineligible users, missing users, or insufficient available places reject the whole request.
- `apply_to_future_occurrences: true` additionally mirrors the same `user_ids` onto the sibling **scheduled** occurrences of the same event with `occurrence_date` today or later (today inclusive, same "future" definition as `EventOccurrenceGeneratorService::regenerateFutureOpenOccurrences`), excluding the explicitly targeted occurrence and excluding `cancelled`/`completed` ones. This mirrors the flow staff previously repeated manually per occurrence for recurring (weekly/monthly) classes. Unlike the target occurrence, this pass is **best effort per occurrence/per user** and reuses `EventParticipantService::isAlreadyRegistered()`/`isEligible()`/`hasAvailableCapacity()` for each future occurrence independently: a full future occurrence, an already-registered user on one future occurrence, or a missing/exhausted required-service access for one user never fails the whole request or the other future occurrences — each outcome is only reported back. When the required service must be consumed (`Event::required_service_id` with a finite `max_accesses`) and the consumption fails at attach time, that user is skipped for that occurrence with reason `service_access_unavailable` instead of raising an error.
- The response then includes `future_occurrences_updated`: an array with one entry per matched future occurrence, `{"occurrence_id", "occurrence_date", "added_user_ids": [...], "skipped": [{"user_id", "reason"}, ...]}`, where `reason` is one of `already_registered`, `missing_required_service`, `capacity_full`, `service_access_unavailable`. The key is present only when `apply_to_future_occurrences` was `true` in the request (it is omitted entirely, not just empty, when the flag is absent or `false`, so the existing response shape for the current single-occurrence behavior is unchanged); if `apply_to_future_occurrences` is `true` but no matching future occurrences exist, the key is present with an empty array.

Centralized participant eligibility (`app/Events/Services/EventParticipantService.php`):

- Duplicate registration, active-service eligibility, capacity, and the `requires_payment` signal used to be reimplemented separately in `EventParticipantController::store/bulkStore/update` and `CheckInService::verdict/confirm`, with divergent behavior. They now share `EventParticipantService`, which exposes `evaluate()` (single-pass check: duplicate + eligibility + capacity + informational `requires_payment`, used by `store`), plus the composable `isAlreadyRegistered()`, `isEligible()`, `hasAvailableCapacity()`, and `requiresPayment()` methods reused by `bulkStore`, the reactivation branch of `update`, `CheckInService`, and the `apply_to_future_occurrences` best-effort pass in `bulkStore` (see below).
- `POST /api/event-occurrences/{occurrence}/participants` and `POST /api/event-occurrences/{occurrence}/participants/bulk` now always return a top-level `requires_payment` boolean in the success response, mirroring the parent event's `requires_payment` flag. This is informational only: it never blocks adding a participant through these two endpoints, unlike check-in (see CheckIns section), where `requires_payment` remains a blocking condition. Duplicate registration, missing required service, and capacity-full remain blocking with the same status codes/messages as before (`422`/`403`/`400`).
- `CheckInService` keeps its own, narrower "already present" concept local (only an existing `attended` pivot row blocks a check-in; a merely `registered` participant can still check in), plus `document_expired` and `member_inactive`, since these are specific to the check-in flow and not shared with the participant-add endpoints.

### Articles and Announcements

Main controller:

- `app/Articles/Http/Controllers/Api/ArticleController.php`

Main model:

- `app/Articles/Models/Article.php`

Routes:

- `GET /api/articles`
- `POST /api/articles`
- `GET /api/articles/{article}`
- `PUT/PATCH /api/articles/{article}`
- `DELETE /api/articles/{article}`
- `GET /api/articles-feed`
- `POST /api/articles/{article}/view`

Functional behavior:

- Articles have title, description, status, publish date, expiration date, priority, audience segment, optional dynamic `segment_id`, and author.
- Statuses: `draft`, `scheduled`, `published`, `expired`.
- Audience segments: `all_users`, `active_subscribers`, `expired_users`, `groups`, `locations`.
- Feed visibility is calculated per user using organization, publication status, dates, segment, group membership, location membership, and service status.
- When `segment_id` is present, `Article::scopeVisibleTo()` asks `SegmentService` for current membership. Both the segment and user must belong to the article organization; an ID from another tenant is rejected by create/update validation and never grants feed visibility.
- `GET /api/articles-feed` records delivery receipts.
- `POST /api/articles/{article}/view` records view time.
- Receipts are stored in `article_user_receipts`.
- Scheduled job `TransitionArticlePublicationStatus` publishes scheduled articles and expires outdated articles.

### E-mail and Push Campaigns

Main files:

- `app/Campaigns/Http/Controllers/Api/CampaignController.php`
- `app/Campaigns/Models/Campaign.php`
- `app/Campaigns/Services/CampaignService.php`
- `app/Campaigns/Jobs/DispatchCampaign.php`
- `routes/campaign.php`

Authenticated routes:

- `GET /api/campaigns`
- `POST /api/campaigns`
- `PUT/PATCH /api/campaigns/{campaign}`
- `GET /api/campaigns/{campaign}/preview`
- `POST /api/campaigns/{campaign}/schedule`
- `POST /api/campaigns/{campaign}/cancel`
- `GET /api/campaigns/{campaign}/statistics`

Functional behavior:

- Campaigns are organization-scoped drafts for the `mail` or `sms` channel and can optionally reference a dynamic segment from the same organization.
- Draft content and audience can be edited. Preview returns the complete current recipient count and at most 100 recipient rows.
- Scheduling does not freeze recipients. The every-minute scheduler queues `DispatchCampaign`, and `CampaignService` evaluates the dynamic segment when dispatch becomes due, so eligibility changes between scheduling and delivery are honored.
- Dispatch creates campaign-linked `notification_deliveries` and uses `campaign:{campaign_id} + user_id + channel` for idempotency. Re-running dispatch does not duplicate deliveries.
- Draft or scheduled campaigns can be cancelled; already sent or cancelled campaigns reject cancellation.
- Statistics aggregate pending, sent, failed, and consent-skipped deliveries.
- Main tables are `campaigns` and the existing `notification_deliveries`; campaign dispatch also produces `notification_attempts` during actual sends.

### Custom Fields

Main controllers:

- `app/CustomFields/Http/Controllers/Api/CustomFieldController.php`
- `app/CustomFields/Http/Controllers/Api/CustomFieldValueController.php`

Main services:

- `app/CustomFields/Services/CustomFieldDefinitionService.php`
- `app/CustomFields/Services/CustomFieldValueService.php`

Routes:

- `GET /api/custom-fields`
- `POST /api/custom-fields`
- `GET /api/custom-fields/{customField}`
- `PUT/PATCH /api/custom-fields/{customField}`
- `DELETE /api/custom-fields/{customField}`
- `GET /api/{entityType}/{entityId}/custom-field-values`
- `POST /api/{entityType}/{entityId}/custom-field-values`

Functional behavior:

- Organizations can define custom fields for supported entity types.
- Custom fields are cached per organization and entity type.
- Values are stored separately and typed into dedicated value columns.
- The values API loads definitions and saved values for an entity.
- The save API validates/saves submitted custom field values.

### Payments

Main controller:

- `app/Payments/Http/Controllers/Api/PaymentController.php`

Main services:

- `app/Payments/Services/PaymentService.php`
- `app/Payments/Services/ReceiptService.php`
- `app/Service/Services/ServiceLifecycleService.php` for service assignment activation

Routes:

- `GET /api/payments`
- `POST /api/payments`
- `PATCH /api/payments/{payment}/attach-model`
- `GET /api/payments/{payment}/receipt`
- `POST /api/payments/callback`

Functional behavior:

- Payments can be cash, card, or bank transfer.
- Payments can attach to service assignments or event participant assignments.
- Payable model types:
  - service assignment: `service_user`
  - event occurrence participant: `event_occurrence_user`
- Payment creation verifies the payable record belongs to the authenticated organization.
- Cash payments are immediately confirmed.
- Non-cash payments start as initiated and are updated by callback.
- Confirmed service payments are delegated to `ServiceLifecycleService::activate()`; the payment service does not update `service_user` directly.
- A payment can activate an assignment only when its `status` is exactly `confirmed`, its `organization_id` matches the service organization, and its `model_type`/`model_id` point to that exact `service_user` row. A populated `paid_at` field alone is not confirmation.
- Activation locks the assignment and atomically writes its lifecycle `status`, `start_date`, `expires_at`, `activated_at`, and `activation_payment_id`. Expiration follows the service's `expiration_rule` (`duration`, `fixed_date`, or `none`), including future starts becoming `reserved`.
- Confirmed payments receive a receipt number in the same transaction as service activation.
- Receipt download is allowed only for confirmed payments with receipt numbers. `ReceiptService` generates the PDF from `storage/chitanta.html` and fills payment number, date, payer, amount, amount in Romanian words, paid model details, and cashier from the persisted payment.
- Callback processing is idempotent and handles terminal statuses; a duplicate confirmed callback does not activate or notify twice.
- The `service.activated` notification and audit business event are emitted only after the activation transaction commits, so rolled-back activations have no external activation side effects.
- Callback signatures use `services.payments.callback_secret`.

### SMS

Main controller:

- `app/Sms/Http/Controllers/Api/SmsMessageController.php`

Main service:

- `app/Sms/Services/SmsPortalService.php`

Route:

- `GET /api/sms-messages`

Functional behavior:

- SMS messages are logged in `sms_messages`.
- SMS list supports filters by service, user, status, dates, and search.
- `SmsPortalService` sends messages to the configured SMS portal endpoint.
- It converts Romanian/non-ASCII text to plain ASCII when required by provider config.
- The older service expiration SMS job stores and updates `SmsMessage` rows.

### Notifications

Main files:

- `app/Notifications/Events/NotificationRequested.php`
- `app/Notifications/Listeners/QueueNotificationDeliveries.php`
- `app/Notifications/Jobs/SendNotificationDelivery.php`
- `app/Notifications/Services/NotificationSender.php`
- `app/Notifications/Jobs/DispatchServiceLifecycleNotifications.php`
- `config/notifications.php`
- `database/migrations/2026_08_06_000001_create_notification_layer.php`
- `database/migrations/2026_08_09_000001_create_campaigns_and_notification_preferences.php`

Functional behavior:

- Feature code dispatches `NotificationRequested`.
- The listener checks user consent for `sms`, `mail`, and `push`.
- `PUT /api/notification-preferences` lets the authenticated user subscribe or unsubscribe by channel and scope. Scope `all` blocks the complete channel, while `campaigns` controls campaign messages.
- Consent is checked again by `SendNotificationDelivery` immediately before calling the provider. A withdrawn consent changes the delivery to `skipped` with reason `consent`, even if the user was eligible when the campaign was scheduled or expanded.
- `POST /api/push-devices` registers or refreshes one of multiple push tokens, and `DELETE /api/push-devices/{device}` removes an owned device. These self-service endpoints require bearer authentication but no administrative right.
- Push sends target every row in `push_devices` for the user. Provider responses `404` and `410` delete invalid tokens; the legacy `users.push_token` is used only as a compatibility fallback when no device rows exist.
- `push_devices.token` stores the raw token (up to 2048 chars, e.g. long web-push endpoints) but is **not** indexed, because MySQL/InnoDB cannot build a unique index on a column that long with utf8mb4 (max key length 3072 bytes). Uniqueness and lookups instead go through `push_devices.token_hash`, a SHA-256 hex digest (64 chars, unique-indexed) kept in sync by a `setTokenAttribute` mutator on `PushDevice` whenever `token` is set. `POST /api/push-devices` (`NotificationPreferenceController::registerDevice()`) upserts by matching `token_hash` computed from the incoming raw token, while still storing/updating the raw `token` value used to call the push provider. This follows the same precedent as `personal_access_tokens`, which indexes a fixed-length token value instead of an arbitrary-length secret.
- For every allowed channel, it creates one `notification_deliveries` row.
- Unique key `event_key + user_id + channel` prevents duplicate sends for the same event/channel.
- New deliveries dispatch `SendNotificationDelivery`.
- `SendNotificationDelivery` creates `notification_attempts`, sends through `NotificationSender`, and updates delivery status.
- Failed sends are retried by Laravel queue using configured tries/backoff.
- Templates live in `config/notifications.php` and use placeholders like `:service`, `:expires_at`, `:event`, `:message`.
- Event types include `service.activated`, `service.expiring`, `service.expired`, `schedule.changed`, `activity.resumed`, `announcement.urgent`, and `occurrence.cancelled` (dispatched by `EventOccurrenceController::cancel()` to participants of a cancelled occurrence).

Known overlap:

- `DispatchServiceLifecycleNotifications` is the new generic notification job.
- `App\Service\Jobs\SendExpiringServiceSms` is an older SMS-specific job.
- Both are scheduled in `routes/console.php`, so service expiration can be handled by two systems if both remain active.

### Audit and Business Activity

Main files:

- `app/Users/Models/AuditLog.php`
- `app/Users/Services/BusinessActivityLogger.php`
- `app/Users/Models/Concerns/LogsModelChanges.php`
- `database/migrations/2026_05_26_000012_create_audit_logs_table.php`
- `database/migrations/2026_08_06_000001_extend_audit_logs_for_business_activity.php`

Functional behavior:

- Model changes are logged for models using `LogsModelChanges`.
- Business events include user creation/update/delete, service assignment/activation/renewal/suspension, payment recorded, approval granted, card issued, and SMS sent.
- Sensitive fields such as passwords, tokens, CNP/personal numeric code, authorization values, and secrets are removed from logged payloads.
- User activity can be retrieved through `GET /api/users/{user}/activity`.

## Scheduled Jobs and Commands

Scheduled in `routes/console.php`:

- `DispatchServiceLifecycleNotifications`: daily at 08:00, sends generic service lifecycle notifications.
- `SendExpiringServiceSms`: daily, sends legacy SMS service expiration notices.
- `TransitionArticlePublicationStatus`: every minute, publishes scheduled articles and expires old articles.
- Campaign scheduler callback: every minute, queues `DispatchCampaign` for due scheduled campaigns; `CampaignService` expands their current tenant-safe audience.

Console commands:

- `CreateOrganizationAdmin`: creates an organization/admin bootstrap account and rights.
- `DeleteOrganisation`: deletes organization-owned data in a controlled order.
- `SeedUsers`: seeds users.
- `SendExpiringServiceSms`: command wrapper for the expiring SMS job.

## Data Model Cheat Sheet

Important tables:

- `organizations`
- `users`
- `personal_access_tokens`
- `groups`
- `rights`
- `group_right`
- `group_user`
- `locations`
- `location_user`
- `location_groups`
- `services`
- `service_user`
- `events`
- `event_occurrences`
- `event_occurrence_user`
- `articles`
- `article_group`
- `article_location`
- `article_user_receipts`
- `custom_fields`
- `custom_field_values`
- `payments`
- `sms_messages`
- `audit_logs`
- `notification_deliveries`
- `notification_attempts`
- `password_setup_tokens`
- `smtp_settings`

## Explanation Style

When explaining a feature:

1. Start with what the feature does in business terms.
2. List the API endpoints involved.
3. Explain permissions.
4. Explain the main data tables.
5. Explain side effects: notifications, audit logs, jobs, payments, receipts.
6. Mention the exact files that implement it.
7. Mention edge cases and known risks if visible in the code.

Example response shape:

```text
Funcționalitatea X permite ...

Endpoint-uri:
- METHOD /api/...

Permisiuni:
- ...

Flux:
1. ...
2. ...
3. ...

Persistență:
- tabela ...

Cod relevant:
- path/to/file.php

Observații:
- ...
```

## Known Implementation Notes

- The project has OpenAPI annotations in each module under `OpenApi`.
- Most feature behavior is covered by feature tests under `tests/Feature`.
- Tests currently run through PHP/Laravel, likely inside the Docker `app` service.
- On Windows host sessions without `php` in PATH or Docker daemon access, tests cannot be run directly.
- Keep explanations aligned with the current migrations, not older test fixtures or stale comments.

## Raportare financiară și segmente dinamice

API-ul oferă raportare tenant-safe peste plăți și cotizații. `GET /api/reports/financial` (drept `reports.view`) acceptă perioada (`from`, `to`), organizația curentă, filiala (`location_id`), operatorul (`admin_id`), metoda de plată, statusul, tipul cotizației și granularitatea zi/lună. Răspunsul include total confirmat/rambursat/net, venit pe perioadă, creanțe din cotizații, reînnoiri și sume de transfer bancar reconciliate/nereconciliate. `ReportController` coordonează validarea, iar `FinancialReportService` execută agregările exclusiv în organizația utilizatorului.

`GET /api/reports/financial-documents` listează facturi, note de plată și chitanțe pentru perioada selectată. `GET /api/reports/financial-documents/{type}/{id}/download/{format?}` descarcă un document individual; facturile acceptă PDF și XML e-Factura, celelalte documente sunt PDF. `GET /api/reports/financial-documents/download` returnează o arhivă ZIP cu toate documentele filtrate și include XML-ul e-Factura pentru fiecare factură. Lista și descărcările sunt tenant-safe prin `FinancialDocumentReportService`.

`POST /api/reports/financial/exports`, `GET /api/reports/exports/{id}` și `GET /api/reports/exports/{id}/download` necesită dreptul distinct `reports.export`. Exportul CSV sau XLSX este procesat asincron de jobul `GenerateReportExport`; starea și filtrele sunt păstrate în `report_exports`, iar fișierul este scris pe discul local în directorul tenantului. Generarea nu produce plăți, chitanțe sau notificări.

Segmentele salvate sunt administrate prin `GET/POST/PUT/DELETE /api/segments` și evaluate prin `GET /api/segments/{segment}/members`. Citirea cere `segments.view` sau `segments.manage`, iar modificarea cere `segments.manage`. `Segment` păstrează în tabela `segments` criterii JSON precum activ/inactiv, filială, tip de plan, expirat sau expirare în N zile. `SegmentService::members()` este interfața reutilizabilă de selecție a membrilor pentru rapoarte, anunțuri și campanii și aplică întotdeauna organizația segmentului. Nu sunt trimise automat anunțuri, campanii, SMS-uri sau notificări la evaluarea unui segment.

Migrarea `2026_08_06_000003_create_reporting_layer.php` creează `segments` și `report_exports` și extinde `payments` cu `bank_reference` și `reconciled_at`. Drepturile noi sunt adăugate de `DatabaseSeeder`.

## Dashboard operational

Dashboard-ul este un sumar read-only pentru prima pagina ERP. Endpoint-ul este `GET /api/dashboard` si accepta filtrele optionale `from`, `to` si `group_by=day|month`; implicit foloseste ultimele 30 de zile si grupare lunara. Accesul este permis pentru utilizatori cu `dashboard.view`, `reports.view` sau `reports.manage`.

Raspunsul include KPI-uri (`active_members`, `flagged_services`, `total_revenue`, `active_locations`), venit pe perioada, distributie status membri, activitate pe perioada si indicatori pentru automatizari. Datele sunt calculate tenant-safe din `users`, `locations`, `payments`, `services`, pivotul `service_user`, `audit_logs` si `articles`, folosind intotdeauna `organization_id` din utilizatorul autentificat.

Endpoint-ul nu produce side effects: nu creeaza plati, nu trimite SMS-uri/notificari, nu porneste joburi si nu modifica statusuri. Implementarea este in `app/Dashboard/Http/Controllers/Api/DashboardController.php`, `app/Dashboard/Services/DashboardService.php`, `routes/dashboard.php` si fisierele OpenAPI din `app/Dashboard/OpenApi`.

## Check-in rapid la receptie

API-ul ofera un flux operational pentru receptie peste membrii si aparitiile de eveniment existente. `GET /api/check-ins/occurrences/current` listeaza toate aparitiile programate pentru ziua curenta, astfel incat operatorul poate alege clasa chiar daca membrul ajunge mai devreme sau intarzie. `POST /api/check-ins/search` cauta un membru dupa cod, telefon sau email si poate primi `occurrence_id` pentru verdict contextual. `POST /api/check-ins/confirm` marcheaza prezenta pentru o aparitie concreta.

Endpoint-urile cer bearer auth si dreptul `checkins.manage` sau `event_participants.manage`. Exceptia explicita peste un verdict invalid foloseste `allow_override=true` si cere dreptul suplimentar `checkins.override`.

Implementarea principala este in `app/CheckIns/Http/Controllers/Api/CheckInController.php`, request-urile din `app/CheckIns/Http/Requests`, `app/CheckIns/Http/Resources/CheckInResource.php` si `app/CheckIns/Services/CheckInService.php`; rutele sunt in `routes/event.php`. Drepturile sunt seeduite in `database/seeders/DatabaseSeeder.php`.

Check-in-ul legat de o clasa reutilizeaza pivotul `event_occurrence_user` si salveaza participantul cu status `attended`. Daca evenimentul cere un serviciu cu limita de intrari, consumul trece prin `ServiceLifecycleService::consumeEventAccess()` in aceeasi tranzactie cu atasarea participantului. Cand operatorul foloseste `allow_override=true` peste un acces invalid, prezenta se marcheaza ca exceptie si nu se consuma intrare dintr-un abonament lipsa sau neeligibil. Raspunsul include membrul gasit, verdictul (`allowed`, `override_allowed`, `refused`, `requires_payment`, `document_expired`, `already_present`, `not_found`), abonamentul activ, serviciile eligibile, motivul refuzului, clasa si ultimul check-in relevant.

`CheckInService::verdict()`/`confirm()` folosesc `app/Events/Services/EventParticipantService.php` pentru eligibilitate (serviciu activ), capacitate maxima si semnalul `requires_payment` — aceleasi metode centrale folosite si de `EventParticipantController` pentru adaugarea participantilor. Spre deosebire de `store`/`bulkStore` (unde `requires_payment` e doar informativ), la check-in `requires_payment` ramane un motiv de blocare: verdictul devine `requires_payment` si `access_allowed=false` daca evenimentul cere plata. Conceptul de "deja prezent" ramane specific check-in-ului: doar un rand pivot existent cu status `attended` blocheaza (un participant cu status `registered` poate fi in continuare confirmat prezent), spre deosebire de `store`/`bulkStore`, unde orice rand pivot existent (indiferent de status) e considerat duplicat si blocheaza adaugarea.

Datele sunt tenant-safe prin scope-urile modelelor `User` si `EventOccurrence`, iar cautarea membrilor pastreaza si filtrarea de locatie aplicata pe `User`. Check-in-urile acceptate si refuzate scriu audit/business activity cu tipurile `checkin.accepted` si `checkin.refused` in `audit_logs`.

## Note servicii gratuite si assignment-uri

Assignment-urile din `service_user` pastreaza statusul lifecycle si legatura de plata prin `activation_payment_id`. La sincronizarea serviciilor unui utilizator, codul trebuie sa detaseze doar serviciile eliminate si sa actualizeze pivot-ul existent pentru serviciile pastrate, altfel se pierde istoricul si plata asociata.

`POST /api/service-assignments/{assignment}/activate` accepta `payment_id` optional: serviciile cu pret mai mare de 0 necesita o plata cu status explicit `confirmed`, din aceeasi organizatie si legata exact de assignment prin `model_type=service_user` si `model_id`; simpla completare a `paid_at` nu confirma plata. Serviciile gratuite pot fi activate fara plata. La atasare noua, serviciile gratuite intra direct in `active` sau `reserved` daca data de start este in viitor; serviciile platite raman `pending`.

Pentru platile cash, confirmarea, numarul chitantei si activarea assignment-ului sunt salvate atomic. Pentru card si transfer bancar, acelasi flux ruleaza la callback-ul confirmat. Activarea seteaza `status`, `start_date`, `expires_at`, `activated_at` si `activation_payment_id` prin `ServiceLifecycleService`; notificarea si auditul `service.activated` sunt emise numai dupa commit. Callback-urile confirmate duplicate sunt idempotente si nu repeta aceste efecte.

`service_history` din `UserResource` trebuie sa expuna statusul lifecycle real din pivot (`pending`, `active`, `expired`, `suspended`, `consumed`, `reserved`) impreuna cu campurile de audit ale assignment-ului. Nu recalcula istoricul doar din `start_date` si `expires_at`.

## Consimțăminte și cereri GDPR

Modulul Users include un registru append-only pentru consimțăminte și un workflow complet pentru drepturile persoanei vizate. Fiecare acord sau retragere creează un rând nou în `consent_records`; rândurile existente nu se actualizează și nu se șterg. Evenimentul păstrează organizația și utilizatorul, scopul, canalul, versiunea politicii, valoarea `granted`, timestamp-ul efectiv, sursa și actorul. `User::consentsTo()` folosește cel mai recent eveniment pentru combinația `notifications` + canal. Migrarea `2026_08_09_000001_create_gdpr_layer.php` importă snapshot-urile JSON istorice din `users.notification_consents` cu sursa `legacy_migration`.

Endpoint-uri self-service, disponibile oricărui utilizator autentificat:

- `GET /api/me/privacy/data` întoarce profilul propriu și istoricul consimțămintelor;
- `POST /api/me/privacy/exports` creează o cerere și pune în coadă exportul propriu;
- `PATCH /api/me/privacy/rectification` rectifică numele, telefonul sau e-mailul propriu;
- `POST /api/me/privacy/consents` adaugă un eveniment de acord sau retragere;
- `POST /api/me/privacy/erasure-requests` înregistrează o cerere de ștergere;
- `GET /api/privacy/exports/{export}` întoarce statusul și, pentru un export gata și neexpirat, linkul temporar semnat;
- `GET /api/privacy/exports/{export}/download` descarcă fișierul privat numai cu semnătură validă, înainte de expirare și pentru persoana vizată sau un operator autorizat.

Endpoint-uri administrative:

- `GET /api/users/{user}/privacy/data` și `POST /api/users/{user}/privacy/exports` cer dreptul `gdpr.export`;
- `PATCH /api/users/{user}/privacy/rectification`, `POST /api/users/{user}/privacy/consents`, `POST /api/users/{user}/privacy/erasure-requests` și `POST /api/privacy/requests/{gdprRequest}/process` cer dreptul `gdpr.process`;
- toate verifică explicit că utilizatorul, cererea sau exportul aparține organizației operatorului; accesul cross-tenant răspunde cu 404.

`GeneratePersonalDataExport` procesează asincron exportul și scrie JSON-ul în storage-ul privat, sub `gdpr/{organization_id}/{export_id}.json`. Interogarea persoanei folosește simultan `organization_id` și `user_id`, iar plățile sunt filtrate din nou după organizație. Exportul nu include `provider_payload` sau actorul intern al consimțământului. După generare, `gdpr_exports` primește statusul `ready`, calea și o expirare la 24 de ore. Jobul nu trimite notificări, SMS-uri, nu creează plăți și nu modifică înregistrări financiare.

Procesarea unei cereri de ștergere este implementată de `GdprErasureService`. Workflow-ul șterge recepțiile articolelor, valorile custom, documentele membrului și livrările de notificări fără obligație de retenție; anonimizează referințele și valorile din `audit_logs`; păstrează plățile/chitanțele, dar elimină numele, referințele directe și payload-ul furnizorului; revocă tokenurile și asocierile de acces; apoi transformă contul într-un cont inactiv pseudonimizat. `gdpr_requests.execution_proof` păstrează doar politica, momentul și categoriile de acțiuni executate, iar legăturile directe la persoana eliminată sunt șterse. `DELETE /api/users/{user}` pornește același workflow de retenție în locul ștergerii fizice directe.

Documentele membrilor folosesc tabela `user_documents`, storage privat pe disk-ul `local`, rute sub `/api/users/{user}/documents` si drepturile `user-documents.view`, `user-documents.upload`, `user-documents.delete`. Upload/replace valideaza MIME/extensie si limita de 10 MB si ruleaza scanarea antivirus configurabila prin `CLAMAV_BINARY`. Download-ul se face prin URL temporar semnat si bearer token, iar upload/download/replace/delete sunt auditate.

Tabelele principale sunt `consent_records`, `gdpr_requests` și `gdpr_exports`, împreună cu tabelele clasificate de workflow: `article_user_receipts`, `custom_field_values`, `user_documents`, `notification_deliveries`, `audit_logs`, `payments`, `personal_access_tokens`, `group_user`, `location_user` și `users`. Implementarea se află în `GdprController`, `GeneratePersonalDataExport`, `GdprErasureService`, modelele GDPR din `app/Users/Models`, `routes/user.php` și fișierele `app/Users/OpenApi`. `LogsModelChanges` exclude explicit CNP, parole, tokenuri, payload-uri și secrete din snapshot-urile de audit.

## Setare parolă la creare user și "forgot password"

La crearea unui user (`POST /api/users`, `/api/clients`, `/api/administrators` — toate rulează prin `UserController::store()`), API-ul trimite întotdeauna un e-mail cu un link de setare a parolei, indiferent dacă administratorul a completat deja o parolă în formular (câmpul `password` e nullable). Acest lucru permite fluxul standard „cont creat de admin, userul își setează singur parola".

Există și un flux public de recuperare a parolei pentru useri existenți care au uitat parola:

- `POST /api/password/forgot` — primește `email` și `organization_id`, caută userul activ pe acea combinație (la fel ca `login`, pentru dezambiguizare multi-tenant) și, dacă există, trimite același tip de e-mail. Răspunde cu un mesaj generic identic indiferent dacă userul a fost găsit, ca să nu permită enumerarea conturilor.
- `POST /api/password/reset` — primește `email`, `organization_id`, `token` și noua parolă (validată cu `PasswordPolicy`, aleasă după dreptul userului țintă: `administrator` sau `operator`), consumă tokenul și schimbă parola.

Ambele rute sunt publice (fără `auth.bearer`) și throttled cu limiter-ul `login` existent, în `routes/user.php`, lângă `/login`.

Mecanism de token, separat de brokerul standard Laravel (`password_reset_tokens` e cheiat pe `email`, iar aici e-mailul se poate repeta între organizații, deci nu e sigur pentru multi-tenant):

- Tabela `password_setup_tokens` (migrația `2026_09_01_000000_create_password_setup_tokens_table.php`) leagă tokenul de `user_id`, nu de e-mail.
- `PasswordSetupTokenService` generează un token random de 64 caractere, îl stochează hashuit (sha256, ca la `PersonalAccessToken`), invalidează orice token neconsumat anterior al aceluiași user, și îl expiră după `config('security.tokens.password_setup_expiration_minutes')` (env `PASSWORD_SETUP_TOKEN_EXPIRATION_MINUTES`, implicit 1440 minute = 24h).
- Tokenul este single-use: la reset reușit se marchează `used_at` și nu mai poate fi refolosit.
- Schimbarea parolei declanșează hook-ul existent din `User::booted()`, care șterge toate `accessTokens` ale userului — sesiunile vechi sunt revocate automat.

E-mailul (`App\Users\Mail\PasswordSetupMail`, cu view `resources/views/emails/users/password-setup.blade.php`) este trimis direct prin `Mail`, **fără** să treacă prin sistemul generic `NotificationRequested` → `NotificationDelivery`. Motivul: acela e condiționat de consimțământul userului pe canalul `mail`, gândit pentru notificări opționale (activare cotizație etc.), iar setarea/resetarea parolei este un e-mail tranzacțional obligatoriu — fără el userul nu se poate autentifica deloc (parola e nullable la creare).

Linkul din e-mail duce spre UI, nu spre acest API, și este construit din URL-ul propriu al organizației, nu dintr-o singură valoare globală: `rtrim($user->organization?->url ?: config('app.frontend_url'), '/') . '/set-password?token=...&email=...'`. Fiecare organizație are acum o coloană `url` (migrația `2026_09_01_000002_add_url_to_organizations_table.php`, nullable, în `Fillable` pe `Organization`) — pentru că `sifu-ui` e servit pe origini diferite per organizație, iar link-ul trimis unui user trebuie să deschidă exact origine-a organizației lui, nu un singur domeniu implicit. Frontend-ul își rezolvă organizația curentă apelând `GET /api/organizations/by-url` cu propria origine, la fiecare încărcare de pagină neautentificată — nu mai printr-un fișier static (`sifu-ui/public/json/organizations.json`, eliminat). `frontend_url` din `config/app.php` (env `FRONTEND_URL`, implicit cade pe `APP_URL`) rămâne doar fallback, folosit când organizația nu are încă `url` completat. Singurul loc unde se setează în prezent `organizations.url` este comanda `artisan create:organisation --url=...` (opțiune nouă, opțională, validată cu regula `url`); nu există endpoint HTTP de creare/editare organizații. Coloana `url` este normalizată automat la salvare (hook `Organization::booted()`, `static::saving`): orice `/` final este tăiat, ca să nu existe ambiguitate între forma stocată și cea căutată.

`GET /api/organizations/slug/{slug}` (`OrganizationController::showBySlug`) și `GET /api/organizations/by-url` (`OrganizationController::showByUrl`) expun ambele `url`, la fel ca restul câmpurilor publice ale organizației (`web`, `email`, etc.), prin `OrganizationResource` comun.

Cod relevant:

- `app/Users/Http/Controllers/Api/UserController.php` (`store()` → `sendPasswordSetupEmail()`)
- `app/Users/Http/Controllers/Api/PasswordResetController.php`
- `app/Users/Http/Controllers/Api/OrganizationController.php` (`showBySlug()`, `showByUrl()`)
- `app/Users/Http/Resources/OrganizationResource.php`
- `app/Users/Http/Requests/ForgotPasswordRequest.php`, `app/Users/Http/Requests/ResetPasswordRequest.php`
- `app/Users/Services/PasswordSetupTokenService.php`
- `app/Users/Models/PasswordSetupToken.php`, `app/Users/Models/Organization.php`
- `app/Users/Mail/PasswordSetupMail.php`
- `app/Console/Commands/CreateOrganizationAdmin.php`
- `app/Users/OpenApi/PasswordResetApiEndpoints.php`
- `tests/Feature/PasswordResetTest.php`, plus `test_creating_user_sends_password_setup_email` în `tests/Feature/UserCrudTest.php`, `tests/Feature/OrganizationLookupTest.php`, `tests/Feature/CreateOrganizationAdminCommandTest.php`

Observații:

- `.env`/`.env.example` conțin `FRONTEND_URL` și `PASSWORD_SETUP_TOKEN_EXPIRATION_MINUTES`; valoarea din `.env` local e doar un placeholder egal cu `APP_URL`, folosit acum doar ca fallback pentru organizațiile fără `url` propriu.

## Setări SMTP per organizație (`smtp_settings`)

Fiecare organizație poate avea propria configurație de server de ieșire pentru e-mail (tabela `smtp_settings`), folosită în locul mailerului implicit din `.env` (`MAIL_*`) când există și e activă.

CRUD complet, tip „singleton" (o singură înregistrare per organizație, fără `{id}` în URL, la fel ca `/api/me`):

- `GET /api/smtp-settings` — citește setările organizației curente (drept `smtp_settings.view`); `404` dacă nu sunt configurate.
- `POST /api/smtp-settings` — creează setările (drept `smtp_settings.manage`); `422` dacă organizația are deja o configurație (folosește update în loc).
- `PUT`/`PATCH /api/smtp-settings` — actualizează (drept `smtp_settings.manage`); un `password` gol/omis păstrează parola existentă, nu o șterge.
- `DELETE /api/smtp-settings` — șterge configurația (drept `smtp_settings.manage`); organizația revine automat la mailerul implicit al sistemului.

Câmpuri: `host`, `port`, `username`, `password` (criptat în DB cu cast Eloquent `encrypted`, niciodată expus în JSON — resursa expune doar `has_password`, un boolean), `encryption` (`tls`/`ssl`/`null`), `from_address`, `from_name`, `active`. Modelul folosește `organization_id` unic (o singură configurație per organizație) și moștenește scoping-ul standard prin `BelongsToAuthenticatedOrganization`/`SetsOrganizationFromAuthenticatedUser`, deci accesul e izolat per tenant ca la restul resurselor organization-scoped.

Folosire efectivă la trimiterea de e-mailuri: `App\Users\Services\OrganizationMailerService::mailerNameFor(Organization $organization)` înregistrează la runtime (`config(['mail.mailers.organization_{id}' => ...])`) un mailer Laravel dinamic de tip `smtp` construit din `smtp_settings`, doar dacă înregistrarea există, e `active`, și are `host`/`from_address` completate; altfel întoarce `null` și apelantul cade pe mailerul implicit din `config('mail.default')`. Sunt cablate două puncte de trimitere:

- `App\Users\Mail\PasswordSetupMail::build()` — apelează `OrganizationMailerService::apply()`, care setează `->mailer(...)` și `->from(...)` pe Mailable înainte de trimitere/queue. Configurarea mailerului rulează din nou de fiecare dată când jobul de queue procesează efectiv mail-ul (în `build()`), nu doar la momentul dispatch-ului — necesar pentru că un worker de queue pornește într-un proces nou, care nu moștenește `config()` setat la runtime în procesul HTTP original.
- `App\Notifications\Services\NotificationSender::mail()` (canalul `mail` din pipeline-ul generic de notificări) — alege mailerul organizației userului destinatar în același fel, cu fallback la `config('mail.default')`.

Cod relevant:

- `app/Users/Models/SmtpSetting.php`, `database/migrations/2026_09_01_000001_create_smtp_settings_table.php`
- `app/Users/Http/Controllers/Api/SmtpSettingController.php`, `Http/Requests/StoreSmtpSettingRequest.php`, `Http/Requests/UpdateSmtpSettingRequest.php`, `Http/Resources/SmtpSettingResource.php`
- `app/Users/Services/OrganizationMailerService.php`
- `app/Users/OpenApi/SmtpSettingApiEndpoints.php`, `app/Users/OpenApi/SmtpSettingSchemas.php`
- `tests/Feature/SmtpSettingCrudTest.php`, plus mailer-wiring tests în `tests/Feature/PasswordResetTest.php`

Observații:

- Drepturile `smtp_settings.view`/`smtp_settings.manage` sunt seedate în `DatabaseSeeder`; grupul `manager` primește doar `smtp_settings.view`.
- SMS-urile (canalul `sms`) nu sunt afectate — folosesc în continuare `SmsPortalService`/`config/services.php`, nu `smtp_settings`.

## Abonamente și limite pentru organizații

Pachetele comerciale sunt stocate în `organization_plans`: Start (30 EUR/lună, 50 membri activi, 1 locație, 30 apariții/lună, 1 administrator), Plus (60 EUR, 150 membri, 3 locații, 100 apariții/lună, 2 administratori), Pro (100 EUR, 500 membri, 10 locații, apariții și administratori nelimitați). Prețurile sunt informative; această funcționalitate nu facturează, nu încasează și nu expiră abonamente.

`organizations.plan_id` selectează pachetul, implicit Start pentru organizațiile noi și cele existente. `organization_limit_overrides` conține excepții unice pe organizație și `resource` (`members`, `locations`, `events`, `administrators`). Lipsa excepției înseamnă moștenire; `value=NULL` înseamnă nelimitat; zero blochează consumul. Excepțiile au prioritate față de pachet. Modificările DB sunt citite la cererea următoare, fără cache persistent. Modificarea unui pachet afectează toate organizațiile care îl folosesc, exceptând limitele personalizate.

Membrii sunt conturile active fără drepturi atribuite în afară de `profile.view`; administratorii sunt conturile active cu alte drepturi atribuite prin grupurile organizației, inclusiv drepturi temporar dezactivate prin `OrganizationAccessService`. Proprietarul contează ca administrator, un utilizator se numără o singură dată și conturile inactive nu consumă locuri. Ruta `/clients` sau `/administrators` nu determină clasificarea: drepturile grupurilor o determină. Toate locațiile existente se numără, inclusiv cele invizibile operatorului; modelul nu are arhivare. Consumul este întotdeauna agregat pentru întreaga organizație.

Aparițiile `scheduled` și `completed` se numără lunar după `occurrence_date`; `cancelled` nu consumă. Luna implicită este cea curentă în fusul aplicației (UTC în configurația actuală). Sunt numărate aparițiile deja generate, inclusiv cele viitoare din fereastra existentă de două luni; nu se rezervă capacitate pentru întreaga durată a seriilor recurente. Regenerarea verifică diferența netă, păstrând istoricul existent. Crearea sau modificarea manuală este atomică pe toate lunile afectate.

### API și autorizare

- `GET /api/organization/subscription?month=YYYY-MM`: pachet, preț, limite efective și sursa lor (`plan`/`override`), consum, disponibil, depășiri și blocaje ale generatorului de recurențe.
- `POST /api/organization/subscription/check`: primește `resource`, `quantity` (întreg pozitiv) și `month` obligatoriu pentru evenimente; întoarce `allowed` și detaliile consumului proiectat. Este doar o verificare comercială, fără rezervare și fără acordarea dreptului de a efectua operația.

Ambele endpoint-uri folosesc bearer auth și dreptul `organization_subscription.view`; organizația este exclusiv cea a utilizatorului autentificat. Dreptul este adăugat la migrare grupurilor care au deja drepturi administrative și inclus în bootstrap/seedere. Nu există endpoint HTTP de schimbare a pachetului sau limitelor. Datele abonamentului nu sunt adăugate în endpoint-urile publice de identificare a organizației.

Preflight-ul negativ răspunde HTTP 200 cu `data.allowed=false`. Operațiile efective care cresc consumul peste limită răspund HTTP 409 cu `message` în română, `code=organization_limit_exceeded`, `resource`, `limit`, `used`, `requested`, `projected`, `period` (YYYY-MM pentru evenimente, null în rest). Autentificarea folosește 401, lipsa dreptului 403, validarea 422. Operațiile existente peste limită pot fi citite, reduse sau modificate fără creșterea resursei depășite; nu se șterg automat date.

Implementarea este în `OrganizationSubscriptionService`, `OrganizationSubscriptionController`, Form Requests și `OrganizationSubscriptionResource`, cu rute în `routes/user.php`. `EnforceOrganizationLimits` aplică serviciul tranzacțional pe scrierile din users/clients/administrators, groups/rights, locations, events/event-occurrences. Schimbările de drepturi globale verifică toate organizațiile, cu blocare în ordinea ID-urilor. Verificarea consumului final și scrierea se fac în aceeași tranzacție, după blocarea organizației; orice depășire anulează și asocierile/auditul scrierii. E-mailul de configurare a parolei este programat după commit, pentru a nu trimite acces pentru un cont refuzat. Validarea group_ids la creare/update verifică organizația.

Generatorul `EventOccurrenceGeneratorService` reutilizează același serviciu, inclusiv la apelare din job. `ExtendRecurringEventOccurrences` extinde seriile în ordinea ID-urilor. Dacă o serie nu încape integral, extinderea ei este anulată, cursorul și aparițiile rămân intacte, iar jobul continuă cu celelalte serii. Tabela `organization_event_limit_blocks` păstrează blocajul per eveniment; API-ul îl expune și jobul îl jurnalizează numai când detaliile se schimbă. Următoarea execuție reîncearcă. Succesul, modificarea acceptată sau dezactivarea/ștergerea evenimentului elimină blocajul. Blocarea nu trimite notificări și nu creează plăți.

### Configurare operațională

```bash
php artisan organization:subscription:show 12 --month=2026-09
php artisan organization:subscription:set 12 --plan=plus
php artisan organization:subscription:set 12 --members=200 --locations=4
php artisan organization:subscription:set 12 --administrators=unlimited
php artisan organization:subscription:set 12 --reset=members
php artisan organization:subscription:set 12 --reset-all
```

Schimbarea pachetului păstrează excepțiile. `--reset` este repetabil; `--reset-all` elimină toate excepțiile. Nu combina setarea și resetarea aceleiași resurse. Valorile CLI sunt întregi între 0 și 2147483647 sau `unlimited`. `create:organisation --plan=plus` selectează pachetul la creare; implicit este Start. CLI-ul scrie `organization.subscription.updated` în `audit_logs`, cu valorile anterioare/noi. Modificările SQL directe nu produc automat audit sau validare de aplicație.

Exemple SQL (într-o tranzacție, după blocarea organizației) pentru administrare directă:

```sql
START TRANSACTION;
SELECT id FROM organizations WHERE id = 12 FOR UPDATE;
UPDATE organizations SET plan_id = (SELECT id FROM organization_plans WHERE code = 'plus'), updated_at = CURRENT_TIMESTAMP WHERE id = 12;
DELETE FROM organization_limit_overrides WHERE organization_id = 12 AND resource = 'members';
INSERT INTO organization_limit_overrides (organization_id, resource, value, created_at, updated_at)
VALUES (12, 'members', 200, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
COMMIT;
```

Pentru nelimitat folosește SQL `NULL`; pentru revenire la moștenire șterge doar rândul excepției. La modificarea unui pachet global, blochează în ordine crescătoare organizațiile care îl folosesc înainte de actualizarea pachetului. Folosește întregi nenegativi și nu șterge pachetul Start: este implicitul organizațiilor noi. Modificările SQL concurente trebuie să respecte aceleași blocări ca serviciul/CLI-ul.

Migrarea `2026_09_11_000001_create_organization_subscriptions.php` creează și inițializează tabelele, atribuie Start și adaugă dreptul fără ștergerea datelor existente. La deploy rulează migrările înainte să servești noul cod și repornește workerii. Verifică organizațiile deja peste limite cu comanda `show` și ajustează pachetul/excepțiile după nevoie. Testele funcționale sunt în `OrganizationSubscriptionTest`; Swagger documentează endpoint-urile și contractul 409.

## Organizație demo

`php artisan demo:reset` creează sau resetează organizația demonstrativă. În configurația Docker actuală: `docker compose exec -T app-sifu php artisan demo:reset`. Comanda este exclusiv CLI, fără endpoint sau drept API nou; nu rulează automat în scheduler.

- La prima rulare creează `Club Demo`, slug `demo`, cu planul `pro` și `organizations.is_demo = true`. Resetarea selectează exclusiv marcajul `is_demo`, nu numele sau slug-ul; refuză fără ștergeri un conflict cu o organizație nemarcată ori existența mai multor organizații demo.
- Autentificare: `demo@club.com` / `password`, împreună cu ID-ul organizației demo în `POST /api/login`. ID-ul organizației și al administratorului se păstrează. Referința internă `organizations.demo_admin_user_id` permite restabilirea contului chiar dacă vizitatorii îi modifică e-mailul, parola, starea sau grupurile; dacă acel cont a fost șters, este recreat. Cele două câmpuri interne nu sunt mass-assignable prin API.
- Administratorul este reactivat și readăugat în grupul admin la fiecare reset. Tokenurile bearer existente ale acestui cont se păstrează la reset; modificările normale de parolă prin API continuă să revoce sesiunile. Membrii suplimentari sunt șterși și recreați.
- Setul demonstrativ conține 10 utilizatori inclusiv administratorul, 2 locații, 4 servicii, 6 assignment-uri activate, 8 plăți, 3 evenimente cu câte 3 ocurențe, 6 check-in-uri, 3 articole (`published`, `draft`, `expired`), 4 definiții de câmpuri personalizate cu 9 valori, 3 SMS-uri, 3 livrări de notificări, 3 campanii și 3 segmente. Fiecare secțiune cerută rămâne sub plafonul de 10; dashboard-ul și rapoartele se calculează din aceste date. Numele și valorile sunt fixe, datele calendaristice sunt relative la ziua resetării, iar ID-urile datelor recreate pot varia.
- `DemoOrganizationResetService` șterge datele tenantului într-o tranzacție, inclusiv rândurile soft-deleted, istoricul de audit, pivoturile și datele suplimentare adăugate de vizitatori: documente, grade, exporturi, cereri GDPR, preferințe, dispozitive push, SMTP și limite personalizate. Fișierele private ale documentelor/exporturilor sunt șterse după commit, iar cache-ul definițiilor custom este invalidat. Grupurile sunt păstrate; grupurile de bază sunt asigurate cu nume `demo-{organization_id}-{admin|manager|staff}`, deoarece numele grupurilor sunt global unice. Planul revine la `pro`.
- `DemoOrganizationSeeder` este apelat doar prin serviciul de reset. Activările folosesc `ServiceLifecycleService::activate(..., notify: false)`: validarea plății și auditul de activare rămân active, notificarea de activare este omisă explicit. Fluxurile obișnuite păstrează implicit `notify: true`. Mesajele demo sunt inserate direct în starea `sent`; campaniile sunt deja `sent`, astfel încât nu sunt preluate de scheduler. Utilizatorii demo nu au consimțăminte de notificare, numere de telefon sau tokenuri push. Seed-ul nu trimite mesaje, nu apelează provideri și nu creează joburi de livrare.
- Un lock de cache serializează resetările concurente. Erorile din ștergere sau seed anulează tranzacția. Catalogul comun `ApplicationRights` este reutilizat de bootstrap-ul administratorului, `DatabaseSeeder` și demo pentru aceleași drepturi.

Fișiere principale: `app/Console/Commands/ResetDemoOrganization.php`, `app/Users/Services/DemoOrganizationResetService.php`, `database/seeders/DemoOrganizationSeeder.php`, `database/seeders/ApplicationRights.php`, migrația `2026_09_11_000002_add_demo_identity_to_organizations.php`. Teste: `tests/Feature/DemoOrganizationResetTest.php`.

## Șabloane email per organizație

`email_templates` stochează personalizări text simplu pentru `account.created`, `event.attached`, `payment.confirmed`, unice per organizație/tip. CRUD-ul `/api/email-templates` și catalogul `/api/email-templates/types` folosesc drepturile `email_templates.view`/`email_templates.manage`. Lipsa/ștergerea unei personalizări folosește conținutul implicit din `EmailTemplateService`. Emailul obligatoriu de cont existent acceptă personalizare și păstrează linkul de setare a parolei; resetarea parolei rămâne neschimbată. Noile înscrieri registered/attended (inclusiv bulk, viitoare și check-in) și plățile confirmate emit notificări exclusiv mail cu consimțământ. Plata atașează chitanța PDF. `NotificationRequested` se emite după commit, iar cheia plății/înscrierii împiedică duplicatele de livrare. SMTP-ul organizației este rezolvat la trimitere. Resetarea demo șterge personalizările. Rutele, payload-urile, variabilele, fallback-ul și instalarea drepturilor sunt documentate în [ghidul UI](email-templates-ui.md).
