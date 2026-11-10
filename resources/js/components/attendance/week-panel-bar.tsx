export type BarPart = {
    /** The share it counts. */
    value: number;
    /** A token class for its fill (`bg-primary`). */
    tone: string;
    name: string;
    /** What the legend reads at its right: «4», «23 h». */
    shown: string;
};

/**
 * A bar cut in parts, each as wide as its share and parted by a gap, with its legend under it; the bar says its numbers
 * to a screen reader.
 */
export default function WeekPanelBar({
    label,
    parts,
}: {
    label: string;
    parts: BarPart[];
}) {
    const whole = parts.reduce((sum, part) => sum + part.value, 0);

    return (
        <div className="flex flex-col gap-2.5">
            <div
                role="img"
                aria-label={label}
                className="flex h-3.5 gap-[3px] overflow-hidden rounded-md bg-track"
            >
                {parts.map((part) => (
                    <span
                        key={part.name}
                        className={`block h-full rounded-md ${part.tone}`}
                        style={{
                            width: `${whole === 0 ? 0 : (part.value / whole) * 100}%`,
                        }}
                    />
                ))}
            </div>
            <ul className="flex flex-col gap-2">
                {parts.map((part) => (
                    <li
                        key={part.name}
                        className="flex items-center gap-2.5 text-sm"
                    >
                        <i
                            aria-hidden
                            className={`size-2.5 rounded-[3px] ${part.tone}`}
                        />
                        <span className="flex-1">{part.name}</span>
                        <span className="font-semibold tabular-nums">
                            {part.shown}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
