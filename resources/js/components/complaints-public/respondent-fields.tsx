import ChoiceCard from '@/components/complaints-public/choice-card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Draft } from '@/lib/complaints/form';
import type { ComplaintKindOption } from '@/types';

/** Against whom, by role and never by name; the options nobody independent of them would receive are not offered. */
export default function RespondentFields({
    kinds,
    draft,
    onChange,
}: {
    kinds: ComplaintKindOption[];
    draft: Draft;
    onChange: (changes: Partial<Draft>) => void;
}) {
    return (
        <>
            <fieldset className="flex flex-col gap-3">
                <legend className="mb-1 font-medium">
                    ¿De quién se trata? (solo su puesto, no su nombre)
                </legend>
                {kinds.map((kind) => (
                    <ChoiceCard
                        key={kind.value}
                        name="respondent_kind"
                        value={kind.value}
                        checked={draft.respondentKind === kind.value}
                        disabled={!kind.available}
                        onSelect={(respondentKind) =>
                            onChange({ respondentKind })
                        }
                    >
                        <span className="font-medium">{kind.label}</span>
                        {!kind.available && (
                            <span className="text-base text-muted-foreground">
                                Por ahora la empresa no tiene a nadie
                                independiente que reciba esta opción. Use la
                                otra vía de abajo.
                            </span>
                        )}
                    </ChoiceCard>
                ))}
            </fieldset>
            <div className="grid gap-2">
                <Label htmlFor="complaint-respondent">
                    Algo más sobre la persona (opcional)
                </Label>
                <Textarea
                    id="complaint-respondent"
                    rows={3}
                    value={draft.respondentText}
                    onChange={(event) =>
                        onChange({ respondentText: event.target.value })
                    }
                />
            </div>
        </>
    );
}
