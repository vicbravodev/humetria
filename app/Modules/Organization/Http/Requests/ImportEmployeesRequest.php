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
            'center' => ['nullable', 'string', 'max:36'],
        ];
    }

    /** The uploaded file's bytes; validation already made sure there is one. */
    public function content(): string
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? ($file->get() ?: '') : '';
    }

    /** The center the rows without one go to, as the page sent it; the controller checks it belongs to the company. */
    public function center(): ?string
    {
        $center = $this->input('center');

        return is_string($center) && $center !== '' ? $center : null;
    }

    public function confirmed(): bool
    {
        return $this->boolean('confirm');
    }
}
