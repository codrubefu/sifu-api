# Emailuri automate și șabloane per organizație — integrare UI

## Comportament

- `account.created`: emailul existent de bun venit și setare a parolei, la `POST /api/users`, `/api/clients`, `/api/administrators`. Nu se trimite un al doilea email de bun venit. Funcționează și fără consimțământ pentru notificări, fiind necesar accesului la cont. API-ul nu are un flux public nou de înregistrare. Resetarea parolei rămâne pe șablonul existent.
- `event.attached`: la o înscriere nouă cu status `registered` sau `attended` într-o ședință concretă. Acoperă înscrierea individuală, bulk, copierea pe ședințe viitoare și înscrierea nouă prin check-in. Câte un email per participant și ședință. Modificarea unei înscrieri existente nu retrimite emailul.
- `payment.confirmed`: la confirmarea plății, pentru cotizații și participări la evenimente. Numerar/card se confirmă imediat; transferul bancar trimite după callback-ul de confirmare. Plățile inițiate, eșuate sau anulate nu declanșează email. Callback-ul repetat nu creează livrări duplicate.
- Plata trimite destinatarului asociat cotizației/participării **chitanța PDF** emisă de aplicație, cu numărul chitanței în numele fișierului. Nu se emite o factură nouă prin acest flux. Descărcarea manuală rămâne `GET /api/payments/{payment}/receipt`.
- Evenimentul și plata folosesc exclusiv canalul `mail`, verifică acordul la creare și din nou la livrare. Preferința `all` poate bloca livrarea. Configurarea conținutului nu acordă automat consimțământ.
- Notificările sunt lansate după commit; rollback-ul nu trimite email. Se reutilizează `notification_deliveries`, `notification_attempts` și jobul cu 4 încercări/backoff 60, 300, 900 secunde. Nu există garanție exact-once a transportului SMTP în cazul unei întreruperi după acceptarea mesajului de către server.
- SMTP-ul activ al organizației este folosit în worker; în lipsa acestuia se folosește mailerul aplicației. Configurarea SMTP rămâne la `/api/smtp-settings`.

## Rute noi

Toate folosesc `Authorization: Bearer <token>` și `Accept: application/json`. Organizația este dedusă din userul autentificat; nu se trimite `organization_id`.

| Metodă | Rută | Drept | Răspuns |
| --- | --- | --- | --- |
| GET | `/api/email-templates/types` | `email_templates.view` sau `email_templates.manage` | 200, tipuri, variabile și conținut implicit |
| GET | `/api/email-templates` | view sau manage | 200, toate personalizările organizației, fără paginare |
| POST | `/api/email-templates` | `email_templates.manage` | 201, creează personalizare |
| GET | `/api/email-templates/{id}` | view sau manage | 200, detalii |
| PUT/PATCH | `/api/email-templates/{id}` | `email_templates.manage` | 200, actualizează câmpurile furnizate |
| DELETE | `/api/email-templates/{id}` | `email_templates.manage` | 204, fără corp; revine la conținutul implicit |

Un singur șablon per tip și organizație. Lista poate fi goală: emailurile funcționează deja cu valorile implicite. Pentru UI, combinați catalogul `types` cu lista de personalizări după `type`. Afișați „Personalizează” când lipsește un ID și „Salvează / Revino la implicit” când există. Ștergerea nu dezactivează trimiterea.

## Câmpuri și variabile

`POST`: `type`, `subject`, `body` obligatorii. `PUT/PATCH`: numai `subject` și/sau `body`; câmpurile omise se păstrează, `type` este imuabil. Subiectul are maximum 255 caractere și nu permite linii noi; corpul maximum 20.000 caractere. Valorile goale/null sunt respinse. Text simplu, nu HTML/Blade; UI poate folosi textarea. La afișarea în UI tratați conținutul ca text, fără `innerHTML`.

Sintaxa exactă este `{{variabila}}`, fără spații în interior. Variabile necunoscute sunt respinse cu 422. Înlocuirea nu execută cod și nu interpretează recursiv valorile ca șabloane.

| Tip | Variabile |
| --- | --- |
| `account.created` | `first_name`, `last_name`, `organization`, `setup_url` |
| `event.attached` | `first_name`, `last_name`, `organization`, `event`, `starts_at` |
| `payment.confirmed` | `first_name`, `last_name`, `organization`, `amount`, `receipt_number` |

Corpul pentru cont **trebuie** să includă `{{setup_url}}`. Linkul real este generat doar în email din URL-ul organizației și tokenul dedicat; nu este expus de CRUD. `starts_at` este data/ora ședinței în ISO 8601 cu offset. `amount` este valoarea numerică stocată, fără monedă adăugată automat. Chitanța este atașată independent de textul șablonului.

Exemplu creare:

```http
POST /api/email-templates
Content-Type: application/json
```
```json
{
  "type": "payment.confirmed",
  "subject": "{{organization}} — plata {{receipt_number}}",
  "body": "Bună, {{first_name}}!\nAm primit plata de {{amount}}. Chitanța {{receipt_number}} este atașată."
}
```

Răspuns 201 (aceeași structură `data` la GET detalii și update):

```json
{
  "data": {
    "id": 12,
    "organization_id": 3,
    "type": "payment.confirmed",
    "subject": "{{organization}} — plata {{receipt_number}}",
    "body": "Bună, {{first_name}}!\nAm primit plata de {{amount}}. Chitanța {{receipt_number}} este atașată.",
    "created_at": "2026-09-13T10:00:00.000000Z",
    "updated_at": "2026-09-13T10:00:00.000000Z"
  }
}
```

Lista răspunde cu `{"data": [...]}`. Catalogul are aceeași anvelopă și elemente cu `type`, `variables` (nume fără acolade), `subject`, `body` (valorile implicite).

Exemplu update: `PATCH /api/email-templates/12` cu `{"subject":"Confirmare {{receipt_number}}"}`.

Erori: 401 fără autentificare, 403 fără drept, 404 pentru ID inexistent sau din altă organizație, 422 pentru tip duplicat, câmpuri invalide sau lipsa linkului de setare a parolei. Erorile de validare au `message` și `errors` mapat pe câmpuri.

## Instalare și operare

```bash
docker compose exec -T app-sifu php artisan migrate
docker compose exec -T app-sifu php artisan db:seed --class=EmailTemplateRightsSeeder
docker compose exec -T app-sifu php artisan queue:restart
```

Seederul dedicat adaugă doar drepturile, fără să modifice grupurile existente. Atribuiți `email_templates.view`/`email_templates.manage` grupurilor dorite prin administrarea existentă a grupurilor. Instalările noi primesc definițiile prin `ApplicationRights`; seederul standard acordă drepturile grupului admin.

Migrarea creează `email_templates` cu FK organizație și unicitate organizație/tip. Nu este necesară seedarea conținutului. Personalizările sunt rezolvate la trimitere, deci o modificare poate afecta și emailurile încă în coadă. Resetarea organizației demo șterge personalizările. Nu există endpoint nou de trimitere manuală sau previzualizare cu date reale.

Verificări automate: CRUD, drepturi și izolare tenant, validare variabile, fallback după ștergere, email de cont cu conținut escap-at, resetare neschimbată, plată confirmată idempotentă cu PDF, notificare de înscriere după commit și consimțământ. Contractele sunt incluse și în OpenAPI.
