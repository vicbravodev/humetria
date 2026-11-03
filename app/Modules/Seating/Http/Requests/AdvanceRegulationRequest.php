<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use App\Modules\Seating\Enums\RegulationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * The next step of the regulation and what it needs: the signing date and the signed PDF, the deposit date and folio, or
 * the distribution date. Whether the step follows the current state is the action's to say.
 */
final class AdvanceRegulationRequest extends FormRequest
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
        $today = 'before_or_equal:'.CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();

        return [
            'to' => ['required', Rule::in([RegulationStatus::Signed->value, RegulationStatus::Deposited->value, RegulationStatus::Distributed->value])],
            'signed_on' => ['required_if:to,signed', 'nullable', 'date_format:Y-m-d', $today],
            // mimetypes reads the file's content, not its extension.
            'file' => ['required_if:to,signed', 'nullable', 'file', 'mimetypes:application/pdf', 'max:'.config()->integer('seating.max_document_kb')],
            'deposited_on' => ['required_if:to,deposited', 'nullable', 'date_format:Y-m-d', $today],
            'deposit_folio' => ['required_if:to,deposited', 'nullable', 'string', 'max:100'],
            'distributed_on' => ['required_if:to,distributed', 'nullable', 'date_format:Y-m-d', $today],
        ];
    }

    public function to(): RegulationStatus
    {
        return RegulationStatus::from($this->string('to')->toString());
    }

    /** @return array{signed_on: string|null, deposited_on: string|null, deposit_folio: string|null, distributed_on: string|null} */
    public function dates(): array
    {
        $value = fn (string $key): ?string => $this->filled($key) ? trim($this->string($key)->toString()) : null;

        return ['signed_on' => $value('signed_on'), 'deposited_on' => $value('deposited_on'), 'deposit_folio' => $value('deposit_folio'), 'distributed_on' => $value('distributed_on')];
    }

    /** Where the uploaded signed PDF is, if one came. */
    public function filePath(): ?string
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile ? $file->getPathname() : null;
    }
}
