<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/** The CSV of the tabulator or of the individual salaries, uploaded to be imported. */
final class SalaryImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-compensation');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt', 'mimes:csv,txt', 'max:1024'],
        ];
    }

    /** The uploaded file's bytes; validation already made sure there is one. */
    public function content(): string
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? ($file->get() ?: '') : '';
    }
}
