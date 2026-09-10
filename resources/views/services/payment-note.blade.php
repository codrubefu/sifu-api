<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Nota de plata - {{ $fullName }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        html {
            margin:10px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #fff;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
        }

        .page {
            width: 100%;
            position: relative;
            margin-top:20px;
        }

        .document {
            min-height: 261mm;
            position: relative;
            width: 50%;
        }

        .document > div {
            padding: 10px;
        }

        .document:nth-child(1) {
            margin-right:5px;
            border-right:1px dashed #111;
            float: left;

        }

        .document:nth-child(2){
            float: right;
            margin-left:5px;
        }
        
        .vertical {
            position: absolute;
            right: 10px;
            font-family: "Times New Roman", Times, serif;
            font-size: 8pt;
            font-weight: bold;
            line-height: 1;
            writing-mode: vertical-rl;
            text-align: left;
              white-space: nowrap;
            transform: rotate(90deg);
            transform-origin: top right;
        }

        .clearfix {
            clear: both;
        }
        .vertical.no {
            top: 0;
        }

        .vertical.number {
            top: 18mm;
        }

        .title {
            margin: 0 0 5mm;
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            letter-spacing: .2pt;
        }

        .meta {
            margin-left: 4mm;
            line-height: 1.42;
        }

        .meta .code {
            font-weight: bold;
        }

        .meta .name {
            display: block;
            margin-top: 1mm;
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .italic {
            font-style: italic;
        }

        .bold-italic {
            font-style: italic;
            font-weight: bold;
        }

        .items {
            width: 100%;
            margin-top: 6mm;
            border-collapse: collapse;
            border-top: 2px solid #111;
            font-family: "Times New Roman", Times, serif;
            font-size: 8pt;
        }

        .items td {
            height: 7mm;
            padding: 1mm 1.2mm;
            background: #efefef;
            vertical-align: middle;
        }

        .vat {
            width: 8mm;
        }

        .description {
            font-family: Arial, Helvetica, sans-serif;
            font-weight: bold;
        }

        .qty {
            width: 12mm;
            text-align: right;
            font-weight: bold;
        }

        .price,
        .line-total {
            width: 25mm;
            text-align: right;
            font-weight: bold;
        }

        .beneficiary {
            width: 100%;
            margin-top: 2mm;
            border-collapse: collapse;
            font-size: 7.5pt;
        }

        .beneficiary td {
            padding: 0;
            vertical-align: top;
        }

        .beneficiary .label {
            width: 22mm;
            font-style: italic;
        }

        .beneficiary .plain {
            font-style: normal;
        }

        .beneficiary .card-label {
            width: 14mm;
            font-style: italic;
        }

        .activation {
            margin-top: 2mm;
            margin-bottom: 8mm;
            font-size: 7.4pt;
            font-style: italic;
        }

        .total-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 2px solid #111;
            font-size: 11pt;
            font-weight: bold;
        }

        .total-table td {
            padding-top: 4mm;
        }

        .total-label {
            text-align: right;
            padding-right: 22mm;
        }

        .total-amount {
            width: 30mm;
            text-align: right;
        }

        .order {
            margin-top: 8mm;
            font-size: 9.5pt;
        }

        .footer {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            border-top: 1.5px solid #111;
            padding-top: 2mm;
        }

        .company {
            margin-left: 3mm;
            font-size: 10pt;
            font-style: italic;
            font-weight: bold;
        }

        .address {
            margin: 1mm 0 0 5mm;
            font-size: 7.5pt;
            line-height: 1.45;
        }

        .document-code {
            margin: 8mm 0 1mm 5mm;
            font-family: "Times New Roman", Times, serif;
            font-size: 6.5pt;
            font-style: italic;
        }

        .software {
            margin-left: 5mm;
            font-family: "Times New Roman", Times, serif;
            font-size: 7pt;
        }
    </style>
</head>
<body>
    <div class="page">
        @foreach ([1, 2] as $copyIndex)
        <section class="document">
            <div>
            <div class="vertical no">No.</div>
            <div class="vertical number">{{ $noteNumber }}</div>

            <h1 class="title">NOTA DE PLATA</h1>

            <div class="meta">
                {{ $issuedAt->format('d-M-Y') }}&nbsp;&nbsp;{{ $issuedAt->format('H:i') }}<br><br>
                <span class="code">SPA ID: {{ $user->user_code ?: $user->id }}</span><br>
                <span class="name">{{ $fullName }}</span>
                {{ $user->email ?: '-' }}<br><br>
                <span class="italic">Nota plata service</span><br>
                <span class="bold-italic">Extern</span><br>
                <span class="italic">Independent</span>
            </div>

            <table class="items">
                <tr>
                    <td class="vat">21%</td>
                    <td class="description">{{ $service->name }}</td>
                    <td class="qty">1.00</td>
                    <td class="price">{{ $amount }}</td>
                    <td class="line-total">{{ $amount }}</td>
                </tr>
            </table>

            <table class="beneficiary">
                <tr>
                    <td class="label">Beneficiar</td>
                    <td class="plain">{{ $fullName }}</td>
                    <td class="card-label">Card:</td>
                    <td class="plain">{{ $cardCode }}&nbsp; Data Activ : {{ $startDate }}</td>
                </tr>
            </table>

            <div class="activation">activare card</div>

            <table class="total-table">
                <tr>
                    <td class="total-label">TOTAL PLATA:</td>
                    <td class="total-amount">{{ $amount }}</td>
                </tr>
            </table>

            <div class="order">{{ $orderDetails }}</div>

            <div class="footer">
                <div class="company">{{ $organization?->name ?? 'Organizatie' }}</div>
                <div class="address">
                    {{ $organization?->address ?? '-' }}<br>
                    Email: {{ $organization?->email ?? '-' }}&nbsp;&nbsp; Web: {{ $organization?->web ?? '-' }}<br>
                    Tel: {{ $organization?->phone ?? '-' }}<br><br>
                    C.U.I. {{ $organization?->cui ?? '-' }}&nbsp;&nbsp;&nbsp;&nbsp;
                    Nr.Reg.Com. {{ $organization?->nr_reg_com ?? '-' }}<br>
                    Capital social: {{ $organization?->capital ?? '-' }}<br>
                    Cont: {{ $organization?->cont ?? '-' }}<br>
                    Banca: {{ $organization?->bank ?? '-' }}
                </div>
                <div class="document-code">{{ $assignment->bill_number ?: $noteNumber }}</div>
                <div class="software">Document generat automat din ERP.</div>
            </div>
        </div>
        </section>
        @endforeach
        <div class="clearfix"></div>
    </div>
</body>
</html>
