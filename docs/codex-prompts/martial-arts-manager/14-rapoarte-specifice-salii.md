# Prompt Codex: Rapoarte specifice salii

Adauga rapoarte de management specifice unei sali de arte martiale.

Scop business: managerul trebuie sa inteleaga retentia, ocuparea, venitul pe disciplina/instructor si evolutia membrilor.

Analizeaza inainte:

- `Reporting` module
- `FinancialReportService`
- `AttendanceReportService`
- `EventParticipationReportService`
- dashboard si recharts UI

Cerinte backend:

- Adauga endpoint-uri sau extinde raportarea pentru: membri noi vs pierduti, retentie lunara, participare per clasa, ocupare per ora, venit per serviciu/disciplina/instructor.
- Foloseste filtre standard: perioada, locatie, categorie/disciplina, instructor, serviciu.
- Pastreaza throttle `expensive` pentru agregari.
- Adauga export CSV/XLSX unde exista pattern existent.
- Protejeaza cu `reports.view/reports.export`.
- Query-urile trebuie sa fie tenant-safe si eficiente.

Cerinte frontend:

- Extinde `ReportsView` cu taburi sau sectiuni pentru rapoarte de sala.
- Foloseste grafice existente Recharts si tabele dense.
- Permite export pentru utilizatori cu drept.
- Adauga localizari `ro/en/uk`.

Teste:

- agregarile respecta perioada si filtrele
- organizatiile nu se amesteca
- exportul reflecta aceleasi filtre
- utilizator fara export right nu descarca fisiere

Acceptance criteria:

- Managerul poate lua decizii despre program, instructori si retentie pe baza rapoartelor.

