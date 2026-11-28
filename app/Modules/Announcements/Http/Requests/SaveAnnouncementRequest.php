<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Http\Requests;

use App\Modules\Announcements\Data\AnnouncementData;
use App\Modules\Announcements\Enums\AnnouncementAudience;
use App\Modules\Announcements\Enums\AnnouncementKind;
use App\Modules\Billing\Contracts\PlanNames;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** An announcement as the console's form sends it: its days and hours are Mexico City's, kept in UTC. */
final class SaveAnnouncementRequest extends FormRequest
{
    private const string SINGLE_LINE = 'regex:/\A[^\x00-\x1F\x7F]*\z/u';

    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not the superadmin must not even learn the body was invalid.
        Gate::authorize('manage-announcements');

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(PlanNames $plans): array
    {
        $time = 'date_format:H:i';

        return [
            'kind' => ['required', Rule::enum(AnnouncementKind::class)],
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)],
            'plan' => ['nullable', 'required_if:audience,plan', Rule::in(array_keys($plans->all()))],
            'title' => ['required', 'string', 'max:120', self::SINGLE_LINE],
            'message' => ['required', 'string', 'max:500'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'starts_time' => ['required', $time],
            'ends_on' => ['required', 'date_format:Y-m-d'],
            'ends_time' => ['required', $time],
            'publish' => ['required', 'boolean'],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && ! $this->moment('ends')->greaterThan($this->moment('starts'))) {
                $validator->errors()->add('ends_on', 'El fin tiene que ser después del inicio.');
            }
        }];
    }

    public function announcement(): AnnouncementData
    {
        return new AnnouncementData(
            kind: AnnouncementKind::from($this->string('kind')->toString()),
            audience: AnnouncementAudience::from($this->string('audience')->toString()),
            plan: $this->filled('plan') ? $this->string('plan')->toString() : null,
            title: $this->string('title')->toString(),
            message: $this->string('message')->toString(),
            startsAt: $this->moment('starts'),
            endsAt: $this->moment('ends'),
            publish: $this->boolean('publish'),
        );
    }

    /** The day and hour the form gave, read in Mexico City and turned into UTC. */
    private function moment(string $which): CarbonImmutable
    {
        $local = $this->string("{$which}_on")->toString().' '.$this->string("{$which}_time")->toString();

        return CarbonImmutable::parse($local, config()->string('app.display_timezone'))->utc();
    }
}
