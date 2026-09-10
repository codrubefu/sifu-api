# Prompt Codex: Rezervari si capacitate

Implementeaza rezervari explicite pentru clase, capacitate si lista de asteptare.

Scop business: managerul trebuie sa controleze ocuparea claselor, iar membrii/operatorii sa poata rezerva locuri fara suprapopulare.

Analizeaza inainte:

- `event_occurrence_user`
- statusurile participantilor
- `EventEligibilityService`
- quick add participants
- calendar UI

Cerinte backend:

- Clarifica statusurile participantilor: reserved/registered, present, absent, cancelled, waitlisted.
- Aplica `max_participants` sau capacitatea locatiei daca exista; daca lipseste, adauga camp minim necesar.
- Cand clasa este plina, permite waitlist daca este activat.
- La anularea unui loc, promoveaza primul din waitlist doar daca regula este documentata si testata.
- Pastreaza compatibilitatea cu rapoartele de participare.
- Protejeaza operatiile admin cu `event_participants.manage`; self-service cu reguli `/me` daca se implementeaza.

Cerinte frontend:

- In calendar si pagina de participanti, afiseaza ocupare `ocupat/total`.
- Marcheaza clasele pline si waitlist.
- Permite schimbare status participant.
- Afiseaza motive cand rezervarea este respinsa.
- Adauga localizari `ro/en/uk`.

Teste:

- nu se depaseste capacitatea
- waitlist-ul functioneaza tenant-safe
- anularea actualizeaza statusul fara pierderea istoricului
- rapoartele includ/exclud statusurile corect

Acceptance criteria:

- Sistemul previne suprapopularea claselor si pastreaza o evidenta clara a rezervarilor.

