import { Head, usePage } from '@inertiajs/react';
import PageHeader from '@/components/page-header';
import InviteMemberDialog from '@/components/tenancy/invite-member-dialog';
import MemberInvitationsTable from '@/components/tenancy/member-invitations-table';
import MembersTable from '@/components/tenancy/members-table';
import type { MemberInvitationRow, MemberRow, RoleOption } from '@/types';

export default function Members({
    members,
    invitations,
    roles,
}: {
    members: MemberRow[];
    invitations: MemberInvitationRow[];
    roles: RoleOption[];
}) {
    // Always set here: this page only exists inside a company.
    const tenantSlug = usePage().props.tenant?.slug ?? '';

    return (
        <>
            <Head title="Usuarios" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader title="Usuarios">
                    <InviteMemberDialog tenantSlug={tenantSlug} roles={roles} />
                </PageHeader>
                <MembersTable
                    tenantSlug={tenantSlug}
                    members={members}
                    roles={roles}
                />
                {invitations.length > 0 && (
                    <section className="flex flex-col gap-4">
                        <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                            Invitaciones pendientes
                        </h2>
                        <MemberInvitationsTable
                            tenantSlug={tenantSlug}
                            invitations={invitations}
                        />
                    </section>
                )}
            </div>
        </>
    );
}
