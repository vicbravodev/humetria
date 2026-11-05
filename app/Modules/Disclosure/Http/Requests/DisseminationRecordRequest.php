<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * A means of disclosure RH records: where, how, which incisos of 5.7, on which day, a short note, its photo or PDF and, for a
 * list of signatures, how many signed. Whether the center is in
 * use is the action's to judge.
 */
final class DisseminationRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-disclosure') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'work_center_id' => ['required', 'uuid'],
            'medium' => ['required', 'in:poster,bulletin,brochure,meeting,other,signed_list'],
            'covered_items' => ['required', 'array', 'min:1'],
            'covered_items.*' => ['in:a,b,c,d,e,f'],
            'done_on' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:500'],
            'signatures' => ['nullable', 'integer', 'max:100000'],
            // The size and what the content is are the action's to judge (EvidenceStore): the extension and MIME prove nothing.
            'evidence' => ['nullable', 'file'],
        ];
    }

    /**
     * @return list<string>
     */
    public function items(): array
    {
        return array_values(array_filter($this->array('covered_items'), is_string(...)));
    }

    public function evidenceFile(): ?string
    {
        $file = $this->file('evidence');

        return $file instanceof UploadedFile ? $file->getPathname() : null;
    }

    public function signatures(): ?int
    {
        return $this->filled('signatures') ? $this->integer('signatures') : null;
    }

    public function userId(): int
    {
        return $this->user()->id ?? abort(403);
    }
}
