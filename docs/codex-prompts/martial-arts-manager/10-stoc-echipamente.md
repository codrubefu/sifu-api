# Prompt Codex: Stoc si echipamente

Implementeaza un modul minim de inventar pentru echipamente vandute in sala.

Scop business: managerul trebuie sa gestioneze produse precum kimono, centuri, manusi, protectii si tricouri, cu stoc si vanzari.

Analizeaza inainte:

- payments
- reports financiare
- structura modulelor Laravel
- UI pentru payments/services

Cerinte backend:

- Creeaza modul inventory cu produse, variante simple si miscari de stoc.
- Produsele trebuie sa fie organization-scoped.
- Suporta pret, SKU optional, categorie, stoc curent, prag minim si active flag.
- Vanzarea unui produs trebuie sa creeze o miscare de stoc si optional o plata.
- Nu amesteca produsele cu `services`; pastreaza conceptele separate.
- Protejeaza cu drepturi `inventory.view/manage` si seed drepturile.
- Include audit pentru ajustari si vanzari.
- Adauga rapoarte simple pentru stoc scazut si vanzari produse.

Cerinte frontend:

- Adauga ecran Inventar cu lista produse, creare/editare si ajustare stoc.
- Adauga flux de vanzare produs sau pregateste integrarea cu POS.
- Afiseaza alerte de stoc minim.
- Adauga localizari `ro/en/uk`.

Teste:

- stocul scade la vanzare
- ajustarea manuala este auditata
- produs cross-tenant nu este accesibil
- drepturi view/manage functioneaza

Acceptance criteria:

- Managerul poate vedea stocul disponibil si inregistra vanzari de echipament.

