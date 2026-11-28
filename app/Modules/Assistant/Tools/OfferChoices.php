<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ToolCard;
use App\Modules\Assistant\Support\ScopeGuard;
use App\Modules\Assistant\Support\UntrustedText;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Asks the person which of two to six things they meant, as buttons: the question and the options the model chose from
 * what its other tools returned. It reads nothing and changes nothing; the panel draws the card and a click sends the
 * option's text as an ordinary message. Everything the model writes here goes out on one line, without markup.
 */
final readonly class OfferChoices implements Tool
{
    public const string NAME = 'OfferChoices';

    private const int MIN_OPTIONS = 2; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    private const int MAX_OPTIONS = 6; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    private const int MAX_LABEL = 40; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    public function __construct(private AssistantScope $scope, private ScopeGuard $guard) {}

    public function description(): string
    {
        return 'Pregunta a la persona, con botones, cuál de varias cosas quiso decir. Úsala solo cuando la respuesta cambia según lo que quiso decir '
            .'y ya consultaste lo que la empresa tiene: de 2 a 6 opciones, la última la de «todo». Cada opción lleva label (corto) y text (la frase que se enviará). '
            .'Nunca la uses para datos que puedes consultar.';
    }

    public function handle(Request $request): string
    {
        $this->guard->check($this->scope);

        $question = $request['question'] ?? null;
        $options = $this->options($request['options'] ?? null);

        if (! is_string($question) || $options === null) {
            return 'No pude armar la pregunta: ofrece de 2 a 6 opciones distintas.';
        }

        return (new ToolCard('choices', UntrustedText::line($question), $options))->toJson();
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()->required(),
            'options' => $schema->array()->items($schema->object([
                'label' => $schema->string()->required(),
                'text' => $schema->string()->required(),
            ]))->required(),
        ];
    }

    /**
     * @return list<array{label: string, text: string}>|null null when they are not 2 to 6 distinct options
     */
    private function options(mixed $given): ?array
    {
        if (! is_array($given)) {
            return null;
        }

        $options = [];

        foreach ($given as $option) {
            if (! is_array($option) || ! is_string($option['label'] ?? null) || ! is_string($option['text'] ?? null)) {
                return null;
            }

            $options[] = [
                'label' => mb_strimwidth(UntrustedText::line($option['label']), 0, self::MAX_LABEL, '…'),
                'text' => UntrustedText::line($option['text']),
            ];
        }

        $distinct = count(array_unique(array_column($options, 'text'))) === count($options);

        return $distinct && count($options) >= self::MIN_OPTIONS && count($options) <= self::MAX_OPTIONS ? $options : null;
    }
}
