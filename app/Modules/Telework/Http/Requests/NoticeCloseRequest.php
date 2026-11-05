<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Support\NoticeAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The evidence that closes an accident notice: a PDF or a JPG or PNG photo. The access check comes first, so whoever may
 * not review the notice is told 404 whatever they send.
 */
final class NoticeCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        resolve(NoticeAccess::class)->reviewable(($this->user() ?? abort(403))->id, $this->route('notice'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // mimetypes reads the file's content, not its extension.
        return ['evidence' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.config()->integer('telework.max_document_kb')]];
    }

    public function evidence(): UploadedFile
    {
        $file = $this->file('evidence');

        return $file instanceof UploadedFile ? $file : abort(422);
    }
}
