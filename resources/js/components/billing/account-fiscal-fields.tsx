import AccountCatalogField from '@/components/billing/account-catalog-field';
import AccountField from '@/components/billing/account-field';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { AccountDetail, SatOption } from '@/types';

type Errors = Partial<Record<string, string>>;

/** What a CFDI will need (regime, postal code, use) and the notes of the contract. */
export default function AccountFiscalFields({
    errors,
    account,
    taxRegimes,
    cfdiUses,
}: {
    errors: Errors;
    account: AccountDetail | undefined;
    taxRegimes: SatOption[];
    cfdiUses: SatOption[];
}) {
    return (
        <>
            <div className="grid gap-4 sm:grid-cols-3">
                <AccountCatalogField
                    name="tax_regime"
                    label="Régimen fiscal"
                    options={taxRegimes}
                    defaultValue={account?.tax_regime ?? ''}
                    error={errors['tax_regime']}
                />
                <AccountField
                    name="postal_code"
                    label="Código postal"
                    inputMode="numeric"
                    maxLength={5}
                    defaultValue={account?.postal_code ?? ''}
                    error={errors['postal_code']}
                />
                <AccountCatalogField
                    name="cfdi_use"
                    label="Uso de CFDI"
                    options={cfdiUses}
                    defaultValue={account?.cfdi_use ?? ''}
                    error={errors['cfdi_use']}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="billing-notes">Notas del contrato</Label>
                <Textarea
                    id="billing-notes"
                    name="notes"
                    rows={3}
                    maxLength={2000}
                    defaultValue={account?.notes ?? ''}
                    aria-invalid={
                        errors['notes'] === undefined ? undefined : true
                    }
                />
                <InputError
                    id="billing-notes-error"
                    message={errors['notes']}
                />
            </div>
        </>
    );
}
