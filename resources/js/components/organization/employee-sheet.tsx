import type { ReactNode } from 'react';
import AlertError from '@/components/alert-error';
import FormSheet from '@/components/humetria/form-sheet';
import EmployeeFormFields from '@/components/organization/employee-form-fields';
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
                    {errors['seats'] !== undefined && (
                        <AlertError
                            title="Tu plan no alcanza"
                            errors={[errors['seats']]}
                        />
                    )}
                    <EmployeeFormFields
                        errors={errors}
                        choices={choices}
                        {...(employee === undefined ? {} : { employee })}
                    />
                </>
            )}
        </FormSheet>
    );
}
