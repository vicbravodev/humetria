<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Support\ChecklistAccess;
use Illuminate\Foundation\Http\FormRequest;

/** Moving a checklist question up or down. The access check comes first, as for `QuestionRequest`. */
final class MoveQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        resolve(ChecklistAccess::class)->editable(($this->user() ?? abort(403))->id, $this->route('workCenter'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['direction' => ['required', 'in:up,down']];
    }

    public function up(): bool
    {
        return $this->string('direction')->toString() === 'up';
    }
}
