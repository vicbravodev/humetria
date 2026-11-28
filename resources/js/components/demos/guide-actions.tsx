import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { openHumi } from '@/lib/assistant/open-humi';
import { continueLabel } from '@/lib/demos/guide';
import type { DemoGuideStep } from '@/types/demo-guide';

/**
 * «Llévame» (a full visit: a page fetched ahead records no visit), «Pregúntale a Humi» when the step has a question, and
 * «Saltar paso», or «Seguir con 3 respuestas» while the audience is still at it. On a phone each is a finger tall.
 */
export default function GuideActions({
    current,
    onSkip,
}: {
    current: DemoGuideStep;
    onSkip: (step: string) => void;
}) {
    const ask = current.ask;
    const next = continueLabel(current);

    return (
        <div className="flex flex-col gap-2 md:flex-row md:flex-wrap">
            <Button
                size="sm"
                className="max-md:h-11"
                onClick={() => router.visit(current.href)}
            >
                Llévame
            </Button>
            <div className="flex gap-2 max-md:[&>*]:h-11 max-md:[&>*]:flex-1">
                {ask !== null && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => openHumi(ask)}
                    >
                        Pregúntale a Humi
                    </Button>
                )}
                <Button
                    size="sm"
                    variant={next === null ? 'ghost' : 'outline'}
                    onClick={() => onSkip(current.key)}
                >
                    {next ?? 'Saltar paso'}
                </Button>
            </div>
        </div>
    );
}
