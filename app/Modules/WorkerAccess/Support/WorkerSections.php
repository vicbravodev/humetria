<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;
use Illuminate\Container\Attributes\Tag;
use Throwable;

/**
 * The tools the person of the session has, in a fixed order: each module answers for its own and says nothing when it has
 * none. A tool that fails is reported and left out, so it never takes the start down with it.
 */
final readonly class WorkerSections
{
    /** The order of the tools in the start; one this list does not know goes last. */
    private const array ORDER = ['telework', 'attendance', 'survey', 'engagement', 'vacations', 'team', 'seating', 'policy', 'climate', 'feedback', 'support', 'center', 'complaints'];

    /**
     * @param  iterable<WorkerSection>  $sections  what each module offers, tagged `worker.sections`
     */
    public function __construct(#[Tag('worker.sections')] private iterable $sections) {}

    /** @return list<WorkerSectionLink> */
    public function for(WorkerIdentity $person): array
    {
        $links = [];

        foreach ($this->sections as $section) {
            try {
                $link = $section->for($person);
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            if ($link !== null) {
                $links[] = $link;
            }
        }

        usort($links, fn (WorkerSectionLink $a, WorkerSectionLink $b): int => self::position($a->key) <=> self::position($b->key));

        return $links;
    }

    private static function position(string $key): int
    {
        $position = array_search($key, self::ORDER, true);

        return $position === false ? count(self::ORDER) : $position;
    }
}
