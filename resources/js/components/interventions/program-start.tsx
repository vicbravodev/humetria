import { Form } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { programStartText } from '@/lib/interventions';
import { store } from '@/routes/tenant/campaigns/program';
import type { ProgramRequirement } from '@/types';

/**
 * An evaluation without a Programa yet (pantallas: Eval-Cerrada, estados vacíos con siguiente paso): what the results ask
 * for, and the button that creates it for whoever may.
 */
export default function ProgramStart({
    campaignId,
    requirement,
    canManage,
}: {
    campaignId: string;
    requirement: ProgramRequirement;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const { title, body } = programStartText(requirement);

    return (
        <EmptyState
            icon={ClipboardList}
            title={title}
            action={
                canManage && requirement !== 'not_required' ? (
                    <Form {...store.form([tenantSlug, campaignId])}>
                        {({ processing }) => (
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Crear el Programa
                            </Button>
                        )}
                    </Form>
                ) : undefined
            }
        >
            {body}
        </EmptyState>
    );
}
