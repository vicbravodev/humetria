<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Enums\StaffGroup;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * The fields of an employee, to add one or to correct it. Who may do it is the controller's check.
 */
final class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-organization');
    }

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
    public static function taken(array $values, ?string $except, bool $union = false): array
    {
        $taken = [];
        $email = $values['email'] ?? null;
        $number = $values['employee_number'] ?? null;

        if (is_string($email) && Employee::query()->where('email', $email)->when($except !== null, fn ($query) => $query->whereKeyNot($except))->exists()) {
            $taken['email'] = 'Ya hay un empleado con este correo.';
        }

        if (is_string($number) && Employee::query()->whereRaw('lower(employee_number) = lower(?)', [$number])->when($except !== null, fn ($query) => $query->whereKeyNot($except))->exists()) {
            $taken['employee_number'] = $union ? 'Ya hay una persona con este ID de la empresa.' : 'Ya hay un empleado con este número.';
        }

        return $taken;
    }

    /**
     * A union's people: only the company ID is required, and what the form does not ask (contact, gender, work center)
     * is left out. Whether it is a union is up to the caller, who knows the account.
     *
     * @return array<string, list<In|string>>
     */
    public static function unionFieldRules(): array
    {
        $rules = self::fieldRules();

        return [
            'job_category' => $rules['job_category'],
            'job_level' => $rules['job_level'],
            'hired_on' => $rules['hired_on'],
            'staff_group' => $rules['staff_group'],
            'state' => $rules['state'],
            'name' => ['nullable', 'string', 'max:200', self::SINGLE_LINE],
            'area' => ['nullable', 'string', 'max:120', self::SINGLE_LINE],
            'job_title' => ['nullable', 'string', 'max:150', self::SINGLE_LINE],
            'employee_number' => ['required', 'string', 'max:50', self::SINGLE_LINE],
        ];
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
            'job_category' => ['nullable', 'required_with:job_level', 'string', 'max:120', self::SINGLE_LINE],
            'job_level' => ['nullable', 'required_with:job_category', 'string', 'max:60', self::SINGLE_LINE],
            // Today as the company counts it: in Mexico City, not in UTC.
            'hired_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString()],
            'staff_group' => ['nullable', 'string', Rule::in(array_column(StaffGroup::cases(), 'value'))],
            'state' => ['nullable', 'string', Rule::in(array_keys(resolve(WorkCenters::class)->states()))],
        ];
    }

    /**
     * @return array<string, list<In|string>>
     */
    public function rules(): array
    {
        return $this->union() ? self::unionFieldRules() : self::fieldRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->union() ? ['employee_number' => 'ID de la empresa'] : [];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(WorkCenters $workCenters): array
    {
        return [function (Validator $validator) use ($workCenters): void {
            $union = $this->union();
            $center = $this->string('work_center_id')->toString();
            $active = array_filter($workCenters->all(), fn (WorkCenterSummary $workCenter): bool => ! $workCenter->archived && $workCenter->id === $center);

            if (! $union && ! $validator->errors()->has('work_center_id') && $active === []) {
                $validator->errors()->add('work_center_id', 'Elige un centro de trabajo de la lista.');
            }

            $editing = $this->route('employee');

            foreach (self::taken($this->all(), is_string($editing) ? $editing : null, $union) as $field => $message) {
                if (! $validator->errors()->has($field)) {
                    $validator->errors()->add($field, $message);
                }
            }
        }];
    }

    public function toData(): EmployeeData
    {
        $union = $this->union();
        // What the form of a union does not ask is not kept even when sent.
        $text = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->toString() : null;

        return new EmployeeData(
            // A company's form requires these, so they are there; a union's may lack all but the company ID.
            name: $text('name'),
            email: $union ? null : $text('email'),
            mobile: $union ? null : $text('mobile'),
            gender: $union ? null : Gender::tryFrom($this->string('gender')->toString()),
            workCenterId: $union ? null : $text('work_center_id'),
            area: $this->filled('area') ? $this->string('area')->trim()->toString() : null,
            jobTitle: $text('job_title'),
            employeeNumber: $text('employee_number'),
            jobCategory: $this->filled('job_category') ? $this->string('job_category')->trim()->toString() : null,
            jobLevel: $this->filled('job_level') ? $this->string('job_level')->trim()->toString() : null,
            hiredOn: $this->date('hired_on', 'Y-m-d')?->toImmutable(),
            staffGroup: $this->filled('staff_group') ? StaffGroup::from($this->string('staff_group')->toString()) : null,
            state: $text('state'),
        );
    }

    /** Whether the account is a union, whose people are loaded with little more than the company ID. */
    private function union(): bool
    {
        return resolve(Companies::class)->current()->isUnion();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalize($this->all()));
    }
}
