import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Errors = Partial<Record<string, string>>;

/** How many pauses per shift and how long each lasts: the law fixes neither, so the form asks for what was agreed. */
export default function AgreementNumbersFields({ errors }: { errors: Errors }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="agreement-pauses">Pausas por turno</Label>
                <Input
                    id="agreement-pauses"
                    name="pauses_per_shift"
                    type="number"
                    min={1}
                    inputMode="numeric"
                />
                <InputError message={errors['pauses_per_shift']} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="agreement-minutes">Minutos de cada pausa</Label>
                <Input
                    id="agreement-minutes"
                    name="minutes_each"
                    type="number"
                    min={1}
                    inputMode="numeric"
                />
                <p className="text-sm text-muted-foreground">
                    La ley no fija un mínimo: pon lo que se acordó.
                </p>
                <InputError message={errors['minutes_each']} />
            </div>
        </div>
    );
}
