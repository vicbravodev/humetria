import { Scale } from 'lucide-react';

/** The notice that goes with every draft, on screen and on paper: the model is not legal advice. It has no close button. */
export default function RegulationNotice({ text }: { text: string }) {
    return (
        <div
            role="note"
            className="flex items-start gap-3 rounded-xl bg-warning px-5 py-3.5 text-warning-foreground"
        >
            <Scale aria-hidden className="mt-0.5 size-5 shrink-0" />
            <p className="text-meta">{text}</p>
        </div>
    );
}
