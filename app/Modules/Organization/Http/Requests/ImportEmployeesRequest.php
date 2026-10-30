<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

final class ImportEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-organization');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt', 'mimes:csv,txt', 'max:1024'],
            'confirm' => ['nullable', 'boolean'],
        ];
    }

    /** The uploaded file's bytes; validation already made sure there is one. */
    public function content(): string
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? ($file->get() ?: '') : '';
    }

    public function confirmed(): bool
    {
        return $this->boolean('confirm');
    }
}
