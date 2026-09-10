<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Chitanta {{ $receiptNumber }}</title>
    <style>


        html {
            margin: 22px;
            background: #fff;
        }
        body {
            margin: 0;
            color: #111;
            font-family: "Times New Roman", serif;
            margin:0;
            background: #fff;
        }

        .page {
            width: 100%;
            background: #fff;
            margin: auto;
        }

        .receipt {
            border: 2px solid #111 ; 
            position: relative;
            padding: 20px;
            margin-bottom: 6mm;
        }

        .receipt_org_info {
            float:left; 
            width: 40%;
        }

        .receipt_info {
            float:right;
            text-align: left;
            width: 60%;
        }

        .clearfix {
            clear: both;
        }

        .copyname {
            position: absolute;
            right: 4mm;
            top: 2mm;
            font-size: 8pt;
            font-style: italic;
        }

        .header {
            display: grid;
            grid-template-columns: 39% 33% 28%;
            align-items: start;
        }

        .company {
            text-align: left;
            font-weight: bold;
            font-size: 9.5pt;
        }

        .company .small {
            font-weight: normal;
            font-size: 7pt;
            line-height: 1.7;
        }

        .title {
            text-align: left;
            font-size: 18pt;
            font-weight: bold;
            margin-top: 8mm;
        }

        .docmeta {
            font-weight: bold;
            font-size: 9pt;
            line-height: 1.6;
            margin-top: 2mm;
        }

        .docmeta span {
            display: inline-block;
            min-width: 11mm;
        }

        .bank {
            font-size: 7.3pt;
            line-height: 1.5;
            margin-top: 2mm;
        }

        .bank b {
            display: inline-block;
            min-width: 12mm;
        }

        .work {
            font-weight: bold;
            margin-top: 1mm;
        }

        .form {
            font-size: 9pt;
            margin-top: 4mm;
            line-height: 1.55;
        }

        .line {
            border-collapse: collapse;
            width: 100%;
        }

        .line-label {
            white-space: nowrap;
            width: 1%;
            padding-right: 6px;
            vertical-align: bottom;
        }

        .dots {
            border-bottom: 1px dashed #555;
            min-height: 17px;
            position: relative;
            vertical-align: bottom;
            width: 99%;
        }

        .value {
            background: #fff;
            display: inline-block;
            line-height: 1.2;
            max-width: 100%;
            padding: 0 4px;
            vertical-align: bottom;
        }


        .sumgrid {
            border-collapse: collapse;
            width: 100%;
        }

        .cash {
            border-collapse: collapse;
            width: 100%;
            margin-top: 1mm;
        }

        .cashier-name {
            white-space: nowrap;
            width: 1%;
            padding-right: 8px;
            vertical-align: bottom;
        }

        .footerlogo {
            position: absolute;
            left: 0;
            right: 0;
            bottom: -5.5mm;
            text-align: center;
            font-size: 7pt;
        }

        .code {
            position: absolute;
            right: 2mm;
            bottom: -4.4mm;
            font-size: 7pt;
        }

        .archivecode {
            position: absolute;
            left: 1mm;
            bottom: -4.5mm;
            font-size: 7pt;
            font-style: italic;
        }

        @media print {
            body {
                background: #fff;
            }

            .page {
                margin: 0;
            }
        }

    

    </style>
</head>
<body>
    <div class="page">
        @foreach (['Exemplar Client', 'Exemplar Contabilitate', 'Exemplar Arhiva'] as $copyName)
        <section class="receipt">
            <div class="copyname">{{ $copyName }}</div>
            <div class="header">
                <div class="receipt_org_info">
                    <div class="company">
                        {{ $organization?->name ?? 'Organizatie' }}
                        <div class="small">
                            {{ $organization?->address ?? '-' }}<br>
                            Email: {{ $organization?->email ?? '-' }}&nbsp;&nbsp; {{ $organization?->web ?? '-' }}<br>
                            Tel. {{ $organization?->phone ?? '-' }}
                        </div>
                    </div>
                    <div class="bank">
                        C.U.I. {{ $organization?->cui ?? '-' }}&nbsp;&nbsp; Nr.Reg.Com. {{ $organization?->nr_reg_com ?? '-' }}<br>
                        <b>Capital social:</b> {{ $organization?->capital ?? '-' }}<br>
                        <b>Cont:</b> {{ $organization?->cont ?? '-' }}<br>
                        <b>Banca:</b> {{ $organization?->bank ?? '-' }}
                        <div class="work">Punct de lucru: {{ $payment->location?->name ?? '-' }}</div>
                    </div>
                </div>
                <div class="receipt_info">
                    <div class="title">CHITANTA</div>
                    <div class="docmeta">
                        <span>Seria:</span> {{ $receiptSeries }}<br>
                        <span>Nr.:</span> {{ $receiptNumber }}<br>
                        <span>Data:</span> {{ $paidAtFormatted }}
                    </div>
                </div>
                <div class="clearfix"></div>
            </div>
            <div class="form">
                <table class="line">
                    <tr>
                        <td class="line-label">Am primit de la</td>
                        <td class="dots"><span class="value">{{ $payerName }}</span></td>
                    </tr>
                </table>
                <table class="line">
                    <tr>
                        <td class="line-label">Adresa</td>
                        <td class="dots"><span class="value">{{ $payerAddress }}</span></td>
                    </tr>
                </table>
                <table class="sumgrid">
                    <tr>
                        <td class="line-label">Suma de</td>
                        <td class="dots"><span class="value">{{ $amount }}</span></td>
                        <td class="line-label">adica</td>
                        <td class="dots"><span class="value">{{ $amountText }}</span></td>
                    </tr>
                </table>
                <table class="line">
                    <tr>
                        <td class="line-label">&nbsp;</td>
                        <td class="dots"></td>
                    </tr>
                </table>
                <table class="line">
                    <tr>
                        <td class="line-label">Reprezentand contravaloare factura nr.</td>
                        <td class="dots"><span class="value">{{ $description }}</span></td>
                    </tr>
                </table>
                <table class="cash">
                    <tr>
                        <td class="line-label">Casier,</td>
                        <td class="cashier-name">{{ $cashier }}</td>
                        <td class="dots"></td>
                    </tr>
                </table>
              
            </div>
            
        </section>
        @endforeach
    </div>
</body>
</html>
