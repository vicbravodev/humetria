<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Enums\CutAttribute;
use App\Modules\Engagement\Enums\ItemBlock;
use App\Modules\Engagement\Support\Bank\ItemBank;

/**
 * The rows of the export: exactly what the screen shows for the company or for one cut, question by question. A group
 * the minimums hide has no row, and a question under the minimum has empty cells; nothing says how many were left out.
 */
final readonly class ResultsCsv
{
    public const array HEADER = ['grupo', 'driver', 'pregunta', 'favorable', 'neutral', 'desfavorable', 'respuestas'];

    public function __construct(private ResultsPresenter $presenter) {}

    /**
     * @return list<list<string>> the header first
     */
    public function rows(SurveyResults $results, ?CutAttribute $cut): array
    {
        $rows = [self::HEADER];

        if ($cut === null) {
            return [...$rows, ...$this->groupRows('Toda la empresa', $results->company())];
        }

        $groups = $results->cut($cut)['groups'];
        $names = $this->presenter->names($cut, array_keys($groups));
        foreach ($groups as $key => $summary) {
            $rows = [...$rows, ...$this->groupRows($names[$key] ?? 'Sin nombre', $summary)];
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function groupRows(string $name, GroupSummary $summary): array
    {
        $rows = [];
        foreach ($summary->byKey as $key => $result) {
            $item = ItemBank::find($key);
            $rows[] = [
                self::text($name),
                $item->driver?->label() ?? ($item->block === ItemBlock::Outcome ? 'Compromiso' : 'Cerrar el ciclo'),
                $item->text,
                self::number($result->favorable),
                self::number($result->neutral),
                self::number($result->unfavorable),
                self::number($result->answered),
            ];
        }

        return $rows;
    }

    private static function number(?int $value): string
    {
        return $value === null ? '' : (string) $value;
    }

    /** A name the company typed that starts like a formula is written as text, so a spreadsheet never runs it. */
    private static function text(string $value): string
    {
        return in_array($value[0] ?? '', ['=', '+', '-', '@', "\t", "\r"], true) ? "'{$value}" : $value;
    }
}
