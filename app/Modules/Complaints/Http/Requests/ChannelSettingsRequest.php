<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Requests;

use App\Modules\Complaints\Support\ChannelSettings;
use Illuminate\Foundation\Http\FormRequest;

/** The procedure, the deadlines and the retention of the channel. */
final class ChannelSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-complaint-channel') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'procedure' => ['required', 'string', 'max:10000', 'regex:/\S/u'],
            'acknowledge_within_days' => ['required', 'integer', 'between:1,15'],
            'resolve_within_days' => ['required', 'integer', 'between:7,180'],
            'retention_months' => ['required', 'integer', 'between:'.config()->integer('complaints.retention.min_months').','.config()->integer('complaints.retention.max_months')],
        ];
    }

    public function settings(): ChannelSettings
    {
        return new ChannelSettings(
            trim($this->string('procedure')->toString()),
            $this->integer('acknowledge_within_days'),
            $this->integer('resolve_within_days'),
            $this->integer('retention_months'),
        );
    }
}
