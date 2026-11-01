<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Support\ReviewAccess;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Recording an in-person visit and the decision it led to. The access check comes first.
 */
final class VisitRecordRequest extends FormRequest
{
    use ReadsDecision;

    public function authorize(): bool
    {
        resolve(ReviewAccess::class)->ofWorkplace($this->userId(), $this->route('workplace'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->decisionRules(), 'visit_authorization_id' => ['required', 'uuid'], 'visited_on' => ['required', 'date_format:Y-m-d']];
    }

    public function authorizationId(): string
    {
        return $this->string('visit_authorization_id')->toString();
    }

    public function visitedOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('visited_on')->toString());
    }
}
