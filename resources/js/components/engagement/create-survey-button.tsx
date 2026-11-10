import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';

/** Starts a draft of the climate survey and goes to it: nothing is sent until it is launched. */
export default function CreateSurveyButton({ label }: { label: string }) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();

    return (
        <Form {...routes.store.form(tenantSlug)}>
            {({ processing }) => (
                <Button type="submit" disabled={processing}>
                    {processing ? <Spinner /> : <Plus aria-hidden="true" />}
                    {label}
                </Button>
            )}
        </Form>
    );
}
