import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import BenefitCostAside from '@/components/compensation/benefit-cost-aside';
import BenefitMoreFields from '@/components/compensation/benefit-more-fields';
import BenefitPayFields from '@/components/compensation/benefit-pay-fields';
import BenefitReachFields from '@/components/compensation/benefit-reach-fields';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useLivePreview } from '@/hooks/use-live-preview';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { parseBenefitPreview } from '@/lib/compensation/benefit-draft';
import {
    grades,
    index,
    preview,
    store,
} from '@/routes/tenant/compensation/benefits';
import type { ChoiceOption } from '@/types';

/** «Nueva prestación» (board V4-Comp-Prestacion, decision A14): answered as the contract says it, with what it costs today at the right. */
export default function BenefitCreateForm({
    kinds,
    frequencies,
    sbcIntegrations,
    scenarios,
}: {
    kinds: (ChoiceOption & { unit: string })[];
    frequencies: ChoiceOption[];
    sbcIntegrations: ChoiceOption[];
    scenarios: number;
}) {
    const tenantSlug = useTenantSlug();
    const [kind, setKind] = useState('salary_percent');
    const [group, setGroup] = useState('');
    const live = useLivePreview(
        preview.url(tenantSlug),
        parseBenefitPreview,
        false,
    );

    return (
        <Form
            {...store.form(tenantSlug)}
            noValidate
            options={{ preserveScroll: true }}
            onChange={live.schedule}
            onClick={live.schedule}
            onKeyUp={live.schedule}
        >
            {({ processing, errors }) => {
                const shown = { ...live.errors, ...errors };

                return (
                    <div
                        ref={live.anchorRef}
                        className="grid items-start gap-7 min-[1240px]:grid-cols-[minmax(0,1fr)_360px]"
                    >
                        <div className="grid gap-7 card p-6 md:p-7">
                            <FormField
                                name="name"
                                idPrefix="benefit"
                                label="Nombre"
                                maxLength={120}
                                autoComplete="off"
                                error={shown['name']}
                            />
                            <BenefitPayFields
                                kinds={kinds}
                                frequencies={frequencies}
                                kind={kind}
                                onKind={setKind}
                                errors={shown}
                            />
                            <BenefitReachFields
                                unit={
                                    kinds.find(
                                        (option) => option.value === kind,
                                    )?.unit ?? ''
                                }
                                sbcIntegrations={sbcIntegrations}
                                group={group}
                                onGroup={setGroup}
                                errors={shown}
                            />
                            <BenefitMoreFields
                                gradesUrl={grades.url(tenantSlug)}
                                errors={shown}
                            />
                            <div className="flex flex-wrap justify-end gap-3 border-t pt-5">
                                <Button variant="ghost" asChild>
                                    <Link href={index(tenantSlug)}>
                                        Cancelar
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Guardar prestación
                                </Button>
                            </div>
                        </div>
                        <BenefitCostAside
                            preview={live.result}
                            loading={live.loading}
                            group={group}
                            scenarios={scenarios}
                        />
                    </div>
                );
            }}
        </Form>
    );
}
