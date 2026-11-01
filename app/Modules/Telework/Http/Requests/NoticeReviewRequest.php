<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Support\NoticeAccess;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The note of a notice's review. The access check comes first, so whoever may not review is told 404 whatever they send.
 */
final class NoticeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        abort_if($user === null, 403);
        resolve(NoticeAccess::class)->reviewable($user->id, $this->route('notice'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:2000']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => 'nota'];
    }
}
