# Prompt Codex: Examene si promovari

Implementeaza workflow pentru examene de grad si promovari in masa.

Scop business: managerul programeaza examen, adauga candidati, incaseaza taxa, marcheaza rezultatul si acorda gradele promovate.

Analizeaza inainte:

- events si event participants
- payments si payable model types
- grades si user grades
- documente/chitante existente

Cerinte backend:

- Adauga model pentru examen sau specializeaza evenimentele prin categorie/tip, alegand varianta cu impact minim.
- Permite candidati cu status: inscris, admis, respins, absent, promovat.
- Leaga optional plata de taxa de examen folosind `payments`.
- La promovare, creeaza `UserGrade` pentru candidatii promovati, atomic si auditat.
- Expune endpoint-uri pentru CRUD examen, candidati, rezultate si promovare finala.
- Protejeaza cu `grades.manage`, `events.manage` si/sau drepturi dedicate.
- Actualizeaza OpenAPI si documentatia.

Cerinte frontend:

- Adauga ecran pentru examene in zona de grade sau evenimente.
- Permite selectie candidati, statusuri, taxa si actiune "Finalizeaza promovari".
- Afiseaza rezultate si istoric examen.
- Adauga localizari `ro/en/uk`.

Teste:

- promovarea creeaza grade o singura data
- candidatii respinsi/absenti nu primesc grad
- plata taxa examen se leaga corect
- tenant isolation, autorizare, tranzactie rollback

Acceptance criteria:

- Un examen complet poate fi gestionat din sistem fara editare manuala a fiecarui membru promovat.

