import { Form } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { RouteFormDefinition } from '@/wayfinder';

/** RH sends the confirmation email again (one person) or to everyone who has not confirmed (a center). */
export default function RequestLinkButton({
    form,
    label,
}: {
    form: RouteFormDefinition<'post'>;
    label: string;
}) {
    return (
        <Form {...form} options={{ preserveScroll: true }}>
            {({ processing }) => (
                <Button variant="outline" disabled={processing}>
                    {processing ? <Spinner /> : <Mail aria-hidden />}
                    {label}
                </Button>
            )}
        </Form>
    );
}
