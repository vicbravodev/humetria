import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import EmployeeFormFields from '@/components/organization/employee-form-fields';
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

/** The form of an employee in a dialog, to add one or to correct it. */
export default function EmployeeDialog(props: Props) {
    const { open, onOpenChange, copy, form, choices, employee } = props;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {props.trigger}
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>{copy.title}</DialogTitle>
                <DialogDescription>{copy.description}</DialogDescription>
                <Form
                    {...form}
                    noValidate
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <EmployeeFormFields
                                errors={errors}
                                choices={choices}
                                {...(employee === undefined
                                    ? {}
                                    : { employee })}
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {copy.submit}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
