import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import WorkCenterFormFields from '@/components/tenancy/work-center-form-fields';
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
import type { MexicanStateOption, WorkCenterRow } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    copy: { title: string; description: string; submit: string };
    form: { action: string; method: 'post' | 'put' };
    states: MexicanStateOption[];
    workCenter?: WorkCenterRow;
    trigger?: ReactNode;
};

/** The form of a work center in a dialog, to add one or to correct it. */
export default function WorkCenterDialog(props: Props) {
    const { open, onOpenChange, copy, form, states, workCenter } = props;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {props.trigger}
            {/* Nine fields: on a phone the dialog scrolls inside the screen. */}
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>{copy.title}</DialogTitle>
                <DialogDescription>{copy.description}</DialogDescription>
                <Form
                    {...form}
                    noValidate
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <WorkCenterFormFields
                                errors={errors}
                                states={states}
                                {...(workCenter === undefined
                                    ? {}
                                    : { workCenter })}
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
