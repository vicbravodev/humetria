<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * A person's access to one area of «Roles y permisos»: the area, the level they reach («Sin acceso», «Ver», «Administrar»…)
 * and the extras of the area they hold, as Humi says them («ve los tamizajes de la Guía I»).
 */
final readonly class AreaAccess
{
    /**
     * @param  list<string>  $extras
     */
    public function __construct(public string $area, public string $level, public array $extras) {}
}
