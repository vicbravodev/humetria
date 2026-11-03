import RegulationPartsForm from '@/components/seating/regulation-parts-form';
import RegulationPartsView from '@/components/seating/regulation-parts-view';
import RegulationProgress from '@/components/seating/regulation-progress';
import RegulationStepForm from '@/components/seating/regulation-step-form';
import type { RegulationRecord } from '@/types';

/** A drafted regulation: where it stands, its signed file, its parts (editable while draft) and the next step. */
export default function RegulationBody({
    workCenterId,
    regulation,
    canManage,
}: {
    workCenterId: string;
    regulation: RegulationRecord;
    canManage: boolean;
}) {
    const editable = regulation.status === 'draft' && canManage;

    return (
        <>
            <RegulationProgress regulation={regulation} />
            {regulation.fileHref !== null && (
                <a
                    href={regulation.fileHref}
                    target="_blank"
                    rel="noreferrer"
                    className="touch-hit w-fit text-primary underline-offset-4 hover:underline print:hidden"
                >
                    Ver el reglamento firmado (PDF)
                </a>
            )}
            <div className="card p-6">
                {editable ? (
                    <RegulationPartsForm
                        workCenterId={workCenterId}
                        parts={regulation.parts}
                    />
                ) : (
                    <RegulationPartsView parts={regulation.parts} />
                )}
            </div>
            {canManage && regulation.nextStep !== null && (
                <div className="card p-6">
                    <RegulationStepForm
                        workCenterId={workCenterId}
                        step={regulation.nextStep}
                    />
                </div>
            )}
        </>
    );
}
