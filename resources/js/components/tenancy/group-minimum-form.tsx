import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import GroupMinimumField from '@/components/tenancy/group-minimum-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/privacy';

/** The company's minimum, saved as typed; while it is the company's own, a way back to the recommended one. */
export default function GroupMinimumForm({
    minimum,
    chosen,
    recommended,
    lowest,
    highest,
}: {
    minimum: number;
    chosen: number | null;
    recommended: number;
    lowest: number;
    highest: number;
}) {
    const tenantSlug = useTenantSlug();
    const [resetting, setResetting] = useState(false);

    return (
        <Form
            {...update.form(tenantSlug)}
            options={{ preserveScroll: true }}
            noValidate
            className="flex flex-col gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <GroupMinimumField
                        key={minimum}
                        minimum={minimum}
                        recommended={recommended}
                        lowest={lowest}
                        highest={highest}
                        error={errors['min_group']}
                    />
                    <div className="flex flex-wrap justify-end gap-3 border-t pt-[18px]">
                        {chosen !== null && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing || resetting}
                                onClick={() =>
                                    router.put(
                                        update.url(tenantSlug),
                                        { min_group: null },
                                        {
                                            preserveScroll: true,
                                            onStart: () => setResetting(true),
                                            onFinish: () => setResetting(false),
                                        },
                                    )
                                }
                            >
                                {resetting && <Spinner />}
                                Volver al recomendado ({recommended})
                            </Button>
                        )}
                        <Button disabled={processing || resetting}>
                            {processing && <Spinner />}
                            Guardar mínimo
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
