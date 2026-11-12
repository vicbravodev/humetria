import { Form } from '@inertiajs/react';
import { useState } from 'react';
import PolicyBasics from '@/components/leave/policy-basics';
import PolicyPreview from '@/components/leave/policy-preview';
import PolicyRowsEditor from '@/components/leave/policy-rows-editor';
import PolicySaveBar from '@/components/leave/policy-save-bar';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { draftsOf } from '@/lib/leave/policy';
import { store, update } from '@/routes/tenant/vacations/policies';
import type { LeavePolicyData } from '@/types';

/**
 * The form of a policy, new or being changed: its sections on the left, what the days become on the right as they are
 * typed, and the bar of saving always in view. A viewer sees it filled in and cannot send it.
 */
export default function PolicyForm({
    policy,
    floors,
    canManage,
}: {
    policy: LeavePolicyData | null;
    floors: number[];
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const [rows, setRows] = useState(() => draftsOf(policy?.rows ?? []));
    const form =
        policy === null
            ? store.form(tenantSlug)
            : update.form({ tenant: tenantSlug, policy: policy.id });

    return (
        <Form {...form} options={{ preserveScroll: true }} noValidate>
            {({ processing, errors, isDirty }) => (
                <fieldset disabled={!canManage} className="disabled:opacity-90">
                    <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                        <div className="grid gap-8">
                            <PolicyBasics policy={policy} errors={errors} />
                            <PolicyRowsEditor
                                rows={rows}
                                setRows={setRows}
                                floors={floors}
                                errors={errors}
                            />
                        </div>
                        <PolicyPreview rows={rows} floors={floors} />
                    </div>
                    <PolicySaveBar
                        dirty={isDirty}
                        errors={Object.keys(errors).length}
                        processing={processing}
                        isNew={policy === null}
                        canManage={canManage}
                    />
                </fieldset>
            )}
        </Form>
    );
}
