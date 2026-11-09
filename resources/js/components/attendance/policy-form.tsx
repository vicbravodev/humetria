import { Form } from '@inertiajs/react';
import PolicyFields from '@/components/attendance/policy-fields';
import PolicyPreview from '@/components/attendance/policy-preview';
import PolicySaveBar from '@/components/attendance/policy-save-bar';
import { usePolicyDraft } from '@/hooks/use-policy-draft';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/attendance/policy';
import type { AttendancePolicy } from '@/types';

/**
 * The company's policy next to «Así queda una marca», which follows what RH is choosing before it is saved; read-only
 * (all of it disabled, no save bar) for whoever may not change it.
 */
export default function PolicyForm({
    policy,
    canManage,
}: {
    policy: AttendancePolicy;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const { draft, change, discard, changes } = usePolicyDraft(policy);

    return (
        <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
            <Form
                {...update.form(tenantSlug)}
                options={{ preserveScroll: true }}
                noValidate
            >
                {({ processing, errors }) => (
                    <fieldset
                        disabled={!canManage}
                        className="flex flex-col gap-7"
                    >
                        <legend className="sr-only">
                            Política de asistencia
                        </legend>
                        <PolicyFields
                            draft={draft}
                            onChange={change}
                            errors={errors}
                        />
                        {canManage && (
                            <PolicySaveBar
                                changes={changes}
                                processing={processing}
                                onDiscard={discard}
                            />
                        )}
                    </fieldset>
                )}
            </Form>
            <PolicyPreview policy={draft} />
        </div>
    );
}
