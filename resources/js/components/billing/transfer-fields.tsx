import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import ChoiceField from '@/components/humetria/choice-field';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { profileChoices } from '@/lib/billing-form';
import { serverSource } from '@/lib/server-options';
import { search } from '@/routes/billing/accounts';
import type { PriceProfile } from '@/types';

type Errors = Partial<Record<string, string>>;

/** The choices of how the company is priced afterwards; keeping the one it has is the first. */
const PROFILES: { value: PriceProfile | ''; label: string }[] = [
    { value: '', label: 'Mantener el actual' },
    ...profileChoices('consultant'),
];

/**
 * Where the company goes (another account, or no payer), from which cut, why, and, when the destination cannot hold how it is
 * priced now, how it is priced from then on.
 */
export default function TransferFields({
    effectiveOn,
    errors,
}: {
    effectiveOn: string;
    errors: Errors;
}) {
    const [destination, setDestination] = useState<'account' | 'none'>(
        'account',
    );
    const [profile, setProfile] = useState<PriceProfile | ''>('');

    return (
        <div className="grid gap-5">
            <ChoiceField
                name="destination"
                legend="A dónde va"
                options={[
                    { value: 'account', label: 'A otra cuenta' },
                    { value: 'none', label: 'Sin pagador' },
                ]}
                value={destination}
                columns={2}
                error={errors['destination']}
                onChange={setDestination}
            />
            {destination === 'account' && (
                <div className="grid gap-2">
                    <Label htmlFor="transfer-to_account">
                        Cuenta de destino
                    </Label>
                    <Combobox
                        id="transfer-to_account"
                        name="to_account"
                        source={serverSource(search.url())}
                        placeholder="Busca por nombre o RFC"
                        invalid={errors['to_account'] !== undefined}
                        describedBy="transfer-to_account-error"
                    />
                    <InputError
                        id="transfer-to_account-error"
                        message={errors['to_account']}
                    />
                </div>
            )}
            <div className="grid gap-2">
                <Label htmlFor="transfer-effective_on">Desde el corte</Label>
                <DatePicker
                    id="transfer-effective_on"
                    name="effective_on"
                    defaultValue={effectiveOn}
                    invalid={errors['effective_on'] !== undefined}
                    describedBy="transfer-effective_on-error"
                />
                <InputError
                    id="transfer-effective_on-error"
                    message={errors['effective_on']}
                />
                <p className="text-sm text-muted-foreground">
                    Un día 1: lo ya facturado se queda con la cuenta de antes.
                </p>
            </div>
            {destination === 'account' && (
                <ChoiceField
                    name="profile"
                    legend="Cómo se cobra desde entonces"
                    options={PROFILES}
                    value={profile}
                    columns={2}
                    error={errors['profile']}
                    onChange={setProfile}
                />
            )}
            <div className="grid gap-2">
                <Label htmlFor="transfer-reason">Motivo</Label>
                <Textarea
                    id="transfer-reason"
                    name="reason"
                    maxLength={500}
                    rows={3}
                    aria-invalid={errors['reason'] !== undefined}
                />
                <InputError
                    id="transfer-reason-error"
                    message={errors['reason']}
                />
            </div>
        </div>
    );
}
