# Prompt Codex: CRM si retentie membri

Adauga fluxuri CRM pentru lead-uri, trial-uri si retentie membri.

Scop business: sala trebuie sa urmareasca persoane interesate, participari de proba, conversie in abonament si membri in risc de renuntare.

Analizeaza inainte:

- users/clients
- campaigns si segments
- articles/announcements
- service lifecycle si attendance reports

Cerinte backend:

- Modeleaza lead-uri sau extinde userii cu status CRM, alegand varianta care pastreaza clar istoricul si nu amesteca membrii activi cu prospectii.
- Suporta stadii: lead nou, contactat, programat trial, trial efectuat, convertit, pierdut.
- Permite notite, sursa lead si urmatorul follow-up.
- Integreaza conversia in membru existent sau nou.
- Adauga segmente utile pentru inactivitate si trial neconvertit.
- Protejeaza cu drepturi dedicate sau `users.manage/campaigns.manage`.
- Auditeaza schimbarile de status.

Cerinte frontend:

- Adauga ecran CRM/Lead-uri.
- Permite pipeline simplu, filtre si actiuni rapide.
- Din profilul membrului, afiseaza risc de inactivitate si ultimul contact.
- Integreaza cu campanii unde exista segmente.
- Adauga localizari `ro/en/uk`.

Teste:

- lead-ul se converteste fara pierderea datelor
- lead-urile sunt scoped pe organizatie
- follow-up-urile se filtreaza dupa data
- utilizator fara drept nu poate modifica pipeline-ul

Acceptance criteria:

- Managerul poate urmari conversia trial -> membru platitor si membrii care necesita follow-up.

