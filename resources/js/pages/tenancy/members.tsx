import { Head, Link, usePage } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import Pagination from '@/components/humetria/pagination';
import PageHeader from '@/components/humetria/page-header';
import MembersTable from '@/components/tenancy/members-table';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { create } from '@/routes/tenant/members/invitations';
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
    const tradeName = tenant?.trade_name ?? '';
    const tenantSlug = useTenantSlug();

    return (
        <>
            <Head title="Usuarios" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Usuarios"
                    description={`Quién entra a Humetría por ${tradeName} y qué puede hacer.`}
                >
                    <Button asChild>
                        <Link href={create(tenantSlug)}>
                            <UserPlus aria-hidden />
                            Invitar
                        </Link>
                    </Button>
                </PageHeader>
                <div className="flex flex-col gap-3">
                    <MembersTable
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
            </div>
        </>
    );
}
