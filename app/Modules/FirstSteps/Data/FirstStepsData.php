<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Data;

/**
 * The guided tour of a company for one person: its steps, with what the person did with it.
 */
final readonly class FirstStepsData
{
    /**
     * @param  list<FirstStep>  $steps
     * @param  list<OtherTool>  $others
     */
    public function __construct(public array $steps, public bool $welcomed, public bool $hidden, public array $others) {}

    public function allDone(): bool
    {
        return array_all($this->steps, fn (FirstStep $step): bool => $step->done);
    }

    public function withState(bool $welcomed, bool $hidden): self
    {
        return new self($this->steps, $welcomed, $hidden, $this->others);
    }

    /**
     * @return array{steps: list<array{key: string, done: bool, href: string|null, tool: string|null}>, welcomed: bool, hidden: bool, others: list<array{key: string, href: string}>}
     */
    public function toArray(): array
    {
        return [
            'steps' => array_map(fn (FirstStep $step): array => ['key' => $step->key, 'done' => $step->done, 'href' => $step->href, 'tool' => $step->tool], $this->steps),
            'welcomed' => $this->welcomed,
            'hidden' => $this->hidden,
            'others' => array_map(fn (OtherTool $other): array => ['key' => $other->key, 'href' => $other->href], $this->others),
        ];
    }
}
