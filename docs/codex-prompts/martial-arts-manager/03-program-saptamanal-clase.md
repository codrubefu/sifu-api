# Prompt Codex: Program saptamanal pentru clase

Construieste un program saptamanal practic pentru clase de arte martiale peste modulul existent de events/occurrences. Respecta arhitectura Laravel API si React/Vite.

Scop business: managerul si membrii trebuie sa vada rapid orarul pe zile, discipline, niveluri, instructori si sali.

Analizeaza inainte:

- `Event`, `EventOccurrence`, `EventCategory`
- rutele `routes/event.php`
- `EventsModule.tsx` si serviciul `eventService`
- calendarul existent si filtrele sale

Cerinte backend:

- Refoloseste evenimentele recurente existente; adauga campuri doar daca lipsesc informatii precum nivel, disciplina, sala fizica sau instructor.
- Expune un endpoint optimizat pentru orar saptamanal cu interval de date obligatoriu.
- Include capacitate, locuri ocupate si status aparitie.
- Pastreaza compatibilitatea cu endpoint-urile existente de calendar.
- Aplica rights `events.view/events.manage`, tenant isolation si location access.

Cerinte frontend:

- Adauga vedere saptamanala densa, nu landing page.
- Permite filtre dupa disciplina/categorie, nivel, locatie si instructor.
- Afiseaza capacitate si status vizual pentru clase pline/anulate.
- Pastreaza acces la editarea evenimentului si la lista de participanti.
- Adauga localizari `ro/en/uk`.

Teste:

- endpoint-ul returneaza doar aparitii din organizatia curenta
- filtrele functioneaza cumulativ
- aparitiile anulate sunt vizibile cu status corect
- build/test frontend trece

Acceptance criteria:

- Managerul poate folosi orarul ca instrument zilnic pentru planificare si verificare rapida.

