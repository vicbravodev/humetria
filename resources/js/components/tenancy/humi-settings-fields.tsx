import InputError from '@/components/humetria/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    tokensText,
    type TenantAiSettings,
} from '@/lib/assistant/tenant-settings';

/** The two things the superadmin sets about Humi for a company: whether it is on, and its tokens per month. */
export default function HumiSettingsFields({
    settings,
    budgetError,
}: {
    settings: TenantAiSettings;
    budgetError: string | undefined;
}) {
    return (
        <>
            <div className="flex items-center gap-3">
                <input type="hidden" name="enabled" value="0" />
                <Checkbox
                    id="humi-enabled"
                    name="enabled"
                    value="1"
                    defaultChecked={settings.enabled}
                />
                <Label htmlFor="humi-enabled">
                    Humi está activa para esta empresa
                </Label>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="humi-budget">Tokens por mes</Label>
                <Input
                    id="humi-budget"
                    name="monthly_token_budget"
                    type="number"
                    min={0}
                    step={1}
                    inputMode="numeric"
                    defaultValue={settings.monthlyTokenBudget ?? ''}
                    placeholder={`${tokensText(settings.defaultBudget)} (el de la plataforma)`}
                    aria-invalid={budgetError !== undefined}
                    aria-describedby="humi-budget-help humi-budget-error"
                />
                <p
                    id="humi-budget-help"
                    className="text-sm text-muted-foreground"
                >
                    Este mes usó {tokensText(settings.usedTokens)} tokens.
                    Déjalo vacío para usar el tope de la plataforma.
                </p>
                <InputError id="humi-budget-error" message={budgetError} />
            </div>
        </>
    );
}
