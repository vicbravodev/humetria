<?php

declare(strict_types=1);

namespace App\Modules\Demos\Contracts;

/**
 * The guide of the demo session in course, for its presenter: the superadmin working in the demo company as its owner or
 * its HR, through impersonation. Nobody else, not even the demo's own people, ever gets it.
 *
 * @phpstan-type GuideTour array{key: string, title: string, done: int, total: int}
 * @phpstan-type GuideStep array{key: string, tour: string, title: string, todo: string, why: string, kind: string, href: string, target: string|null, ask: string|null, live: string|null, index: int, total: int}
 * @phpstan-type Guide array{tours: list<GuideTour>, current: GuideStep|null, finished: bool, justDone: string|null}
 */
interface DemoGuide
{
    /**
     * Whether the request is the presenter's: someone impersonating the demo company, entered from the console by a user the
     * `manage-demo` gate lets through (the superadmin), with or without a session in course.
     */
    public function isPresenter(): bool;

    /**
     * The guide of the session in course: each tour with its progress (done or skipped of its steps) and the current step,
     * the first one neither done by the real data nor skipped, with the address of its page in the demo. Null unless the
     * presenter asks and the session is active. Counts and fixed copy only: never a person nor an answer. A page fetched ahead
     * (`$advance` false) only looks: the guide's step stays and the step just done is left for the page the presenter opens.
     *
     * @return Guide|null
     */
    public function forCurrentRequest(bool $advance = true): ?array;

    /** The presenter opened the panel page `$routeName`: kept when a step waits for it, for the session in course only. */
    public function recordVisit(string $routeName): void;
}
