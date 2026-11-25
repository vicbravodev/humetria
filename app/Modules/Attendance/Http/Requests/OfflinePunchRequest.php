<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Data\OfflineSubmission;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\SignerType;
use App\Modules\Attendance\Support\OfflineTicket;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * The delivery of marks a phone made without a connection (criterio 3.3.1): up to 20 in a body of up to 64 KB, each with its
 * own ticket and signature. `authorize()` is true on purpose: what authorizes each mark is its signature and its ticket, as the
 * signature is the credential of `MarkRequest`; there is no account. An error here is of the envelope only (the shape, more
 * than 20, two people in one delivery): the phone splits the batch and sends them one by one. No field may hold a `|`, which
 * would shift the ones after it in the challenge that was signed.
 */
final class OfflinePunchRequest extends FormRequest
{
    /** Bytes the body may have: a mark with its signature takes about 2 KB, 20 of them fit with room. */
    private const int MAX_BYTES = 65536;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        abort_if(strlen($this->getContent()) > self::MAX_BYTES, 413);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.config()->integer('attendance.offline.delivery_batch_max')],
            'items.*' => ['array'],
            'items.*.seq' => ['required', 'integer', 'min:0', 'distinct'],
            'items.*.type' => ['required', Rule::enum(MarkType::class)],
            'items.*.ticket' => ['required', 'string', 'max:256', 'not_regex:/\|/'],
            'items.*.center_id' => ['required', 'uuid'],
            'items.*.qr' => ['nullable', 'string', 'max:128', 'not_regex:/\|/'],
            'items.*.declared_at' => ['required', 'integer', 'min:0'],
            'items.*.lat_e5' => ['nullable', 'required_with:items.*.lng_e5,items.*.accuracy_m', 'integer', 'between:-9000000,9000000'],
            'items.*.lng_e5' => ['nullable', 'required_with:items.*.lat_e5,items.*.accuracy_m', 'integer', 'between:-18000000,18000000'],
            'items.*.accuracy_m' => ['nullable', 'required_with:items.*.lat_e5,items.*.lng_e5', 'integer', 'min:0'],
            'items.*.device_clock_at' => ['nullable', 'integer', 'min:0'],
            'items.*.justification' => ['nullable', 'string', 'max:500'],
            'items.*.agreement_version' => ['required', 'integer', 'min:1'],
            'items.*.signer' => ['required', Rule::enum(SignerType::class)],
            'items.*.credential' => ['required_if:items.*.signer,passkey', 'array'],
            'items.*.credential.id' => ['required_with:items.*.credential', 'string'],
            'items.*.credential.response' => ['required_with:items.*.credential', 'array'],
            'items.*.credential.response.clientDataJSON' => ['required_with:items.*.credential', 'string'],
            'items.*.credential.response.authenticatorData' => ['required_with:items.*.credential', 'string'],
            'items.*.credential.response.signature' => ['required_with:items.*.credential', 'string'],
            'items.*.credential.response.userHandle' => ['nullable', 'string'],
            'items.*.device_key_id' => ['required_if:items.*.signer,device_key', 'uuid'],
            'items.*.signature' => ['required_if:items.*.signer,device_key', 'string', 'max:200'],
            'items.*.pass' => ['required_if:items.*.signer,device_key', 'string', 'max:512', 'not_regex:/\|/'],
        ];
    }

    /**
     * A delivery is of one person: the tickets that verify must name the same one, or the tickets the answer hands out would
     * have no owner. A shared phone sends one delivery per person.
     *
     * @return list<callable(Validator): void>
     */
    public function after(OfflineTicket $tickets): array
    {
        return [function (Validator $validator) use ($tickets): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $people = [];

            foreach ($this->array('items') as $item) {
                $ticket = is_array($item) && is_string($item['ticket'] ?? null) ? $tickets->verify($item['ticket']) : null;
                $people[$ticket['employee_id'] ?? ''] = true;
            }

            unset($people['']);

            if (count($people) > 1) {
                $validator->errors()->add('items', 'mixed_people');
            }
        }];
    }

    /**
     * The marks of the delivery, in the order of the phone's own number: what the server processes first.
     *
     * @return list<OfflineSubmission>
     */
    public function submissions(): array
    {
        $submissions = [];

        foreach ($this->array('items') as $item) {
            $submissions[] = is_array($item) ? $this->submission(new Fluent($item)) : throw new LogicException('The items were not validated.');
        }

        usort($submissions, fn (OfflineSubmission $a, OfflineSubmission $b): int => $a->seq <=> $b->seq);

        return $submissions;
    }

    /**
     * @param  Fluent<array-key, mixed>  $item
     */
    private function submission(Fluent $item): OfflineSubmission
    {
        return new OfflineSubmission(
            seq: $item->integer('seq'),
            type: $item->enum('type', MarkType::class) ?? throw new LogicException('The type was not validated.'),
            ticket: $item->string('ticket')->toString(),
            workCenterId: $item->string('center_id')->toString(),
            qr: $item->filled('qr') ? $item->string('qr')->toString() : null,
            declaredAt: CarbonImmutable::createFromTimestampUTC($item->integer('declared_at')),
            latE5: $item->filled('lat_e5') ? $item->integer('lat_e5') : null,
            lngE5: $item->filled('lng_e5') ? $item->integer('lng_e5') : null,
            accuracyM: $item->filled('accuracy_m') ? $item->integer('accuracy_m') : null,
            deviceClockAt: $item->filled('device_clock_at') ? CarbonImmutable::createFromTimestampUTC($item->integer('device_clock_at')) : null,
            justification: $item->filled('justification') ? $item->string('justification')->toString() : null,
            agreementVersion: $item->integer('agreement_version'),
            signerType: $item->enum('signer', SignerType::class) ?? throw new LogicException('The signer was not validated.'),
            credential: $item->filled('credential') ? $item->array('credential') : null,
            deviceKeyId: $item->filled('device_key_id') ? $item->string('device_key_id')->toString() : null,
            signature: $item->filled('signature') ? $item->string('signature')->toString() : null,
            pass: $item->filled('pass') ? $item->string('pass')->toString() : null,
        );
    }
}
