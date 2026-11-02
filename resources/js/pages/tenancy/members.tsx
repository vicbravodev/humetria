import { Head, usePage } from '@inertiajs/react';
import Pagination from '@/components/pagination';
import PageHeader from '@/components/page-header';
import InviteMemberDialog from '@/components/tenancy/invite-member-dialog';
import MembersTable from '@/components/tenancy/members-table';
import RoleCapabilities from '@/components/tenancy/role-capabilities';
import type {
    MemberInvitationRow,
    MemberRow,
    Paginated,
    RoleOption,
} from '@/types';

export default function Members({
    members,
    invitations,
    roles,
}: {
    members: Paginated<MemberRow>;
    invitations: Paginated<MemberInvitationRow>;
    roles: RoleOption[];
}) {
    // Always set here: this page only exists inside a company.
    const { tenant } = usePage().props;
    const tenantSlug = tenant?.slug ?? '';
    const tradeName = tenant?.trade_name ?? '';

    return (
        <>
            <Head title="Usuarios" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Usuarios"
                    description={`Quién entra a Humetría por ${tradeName} y qué puede hacer.`}
                >
                    <InviteMemberDialog
                        tenantSlug={tenantSlug}
                        tradeName={tradeName}
                        roles={roles}
                    />
                </PageHeader>
                <div className="flex flex-col gap-3">
                    <MembersTable
                        tenantSlug={tenantSlug}
                        members={members.data}
                        invitations={invitations.data}
                        roles={roles}
                    />
                    <Pagination
                        page={members}
                        label="Páginas de usuarios"
                        pinned={false}
                    />
                    <Pagination
                        previous="Ver más recientes"
                        next="Ver más antiguas"
                        page={invitations}
                        label="Páginas de invitaciones"
                        pinned={false}
                    />
                </div>
                <RoleCapabilities />
            </div>
        </>
    );
}
