<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Support\ReviewAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The person's signed paper authorization, as a PDF. RH or the assigned Comisión; the access check comes first.
 */
final class VisitDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        resolve(ReviewAccess::class)->readerOfAuthorization(($this->user() ?? abort(403))->id, $this->route('authorization'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // mimetypes reads the file's content, not its extension.
        return ['document' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config()->integer('telework.max_document_kb')]];
    }

    /** Where the uploaded PDF is, once validated. */
    public function documentPath(): string
    {
        $file = $this->file('document');

        return $file instanceof UploadedFile ? $file->getPathname() : abort(422);
    }
}
