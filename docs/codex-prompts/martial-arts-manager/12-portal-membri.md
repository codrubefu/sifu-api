# Prompt Codex: Portal pentru membri

Extinde experienta self-service pentru membri.

Scop business: membrul trebuie sa isi vada abonamentele, rezervarile, platile, anunturile, progresul si documentele proprii.

Analizeaza inainte:

- endpoint-urile `/api/me`
- profile pages din frontend
- events/profile-events
- services/profile-services
- privacy/GDPR

Cerinte backend:

- Completeaza endpoint-urile self-service lipsa: rezervare/anulare clasa, istoric plati proprii, documente proprii permise, progres tehnic read-only.
- Nu expune date administrative sau ale altor membri.
- Respecta consimtamantul si GDPR.
- Pentru actiuni self-service, defineste reguli clare: termen anulare, clase pline, eligibilitate abonament.
- Endpoint-urile trebuie sa fie auth.bearer, fara drepturi admin daca sunt strict pentru utilizatorul curent.

Cerinte frontend:

- Imbunatateste zona profil membru cu sectiuni clare: abonamente, clase, plati, documente, progres, notificari.
- Permite rezervare/anulare doar cand backend-ul o permite.
- Afiseaza motive de refuz.
- Adauga localizari `ro/en/uk`.

Teste:

- membrul vede doar propriile date
- rezervarea respecta capacitatea si abonamentul
- anularea dupa deadline este respinsa daca regula exista
- admin flow existent ramane neschimbat

Acceptance criteria:

- Un membru poate folosi portalul fara acces la module administrative.

