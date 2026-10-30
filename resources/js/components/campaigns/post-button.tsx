import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/** A button that posts a form with nothing but itself: one action, one click. */
export default function PostButton({
    form,
    variant = 'outline',
    size,
    children,
}: {
    form: { action: string; method: 'post' };
    variant?: 'outline' | 'ghost' | 'default';
    size?: 'sm';
    children: ReactNode;
}) {
    return (
        <Form {...form} options={{ preserveScroll: true }}>
            {({ processing }) => (
                <Button
                    type="submit"
                    variant={variant}
                    size={size}
                    disabled={processing}
                >
                    {processing && <Spinner />}
                    {children}
                </Button>
            )}
        </Form>
    );
}
