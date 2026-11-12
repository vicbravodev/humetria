<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * The list of the reporting line as a CSV a spreadsheet opens: active people by name with their job, area, work center,
 * manager and how many people report to them. Never contact data.
 */
final readonly class ExportOrgChart
{
    private const array COLUMNS = ['Nombre', 'Puesto', 'Área', 'Centro de trabajo', 'Jefe directo', 'Directos'];

    public function __construct(private WorkCenters $workCenters) {}

    /**
     * @return array{content: string, people: int}
     */
    public function handle(): array
    {
        $centers = array_column($this->workCenters->all(), 'name', 'id');
        $people = Employee::query()
            ->whereNull('deactivated_at')
            ->with(['area:id,name', 'manager:id,name,employee_number'])
            ->withCount(['reports as direct_reports' => fn (Builder $reports) => $reports->whereNull('deactivated_at')])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $lines = [self::line(self::COLUMNS)];

        foreach ($people as $person) {
            $lines[] = self::line([
                $person->name ?? $person->employee_number ?? '',
                $person->job_title ?? '',
                $person->area->name ?? '',
                $centers[$person->work_center_id ?? ''] ?? '',
                $person->manager === null ? '' : ($person->manager->name ?? $person->manager->employee_number ?? ''),
                (string) (is_int($person->getAttributeValue('direct_reports')) ? $person->getAttributeValue('direct_reports') : 0),
            ]);
        }

        Log::info('org_chart.exported', ['people' => $people->count()]);

        // The byte order mark is what makes Excel read the accents.
        return ['content' => "\u{FEFF}".implode("\n", $lines)."\n", 'people' => $people->count()];
    }

    /**
     * @param  list<string>  $cells
     */
    private static function line(array $cells): string
    {
        return implode(',', array_map(self::cell(...), $cells));
    }

    /** A cell quoted when it needs it, and defused when a spreadsheet would run it as a formula. */
    private static function cell(string $text): string
    {
        $text = preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;

        return preg_match('/[",\n\r]/', $text) === 1 ? '"'.str_replace('"', '""', $text).'"' : $text;
    }
}
