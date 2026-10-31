<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Support\ReviewAccess;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Comisión's decision on a checklist (address has `application`) or a remote check (`workplace`). The access check
 * comes first, so whoever may not decide is told 403/404 whatever they send.
 */
final class ReviewRequest extends FormRequest
{
    use ReadsDecision;

    public function authorize(): bool
    {
        $access = resolve(ReviewAccess::class);

        if ($this->route('application') === null) {
            $access->ofWorkplace($this->userId(), $this->route('workplace'));
        } else {
            $access->ofApplication($this->userId(), $this->route('application'));
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->decisionRules();
    }
}
