<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Enums\NoticeAction;
use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Support\NoticeAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The review of a notice: its note; for an accident (6.9), what was done, the note then optional. The access check comes
 * first, so whoever may not review is told 404 whatever they send.
 */
final class NoticeReviewRequest extends FormRequest
{
    private ?WorkerNotice $notice = null;

    public function authorize(): bool
    {
        $user = $this->user();
        abort_if($user === null, 403);
        $this->notice = resolve(NoticeAccess::class)->reviewable($user->id, $this->route('notice'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->notice?->kind !== NoticeKind::WorkRisk) {
            return ['note' => ['required', 'string', 'max:2000'], 'actions' => ['prohibited']];
        }

        return [
            'actions' => ['required_without:note', 'array'],
            'actions.*' => ['distinct', Rule::enum(NoticeAction::class)],
            'note' => ['nullable', 'required_without:actions', 'string', 'max:2000'],
        ];
    }

    /** @return list<NoticeAction> */
    public function actions(): array
    {
        $values = $this->input('actions');

        return is_array($values) ? array_values(array_filter(array_map(fn (mixed $value): ?NoticeAction => is_string($value) ? NoticeAction::tryFrom($value) : null, $values))) : [];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => 'nota', 'actions' => 'qué se hizo', 'actions.*' => 'qué se hizo'];
    }
}
