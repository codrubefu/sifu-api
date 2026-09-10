# Prompt Codex: Alerte operationale pentru manager

Adauga alerte operationale centralizate pentru managerul salii.

Scop business: dashboard-ul trebuie sa arate actiuni urgente: abonamente expirate, plati restante, documente expirate, clase pline, membri absenti si zile de nastere.

Analizeaza inainte:

- `DashboardService`
- reports existente
- notifications layer
- service expirations
- event participation reports

Cerinte backend:

- Adauga un endpoint read-only pentru alerte sau extinde dashboard-ul daca este potrivit.
- Grupeaza alertele dupa severitate si tip.
- Fiecare alerta trebuie sa contina link/context actionabil: membru, serviciu, document, eveniment.
- Nu trimite notificari automat doar pentru ca alerta este calculata.
- Aplica `dashboard.view` sau `reports.view`.
- Asigura query-uri eficiente si bounded.

Cerinte frontend:

- Adauga panou compact in dashboard.
- Permite filtrare/acknowledge local sau server-side doar daca exista cerinta clara; pentru prima versiune prefera read-only.
- Afiseaza stari empty si loading.
- Adauga localizari `ro/en/uk`.

Teste:

- fiecare tip de alerta este calculat corect
- alertele sunt tenant-safe
- endpoint-ul nu produce side effects
- drepturi insuficiente primesc 403

Acceptance criteria:

- Managerul vede din prima pagina ce necesita actiune in ziua curenta.

