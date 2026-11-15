import type { FormFieldProps } from '@/components/form-field';
import FormField from '@/components/form-field';

/** A field of the billing forms: its id starts with `billing-`. */
export default function AccountField(props: Omit<FormFieldProps, 'idPrefix'>) {
    return <FormField idPrefix="billing" {...props} />;
}
