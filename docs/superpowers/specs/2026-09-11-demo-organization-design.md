# Demo organization + reset — design

Date: 2026-09-11

## Problem

We want a permanent "demo" organization that showcases every module of the
product with a small, realistic dataset, with a fixed admin login
(`demo@club.com` / `password`), and a safe way to wipe it back to a pristine
state (discarding both the original seed data and anything added later by
people trying the demo) and reseed it identically.

## Identification: `is_demo` flag

Add a `is_demo` boolean column to `organizations` (migration,
default `false`). The reset command only ever targets the organization row
where `is_demo = true`. This is the safety rail: even if the slug or name
changes, or the command is invoked in the wrong environment, it can never
wipe a real customer organization by accident, because no real organization
should ever have `is_demo = true`.

The demo organization is created with:
- `slug`: `demo`
- `name`: `Club Demo`
- `plan`: `pro` (highest existing plan — `null` limits on events/administrators,
  500 members, 10 locations — so the seeded data and any manual poking by a
  demo visitor never trips `EnforceOrganizationLimits`)
- `is_demo`: `true`

## Command: `php artisan demo:reset`

One idempotent artisan command, `app/Console/Commands/ResetDemoOrganization.php`:

1. Find organization where `is_demo = true`.
2. If none exists: create it (org row, `admin`/`manager`/`staff` groups scoped
   to the org — same shape as `CreateOrganizationAdmin` — and the fixed admin
   user `demo@club.com` / `password` in the `admin` group).
3. If it exists: wipe all of its child data (see below), but keep the
   organization row and its id. The admin user row is kept (not deleted
   and recreated, to avoid churning its id/tokens) but its identity fields
   are force-reasserted every reset (`email = demo@club.com`,
   `password = password`, `active = true`, in the `admin` group) —
   this way the fixed credentials always work again after a reset, even if
   a demo visitor changed the password or deactivated the account.
4. Reseed every section (see below) via a single `DemoOrganizationSeeder`
   (`database/seeders/DemoOrganizationSeeder.php`) that both the "create"
   and "reset" paths call, so creation and reset always produce the exact
   same dataset.

Command is safe to run repeatedly (cron nightly, or manually) and does not
touch any other organization.

## Wipe order

Implemented as a `DemoOrganizationResetService` (`app/Users/Services/`),
following the existing raw-`DB::table()` pattern from `GdprErasureService`
(not Eloquent cascades), inside one `DB::transaction()`, deepest/leaf tables
first, all scoped to the demo organization's id (joining through `user_id`/
`service_id`/`event_id` for tables that don't carry `organization_id`
directly):

1. `notification_attempts` (join via `notification_deliveries.user_id`)
2. `notification_deliveries` (`user_id` in demo org)
3. `sms_messages` (`user_id` in demo org)
4. `campaigns` (`organization_id`)
5. `segments` (`organization_id`)
6. `service_user` (`service_id` in demo org)
7. `services` (`organization_id`)
8. `payments` (`organization_id`)
9. event occurrence participants, then `event_occurrences`, then `events`,
   then `event_categories` (`organization_id`)
10. `custom_field_values` then `custom_fields` (`organization_id`)
11. `article_user_receipts` (join via `articles.organization_id`) then
    `articles`
12. `audit_logs` (`organization_id`) — except we keep none, full delete is
    fine here since this is demo history, unlike the GDPR case
13. `user_documents`, `user_grades` (`organization_id`)
14. Non-admin `users` of the org (delete-and-recreate on every reset) —
    the fixed admin user row is preserved and re-asserted (see step 3),
    not deleted
15. `locations` of the org

Groups (`admin`/`manager`/`staff`) are **not** deleted/recreated on reset —
only ensured to exist (`updateOrCreate`), same as `DatabaseSeeder` does
globally.

## Seeded data per section (cap: 10 records each)

All factories/records are tagged so they're recognizable as demo data where
it matters for support/debugging (e.g. names prefixed `Demo`), but no schema
change is needed for that — it's just naming convention in the seeder.

- **Users**: fixed admin (`demo@club.com`) + up to 9 additional members
  (regular `UserFactory` output, `staff`/no group).
- **Locations**: 2 locations (supporting data for events/check-ins; not one
  of the requested "sections" itself, kept minimal).
- **Service**: up to 10 services (varied `type`/`price`/`duration_days`).
  A handful of members get a `ServiceUser` assignment activated through
  `ServiceLifecycleService::activate()` — **never** created directly via
  `PaymentService`, per the architecture rule in
  `docs/project-rules-agent.md`.
- **Events**: up to 10 events, each with a few generated occurrences; a
  couple of members registered as participants on some occurrences.
- **CheckIns**: no dedicated table — represented by up to 10 `AuditLog`
  rows with `action = AuditLog::CHECKIN_ACCEPTED` (a plausible check-in
  history), tied to demo members and occurrences.
- **Articles**: up to 10, mixed `status` (`draft`/`published`/`expired`).
- **CustomFields**: up to 10 field definitions (mixed `entity_type`/`type`)
  with a few `CustomFieldValue` rows against demo users.
- **Payments**: up to 10 — largely a byproduct of the service activations
  above; topped up with a few standalone historical payments if fewer than
  10 result naturally.
- **Sms**: up to 10 `SmsMessage` rows inserted directly with
  `status = SmsMessage::STATUS_SENT` and a past `sent_at` — no real
  provider call.
- **Notifications**: up to 10 `NotificationDelivery` rows inserted directly
  with a delivered/sent-looking state — no dispatch through
  `NotificationRequested`/queue.
- **Campaigns**: up to 10, created with a terminal `status` (e.g. `sent`,
  never `scheduled` with a past `scheduled_at`) so the existing
  `Schedule::call` in `routes/console.php` never picks them up for real
  dispatch.
- **Reporting**: up to 10 `Segment` rows with representative `criteria`.
- **Dashboard**: nothing to seed — purely computed from the above.

Deliberately out of scope for v1 (no seeding): GDPR requests/exports, SMTP
settings, push devices. Low value for a demo, and each carries its own
footguns (fake device tokens, fake outbound mail credentials).

## Testing

New `tests/Feature/DemoOrganizationResetTest.php`:
- Running `demo:reset` on a fresh database creates the org + admin and
  populates every section, each capped at 10.
- Running it a second time (after manually inserting extra rows into a few
  demo-org tables, simulating a visitor's changes) restores the exact same
  shape — no leftover rows, no duplicate admin, same counts.
- Login with `demo@club.com` / `password` succeeds against the (custom
  bearer token) auth flow after a reset.
- A control non-demo organization's data is untouched by the reset
  (multi-tenant isolation).
- Full suite (`php artisan test`) run once at the end since this touches
  many modules and a migration.

## Documentation

Add a "Organizație demo" section to
`docs/functionality-explainer-agent.md` describing the `demo:reset`
command, the `is_demo` flag, the fixed admin credentials, and the section
caps — before this task is considered done, per repo convention.
