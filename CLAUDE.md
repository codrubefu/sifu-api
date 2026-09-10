# erp-laravel

Backend Laravel API pentru un ERP de sală de arte marțiale / club sportiv, multi-organizație (multi-tenant). Frontend-ul (React/Vite) este într-un repository separat, `erp-ui`.

## Documentație obligatorie de citit

Înainte de orice implementare sau explicație, citește:

- `docs/project-rules-agent.md` — regulile de implementare ale proiectului (arhitectură, securitate, testare, convenții per modul). Sursă unică de adevăr.
- `docs/functionality-explainer-agent.md` — ce face sistemul, endpoint cu endpoint, modul cu modul. Sursă unică de adevăr pentru comportamentul de business existent.
- `docs/deployment-security.md` — cerințe de securitate pentru producție (HTTPS, proxy-uri, secrete, VPN).

Există și doi subagenți dedicați în `.claude/agents/` care încarcă aceste fișiere automat:

- `project-rules` — pentru implementare/review de funcționalități.
- `functionality-explainer` — pentru explicarea comportamentului existent.

**Regulă obligatorie**: orice endpoint, job, workflow, permisiune, tabelă sau comportament nou/schimbat trebuie reflectat în `docs/functionality-explainer-agent.md` înainte de a considera task-ul terminat.

## Structură pe module

Cod organizat pe module de business sub `app/`, fiecare cu propriile `Http/Controllers/Api`, `Http/Requests`, `Http/Resources`, `Models`, `Services`, `Jobs`, `OpenApi`:

`Users`, `Service`, `Events`, `CheckIns`, `Articles`, `CustomFields`, `Payments`, `Sms`, `Notifications`, `Campaigns`, `Reporting`, `Dashboard`.

Rutele sunt separate pe module în `routes/*.php` și incluse din `routes/api.php`. Nu adăuga rute noi direct în `routes/api.php`.

## Comenzi de bază

```bash
php artisan test                        # rulare teste
docker compose exec -T app php artisan test   # varianta Docker
```

PHP `^8.3`, Laravel `^13.0`, PHPUnit `^11.5`. Teste: SQLite in-memory, `RefreshDatabase`.

## Puncte critice de arhitectură

- **Autentificare**: bearer tokens custom (`AuthenticateBearerToken`, `BearerTokenService`, `PersonalAccessToken`), NU Sanctum/Passport.
- **Autorizare**: drepturi pe grupuri (`right:modul.view,modul.manage` — oricare dintre drepturi, nu toate).
- **Multi-tenant**: aproape toate tabelele au `organization_id`; folosește `SetsOrganizationFromAuthenticatedUser` / `BelongsToAuthenticatedOrganization`. Nu lăsa niciodată accesul cross-organizație prin ID.
- **Plăți**: activarea unui assignment de serviciu trece exclusiv prin `ServiceLifecycleService::activate()`, niciodată direct din `PaymentService`.
- **Notificări**: flux generic prin `NotificationRequested` → `QueueNotificationDeliveries` → `SendNotificationDelivery`, cu `event_key` stabil pentru idempotență.

Branch-ul curent de dezvoltare este `club`.

Pentru orice altceva, vezi `docs/project-rules-agent.md`.
