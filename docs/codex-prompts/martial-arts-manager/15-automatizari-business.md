# Prompt Codex: Automatizari business configurabile

Adauga un cadru simplu pentru automatizari operationale.

Scop business: sala trebuie sa poata trimite automat remindere si follow-up-uri pentru expirari, absente, documente, examene si zile de nastere.

Analizeaza inainte:

- `Notifications` module
- `Campaigns`
- scheduler in `routes/console.php`
- service expiration jobs existente si overlap-ul documentat
- notification preferences/consents

Cerinte backend:

- Creeaza configuratii tenant-safe pentru reguli automate.
- Pentru v1, implementeaza cateva reguli cu valoare mare: abonament expira in N zile, membru absent de N zile, document expira in N zile, zi de nastere.
- Foloseste `NotificationRequested` si template-uri in `config/notifications.php`.
- Asigura idempotenta prin event_key stabil.
- Respecta consimtamantul pe canal.
- Evita dublarea joburilor existente pentru expirari servicii; rezolva sau documenteaza overlap-ul.
- Adauga scheduler/joburi `withoutOverlapping`.
- Protejeaza configurarea cu drept dedicat sau `campaigns.manage`.

Cerinte frontend:

- Adauga ecran de configurare automatizari.
- Permite activare/dezactivare regula, canal, offset zile si mesaj unde este permis.
- Afiseaza ultima rulare si rezultate sumare daca backend-ul le expune.
- Adauga localizari `ro/en/uk`.

Teste:

- joburile nu trimit duplicate
- consimtamantul este respectat
- regulile sunt scoped pe organizatie
- dezactivarea regulii opreste trimiterile
- rate/queue failures nu creeaza business side effects gresite

Acceptance criteria:

- Managerul poate activa automatizari de baza fara interventie manuala zilnica, iar trimiterile raman auditable si idempotente.

