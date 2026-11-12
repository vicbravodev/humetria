import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import ModeOptions from '@/components/engagement/pulse/mode-options';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { PulseModeOption } from '@/types';

/** Starts a pulse draft: RH picks what this wave looks at (one of the modes) and nothing is sent until it is launched. */
export default function ModePicker({
    modes,
    label,
}: {
    modes: PulseModeOption[];
    label: string;
}) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();

    return (
        <Form
            {...routes.store.form(tenantSlug)}
            className="flex w-full max-w-md flex-col gap-4 text-left"
        >
            {({ processing, errors }) => (
                <>
                    <ModeOptions modes={modes} error={errors['mode']} />
                    <Button type="submit" disabled={processing}>
                        {processing ? <Spinner /> : <Plus aria-hidden="true" />}
                        {label}
                    </Button>
                </>
            )}
        </Form>
    );
}
