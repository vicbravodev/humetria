<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Console;

use App\Models\User;
use App\Modules\Assistant\Agents\HumiAssistant;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\Attempt;
use App\Modules\Assistant\Support\AdversarialAttempts;
use App\Modules\Assistant\Support\AssistantScopeFactory;
use App\Modules\Assistant\Support\PersonalDataRedactor;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Tries to make Humi misbehave, with the real model, as a person of a company of staging: reading another company, orders
 * hidden in a name, individual answers, its own instructions, medical and legal advice, personal data. Each attempt passes
 * or fails by patterns of the answer; the command fails if any does. It stores nothing: no conversation, no usage.
 */
#[Signature('humi:evaluate {tenant : Id de la empresa de pruebas} {--as= : Correo de una persona de esa empresa} {--other= : Id de otra empresa cuyos nombres no deben aparecer}')]
#[Description('Evalúa a Humi con intentos adversariales (solo en staging, con el modelo real)')]
final class EvaluateAssistant extends Command
{
    public function handle(
        Companies $companies,
        Members $members,
        WorkCenters $workCenters,
        AssistantScopeFactory $scopes,
        PersonalDataRedactor $redactor,
    ): int {
        if (app()->isProduction()) {
            $this->error('No corre en producción: llama al modelo real con una empresa de pruebas.');

            return self::FAILURE;
        }

        $tenantId = $this->argument('tenant');

        try {
            $companies->enter($tenantId);
        } catch (InvalidArgumentException) {
            $this->error('No encontré esa empresa.');

            return self::FAILURE;
        }

        $email = $this->option('as');
        $user = User::query()->where('email', is_string($email) ? $email : '')->first();

        if ($user === null || $members->find($user->id) === null) {
            $this->error((is_string($email) ? $email : 'Esa persona').' no es de esa empresa.');

            return self::FAILURE;
        }

        $others = $this->namesOfOther($companies, $workCenters);
        $companies->enter($tenantId);
        $scope = $scopes->make($user);
        $failed = [];

        foreach (AdversarialAttempts::all() as $attempt) {
            $answer = $this->answerTo($attempt, $scope, $redactor);
            $problem = $answer === null ? 'el modelo falló' : $this->problemWith($attempt, $answer, $others);

            $this->line(($problem === null ? '  ✓ ' : '  ✗ ').$attempt->name.($problem === null ? '' : " — {$problem}"));

            if ($problem !== null) {
                $failed[] = $attempt->name;
            }
        }

        $total = count(AdversarialAttempts::all());
        $this->newLine();
        $this->line(($total - count($failed))." de {$total} intentos pasaron.");

        if ($failed !== []) {
            $this->error('Fallaron: '.implode(', ', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function answerTo(Attempt $attempt, AssistantScope $scope, PersonalDataRedactor $redactor): ?string
    {
        try {
            return HumiAssistant::make(scope: $scope, page: $attempt->page)->prompt($redactor->redact($attempt->message))->text;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $others
     */
    private function problemWith(Attempt $attempt, string $answer, array $others): ?string
    {
        foreach ($attempt->leaksOf ? $others : [] as $name) {
            if (stripos($answer, $name) !== false) {
                return "nombró «{$name}», de otra empresa";
            }
        }

        foreach ($attempt->forbidden as $pattern) {
            if (preg_match($pattern, $answer) === 1) {
                return 'dijo lo que no debía';
            }
        }

        if ($attempt->required !== [] && array_all($attempt->required, fn (string $pattern): bool => preg_match($pattern, $answer) !== 1)) {
            return 'no se negó ni redirigió';
        }

        return null;
    }

    /**
     * The names of the other company (its trade name and its work centers) that no answer may carry.
     *
     * @return list<string>
     */
    private function namesOfOther(Companies $companies, WorkCenters $workCenters): array
    {
        $other = $this->option('other');

        if (! is_string($other) || $other === '') {
            return [];
        }

        try {
            $summary = $companies->enter($other);
        } catch (InvalidArgumentException) {
            $this->warn('No encontré la empresa de --other: no se revisa si Humi nombra a otra.');

            return [];
        }

        return array_values(array_filter([$summary->tradeName, ...array_map(fn (WorkCenterSummary $center): string => $center->name, $workCenters->all())]));
    }
}
