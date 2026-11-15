import AccountField from '@/components/billing/account-field';
import type { AccountDetail } from '@/types';

type Errors = Partial<Record<string, string>>;

/** Whom to write to about the account: name, phone and email. */
export default function AccountContactFields({
    errors,
    account,
}: {
    errors: Errors;
    account: AccountDetail | undefined;
}) {
    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2">
                <AccountField
                    name="contact_name"
                    label="Nombre del contacto"
                    required
                    maxLength={120}
                    defaultValue={account?.contact_name}
                    error={errors['contact_name']}
                />
                <AccountField
                    name="contact_phone"
                    label="Teléfono del contacto"
                    type="tel"
                    maxLength={30}
                    defaultValue={account?.contact_phone ?? ''}
                    error={errors['contact_phone']}
                />
            </div>
            <AccountField
                name="contact_email"
                label="Correo del contacto"
                type="email"
                required
                maxLength={255}
                defaultValue={account?.contact_email}
                error={errors['contact_email']}
            />
        </>
    );
}
