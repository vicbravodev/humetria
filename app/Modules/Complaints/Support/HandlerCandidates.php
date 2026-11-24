<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;

/**
 * The people who could be designated to handle complaints, with the kinds of complaint each may not receive (criterio
 * C-45) as the server decides them, by what they hold and never by what their role is called: the dialog only reads it.
 */
final readonly class HandlerCandidates
{
    public function __construct(private Members $members, private ExcludingPermissions $excluding) {}

    /**
     * @return list<array{value: string, label: string, role: string, roleLabel: string, excludedKinds: list<string>}>
     */
    public function all(): array
    {
        $held = $this->excluding->byMember();

        return array_map(fn (MemberSummary $member): array => [
            'value' => (string) $member->userId,
            'label' => $member->name,
            'role' => $member->role->key,
            'roleLabel' => $member->role->label,
            'excludedKinds' => array_values(array_map(
                fn (RespondentKind $kind): string => $kind->value,
                array_filter(RespondentKind::cases(), fn (RespondentKind $kind): bool => $kind->excludes($member->role->key, $held[$member->userId] ?? [])),
            )),
        ], $this->members->complaintHandlers());
    }
}
