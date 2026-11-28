<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Modules\Demos\Enums\DemoOrigin;
use App\Modules\Demos\Enums\Workforce;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the console narrows its requests to, in the list and in the funnel alike: the text searched, the origin, who
 * owns them ("none" for nobody) and the size. A value it does not know narrows nothing.
 */
final readonly class DemoListFilters
{
    private function __construct(
        public string $search,
        public ?DemoOrigin $origin,
        public ?string $owner,
        public ?Workforce $size,
    ) {}

    public static function from(string $search = '', string $origin = '', string $owner = '', string $size = ''): self
    {
        return new self(
            $search,
            DemoOrigin::tryFrom($origin),
            $owner === 'none' || ctype_digit($owner) ? $owner : null,
            Workforce::tryFrom($size),
        );
    }

    /**
     * @param  Builder<DemoRequest>  $query
     * @return Builder<DemoRequest>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->matching(['name', 'email', 'company'], $this->search, ['whatsapp'])
            ->when($this->origin, fn (Builder $query, DemoOrigin $origin) => $query->whereRaw('('.DemoOrigin::SQL.') = ?', [$origin->value]))
            ->when($this->owner === 'none', fn (Builder $query) => $query->whereNull('owner_user_id'))
            ->when($this->owner !== null && $this->owner !== 'none', fn (Builder $query) => $query->where('owner_user_id', (int) $this->owner))
            ->when($this->size, fn (Builder $query, Workforce $size) => $query->where('workforce', $size->value));
    }

    /** @return array{origin: string, owner: string, size: string} as the page echoes them, empty when not set */
    public function toArray(): array
    {
        return ['origin' => $this->origin->value ?? '', 'owner' => $this->owner ?? '', 'size' => $this->size->value ?? ''];
    }
}
