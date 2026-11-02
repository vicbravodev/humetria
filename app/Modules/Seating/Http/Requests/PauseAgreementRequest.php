<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * An agreement on pauses for a position. The law fixes no number of pauses and no minimum of minutes (criterio C-14), so
 * only that there are some is required; the bounds are those of a working day.
 */
final class PauseAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-seating') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'position_id' => ['required', 'uuid'],
            'pauses_per_shift' => ['required', 'integer', 'between:1,50'],
            'minutes_each' => ['required', 'integer', 'between:1,480'],
            'agreed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString()],
            'how_agreed' => ['required', 'string', 'max:1000'],
            // mimetypes reads the file's content, not its extension.
            'evidence' => ['nullable', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.config()->integer('seating.max_document_kb')],
        ];
    }

    public function howAgreed(): string
    {
        return trim($this->string('how_agreed')->toString());
    }

    /** Where the uploaded evidence is, if one came. */
    public function evidenceFile(): ?string
    {
        $file = $this->file('evidence');

        return $file instanceof UploadedFile ? $file->getPathname() : null;
    }
}
