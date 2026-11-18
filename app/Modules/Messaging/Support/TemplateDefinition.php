<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Enums\TemplateButtonType;
use App\Modules\Messaging\Enums\TemplateCategory;
use InvalidArgumentException;

/**
 * One UTILITY template in full: what Humetria sends and what Meta is asked to approve come from here. Variables have names
 * (`{{empresa}}`) and one example each for Meta; Meta's rules are checked on construction, so an invalid definition fails a
 * test, not production. An AUTHENTICATION template is not built from our text: see AuthenticationTemplate.
 */
final readonly class TemplateDefinition
{
    public TemplateCategory $category;

    /** How many positional parameters a message for this template carries (the body variables). */
    public int $bodyParameterCount;

    /**
     * @param  array<string, string>  $examples  the example Meta is shown for each variable
     * @param  list<TemplateButton>  $buttons
     */
    public function __construct(
        public string $key,
        public string $language,
        public string $body,
        public array $examples,
        public ?string $header = null,
        public ?string $footer = null,
        public array $buttons = [],
    ) {
        $this->category = TemplateCategory::Utility;

        if (preg_match('/^[a-z][a-z0-9_]*_v[0-9]+$/', $key) !== 1) {
            throw new InvalidArgumentException("A template name carries its version [{$key}].");
        }

        $this->checkTexts();
        $this->checkVariables();
        $this->checkButtons();
        $this->bodyParameterCount = count($this->bodyVariables());
    }

    /** @return list<string> the body variables in the order they appear, once each */
    public function bodyVariables(): array
    {
        return self::variablesOf($this->body);
    }

    /** The one variable of the header, if it has one. */
    public function headerVariable(): ?string
    {
        return $this->header === null ? null : (self::variablesOf($this->header)[0] ?? null);
    }

    /**
     * The positional list a caller builds (the order of the body variables) as the named values Meta takes.
     *
     * @param  list<string>  $positional
     * @return array<string, string>
     */
    public function namedParameters(array $positional): array
    {
        $names = $this->bodyVariables();

        if (count($names) !== count($positional)) {
            throw new InvalidArgumentException("Template [{$this->key}] takes ".count($names).' parameters, got '.count($positional).'.');
        }

        return array_combine($names, $positional);
    }

    public function isPending(): bool
    {
        return array_any($this->buttons, fn (TemplateButton $button): bool => $button->pending !== null);
    }

    /** @return list<string> */
    private static function variablesOf(string $text): array
    {
        preg_match_all('/\{\{([^{}]*)\}\}/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    private function checkTexts(): void
    {
        if ($this->body === '' || mb_strlen($this->body) > 1024) {
            throw new InvalidArgumentException("The body of [{$this->key}] has 1 to 1024 characters.");
        }

        foreach (['header' => $this->header, 'footer' => $this->footer] as $name => $text) {
            if ($text !== null && ($text === '' || mb_strlen($text) > 60)) {
                throw new InvalidArgumentException("The {$name} of [{$this->key}] has 1 to 60 characters.");
            }
        }

        // The providers advise it (Meta's page does not say it): a body that starts or ends with a variable, or has two together.
        if (preg_match('/^\{\{|\}\}$|\}\}\s*\{\{/', trim($this->body)) === 1) {
            throw new InvalidArgumentException("The body of [{$this->key}] does not start or end with a variable, nor has two together.");
        }
    }

    private function checkVariables(): void
    {
        $header = $this->header === null ? [] : self::variablesOf($this->header);

        if (count($header) > 1) {
            throw new InvalidArgumentException("The header of [{$this->key}] has at most one variable.");
        }

        $used = [...$this->bodyVariables(), ...$header];

        foreach ($used as $name) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
                throw new InvalidArgumentException("Variable [{$name}] of [{$this->key}] needs a name like `empresa`.");
            }
        }

        $missing = array_diff($used, array_keys($this->examples));
        $unused = array_diff(array_keys($this->examples), $used);

        if ($missing !== [] || $unused !== []) {
            throw new InvalidArgumentException("The examples of [{$this->key}] are the variables it uses, one each.");
        }
    }

    private function checkButtons(): void
    {
        $types = array_map(fn (TemplateButton $button): TemplateButtonType => $button->type, $this->buttons);
        $count = fn (TemplateButtonType $type): int => count(array_filter($types, fn (TemplateButtonType $each): bool => $each === $type));

        if (count($types) > 10 || $count(TemplateButtonType::Url) > 2 || $count(TemplateButtonType::Phone) > 1 || $count(TemplateButtonType::CopyCode) > 0) {
            throw new InvalidArgumentException("Too many buttons in [{$this->key}].");
        }

        // Quick replies go together, after every other type.
        $seenQuickReply = false;

        foreach ($types as $type) {
            if ($type === TemplateButtonType::QuickReply) {
                $seenQuickReply = true;
            } elseif ($seenQuickReply) {
                throw new InvalidArgumentException("The quick replies of [{$this->key}] go after the other buttons.");
            }
        }
    }
}
