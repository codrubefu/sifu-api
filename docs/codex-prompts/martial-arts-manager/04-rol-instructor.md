# Prompt Codex: Rol de instructor/antrenor

Adauga suport explicit pentru instructori intr-o sala de arte martiale. Proiectul are deja users, groups, rights, events si location access.

Scop business: un instructor trebuie sa vada clasele proprii, sa marcheze prezenta si sa adauge observatii, fara acces complet la administratie financiara.

Analizeaza inainte:

- modelul `User` si grupurile/drepturile
- evenimentele si participantii
- `LocationAccessScope`
- sidebar-ul si `permissions.ts`

Cerinte backend:

- Decide daca instructorul este un `User` cu grup/drepturi sau necesita relatie dedicata; prefera reutilizarea `users`.
- Permite asignarea unuia sau mai multor instructori la evenimente/aparitii.
- Expune endpoint-uri pentru clasele instructorului autentificat.
- Permite instructorului sa modifice doar prezenta/statusul participantilor la clasele proprii.
- Adauga drepturi dedicate daca este necesar, de exemplu `instructor.classes.view` si `instructor.attendance.manage`.
- Protejeaza accesul financiar si administrativ.
- Adauga audit pentru modificari de prezenta facute de instructor.

Cerinte frontend:

- Adauga o vedere "Clasele mele" pentru instructor.
- Afiseaza lista de participanti si actiuni rapide pentru prezent/absent.
- Ascunde zonele de plati, rapoarte financiare si administrare daca drepturile lipsesc.
- Adauga localizari `ro/en/uk`.

Teste:

- instructorul vede doar clasele asignate
- instructorul nu poate modifica clasele altui instructor
- adminul pastreaza acces complet
- tenant isolation

Acceptance criteria:

- Un cont de instructor poate opera prezenta zilnica fara sa primeasca drepturi de manager.

