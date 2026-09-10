<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Prezenta eveniment</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; color: #0f172a; font-size: 12px; line-height: 1.45; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        h2 { margin: 22px 0 8px; font-size: 15px; }
        .muted { color: #64748b; }
        .meta { margin-top: 14px; width: 100%; border-collapse: collapse; }
        .meta td { padding: 5px 0; vertical-align: top; }
        .meta .label { width: 130px; color: #64748b; }
        .summary { margin-top: 18px; width: 100%; border-collapse: collapse; }
        .summary td { border: 1px solid #cbd5e1; padding: 8px; text-align: center; }
        .summary .value { display: block; margin-top: 3px; font-size: 18px; font-weight: 700; }
        table.participants { width: 100%; border-collapse: collapse; }
        table.participants th { background: #f1f5f9; color: #475569; font-size: 10px; text-transform: uppercase; text-align: left; }
        table.participants th, table.participants td { border: 1px solid #cbd5e1; padding: 7px; vertical-align: top; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; color: #94a3b8; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Prezenta eveniment</h1>
    <div class="muted">Generat la {{ $generatedAt->format('Y-m-d H:i') }}</div>

    <table class="meta">
        <tr><td class="label">Eveniment</td><td>{{ $event?->title ?? '-' }}</td></tr>
        <tr><td class="label">Categorie</td><td>{{ $event?->category?->name ?? '-' }}</td></tr>
        <tr><td class="label">Data</td><td>{{ optional($occurrence->occurrence_date)->format('Y-m-d') ?? '-' }}</td></tr>
        <tr><td class="label">Interval</td><td>{{ optional($occurrence->start_datetime)->format('H:i') ?? '-' }} - {{ optional($occurrence->end_datetime)->format('H:i') ?? '-' }}</td></tr>
        <tr><td class="label">Locatie</td><td>{{ $event?->location ?? '-' }}</td></tr>
        <tr><td class="label">Status aparitie</td><td>{{ $occurrence->status }}</td></tr>
    </table>

    <table class="summary">
        <tr>
            <td>Total<span class="value">{{ $participants->count() }}</span></td>
            <td>Inscrisi<span class="value">{{ $statusCounts['registered'] }}</span></td>
            <td>Prezenti<span class="value">{{ $statusCounts['attended'] }}</span></td>
            <td>Anulati<span class="value">{{ $statusCounts['cancelled'] }}</span></td>
            <td>Absenti<span class="value">{{ $statusCounts['no_show'] }}</span></td>
        </tr>
    </table>

    <h2>Lista nominala</h2>
    <table class="participants">
        <thead>
            <tr>
                <th style="width: 34px;">#</th>
                <th>Nume</th>
                <th>Email</th>
                <th>Status</th>
                <th>Inscris la</th>
                <th>Observatii</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($participants as $index => $participant)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ trim(($participant->last_name ?? '').' '.($participant->first_name ?? '')) ?: '-' }}</td>
                    <td>{{ $participant->email ?? '-' }}</td>
                    <td>{{ $participant->pivot?->status ?? '-' }}</td>
                    <td>{{ $participant->pivot?->registered_at ?? '-' }}</td>
                    <td>{{ $participant->pivot?->notes ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #64748b;">Nu exista participanti.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Prezenta eveniment #{{ $occurrence->id }}</div>
</body>
</html>
