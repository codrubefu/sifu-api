<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Multi-organization support

The API now includes an organization layer for tenant isolation:

- Core domain tables include an `organization_id` foreign key.
- Records are automatically assigned to the authenticated user's organization on create when `organization_id` is not explicitly provided.
- This behavior is centralized in `App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser`.

To apply the schema changes, run:

```bash
php artisan migrate
```

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

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
