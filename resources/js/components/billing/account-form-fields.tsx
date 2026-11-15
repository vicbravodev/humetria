import AccountContactFields from '@/components/billing/account-contact-fields';
import AccountField from '@/components/billing/account-field';
import AccountFiscalFields from '@/components/billing/account-fiscal-fields';
import AccountKindChoice from '@/components/billing/account-kind-choice';
import type { AccountDetail, SatOption } from '@/types';

/** The fields of a payer account; its kind is chosen when creating, and never changes after. */
export default function AccountFormFields({
    errors,
    account,
    taxRegimes,
    cfdiUses,
}: {
    errors: Partial<Record<string, string>>;
    account?: AccountDetail;
    taxRegimes: SatOption[];
    cfdiUses: SatOption[];
}) {
    return (
        <div className="grid gap-4">
            {account === undefined && (
                <AccountKindChoice error={errors['kind']} />
            )}
            <AccountField
                name="legal_name"
                label="Razón social"
                required
                maxLength={200}
                defaultValue={account?.legal_name}
                error={errors['legal_name']}
            />
            <AccountField
                name="rfc"
                label="RFC"
                required
                maxLength={20}
                defaultValue={account?.rfc}
                autoCapitalize="characters"
                error={errors['rfc']}
            />
            <AccountContactFields errors={errors} account={account} />
            <AccountFiscalFields
                errors={errors}
                account={account}
                taxRegimes={taxRegimes}
                cfdiUses={cfdiUses}
            />
        </div>
    );
}
