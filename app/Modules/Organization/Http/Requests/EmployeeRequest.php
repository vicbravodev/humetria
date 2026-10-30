<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * The fields of an employee, to add one or to correct it. Who may do it is the controller's check.
 */
final class EmployeeRequest extends FormRequest
{
    /** Valid UTF-8 without control characters, as every single-line field of the app. */
    private const string SINGLE_LINE = 'regex:/\A[^\x00-\x1F\x7F]*\z/u';

    /**
     * What a person types is not what is stored: the email is lowercase and the mobile only its 10 digits, without
     * the country code (+52, and the old 1 after it) that people sometimes add.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    public static function normalize(array $input): array
    {
        $email = $input['email'] ?? null;
        $mobile = $input['mobile'] ?? null;

        return [
            ...$input,
            'email' => is_string($email) && $email !== '' ? Str::lower($email) : null,
            'mobile' => is_string($mobile) && $mobile !== '' ? (string) preg_replace('/\A(?:52)?1?(?=\d{10}\z)/', '', (string) preg_replace('/\D+/', '', $mobile)) : null,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, string> the message for each field of `$values` that is already another employee's.
     */
    public static function taken(array $values, ?string $except): array
    {
        $taken = [];
        $email = $values['email'] ?? null;
        $number = $values['employee_number'] ?? null;

        if (is_string($email) && Employee::query()->where('email', $email)->when($except !== null, fn ($query) => $query->whereKeyNot($except))->exists()) {
            $taken['email'] = 'Ya hay un empleado con este correo.';
        }

        if (is_string($number) && Employee::query()->whereRaw('lower(employee_number) = lower(?)', [$number])->when($except !== null, fn ($query) => $query->whereKeyNot($except))->exists()) {
            $taken['employee_number'] = 'Ya hay un empleado con este número.';
        }

        return $taken;
    }

    /**
     * @return array<string, list<In|string>>
     */
    public static function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200', self::SINGLE_LINE],
            'email' => ['nullable', 'required_without:mobile', 'string', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'regex:/\A[0-9]{10}\z/'],
            'gender' => ['required', 'string', Rule::in(array_column(Gender::cases(), 'value'))],
            'work_center_id' => ['required', 'string'],
            'area' => ['required', 'string', 'max:120', self::SINGLE_LINE],
            'job_title' => ['required', 'string', 'max:150', self::SINGLE_LINE],
            'employee_number' => ['nullable', 'string', 'max:50', self::SINGLE_LINE],
        ];
    }

    /**
     * @return array<string, list<In|string>>
     */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(WorkCenters $workCenters): array
    {
        return [function (Validator $validator) use ($workCenters): void {
            $center = $this->string('work_center_id')->toString();
            $active = array_filter($workCenters->all(), fn (WorkCenterSummary $workCenter): bool => ! $workCenter->archived && $workCenter->id === $center);

            if (! $validator->errors()->has('work_center_id') && $active === []) {
                $validator->errors()->add('work_center_id', 'Elige un centro de trabajo de la lista.');
            }

            $editing = $this->route('employee');

            foreach (self::taken($this->all(), is_string($editing) ? $editing : null) as $field => $message) {
                if (! $validator->errors()->has($field)) {
                    $validator->errors()->add($field, $message);
                }
            }
        }];
    }

    public function toData(): EmployeeData
    {
        return new EmployeeData(
            name: $this->string('name')->toString(),
            email: $this->filled('email') ? $this->string('email')->toString() : null,
            mobile: $this->filled('mobile') ? $this->string('mobile')->toString() : null,
            gender: Gender::from($this->string('gender')->toString()),
            workCenterId: $this->string('work_center_id')->toString(),
            area: $this->string('area')->trim()->toString(),
            jobTitle: $this->string('job_title')->toString(),
            employeeNumber: $this->filled('employee_number') ? $this->string('employee_number')->toString() : null,
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalize($this->all()));
    }
}
