<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One guide's answers, keyed by item number and gate. Whether they are the complete, coherent guide is the
 * instrument's to say.
 */
final class SubmitSurveyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answers' => ['present', 'array', 'max:80'],
            'answers.*' => ['string', 'max:20'],
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function answers(): array
    {
        return $this->array('answers');
    }
}
