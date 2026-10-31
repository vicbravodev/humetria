<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Data\AddressData;
use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Adds a workplace to a teleworker and asks for its checklist (Sección I unless told otherwise), so a place never exists without one to answer.
 */
final readonly class AddWorkplace
{
    public function __construct(private RequestChecklist $request) {}

    public function handle(Teleworker $teleworker, AddressData $address, Section $section = Section::One): Workplace
    {
        return DB::transaction(function () use ($teleworker, $address, $section): Workplace {
            $workplace = Workplace::query()->create(['tenant_id' => $teleworker->tenant_id, 'teleworker_id' => $teleworker->id, ...$address->attributes()]);
            $this->request->handle($workplace, $section);
            Log::info('telework.workplace_added', ['teleworker_id' => $teleworker->id, 'workplace_id' => $workplace->id]);

            return $workplace;
        });
    }
}
