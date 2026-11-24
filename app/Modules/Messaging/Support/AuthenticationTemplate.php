<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Enums\TemplateCategory;

/**
 * A template of Meta's authentication library: Meta writes the text (fixed, in Spanish, with the code), and the only button is
 * the one that copies the code. It has no text, links or media of ours, which is why it is not a TemplateDefinition.
 */
final readonly class AuthenticationTemplate
{
    public TemplateCategory $category;

    public function __construct(public string $key, public string $language, public bool $addSecurityRecommendation = true)
    {
        $this->category = TemplateCategory::Authentication;
    }

    public function button(): TemplateButton
    {
        return TemplateButton::copyCode('Copiar código');
    }
}
