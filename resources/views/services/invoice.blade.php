<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Factura {{ $invoiceNumber }}</title>
    <style>
        body { margin: 0; color: #111; font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
        .page { padding: 36px; }
        h1 { margin: 0 0 24px; font-size: 24px; text-align: center; }
        .grid { display: table; width: 100%; margin-bottom: 24px; }
        .col { display: table-cell; width: 50%; vertical-align: top; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
        .total { margin-top: 18px; text-align: right; font-size: 16px; font-weight: 700; }
    </style>
</head>
<body>
<main class="page">
    <h1>FACTURA</h1>
    <div class="grid">
        <div class="col">
            <strong>Furnizor</strong><br>
            {{ $organization->name ?? '-' }}<br>
            <span class="muted">Organizatie #{{ $organization->id ?? '-' }}</span>
        </div>
        <div class="col right">
            <strong>Factura: {{ $invoiceNumber }}</strong><br>
            Data: {{ $issuedAt->format('d.m.Y') }}<br>
            Assignment: #{{ $assignment->id }}
        </div>
    </div>
    <div>
        <strong>Client</strong><br>
        {{ $fullName }}<br>
        {{ $user->email ?: '-' }}<br>
        {{ $user->phone ?: '-' }}
    </div>
    <table>
        <thead>
        <tr>
            <th>Serviciu</th>
            <th class="right">Cantitate</th>
            <th class="right">Pret</th>
            <th class="right">Total</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>{{ $service->name }}</td>
            <td class="right">1</td>
            <td class="right">{{ $amount }} {{ $service->currency }}</td>
            <td class="right">{{ $amount }} {{ $service->currency }}</td>
        </tr>
        </tbody>
    </table>
    <div class="total">Total: {{ $amount }} {{ $service->currency }}</div>
</main>
</body>
</html>
