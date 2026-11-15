import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { RouteFormDefinition } from '@/wayfinder';

type Errors = Partial<Record<string, string>>;

/**
 * A dialog that holds one form (pantallas: Canalizacion, a dialog with its fields, Cancelar and the main action). It
 * closes when the server accepts the form; the page behind keeps its scroll.
 */
export default function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    form,
    submit,
    tone = 'default',
    wide = false,
    blocked = false,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: ReactNode;
    form: RouteFormDefinition<'post'>;
    submit: string;
    tone?: 'default' | 'destructive';
    /** For a form that needs room beside its fields (a map): wider from the tablet up. */
    wide?: boolean;
    /** The main action waits for something the form asks for (a confirmation) before it can be sent. */
    blocked?: boolean;
    children: (errors: Errors) => ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={cn(
                    'max-h-[90svh] overflow-y-auto',
                    wide ? 'sm:max-w-lg md:max-w-4xl' : 'sm:max-w-lg',
                )}
            >
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    noValidate
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            {children(errors)}
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant={
                                        tone === 'destructive'
                                            ? 'destructive'
                                            : 'default'
                                    }
                                    disabled={processing || blocked}
                                >
                                    {processing && <Spinner />}
                                    {submit}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
