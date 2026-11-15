<!DOCTYPE html>
<html lang="es-MX">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Estado de cuenta · {{ $statement['trade_name'] }} · {{ $statement['period'] }}</title>
        <style nonce="{{ Vite::cspNonce() }}">
            :root { color-scheme: light; }
            body { font: 14px/1.5 system-ui, sans-serif; color: #111; background: #fff; margin: 0; padding: 32px; }
            main { max-width: 760px; margin: 0 auto; }
            h1 { font-size: 22px; margin: 0 0 4px; }
            .muted { color: #555; }
            .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 24px 0; }
            table { width: 100%; border-collapse: collapse; }
            th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #ccc; vertical-align: top; }
            th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
            td.amount, th.amount { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
            .explanation { color: #555; font-size: 12px; }
            tfoot td { border-bottom: 0; font-weight: 600; }
            .actions { margin-bottom: 24px; }
            button { font: inherit; padding: 8px 14px; }
            @media print {
                body { padding: 0; }
                .actions { display: none; }
            }
        </style>
    </head>
    <body>
        <main>
            <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>
            <h1>Estado de cuenta{{ $statement['is_adjustment'] ? ' (ajuste)' : '' }}</h1>
            <p class="muted">{{ $statement['period'] }} · del {{ $statement['period_range'] }} · {{ $statement['state_label'] }}</p>
            <div class="parties">
                <div>
                    <strong>Cuenta</strong><br>
                    {{ $statement['account_name'] }}<br>
                    <span class="muted">{{ $statement['account_rfc'] }}</span>
                </div>
                <div>
                    <strong>Empresa</strong><br>
                    {{ $statement['legal_name'] }} ({{ $statement['trade_name'] }})<br>
                    <span class="muted">{{ $statement['company_rfc'] }}</span>
                </div>
            </div>
            <table>
                <thead>
                    <tr><th>Concepto</th><th class="amount">Importe ({{ $statement['currency'] }})</th></tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td>{{ $line['label'] }}<div class="explanation">{{ $line['explanation'] }}</div></td>
                            <td class="amount">{{ $line['amount'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td>Subtotal</td><td class="amount">{{ $statement['subtotal'] }}</td></tr>
                    <tr><td>IVA {{ $statement['vat_percent'] }}%</td><td class="amount">{{ $statement['vat'] }}</td></tr>
                    <tr><td>Total</td><td class="amount">{{ $statement['total'] }}</td></tr>
                    @if ($statement['paid'] !== null)
                        <tr><td>Pagado</td><td class="amount">{{ $statement['paid'] }}</td></tr>
                        <tr><td>Saldo</td><td class="amount">{{ $statement['balance'] }}</td></tr>
                    @endif
                </tfoot>
            </table>
            @if ($statement['reason'])
                <p>Motivo del ajuste: {{ $statement['reason'] }}</p>
            @endif
            <p class="muted">
                Catálogo {{ $statement['catalog_version'] }}.
                @if ($statement['issued_at'])
                    Emitido el {{ $statement['issued_at'] }}.
                @else
                    Borrador: aún no se emite.
                @endif
            </p>
        </main>
    </body>
</html>
