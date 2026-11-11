<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Modules\Leave\Models\LeaveType;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * A leave type of the company's own (criterio C-19): added, changed or taken out of use. The ones that come with the
 * tool stay as they are. The name of a type is never logged (criterio C-22).
 */
final readonly class SaveLeaveType
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * @throws ValidationException
     */
    public function handle(string $name, bool $paid, ?string $typeId = null): LeaveType
    {
        $type = $typeId === null ? new LeaveType(['tenant_id' => $this->tenant->id()]) : LeaveType::query()->findOrFail($typeId);
        $name = trim($name);

        $error = match (true) {
            $type->key !== null => 'Este permiso viene de la ley: no se puede cambiar.',
            $name === '' => 'Escribe el nombre del permiso.',
            mb_strlen($name) > 80 => 'El nombre no puede pasar de 80 caracteres.',
            LeaveType::query()->where('name', $name)->whereKeyNot($type->id)->exists() => 'Ya hay un permiso con ese nombre.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['name' => $error]);
        }

        $type->fill(['name' => $name, 'paid' => $paid])->save();
        Log::info('leave.type_saved', ['type_id' => $type->id]);

        return $type;
    }

    /**
     * Requests already made with it keep it; it is no longer offered.
     *
     * @throws ValidationException
     */
    public function archive(string $typeId): void
    {
        $type = LeaveType::query()->findOrFail($typeId);

        if ($type->key !== null) {
            throw ValidationException::withMessages(['type_id' => 'Este permiso viene de la ley: no se puede archivar.']);
        }

        if ($type->archived_at === null) {
            $type->update(['archived_at' => now()]);
            Log::info('leave.type_archived', ['type_id' => $type->id]);
        }
    }
}
