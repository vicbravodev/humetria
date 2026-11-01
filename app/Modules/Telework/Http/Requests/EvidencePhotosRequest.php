<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * The photos a person sends for an evidence request, in flat fields `photo_general_{n}` (as the checklist sends its own).
 * Their content is judged by `PhotoStore`; this keeps the count sane. No account: the portal link is the credential.
 */
final class EvidencePhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $most = config()->integer('telework.max_photos_per_group');
            $count = count($this->photos());

            if ($count === 0) {
                $validator->errors()->add('photos', 'Agregue al menos una foto.');
            } elseif ($count > $most) {
                $validator->errors()->add('photos', "Puede enviar hasta {$most} fotos.");
            }
        }];
    }

    /** @return list<UploadedFile> */
    public function photos(): array
    {
        $photos = [];

        foreach ($this->allFiles() as $field => $file) {
            if ($file instanceof UploadedFile && preg_match('/^photo_general_\d{1,3}$/', (string) $field) === 1) {
                $photos[] = $file;
            }
        }

        return $photos;
    }
}
