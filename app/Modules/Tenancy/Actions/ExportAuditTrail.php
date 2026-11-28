<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Support\AuditPages;
use Illuminate\Support\Facades\Log;

/**
 * The trail the filters find as CSV, newest first, UTF-8 with a byte-order mark so a spreadsheet opens its accents, every cell
 * safe against formulas. It holds what the console shows and nothing more. Taking a copy of the trail is itself sensitive:
 * the export is logged and written to the trail, with its filters but never the search typed.
 * ponytail: built in memory and capped at LIMIT rows; stream by cursor if a period ever holds more.
 */
final readonly class ExportAuditTrail
{
    public const int LIMIT = 10_000; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    public function __construct(private AuditPages $pages, private RecordAudit $recordAudit) {}

    /**
     * @param  array{q: string, kind: string, family: string, company: string, from: string, to: string}  $filters
     */
    public function handle(array $filters): string
    {
        $rows = $this->pages->rows($filters, self::LIMIT);
        $lines = [['Fecha y hora', 'Evento', 'Quién', 'Correo', 'Actuó como', 'Empresa', 'Detalle', 'Cambio']];

        foreach ($rows as $row) {
            $lines[] = [
                $row['at'],
                $row['event'],
                $row['actor']['name'] ?? 'Sistema',
                $row['actor']['email'] ?? '',
                $row['impersonator']['name'] ?? '',
                $row['company']['name'] ?? 'Plataforma',
                implode('; ', array_map(fn (string $key, string $value): string => "{$key}: {$value}", array_keys($row['context']), $row['context'])),
                implode('; ', array_map(fn (array $change): string => "{$change['field']}: {$change['before']} → {$change['after']}", $row['change'])),
            ];
        }

        $metadata = [
            'view' => $filters['kind'],
            'family' => $filters['family'],
            'tenant_id' => $filters['company'] === '' ? null : $filters['company'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'rows' => count($rows),
        ];
        $metadata = array_filter($metadata, fn (string|int|null $value): bool => $value !== '' && $value !== null);

        Log::info('audit.exported', $metadata);
        $this->recordAudit->handle('audit.exported', null, $metadata);

        return "\xEF\xBB\xBF".implode('', array_map($this->line(...), $lines));
    }

    /** @param  list<string>  $cells */
    private function line(array $cells): string
    {
        $handle = fopen('php://memory', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, array_map(fn (string $text): string => $text !== '' && str_contains("=+-@\t\r", $text[0]) ? "'{$text}" : $text, $cells), ',', '"', '');
        rewind($handle);
        $line = stream_get_contents($handle);
        fclose($handle);

        return $line === false ? '' : $line;
    }
}
