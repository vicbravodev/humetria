import BarMeter from '@/components/humetria/bar-meter';
import { percentOf } from '@/lib/chart';
import type { SummaryTheme } from '@/types';

/** The themes of a summary as bars from zero on a scale shared by both lists, each with how many comments mention it. */
export default function ThemeList({
    heading,
    themes,
    max,
}: {
    heading: string;
    themes: SummaryTheme[];
    /** The most mentions of any theme in the summary: the full bar. */
    max: number;
}) {
    return (
        <div className="flex flex-col gap-3">
            <h3 className="font-semibold">{heading}</h3>
            {themes.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Ningún tema lo mencionó el mínimo de personas.
                </p>
            ) : (
                <ul className="flex flex-col gap-3">
                    {themes.map((theme, index) => (
                        <li key={theme.theme} className="flex flex-col gap-1.5">
                            <span className="flex items-baseline justify-between gap-4 text-sm">
                                <span className="text-pretty">
                                    {theme.theme}
                                </span>
                                <span className="font-semibold tabular-nums">
                                    {theme.count}
                                </span>
                            </span>
                            <BarMeter
                                percent={percentOf(theme.count, max)}
                                tooltip={`${theme.theme}: ${theme.count} comentarios`}
                                index={index}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
