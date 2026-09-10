# Prompt Codex: Acces pe baza de abonament

Extinde modelul de abonamente/servicii pentru acces real intr-o sala de arte martiale. Backend-ul este Laravel API in `erp-laravel`; frontend-ul este React/Vite in `erp-ui`. Respecta multi-organization, bearer auth, rights si documentatia obligatorie.

Scop business: managerul trebuie sa defineasca abonamente nelimitate, abonamente cu numar de intrari, acces pe discipline/clase si reguli clare de consum.

Analizeaza inainte:

- migratiile si modelul `Service`
- pivotul `service_user`
- `ServiceLifecycleService`
- `EventEligibilityService`
- fluxurile de payment activation si participant add

Cerinte backend:

- Clarifica si normalizeaza regulile de acces: nelimitat, numar limitat de intrari, perioada fixa, perioada calculata din durata.
- Daca schema existenta are deja `max_accesses`, foloseste-o si completeaza lipsurile; nu duplica inutil conceptele.
- Pastreaza un istoric auditable pentru consumul intrarilor, inclusiv clasa/aparitia care a consumat intrarea.
- Asigura restaurarea unei intrari cand participarea este stearsa sau anulata, daca business rule-ul aleasa cere acest lucru.
- Valideaza ca un membru poate folosi doar servicii active, neexpirate, din aceeasi organizatie.
- Adauga endpoint-uri/API resource fields pentru sold intrari, status acces si motive de ineligibilitate.
- Protejeaza operatiile cu `services.view`, `services.update` sau drepturi dedicate.

Cerinte frontend:

- In formularul de serviciu, afiseaza clar tipul de acces si limitele.
- In profilul membrului, afiseaza intrari ramase, valabilitate, status si istoric consum.
- In fluxul de evenimente/check-in, arata ce abonament va fi consumat.
- Adauga localizari `ro/en/uk`.

Teste:

- abonament nelimitat permite check-in fara scadere sold
- abonament cu intrari scade exact o intrare
- accesul expirat sau fara intrari este respins
- stergerea/anularea participarii reface soldul daca regula este implementata
- tenant isolation si drepturi insuficiente

Acceptance criteria:

- Regulile de acces sunt unice, documentate si folosite de evenimente, check-in si profil membru.
- Nu se pierde istoricul de plata sau activare al assignment-urilor existente.

