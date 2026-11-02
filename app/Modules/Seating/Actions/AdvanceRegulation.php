<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Actions\RecordAudit;
use App\Modules\Seating\Enums\RegulationStatus;
use App\Modules\Seating\Models\InternalRegulation;
use App\Modules\Seating\Support\PdfStore;
use App\Modules\Seating\Support\SeatingFiles;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Records the next step of the internal regulation: signed by the comisión mixta, deposited at the Centro Federal de
 * Conciliación y Registro Laboral, distributed (LFT 424 and 425; criterio C-13). Each step follows the previous one, and
 * signing needs the four parts written and the signed PDF. The system registers what RH did: it deposits and sends nothing.
 */
final readonly class AdvanceRegulation
{
    public function __construct(private CurrentTenant $tenant, private PdfStore $pdfs, private SeatingFiles $files, private RecordAudit $audit) {}

    /**
     * @param  array{signed_on?: string|null, deposited_on?: string|null, deposit_folio?: string|null, distributed_on?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function handle(InternalRegulation $regulation, RegulationStatus $to, array $data, ?string $file, int $userId): InternalRegulation
    {
        $stored = $to === RegulationStatus::Signed && $file !== null ? $this->pdfs->store($file, "regulations/{$this->tenant->id()}/{$regulation->work_center_id}") : null;

        try {
            return DB::transaction(function () use ($regulation, $to, $data, $stored): InternalRegulation {
                $locked = InternalRegulation::query()->whereKey($regulation->id)->lockForUpdate()->firstOrFail();
                $from = $locked->status;

                if ($this->next($from) !== $to) {
                    throw ValidationException::withMessages(['to' => 'Ese paso no sigue al estado actual del reglamento.']);
                }

                $changes = match ($to) {
                    RegulationStatus::Signed => $this->signing($locked, $data, $stored),
                    RegulationStatus::Deposited => $this->depositing($locked, $data),
                    default => $this->distributing($locked, $data),
                };
                $locked->update([...$changes, 'status' => $to]);

                $record = ['regulation_id' => $locked->id, 'work_center_id' => $locked->work_center_id, 'from' => $from->value, 'to' => $to->value];
                $this->audit->handle('seating.regulation_status_changed', $locked, $record);
                Log::info('seating.regulation_status_changed', $record);

                return $locked;
            });
        } catch (Throwable $failure) {
            if ($stored !== null) {
                $this->files->delete($stored);
            }

            throw $failure;
        }
    }

    private function next(RegulationStatus $status): ?RegulationStatus
    {
        return match ($status) {
            RegulationStatus::Draft => RegulationStatus::Signed,
            RegulationStatus::Signed => RegulationStatus::Deposited,
            RegulationStatus::Deposited => RegulationStatus::Distributed,
            RegulationStatus::Distributed => null,
        };
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array{signed_on: string|null, file_path: string|null}
     */
    private function signing(InternalRegulation $regulation, array $data, ?string $stored): array
    {
        if (count(array_filter($regulation->parts, fn (string $part): bool => trim($part) !== '')) < 4) {
            throw ValidationException::withMessages(['parts' => 'Faltan partes del reglamento: los factores de riesgo, las pausas, la ubicación de los asientos y la norma de uso deben estar escritos.']);
        }

        return ['signed_on' => $data['signed_on'] ?? null, 'file_path' => $stored];
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array{deposited_on: string|null, deposit_folio: string|null}
     */
    private function depositing(InternalRegulation $regulation, array $data): array
    {
        if ($regulation->signed_on !== null && ($data['deposited_on'] ?? '') < $regulation->signed_on->toDateString()) {
            throw ValidationException::withMessages(['deposited_on' => 'El depósito no puede ser antes de la firma.']);
        }

        return ['deposited_on' => $data['deposited_on'] ?? null, 'deposit_folio' => $data['deposit_folio'] ?? null];
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array{distributed_on: string|null}
     */
    private function distributing(InternalRegulation $regulation, array $data): array
    {
        if ($regulation->deposited_on !== null && ($data['distributed_on'] ?? '') < $regulation->deposited_on->toDateString()) {
            throw ValidationException::withMessages(['distributed_on' => 'El reparto no puede ser antes del depósito.']);
        }

        return ['distributed_on' => $data['distributed_on'] ?? null];
    }
}
