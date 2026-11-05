import { Head, setLayoutProps } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import InviteMemberForm from '@/components/tenancy/invite-member-form';
import RoleMatrix from '@/components/tenancy/role-matrix';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/members';
import type { RoleMatrix as Matrix, RoleOption } from '@/types';

/** «Invitar a alguien» (board V3-Roles): the form, and beside it what each role can do. */
export default function InviteMember({
    roles,
    matrix,
}: {
    roles: RoleOption[];
    matrix: Matrix | null;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Usuarios', href: index(tenantSlug) },
            { title: 'Invitar' },
        ],
    });

    return (
        <>
            <Head title="Invitar a alguien" />
            <div className="mx-auto grid w-full max-w-[1440px] items-start gap-8 px-4 py-6 min-[1240px]:grid-cols-[minmax(0,560px)_minmax(0,1fr)] md:px-10 md:py-8">
                <div className="flex flex-col gap-6">
                    <PageHeader title="Invitar a alguien" />
                    <InviteMemberForm roles={roles} />
                </div>
                {matrix !== null && <RoleMatrix matrix={matrix} />}
            </div>
        </>
    );
}
