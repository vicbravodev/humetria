import Combobox from '@/components/humetria/combobox';
import { Button } from '@/components/ui/button';
import { DialogClose } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useEvaluationCandidate } from '@/hooks/use-evaluation-candidate';
import { serverSource } from '@/lib/server-options';
import { options as workCenterOptions } from '@/routes/tenant/work-centers';
import type { WorkCenterEvaluation } from '@/types';

/**
 * First step of a new evaluation: the work center, searched on the server. Its people and what applies come from the
 * page's `candidate`, asked for alone once it is chosen.
 */
export default function EvaluationCenterStep({
    tenantSlug,
    chosen,
    onNext,
}: {
    tenantSlug: string;
    chosen: WorkCenterEvaluation | null;
    onNext: (center: WorkCenterEvaluation) => void;
}) {
    const { choice, found, loading, choose } = useEvaluationCandidate(chosen);

    return (
        <>
            <div className="flex flex-col gap-2 px-6 py-[22px]">
                <Label htmlFor="evaluation-work-center">
                    Centro de trabajo
                </Label>
                <Combobox
                    id="evaluation-work-center"
                    name="evaluate"
                    source={serverSource(
                        workCenterOptions.url(tenantSlug),
                        choice === undefined ? [] : [choice],
                    )}
                    defaultValue={choice}
                    placeholder="Elige un centro"
                    onChange={choose}
                />
                <p className="text-[13px] leading-[18px] text-muted-foreground">
                    La NOM-035 se evalúa por centro de trabajo: su número de
                    personas decide qué guías aplican.
                </p>
            </div>
            <div className="flex justify-between gap-2 rounded-b-xl border-t bg-muted px-6 py-4">
                <DialogClose asChild>
                    <Button variant="ghost">Cancelar</Button>
                </DialogClose>
                <Button
                    type="button"
                    disabled={found === null || loading}
                    onClick={() => {
                        if (found !== null) {
                            onNext(found);
                        }
                    }}
                >
                    {loading && <Spinner />}
                    Siguiente: guías y muestra
                </Button>
            </div>
        </>
    );
}
