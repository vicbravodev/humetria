import { Form } from '@inertiajs/react';
import ActionFormFields from '@/components/interventions/action-form-fields';
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
import type {
    ActionCatalog,
    AreaOption,
    ProgramAction,
    ProgramMember,
} from '@/types';

/**
 * The form of an action in a side panel (pantalla Empleados-Alta), to add one to a row or to correct it, with the Programa
 * still in view behind it.
 */
export default function ActionSheet({
    open,
    onOpenChange,
    form,
    itemId,
    itemLabel,
    action,
    tools,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    form: { action: string; method: 'post' | 'put' };
    itemId: string;
    itemLabel: string;
    action: ProgramAction | null;
    tools: {
        catalog: ActionCatalog;
        areas: AreaOption[];
        members: ProgramMember[];
        responsible: string | null;
    };
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 bg-popover sm:inset-y-2 sm:right-2 sm:h-auto sm:max-w-[520px] sm:rounded-xl sm:border-0 sm:shadow-float">
                <SheetHeader className="gap-0.5 border-b px-6 pt-5 pb-4">
                    <SheetTitle className="text-lg leading-[26px]">
                        {action === null ? 'Agregar acción' : 'Editar acción'}
                    </SheetTitle>
                    <SheetDescription>{itemLabel}</SheetDescription>
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
                            <div className="flex-1 overflow-y-auto px-6 py-5">
                                <ActionFormFields
                                    itemId={itemId}
                                    action={action}
                                    tools={tools}
                                    errors={errors}
                                />
                            </div>
                            <SheetFooter className="flex-row justify-end gap-2 border-t px-6 py-4">
                                <SheetClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </SheetClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Guardar acción
                                </Button>
                            </SheetFooter>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
