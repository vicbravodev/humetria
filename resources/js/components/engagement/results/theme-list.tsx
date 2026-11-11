import type { SummaryTheme } from '@/types';

/** The themes of a summary, each with how many comments mention it. */
export default function ThemeList({
    heading,
    themes,
}: {
    heading: string;
    themes: SummaryTheme[];
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
                    {themes.map((theme) => (
                        <li
                            key={theme.theme}
                            className="flex items-start justify-between gap-4 text-sm"
                        >
                            <span className="text-pretty">{theme.theme}</span>
                            <span className="whitespace-nowrap text-muted-foreground tabular-nums">
                                {theme.count} comentarios
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
