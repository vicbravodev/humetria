import ChoiceCard from '@/components/complaints-public/choice-card';
import Combobox from '@/components/humetria/combobox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Draft } from '@/lib/complaints/form';
import { serverSource } from '@/lib/server-options';
import { centers } from '@/routes/complaints/public';
import type { ComplaintKindOption } from '@/types';

/** Step 3: where it happened and against whom, by role: the options nobody independent would receive are not offered. */
export default function WhomStep({
    code,
    kinds,
    draft,
    onChange,
}: {
    code: string;
    kinds: ComplaintKindOption[];
    draft: Draft;
    onChange: (changes: Partial<Draft>) => void;
}) {
    return (
        <div className="flex flex-col gap-6">
            <h2 className="text-xl font-semibold">
                ¿Dónde y de quién se trata?
            </h2>
            <div className="grid gap-2">
                <Label htmlFor="complaint-center">
                    Centro de trabajo (opcional)
                </Label>
                <Combobox
                    id="complaint-center"
                    name="work_center_id"
                    source={serverSource(centers.url({ code }))}
                    placeholder="Busque su centro de trabajo"
                    {...(draft.workCenter === null
                        ? {}
                        : { defaultValue: draft.workCenter })}
                    onChange={(workCenter) => onChange({ workCenter })}
                />
                <p className="text-caption text-muted-foreground">
                    Si su centro es pequeño, decirlo puede acotar quién es
                    usted. Déjelo vacío si prefiere no indicarlo.
                </p>
            </div>
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
        </div>
    );
}
