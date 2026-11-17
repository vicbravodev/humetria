import HumiToolStep from '@/components/assistant/humi-tool-step';
import type { MessageView } from '@/lib/assistant/message-view';
import { minGroupStep, stepLabel } from '@/lib/assistant/tool-steps';

/** The lines over an answer saying what Humi looked at, and that it applied the minimum per group to results. */
export default function HumiToolSteps({ view }: { view: MessageView }) {
    const minimum = view.items.flatMap((item) =>
        item.kind === 'card' &&
        item.card.render === 'risk_levels' &&
        item.card.minGroup !== null
            ? item.card.minGroup
            : [],
    )[0];

    if (view.steps.length === 0) {
        return null;
    }

    return (
        <ul className="flex flex-col gap-1">
            {view.steps.map((step) => (
                <HumiToolStep
                    key={step.id}
                    label={stepLabel(step.tool, step.done)}
                    done={step.done}
                />
            ))}
            {minimum !== undefined && (
                <HumiToolStep label={minGroupStep(minimum)} done />
            )}
        </ul>
    );
}
