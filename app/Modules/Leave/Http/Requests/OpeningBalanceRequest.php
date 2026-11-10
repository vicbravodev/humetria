<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Requests;

use App\Modules\Leave\Support\OpeningCsv;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * The file of opening balances, checked and loaded by owner and hr. The file is the only thing that counts: whoever
 * confirms sends it again and it is planned again on the server, so no row comes from the client already validated.
 */
final class OpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-vacations') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'extensions:csv,txt', 'mimes:csv,txt', 'max:1024']];
    }

    /**
     * The rows of the file, at most `leave.import_max_rows`.
     *
     * @return list<array<string, string>>
     *
     * @throws ValidationException when the file as a whole cannot be taken
     */
    public function rows(): array
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? OpeningCsv::read($file->getRealPath()) : [];
    }

    public function userId(): int
    {
        return $this->user()->id ?? abort(403);
    }
}
