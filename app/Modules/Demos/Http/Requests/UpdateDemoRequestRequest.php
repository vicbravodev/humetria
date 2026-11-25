<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Requests;

use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use App\Modules\Demos\Support\DemoOwners;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class UpdateDemoRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not the superadmin must not even learn the body was invalid.
        Gate::authorize('manage-demo-requests');

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(DemoOwners $owners): array
    {
        $route = $this->route('demoRequest');
        $current = $route instanceof DemoRequest ? $route->status : null;
        // «Convertida» is reached by creating the company, «Demo agendada» by scheduling it with its day: never by hand.
        // A request already in one of them may keep it.
        $byHand = array_filter(
            [DemoStatus::Converted, DemoStatus::Scheduled],
            fn (DemoStatus $status): bool => $status !== $current,
        );

        return [
            'status' => ['required', 'string', Rule::enum(DemoStatus::class)->except($byHand)],
            'note' => ['nullable', 'string', 'max:2000'],
            // Only Humetría's own people: an id of someone of a company exists in users too.
            'owner_user_id' => ['bail', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($owners): void {
                if (! $owners->has($this->integer('owner_user_id'))) {
                    $fail(__('validation.exists', ['attribute' => __('validation.attributes.owner_user_id')]));
                }
            }],
            'next_step' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function status(): DemoStatus
    {
        return DemoStatus::from($this->string('status')->toString());
    }

    public function note(): ?string
    {
        return $this->text('note');
    }

    public function ownerId(): ?int
    {
        return $this->filled('owner_user_id') ? $this->integer('owner_user_id') : null;
    }

    public function nextStep(): ?string
    {
        return $this->text('next_step');
    }

    private function text(string $key): ?string
    {
        $text = trim($this->string($key)->toString());

        return $text === '' ? null : $text;
    }
}
