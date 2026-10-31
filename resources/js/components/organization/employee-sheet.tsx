import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import EmployeeFormFields from '@/components/organization/employee-form-fields';
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
import type { EmployeeChoices, EmployeeRow } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    copy: { title: string; description: string; submit: string };
    form: { action: string; method: 'post' | 'put' };
    choices: EmployeeChoices;
    employee?: EmployeeRow;
    trigger?: ReactNode;
};

/** The form of an employee in a side panel, to add one or to correct it, with the list still in view behind it. */
export default function EmployeeSheet(props: Props) {
    const { open, onOpenChange, copy, form, choices, employee } = props;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            {props.trigger}
            <SheetContent className="w-full gap-0 bg-popover sm:inset-y-2 sm:right-2 sm:h-auto sm:max-w-[480px] sm:rounded-xl sm:border-0 sm:shadow-float">
                <SheetHeader className="gap-0.5 border-b px-6 pt-5 pb-4">
                    <SheetTitle className="text-lg leading-[26px]">
                        {copy.title}
                    </SheetTitle>
                    <SheetDescription>{copy.description}</SheetDescription>
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
                                <EmployeeFormFields
                                    errors={errors}
                                    choices={choices}
                                    {...(employee === undefined
                                        ? {}
                                        : { employee })}
                                />
                            </div>
                            <SheetFooter className="flex-row justify-end gap-2 border-t px-6 py-4">
                                <SheetClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </SheetClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {copy.submit}
                                </Button>
                            </SheetFooter>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
