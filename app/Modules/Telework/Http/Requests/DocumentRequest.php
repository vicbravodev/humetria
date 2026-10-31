<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Enums\DocumentKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Publishing a NOM-037 document: its kind, the PDF, the work centers and the items of the norm it covers. Which items fit
 * the kind and which centers are in use is checked by the action.
 */
final class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-telework') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(DocumentKind::class)],
            // mimetypes reads the file's content, not its extension.
            'document' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config()->integer('telework.max_document_kb')],
            'all_centers' => ['nullable', 'boolean'],
            'work_center_ids' => ['required_without:all_centers', 'array'],
            'work_center_ids.*' => ['string'],
            'covered_items' => ['nullable', 'array'],
            'covered_items.*' => ['string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['kind' => 'tipo de documento'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['work_center_ids.required_without' => 'Elige al menos un centro de trabajo.'];
    }

    public function kind(): DocumentKind
    {
        return DocumentKind::from($this->string('kind')->toString());
    }

    /** Where the uploaded PDF is. */
    public function documentFile(): string
    {
        $document = $this->file('document');

        return $document instanceof UploadedFile ? $document->getPathname() : '';
    }

    public function allCenters(): bool
    {
        return $this->boolean('all_centers');
    }

    /** @return list<string> */
    public function workCenterIds(): array
    {
        return array_values(array_filter($this->array('work_center_ids'), is_string(...)));
    }

    /** @return list<string> */
    public function coveredItems(): array
    {
        return array_values(array_filter($this->array('covered_items'), is_string(...)));
    }
}
