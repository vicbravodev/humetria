<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\ToolContent\Attendance;
use App\Http\ToolContent\Compensation;
use App\Http\ToolContent\Complaints;
use App\Http\ToolContent\Engagement;
use App\Http\ToolContent\Leave;
use App\Http\ToolContent\LeySilla;
use App\Http\ToolContent\Nom035;
use App\Http\ToolContent\Nom037;
use App\Http\ToolContent\OrgChart;
use App\Http\ToolContent\WorkdayGuide;
use InvalidArgumentException;

/**
 * The public page of each tool (`/registro-de-jornada`, `/nom-035`…) and the guide to the workday of 2027: one place for
 * their addresses and their text, which the page shows and the JSON-LD of its head repeats. Each tool's text lives in its
 * own class of App\Http\ToolContent; what it says of the law comes from docs/normas.
 *
 * @phpstan-type Feature array{kicker: string, title: string, text: string, bullets: list<string>, shot: string, alt: string, caption: string}
 * @phpstan-type Rule array{text: string, source: string}
 * @phpstan-type Fact array{label: string, value: string, note: string}
 * @phpstan-type Faq array{question: string, answer: string}
 * @phpstan-type Film array{name: string, title: string, description: string, duration: int, uploaded: string}
 * @phpstan-type Norm array{title: string, lead: string, facts: list<Fact>, rules: list<Rule>, note: string|null}
 * @phpstan-type Pick array{title: string, lead: string, question: string, options: list<array{label: string, answer: string, detail: string}>}
 * @phpstan-type Step array{title: string, text: string}
 * @phpstan-type BeforeAfter array{before: string, after: string}
 * @phpstan-type Audience array{who: string, title: string, text: string}
 * @phpstan-type Privacy array{title: string, items: list<array{title: string, text: string}>}
 * @phpstan-type Related array{key: string, why: string}
 * @phpstan-type Page array{title: string, description: string, name: string, family: string, chip: string, h1: string, lead: string, proof: string, heroPhone: string|null, phoneAlt: string|null, norm: Norm|null, guideLink: bool, pick: Pick|null, steps: list<Step>, features: list<Feature>, beforeAfterTitle: string, beforeAfter: list<BeforeAfter>, audience: list<Audience>, privacy: Privacy|null, faqs: list<Faq>, related: list<Related>, film: Film}
 * @phpstan-type GuideSection array{heading: string, paragraphs: list<string>, sources: list<string>}
 * @phpstan-type Guide array{title: string, description: string, h1: string, lead: string, updated: string, sections: list<GuideSection>}
 */
final class ToolPages
{
    /** The tool, as the demo form sends it (App\Modules\Demos\Enums\Tool), and the address of its page. */
    public const array SLUGS = [
        'nom_035' => 'nom-035',
        'nom_037' => 'nom-037',
        'ley_silla' => 'ley-silla',
        'complaints' => 'buzon-de-quejas',
        'compensation' => 'compensacion',
        'attendance' => 'registro-de-jornada',
        'leave' => 'vacaciones-y-permisos',
        'org_chart' => 'organigrama',
        'engagement' => 'clima-laboral',
    ];

    public const string GUIDE_ROUTE = 'guides.workday-2027';

    public const string GUIDE_PATH = 'guias/registro-de-jornada-2027';

    /** The text of each tool's page. */
    private const array CONTENT = [
        'nom_035' => Nom035::class,
        'nom_037' => Nom037::class,
        'ley_silla' => LeySilla::class,
        'complaints' => Complaints::class,
        'compensation' => Compensation::class,
        'attendance' => Attendance::class,
        'leave' => Leave::class,
        'org_chart' => OrgChart::class,
        'engagement' => Engagement::class,
    ];

    public static function route(string $key): string
    {
        return "tools.{$key}";
    }

    /** @return list<string> */
    public static function routes(): array
    {
        return array_map(self::route(...), array_keys(self::SLUGS));
    }

    /** @return Page */
    public static function page(string $key): array
    {
        return (self::CONTENT[$key] ?? throw new InvalidArgumentException("No tool page for [{$key}]."))::page();
    }

    /** @return Guide */
    public static function guide(): array
    {
        return WorkdayGuide::page();
    }

    /**
     * What the page component receives: the text, without what only the head uses, and each related tool with its name
     * and address.
     *
     * @return array<string, mixed>
     */
    public static function props(string $key): array
    {
        $page = self::page($key);
        unset($page['title'], $page['description'], $page['film']);

        return ['key' => $key, ...$page, 'related' => array_map(fn (array $item): array => [...$item, 'name' => self::page($item['key'])['name'], 'href' => route(self::route($item['key']), [], false)], $page['related'])];
    }
}
