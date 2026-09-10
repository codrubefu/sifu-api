# Prompt Codex: Documente obligatorii si expirari

Extinde documentele membrilor pentru cerinte obligatorii si expirari.

Scop business: managerul trebuie sa stie ce membri au adeverinta medicala, acord parental, contract sau document GDPR lipsa/expirat.

Analizeaza inainte:

- `UserDocument`
- upload/download documente
- GDPR workflow
- rapoarte si dashboard

Cerinte backend:

- Adauga configurare pe organizatie pentru tipuri de documente obligatorii.
- Pentru fiecare document, suporta data emitere, data expirare, status verificare si observatii.
- Expune endpoint pentru documente lipsa/expirate per membru si raport global.
- Integreaza verificarea in check-in ca motiv de avertizare sau refuz, conform regula configurata.
- Protejeaza cu `user-documents.view/upload/delete` si drepturi de configurare.
- Pastreaza fisierele in storage privat si auditul existent.

Cerinte frontend:

- In profilul membrului, evidentiaza documente lipsa/expirate.
- Adauga raport/ecran pentru documente obligatorii.
- Permite configurarea cerintelor de documente pentru organizatie.
- Adauga localizari `ro/en/uk`.

Teste:

- document expirat apare in raport
- document din alta organizatie nu este vizibil
- check-in foloseste verdictul documentelor daca regula e activa
- upload/download existent ramane functional

Acceptance criteria:

- Managerul poate identifica rapid membrii care nu indeplinesc cerintele administrative sau medicale.

