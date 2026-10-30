<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Instruments\Contracts\Nom035Sample;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Random\Randomizer;

/**
 * Draws the Guía III sample of a draft (III.1, criterio C-10): its size by the norm's formula, split among genders in
 * proportion to the work center's active people, and each gender's people taken at random, so anyone, of any shift,
 * position or area, can be drawn. Drawing again replaces the previous draw.
 */
final readonly class DrawSample
{
    /** The order genders are listed in; "other" is a stratum like the rest, so everyone can be drawn. */
    private const array GENDERS = ['female', 'male', 'other'];

    public function __construct(private Roster $roster, private Nom035Sample $sample) {}

    public function handle(Campaign $campaign): void
    {
        if ($campaign->population !== Population::Sample) {
            throw new LogicException('Only the draft of a sampled evaluation is drawn.');
        }

        $people = $this->roster->activeIn($campaign->work_center_id);
        $strata = self::strata($people);
        $size = $this->sample->size(count($people));
        $quotas = $this->sample->allocate($size, array_map(count(...), $strata));
        $randomizer = new Randomizer;
        $drawn = [];

        foreach ($strata as $gender => $members) {
            foreach (array_slice($randomizer->shuffleArray($members), 0, $quotas[$gender]) as $person) {
                $drawn[] = $person->id;
            }
        }

        DB::transaction(function () use ($campaign, $people, $size, $quotas, $drawn): void {
            // Under lock: a launch racing this redraw either sees the new draw or this one sees it launched.
            $campaign = Campaign::lockIn($campaign, CampaignStatus::Draft);
            $campaign->participants()->delete();
            $campaign->update(['workers' => count($people), 'sample_size' => $size, 'quotas' => $quotas]);
            $now = now();

            $campaign->participants()->insert(array_map(fn (string $employeeId): array => [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $campaign->tenant_id,
                'campaign_id' => $campaign->id,
                'employee_id' => $employeeId,
                'in_sample' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $drawn));
        });

        Log::info('campaign.sample_drawn', ['campaign_id' => $campaign->id, 'workers' => count($people), 'sample_size' => $size]);
    }

    /**
     * The active people by gender, in a fixed order; a gender nobody has is left out.
     *
     * @param  list<RosterEntry>  $people
     * @return array<string, list<RosterEntry>>
     */
    public static function strata(array $people): array
    {
        $strata = [];

        foreach (self::GENDERS as $gender) {
            $members = array_values(array_filter($people, fn (RosterEntry $person): bool => $person->gender === $gender));

            if ($members !== []) {
                $strata[$gender] = $members;
            }
        }

        return $strata;
    }
}
