<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #333;">

    <h2 style="color: #16a34a;">Facture n° {{ $invoice->invoice_number }}</h2>

    <p>Bonjour {{ $invoice->client_name }},</p>

    <p>
        Veuillez trouver ci-joint votre facture n° <strong>{{ $invoice->invoice_number }}</strong>
        datée du {{ $invoice->invoice_date->format('d/m/Y') }}, d'un montant total de
        <strong>{{ number_format($invoice->total_ttc, 3) }} {{ $invoice->currency }}</strong>.
    </p>

    <p>Cette facture est jointe à cet email aux formats PDF et XML (TEIF v1.9.0).</p>

    <p>Cordialement,<br>
    {{ $invoice->company->name }}</p>

</body>
</html>