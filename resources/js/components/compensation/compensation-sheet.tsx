import type { ReactNode } from 'react';
import FormSheet from '@/components/humetria/form-sheet';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    copy: { title: string; description: string; submit: string };
    form: { action: string; method: 'post' | 'put' };
    /** The fields, given the errors the server answered. */
    children: (errors: Partial<Record<string, string>>) => ReactNode;
    /** Shown above the form, inside the scrolling area: the history a form adds to. */
    above?: ReactNode;
    trigger?: ReactNode;
};

/** A form of Compensación in a side panel with the list still in view behind it (as the employee form). */
export default function CompensationSheet(props: Props) {
    const { open, onOpenChange, copy, form, children, above, trigger } = props;

    return (
        <FormSheet
            open={open}
            onOpenChange={onOpenChange}
            title={copy.title}
            description={copy.description}
            form={form}
            submit={copy.submit}
            trigger={trigger}
        >
            {(errors) => (
                <>
                    {above}
                    {children(errors)}
                </>
            )}
        </FormSheet>
    );
}
