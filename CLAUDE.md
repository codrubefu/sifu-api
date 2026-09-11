# erp-laravel

Backend Laravel API pentru un ERP de sală de arte marțiale / club sportiv, multi-organizație (multi-tenant). Frontend-ul (React/Vite) este într-un repository separat, `erp-ui`.

## Documentație obligatorie de citit

Înainte de orice implementare sau explicație, citește:

- `docs/project-rules-agent.md` — regulile de implementare ale proiectului (arhitectură, securitate, testare, convenții per modul). Sursă unică de adevăr.
- `docs/functionality-explainer-agent.md` — ce face sistemul, endpoint cu endpoint, modul cu modul. Sursă unică de adevăr pentru comportamentul de business existent.
- `docs/deployment-security.md` — cerințe de securitate pentru producție (HTTPS, proxy-uri, secrete, VPN).

Există un subagent dedicat în `.claude/agents/sifu-api-dev.md` care încarcă aceste fișiere automat, atât pentru implementare/review cât și pentru explicarea comportamentului existent (un singur agent — cele două roluri foloseau aceeași sursă de adevăr, nu are sens să pornești două).

**Task trivial → nu porni subagent.** Dacă schimbarea e evidentă dintr-o privire și atinge un singur fișier (sau câteva strâns legate) — editează direct, fără să pornești `sifu-api-dev`. Un subagent pornește fără context și trebuie să recitească `docs/project-rules-agent.md` (450+ linii) de la zero; pentru un task de o linie, costul ăsta depășește task-ul însuși. Dacă ai dubii dacă task-ul e chiar trivial, nu e trivial — folosește subagentul.

**Nu citi documentele mari în întregime pentru un task îngust.** `docs/functionality-explainer-agent.md` are 850+ linii; pentru "cum funcționează X" sau o schimbare pe un singur modul, caută (grep) modulul relevant și citește doar acea secțiune. Citește tot fișierul doar pentru un audit pe tot repo-ul.

**Testare țintită.** Rulează întâi testele modulului schimbat (`php artisan test --filter=<Modul>` sau calea `tests/Feature/...` relevantă); suita completă doar pentru schimbări cross-cutting, migrații, sau auth/plăți, ori ca validare finală.

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
