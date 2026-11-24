import { Head, setLayoutProps } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import InviteMemberForm from '@/components/tenancy/invite-member-form';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/members';
import type { InvitableRole, PermissionGroup } from '@/types';

/** «Invitar a alguien» (board V3-Roles): the form, and beside it what the role and extras chosen let the person do. */
export default function InviteMember({
    roles,
    groups,
}: {
    roles: InvitableRole[];
    groups: PermissionGroup[];
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
            <div className="flex flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
                <PageHeader title="Invitar a alguien" />
                <InviteMemberForm roles={roles} groups={groups} />
            </div>
        </>
    );
}
