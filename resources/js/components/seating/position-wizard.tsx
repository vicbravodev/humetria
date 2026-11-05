import { Form } from '@inertiajs/react';
import { useState } from 'react';
import AreaFields from '@/components/seating/area-fields';
import ConditionDefaultsFields from '@/components/seating/condition-defaults-fields';
import FigureOneFields from '@/components/seating/figure-one-fields';
import NatureFields from '@/components/seating/nature-fields';
import PositionBasicsFields from '@/components/seating/position-basics-fields';
import PositionStepsNav from '@/components/seating/position-steps-nav';
import PositionWizardFooter from '@/components/seating/position-wizard-footer';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { stepWithError } from '@/lib/seating/position-steps';
import { store, update } from '@/routes/tenant/seating/positions';
import type { ConditionOption, StandingPosition, TypeOption } from '@/types';

const QUESTIONS = [
    '¿Qué se hace de pie en este puesto?',
    '¿Dónde va el asiento de este puesto?',
    '¿Cómo es el área de trabajo?',
    '¿Qué comparten las personas de este puesto?',
];

/**
 * A standing position step by step (board LS-Puesto): the position, Figura 1 with its result as it is answered, the area
 * and what its people share. One form: every step stays in it, and a rejected field opens its step.
 */
export default function PositionWizard({
    workCenterId,
    position,
    types,
    conditions,
    industrial,
}: {
    workCenterId: string;
    position: StandingPosition | null;
    types: TypeOption[];
    conditions: ConditionOption[];
    industrial: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const [step, setStep] = useState(1);
    const action =
        position === null
            ? store.form([tenantSlug, workCenterId])
            : update.form([tenantSlug, position.id]);

    return (
        <div className="grid gap-8 md:grid-cols-[220px_minmax(0,1fr)]">
            <PositionStepsNav
                step={step}
                saved={position !== null}
                onStep={setStep}
            />
            <Form
                {...action}
                options={{ preserveScroll: true }}
                onError={(errors) => setStep(stepWithError(errors) ?? step)}
                noValidate
                className="flex min-w-0 flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="flex flex-col gap-1">
                            <p className="text-sm font-medium text-primary">
                                Paso {step} de 5
                            </p>
                            <h2 className="text-2xl leading-8 font-semibold tracking-[-0.01em]">
                                {QUESTIONS[step - 1]}
                            </h2>
                        </div>
                        <div
                            hidden={step !== 1}
                            className="flex flex-col gap-7"
                        >
                            <PositionBasicsFields
                                position={position}
                                types={types}
                                errors={errors}
                            />
                            {industrial && (
                                <NatureFields
                                    position={position}
                                    errors={errors}
                                />
                            )}
                        </div>
                        <div hidden={step !== 2}>
                            <p className="mb-5 text-muted-foreground">
                                Son las preguntas del flujograma de la Figura 1
                                de las Disposiciones. Contesta y Humetría
                                calcula el resultado.
                            </p>
                            <FigureOneFields
                                position={position}
                                errors={errors}
                            />
                        </div>
                        <div hidden={step !== 3}>
                            <AreaFields position={position} errors={errors} />
                        </div>
                        <div hidden={step !== 4}>
                            <ConditionDefaultsFields
                                conditions={conditions}
                                position={position}
                                errors={errors}
                            />
                        </div>
                        <PositionWizardFooter
                            step={step}
                            saved={position !== null}
                            processing={processing}
                            onStep={setStep}
                        />
                    </>
                )}
            </Form>
        </div>
    );
}
