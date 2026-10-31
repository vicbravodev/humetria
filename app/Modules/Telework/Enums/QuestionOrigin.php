<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/**
 * Where a question of a center's checklist comes from. Only `dof` is the literal text of the Apéndice 3; an edited one
 * keeps its `base_code` and becomes `dof_modified`.
 */
enum QuestionOrigin: string
{
    case Dof = 'dof';
    case DofModified = 'dof_modified';
    case Humetria = 'humetria';
    case Committee = 'committee';
}
