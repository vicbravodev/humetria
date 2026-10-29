import type { FormFieldProps } from '@/components/form-field';
import FormField from '@/components/form-field';

/** A field of the Tenancy forms: its id starts with `tenant-`. */
export default function TenantField(props: Omit<FormFieldProps, 'idPrefix'>) {
    return <FormField idPrefix="tenant" {...props} />;
}
