import { Form, Link } from '@inertiajs/react';
import PolicyBasics from '@/components/leave/policy-basics';
import PolicyRowsEditor from '@/components/leave/policy-rows-editor';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index, store, update } from '@/routes/tenant/vacations/policies';
import type { LeavePolicyData } from '@/types';

/** The form of a policy, new or being changed. A viewer sees it filled in and cannot send it. */
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
    const form =
        policy === null
            ? store.form(tenantSlug)
            : update.form({ tenant: tenantSlug, policy: policy.id });

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            noValidate
            className="grid gap-8"
        >
            {({ processing, errors }) => (
                <fieldset
                    disabled={!canManage}
                    className="grid gap-8 disabled:opacity-90"
                >
                    <PolicyBasics policy={policy} errors={errors} />
                    <PolicyRowsEditor
                        initial={policy?.rows ?? []}
                        floors={floors}
                        errors={errors}
                    />
                    <div className="flex flex-wrap items-center justify-end gap-3 border-t pt-4">
                        <p className="mr-auto text-sm text-muted-foreground">
                            Los periodos ya otorgados conservan los términos con
                            que se otorgaron.
                        </p>
                        <Button variant="outline" asChild>
                            <Link href={index(tenantSlug)}>
                                {canManage ? 'Cancelar' : 'Volver'}
                            </Link>
                        </Button>
                        {canManage && (
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {policy === null
                                    ? 'Crear política'
                                    : 'Guardar política'}
                            </Button>
                        )}
                    </div>
                </fieldset>
            )}
        </Form>
    );
}
