<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { color: #16a34a; margin: 0; }
        .info-block { width: 48%; display: inline-block; vertical-align: top; }
        .info-block strong { display: block; margin-bottom: 5px; color: #2563eb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        .totals { width: 300px; margin-left: auto; margin-top: 20px; }
        .totals table { width: 100%; }
        .totals td { border: none; padding: 4px 8px; }
        .totals .ttc { font-weight: bold; font-size: 1.2em; color: #16a34a; }
    </style>
</head>
<body>

    <div class="header">
        <h1>FACTURE</h1>
        <p>N° {{ $invoice->invoice_number }} — {{ $invoice->invoice_date->format('d/m/Y') }}</p>
    </div>

    <div class="info-block">
        <strong>Émetteur</strong>
        {{ $invoice->company->name }}<br>
        MF: {{ $invoice->company->tax_identifier }}<br>
        {{ $invoice->company->address }}<br>
        {{ $invoice->company->postal_code }} {{ $invoice->company->city }}<br>
        {{ $invoice->company->country }}
    </div>

    <div class="info-block">
        <strong>Client</strong>
        {{ $invoice->client_name }}<br>
        @if($invoice->client_tax_number)
            MF: {{ $invoice->client_tax_number }}<br>
        @endif
        {{ $invoice->client_address }}<br>
        {{ $invoice->client_postal_code }} {{ $invoice->client_city }}<br>
        {{ $invoice->client_country }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Désignation</th>
                <th>Qté</th>
                <th>Unité</th>
                <th>PU HT</th>
                <th>TVA %</th>
                <th>Remise</th>
                <th>Total HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->designation }}</td>
                <td>{{ number_format($item->quantity, 3) }}</td>
                <td>{{ $item->unit }}</td>
                <td>{{ number_format($item->unit_price, 3) }}</td>
                <td>{{ number_format($item->vat_rate, 2) }}%</td>
                <td>{{ number_format($item->discount, 3) }}</td>
                <td>{{ number_format($item->line_total, 3) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>Total HT</td>
                <td>{{ number_format($invoice->total_ht, 3) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td>Total TVA</td>
                <td>{{ number_format($invoice->total_vat, 3) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td>Droit de timbre</td>
                <td>{{ number_format($invoice->stamp_duty, 3) }} {{ $invoice->currency }}</td>
            </tr>
            <tr class="ttc">
                <td>Total TTC</td>
                <td>{{ number_format($invoice->total_ttc, 3) }} {{ $invoice->currency }}</td>
            </tr>
        </table>
    </div>

</body>
</html>