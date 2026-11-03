import ChoiceCard from '@/components/complaints-public/choice-card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Draft } from '@/lib/complaints/form';
import { NOTICE } from '@/lib/complaints/form';

/** Step 4: how the person goes. Anonymous by default; with a name, only the handlers read it. The notice of an event needs it. */
export default function IdentityStep({
    draft,
    onChange,
}: {
    draft: Draft;
    onChange: (changes: Partial<Draft>) => void;
}) {
    const notice = draft.type === NOTICE;

    return (
        <fieldset className="flex flex-col gap-3">
            <legend className="mb-1 text-xl font-semibold">
                ¿Cómo quiere ir?
            </legend>
            {notice ? (
                <p className="rounded-lg bg-muted px-4 py-3 text-base">
                    Este aviso necesita su nombre: sin él no podemos canalizarle
                    a atención ni cuenta como el escrito que pide la norma. Solo
                    lo ven las personas que atienden el caso.
                </p>
            ) : (
                <ChoiceCard
                    name="identity_mode"
                    value="anonymous"
                    checked={draft.identityMode === 'anonymous'}
                    onSelect={() => onChange({ identityMode: 'anonymous' })}
                >
                    <span className="font-medium">Anónima</span>
                    <span className="text-base text-muted-foreground">
                        Sin su nombre ni forma de contactarle. Podrá dar
                        seguimiento con la referencia y la clave.
                    </span>
                </ChoiceCard>
            )}
            <ChoiceCard
                name="identity_mode"
                value="confidential"
                checked={draft.identityMode === 'confidential'}
                onSelect={() => onChange({ identityMode: 'confidential' })}
            >
                <span className="font-medium">Confidencial, con mi nombre</span>
                <span className="text-base text-muted-foreground">
                    Solo lo ven las personas que atienden su caso, nunca la
                    persona de la que se trata.
                </span>
            </ChoiceCard>
            {draft.identityMode === 'confidential' && (
                <div className="grid gap-4 rounded-lg border bg-card p-4">
                    <div className="grid gap-2">
                        <Label htmlFor="reporter-name">Su nombre</Label>
                        <Input
                            id="reporter-name"
                            value={draft.reporterName}
                            maxLength={150}
                            autoComplete="off"
                            onChange={(event) =>
                                onChange({ reporterName: event.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="reporter-contact">
                            Cómo contactarle (opcional)
                        </Label>
                        <Input
                            id="reporter-contact"
                            value={draft.reporterContact}
                            maxLength={200}
                            autoComplete="off"
                            onChange={(event) =>
                                onChange({
                                    reporterContact: event.target.value,
                                })
                            }
                        />
                    </div>
                </div>
            )}
        </fieldset>
    );
}
