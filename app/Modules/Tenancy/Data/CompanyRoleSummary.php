<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * A role a person holds in a company, as other modules see it.
 *
 * @param  string  $key  The internal name: a template key (owner, hr, viewer, committee, complaints) or `custom_<uuid>`.
 * @param  string  $label  What the panel calls it: the template's label or the one the owner typed.
 * @param  bool  $template  Whether it is one of the system's templates rather than one of the company's own.
 */
final readonly class CompanyRoleSummary
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $template,
    ) {}
}
