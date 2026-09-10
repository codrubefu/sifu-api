# Prompt Codex: POS si vanzare rapida

Adauga un flux POS simplu pentru receptie.

Scop business: operatorul trebuie sa incaseze rapid abonamente, taxe de examen, evenimente si produse, cu chitanta/factura unde se aplica.

Analizeaza inainte:

- `PaymentService`, `ReceiptService`
- service assignment activation
- event participant payments
- viitorul inventory daca exista
- Payments UI

Cerinte backend:

- Creeaza endpoint-uri POS care pot compune o vanzare cu linii: serviciu, eveniment, produs, taxa.
- Pentru v1, daca linia produs nu exista inca, proiecteaza extensibil dar implementeaza doar tipurile disponibile.
- Platile cash trebuie confirmate imediat si sa declanseze activarea serviciului cand e cazul.
- Pastreaza atomicitatea intre plata, document fiscal, activare si consum stoc.
- Evita duplicarea logicii din `PaymentService`; extrage servicii daca este necesar.
- Protejeaza cu `payments.create/payments.manage` sau drept dedicat `pos.manage`.
- Adauga audit.

Cerinte frontend:

- Adauga ecran POS optimizat pentru tastatura si cautare membru.
- Permite adaugare linii, total, metoda plata si confirmare.
- Afiseaza rezultat cu linkuri descarcare chitanta/factura.
- Adauga localizari `ro/en/uk`.

Teste:

- vanzare abonament cash activeaza assignment-ul
- vanzare esuata nu lasa date partiale
- documentele financiare raman descarcabile
- tenant isolation si drepturi

Acceptance criteria:

- O incasare uzuala la receptie se poate face intr-un singur flux, fara navigare prin mai multe module.

