<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Console;

use App\Models\User;
use App\Modules\Assistant\Agents\HumiAssistant;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Support\AnswerMetrics;
use App\Modules\Assistant\Support\AssistantScopeFactory;
use App\Modules\Assistant\Support\PersonalDataRedactor;
use App\Modules\Assistant\Support\QualityQuestions;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Members;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Laravel\Ai\Responses\Data\ToolCall;
use Throwable;

/**
 * Asks Humi a fixed set of everyday questions, with the real model and the real tools, as a person of a company of
 * staging, and reports numbers per answer (words, links, closing offers) so two prompts or two models can be compared:
 * run it on each and read the averages. It stores nothing: no conversation, no usage.
 */
#[Signature('humi:quality {tenant : Id de la empresa de pruebas} {--as= : Correo de una persona de esa empresa} {--model= : Otro modelo, solo para esta corrida}')]
#[Description('Mide la calidad de las respuestas de Humi con preguntas fijas (solo en staging, con el modelo real)')]
final class QualityAssistant extends Command
{
    public function handle(Companies $companies, Members $members, AssistantScopeFactory $scopes, PersonalDataRedactor $redactor): int
    {
        if (app()->isProduction()) {
            $this->error('No corre en producción: llama al modelo real con una empresa de pruebas.');

            return self::FAILURE;
        }

        try {
            $companies->enter($this->stringArgument('tenant'));
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

        $scope = $scopes->make($user);
        $model = $this->option('model');
        $previous = config('humi.model');

        if (is_string($model) && $model !== '') {
            config()->set('humi.model', $model);
        }

        try {
            $results = [];

            foreach (QualityQuestions::all() as $name => $question) {
                $metrics = $this->answerTo($scope, $redactor->redact($question['text']));
                $results[$name] = $metrics;
                $this->line('  '.$name.': '.($metrics === null ? 'el modelo falló' : $this->describe($metrics)));
            }
        } finally {
            config()->set('humi.model', $previous);
        }

        return $this->summarize($results);
    }

    private function answerTo(AssistantScope $scope, string $question): ?AnswerMetrics
    {
        try {
            $answer = HumiAssistant::make(scope: $scope)->prompt($question);

            return AnswerMetrics::of($answer->text, array_values(array_map(fn (ToolCall $call): string => $call->name, $answer->toolCalls->all())));
        } catch (Throwable) {
            return null;
        }
    }

    private function describe(AnswerMetrics $metrics): string
    {
        return "{$metrics->words} palabras, {$metrics->links} ".($metrics->links === 1 ? 'enlace' : 'enlaces').($metrics->endsWithOffer ? ', cierra con oferta' : '').($metrics->asksChoices ? ', pregunta con botones' : '');
    }

    /**
     * @param  array<string, AnswerMetrics|null>  $results
     */
    private function summarize(array $results): int
    {
        $answered = array_values(array_filter($results));
        $total = count($results);
        $this->newLine();
        $this->line($total.' preguntas.');

        if ($answered !== []) {
            $words = (int) round(array_sum(array_map(fn (AnswerMetrics $metrics): int => $metrics->words, $answered)) / count($answered));
            $offers = count(array_filter($answered, fn (AnswerMetrics $metrics): bool => $metrics->endsWithOffer));
            $this->line("Promedio: {$words} palabras.");
            $this->line("{$offers} de {$total} cierran con oferta.");
            $this->line($this->choicesLine($results));
        }

        if (count($answered) < $total) {
            $this->error((string) ($total - count($answered)).' sin respuesta.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * How many of the questions that can be read two ways Humi asked with buttons, and how many of the clear ones it did.
     *
     * @param  array<string, AnswerMetrics|null>  $results
     */
    private function choicesLine(array $results): string
    {
        $ambiguous = array_filter(QualityQuestions::all(), fn (array $question): bool => $question['ambiguous']);
        $asked = fn (string $name): bool => ($results[$name] ?? null)?->asksChoices === true;
        $askedAmbiguous = count(array_filter(array_keys($ambiguous), $asked));
        $clear = array_diff(array_keys($results), array_keys($ambiguous));
        $askedClear = count(array_filter($clear, $asked));

        return 'Preguntaron con botones: '.$askedAmbiguous.' de '.count($ambiguous).' ambiguas; '.$askedClear.' de '.count($clear).' claras.';
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }
}
