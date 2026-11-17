import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';

type Errors = Partial<Record<string, string>>;

/**
 * The side panel that holds the form to add or edit a record, with its list still in view behind it (pantalla
 * Empleados-Alta; docs/design-system/componentes/Sheet.md). The whole width of a phone, 520 px from the tablet up. It
 * closes when the server accepts the form; the page behind keeps its scroll.
 */
export default function FormSheet({
    open,
    onOpenChange,
    title,
    description,
    form,
    submit,
    trigger,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: ReactNode;
    form: { action: string; method: 'post' | 'put' };
    submit: string;
    /** The button that opens it, wrapped in a `SheetTrigger`; without one the page opens it through `open`. */
    trigger?: ReactNode;
    children: (errors: Errors) => ReactNode;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            {trigger}
            <SheetContent className="w-full gap-0 bg-popover sm:inset-y-2 sm:right-2 sm:h-auto sm:max-w-[520px] sm:rounded-xl sm:border-0 sm:shadow-float">
                <SheetHeader className="gap-0.5 border-b px-6 pt-5 pb-4">
                    <SheetTitle className="text-lg leading-[26px]">
                        {title}
                    </SheetTitle>
                    <SheetDescription>{description}</SheetDescription>
                </SheetHeader>
                <Form
                    {...form}
                    noValidate
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid flex-1 content-start gap-6 overflow-y-auto px-6 py-5">
                                {children(errors)}
                            </div>
                            <SheetFooter className="flex-row justify-end gap-2 border-t px-6 py-4">
                                <SheetClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </SheetClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {submit}
                                </Button>
                            </SheetFooter>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
