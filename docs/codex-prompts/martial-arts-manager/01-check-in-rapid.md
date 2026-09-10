# Prompt Codex: Check-in rapid la intrare

Implementeaza un flux de check-in rapid pentru receptia unei sali de arte martiale. Proiectul are backend Laravel API in `erp-laravel` si frontend React/Vite in `erp-ui`. Respecta multi-organization isolation, bearer auth, rights-based permissions si regulile din `docs/project-rules-agent.md`. Actualizeaza documentatia backend si frontend cand adaugi comportament vizibil.

Scop business: operatorul de la receptie trebuie sa poata cauta sau scana un membru, sa vada imediat daca are acces valid astazi si sa marcheze prezenta la clasa/sesiunea curenta cu cat mai putini pasi.

Analizeaza inainte de implementare:

- rutele si modelele existente pentru users, services, events, event occurrences si participants
- logica de eligibilitate din `EventEligibilityService`
- lifecycle-ul din `ServiceLifecycleService`
- componentele UI pentru membri, evenimente si dashboard
- testele pentru participanti, servicii si rapoarte de prezenta

Cerinte backend:

- Adauga endpoint-uri dedicate pentru check-in, de exemplu cautare membru dupa cod/telefon/email si confirmare check-in pe o aparitie de eveniment.
- Refoloseste participantii la `event_occurrence_user` cand check-in-ul este legat de o clasa existenta.
- Returneaza un raspuns clar: membru gasit, abonament activ, servicii eligibile, motiv refuz acces, ultimul check-in relevant.
- Aplica tenant isolation si location access unde se aplica.
- Protejeaza cu drepturi dedicate sau existente, de exemplu `checkins.manage` sau `event_participants.manage`.
- Logheaza audit/business activity pentru check-in acceptat si refuzat.
- Daca se consuma o intrare din abonament, foloseste fluxul existent de consum si pastreaza tranzactia atomica.
- Adauga OpenAPI si teste feature pentru succes, refuz, membru din alta organizatie, drepturi insuficiente si check-in duplicat.

Cerinte frontend:

- Adauga ecran operational pentru receptie, accesibil din sidebar.
- Permite cautare rapida si suport pentru input de scanner tip tastatura.
- Afiseaza status mare si clar: acces permis, acces refuzat, necesita plata, document expirat, deja prezent.
- Permite alegerea clasei curente daca exista mai multe aparitii active.
- Include loading, error, empty state si localizare in `ro/en/uk`.
- Nu recalcula statusul abonamentului in UI daca API-ul returneaza verdictul.

Acceptance criteria:

- Un operator poate face check-in in sub 3 actiuni dupa cautarea membrului.
- Nu se poate marca prezenta pentru membru fara acces valid, cu exceptia unei optiuni explicite permise prin drept.
- Datele cross-tenant nu sunt expuse.
- Testele relevante backend si build/test frontend trec.

