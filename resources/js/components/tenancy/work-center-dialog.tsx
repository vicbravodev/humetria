import type { ReactNode } from 'react';
import AlertError from '@/components/alert-error';
import FormSheet from '@/components/humetria/form-sheet';
import WorkCenterFormFields from '@/components/tenancy/work-center-form-fields';
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

/** The form of a work center in the side panel, to add one or to correct it, with the centers still in view behind it. */
export default function WorkCenterDialog(props: Props) {
    const { open, onOpenChange, copy, form, states, workCenter } = props;

    return (
        <FormSheet
            open={open}
            onOpenChange={onOpenChange}
            title={copy.title}
            description={copy.description}
            form={form}
            submit={copy.submit}
            trigger={props.trigger}
        >
            {(errors) => (
                <>
                    {errors['centers'] !== undefined && (
                        <AlertError
                            title="Tu plan no alcanza"
                            errors={[errors['centers']]}
                        />
                    )}
                    <WorkCenterFormFields
                        errors={errors}
                        states={states}
                        {...(workCenter === undefined ? {} : { workCenter })}
                    />
                </>
            )}
        </FormSheet>
    );
}
