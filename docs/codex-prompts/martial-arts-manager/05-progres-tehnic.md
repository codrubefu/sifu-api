# Prompt Codex: Progres tehnic al membrilor

Extinde modulul de grade pentru a urmari progres tehnic, tehnici si observatii de instructor.

Scop business: sala trebuie sa poata urmari ce stie fiecare elev, ce ii lipseste pentru urmatorul grad si istoricul observatiilor.

Analizeaza inainte:

- `Grade` si `UserGrade`
- componentele `GradesView` si `MemberFormPage`
- custom fields existente
- event attendance pentru conditii de prezenta

Cerinte backend:

- Adauga entitati pentru cerinte/tehnici pe grad sau foloseste o structura configurabila daca se potriveste mai bine.
- Permite marcarea progresului pe membru: neinceput, in lucru, validat, respins.
- Pastreaza cine a validat, cand si observatii.
- Include endpoint-uri pentru progresul unui membru si pentru configurarea cerintelor unui grad.
- Aplica tenant isolation si drepturi `grades.view/grades.manage` sau drepturi dedicate.
- Auditeaza modificarile importante.

Cerinte frontend:

- In profilul membrului, adauga tab/panou pentru progres tehnic.
- In modulul grade, permite definirea cerintelor pentru fiecare grad.
- Afiseaza progresul catre urmatorul grad intr-un mod compact.
- Adauga localizari `ro/en/uk`.

Teste:

- cerintele sunt scoped pe organizatie
- progresul nu poate fi scris de utilizator fara drept
- istoricul de grade existent ramane functional
- validari pentru statusuri si ownership

Acceptance criteria:

- Managerul poate vedea rapid eligibilitatea tehnica a unui membru pentru urmatorul grad.

