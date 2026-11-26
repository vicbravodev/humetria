<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Requests;

use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use LogicException;

/** The day of a request's demo, as the console's field writes it in Mexico City time: "2026-12-03T11:00". */
final class ScheduleDemoRequest extends FormRequest
{
    private const string FORMAT = 'Y-m-d\TH:i';

    public function authorize(): bool
    {
        Gate::authorize('manage-demo-requests');

        return true;
    }

    /** The dialog sends the day and the hour apart (its pickers); they make one moment here. */
    protected function prepareForValidation(): void
    {
        if (! $this->has('scheduled_at') && $this->filled(['scheduled_on', 'scheduled_time'])) {
            $this->merge(['scheduled_at' => $this->string('scheduled_on')->toString().'T'.$this->string('scheduled_time')->toString()]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => ['bail', 'required', 'date_format:'.self::FORMAT, function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->converted()) {
                    $fail('Esta solicitud ya es una empresa.');
                } elseif ($this->at()->isPast()) {
                    $fail('Elige un día y una hora por venir.');
                }
            }],
        ];
    }

    /** The moment, in UTC. */
    public function at(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(self::FORMAT, $this->string('scheduled_at')->toString(), config()->string('app.display_timezone'))
            ?->utc() ?? throw new LogicException('scheduled_at was validated');
    }

    private function converted(): bool
    {
        $route = $this->route('demoRequest');

        return $route instanceof DemoRequest && $route->status === DemoStatus::Converted;
    }
}
