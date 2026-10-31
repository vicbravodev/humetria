<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Tenancy\Contracts\Members;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Registering the Comisión of a work center: the date of its act, the act's PDF (optional) and who sits on it.
 */
final class CommitteeRequest extends FormRequest
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
            'constituted_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            // mimetypes reads the file's content, not its extension.
            'act' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:'.config()->integer('telework.max_document_kb')],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'distinct', Rule::in(array_map(fn ($member): int => $member->userId, resolve(Members::class)->reviewers()))],
        ];
    }

    public function constitutedOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('constituted_on')->toString());
    }

    /** Where the uploaded act PDF is, if one came. */
    public function actFile(): ?string
    {
        $act = $this->file('act');

        return $act instanceof UploadedFile ? $act->getPathname() : null;
    }

    /** @return list<int> */
    public function memberIds(): array
    {
        return array_values(collect($this->array('member_ids'))->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all());
    }
}
