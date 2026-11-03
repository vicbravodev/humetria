<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use App\Modules\Seating\Enums\DocumentKind;
use App\Modules\Seating\Support\QuintaIncisos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * A PDF RH keeps for the inspection: its kind, a title, its date and the incisos of Quinta it covers.
 */
final class DocumentRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(DocumentKind::class)],
            'title' => ['required', 'string', 'max:200'],
            'document_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString()],
            'incisos' => ['nullable', 'array'],
            'incisos.*' => ['string', Rule::in(array_keys(QuintaIncisos::LABELS))],
            // mimetypes reads the file's content, not its extension.
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config()->integer('seating.max_document_kb')],
        ];
    }

    public function kind(): DocumentKind
    {
        return DocumentKind::from($this->string('kind')->toString());
    }

    public function title(): string
    {
        return trim($this->string('title')->toString());
    }

    /** @return list<string> */
    public function incisos(): array
    {
        return array_values(array_filter($this->array('incisos'), is_string(...)));
    }

    /** Where the uploaded PDF is. */
    public function filePath(): string
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file->getPathname() : '';
    }
}
