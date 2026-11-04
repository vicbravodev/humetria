import { Form } from '@inertiajs/react';
import Notice from '@/components/humetria/notice';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { countOf } from '@/lib/plural';
import { route } from '@/routes/tenant/complaints/unassigned';

/** The complaints nobody holds: how many, never which, and the button that assigns them again by the same rules. */
export default function UnassignedNotice({ count }: { count: number }) {
    const tenantSlug = useTenantSlug();

    if (count === 0) {
        return null;
    }

    return (
        <Form {...route.form(tenantSlug)} options={{ preserveScroll: true }}>
            {({ processing }) => (
                <Notice
                    action={
                        <Button variant="outline" disabled={processing}>
                            {processing && <Spinner />}
                            Asignar de nuevo
                        </Button>
                    }
                >
                    <strong>
                        {countOf(count, 'caso', 'casos')} sin encargada.
                    </strong>{' '}
                    Se asignan a quien atiende y no está en conflicto con la
                    queja: quien administra el canal no ve de qué tratan.
                </Notice>
            )}
        </Form>
    );
}
