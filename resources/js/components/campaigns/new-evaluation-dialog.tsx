import type { ReactNode } from 'react';
import { useState } from 'react';
import EvaluationCenterStep from '@/components/campaigns/evaluation-center-step';
import EvaluationGuidesStep from '@/components/campaigns/evaluation-guides-step';
import EvaluationSteps from '@/components/campaigns/evaluation-steps';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { WorkCenterEvaluation } from '@/types';

/**
 * The assistant that starts a NOM-035 evaluation (pantalla NOM035-Nueva): the work center, then its guides and who
 * answers Guía III. It creates a draft; the date and the sending are its third step, on the draft. Opened from a work
 * center's card it starts at the second step.
 */
export default function NewEvaluationDialog({
    tenantSlug,
    workCenter = null,
    minGroup,
    children,
}: {
    tenantSlug: string;
    workCenter?: WorkCenterEvaluation | null;
    minGroup: number;
    /** What opens it. */
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [center, setCenter] = useState(workCenter);
    const [step, setStep] = useState(workCenter === null ? 1 : 2);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setCenter(workCenter);
                setStep(workCenter === null ? 1 : 2);
            }}
        >
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-h-[calc(100dvh-2rem)] gap-0 overflow-y-auto p-0 sm:max-w-[620px]">
                <div className="flex flex-col gap-[18px] border-b px-6 pt-[22px] pb-[18px]">
                    <DialogTitle className="text-lg leading-[26px]">
                        Nueva evaluación
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        Se prepara como borrador. Nadie recibe nada hasta que la
                        lances.
                    </DialogDescription>
                    <EvaluationSteps current={step} />
                </div>
                {step === 2 && center !== null ? (
                    <EvaluationGuidesStep
                        tenantSlug={tenantSlug}
                        center={center}
                        minGroup={minGroup}
                        onBack={() => setStep(1)}
                    />
                ) : (
                    <EvaluationCenterStep
                        tenantSlug={tenantSlug}
                        chosen={center}
                        onNext={(chosen) => {
                            setCenter(chosen);
                            setStep(2);
                        }}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
