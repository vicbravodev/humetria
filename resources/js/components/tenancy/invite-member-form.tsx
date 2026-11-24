import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InviteFields from '@/components/tenancy/invite-fields';
import InviteSummary from '@/components/tenancy/invite-summary';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/members/invitations';
import type { InvitableRole, PermissionGroup } from '@/types';

/**
 * Who is invited and what they will do (board V3-Roles), with beside it a summary of what the role and extras chosen let them
 * do. The link goes by email, single use, for 7 days; the people of a center never come in here: they get their personal link
 * from the route.
 */
export default function InviteMemberForm({
    roles,
    groups,
}: {
    roles: InvitableRole[];
    groups: PermissionGroup[];
}) {
    const tenantSlug = useTenantSlug();
    // «Ayuda con las rutas» to begin with, where the account hands it out.
    const [role, setRole] = useState(
        roles.find((option) => option.value === 'hr')?.value ??
            roles[0]?.value ??
            '',
    );
    const [chosen, setChosen] = useState<string[]>([]);
    const picked = roles.find((option) => option.value === role);
    const base = picked?.permissions ?? [];

    return (
        <Form
            {...store.form(tenantSlug)}
            noValidate
            className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_320px]"
        >
            {({ processing, errors }) => (
                <>
                    <InviteFields
                        roles={roles}
                        groups={groups}
                        state={{
                            role: { value: role, onChange: setRole },
                            chosen: { value: chosen, onChange: setChosen },
                            processing,
                            errors,
                        }}
                    />
                    <InviteSummary
                        groups={groups}
                        role={{ label: picked?.label ?? '', base }}
                        own={chosen.filter((name) => !base.includes(name))}
                    />
                </>
            )}
        </Form>
    );
}
