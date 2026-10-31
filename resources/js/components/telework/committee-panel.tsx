import { ShieldCheck, Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Notice from '@/components/humetria/notice';
import AssignMemberForm from '@/components/telework/assign-member-form';
import CommitteeMembersTable from '@/components/telework/committee-members-table';
import RegisterCommitteeForm from '@/components/telework/register-committee-form';
import type { Committee, CommitteePerson } from '@/types';

/**
 * The Comisión of one center, in the state it is: not registered yet (its first step), registered without anyone on it, or
 * with its people (pantallas: Usuarios, Ajustes).
 */
export default function CommitteePanel({
    tenantSlug,
    workCenterId,
    committee,
    candidates,
    canManage,
}: {
    tenantSlug: string;
    workCenterId: string;
    committee: Committee | null;
    candidates: CommitteePerson[];
    canManage: boolean;
}) {
    if (committee === null) {
        return canManage ? (
            <div className="rounded-xl border bg-card p-6">
                <RegisterCommitteeForm
                    tenantSlug={tenantSlug}
                    workCenterId={workCenterId}
                    candidates={candidates}
                />
            </div>
        ) : (
            <EmptyState
                icon={ShieldCheck}
                title="Este centro aún no tiene Comisión"
            >
                Recursos Humanos la registra primero; después la Comisión revisa
                la lista de verificación.
            </EmptyState>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            {committee.members.length === 0 ? (
                <EmptyState
                    icon={Users}
                    title="La Comisión no tiene integrantes"
                >
                    {canManage
                        ? 'Agrega al menos a una persona para que el centro vuelva a tener una Comisión activa.'
                        : 'Recursos Humanos debe agregar a una persona para que el centro vuelva a tener una Comisión activa.'}
                </EmptyState>
            ) : (
                <CommitteeMembersTable
                    tenantSlug={tenantSlug}
                    workCenterId={workCenterId}
                    members={committee.members}
                    canManage={canManage}
                />
            )}
            {canManage && (
                <div className="rounded-xl border bg-card p-5">
                    <AssignMemberForm
                        tenantSlug={tenantSlug}
                        workCenterId={workCenterId}
                        candidates={candidates}
                    />
                </div>
            )}
            {!committee.hasAct && (
                <Notice>
                    <strong>Sin acta en PDF.</strong> La Comisión quedó
                    registrada solo con la fecha del acta.
                </Notice>
            )}
        </div>
    );
}
