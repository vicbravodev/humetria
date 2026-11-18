import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/humetria/input-error';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { state } from '@/routes/tenant/sso';

/** One change of state: it sends the whole state it wants, as the server keeps them consistent. */
export default function StateButton({
    enabled,
    enforced,
    variant = 'default',
    error,
    children,
}: {
    enabled: boolean;
    enforced: boolean;
    variant?: 'default' | 'outline';
    error?: string | undefined;
    children: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...state.form(tenantSlug)}
            transform={() => ({ enabled, enforced })}
            options={{ preserveScroll: true }}
            className="grid gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <div>
                        <Button
                            type="submit"
                            variant={variant}
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {children}
                        </Button>
                    </div>
                    <InputError
                        message={
                            error ?? errors['enabled'] ?? errors['enforced']
                        }
                    />
                </>
            )}
        </Form>
    );
}
