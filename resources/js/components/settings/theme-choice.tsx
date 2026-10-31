import ThemePreview from '@/components/settings/theme-preview';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const THEMES: { value: Appearance; label: string }[] = [
    { value: 'light', label: 'Claro' },
    { value: 'dark', label: 'Oscuro' },
    { value: 'system', label: 'Igual que tu equipo' },
];

/** The theme as three cards with a miniature of each; choosing one applies it right away. */
export default function ThemeChoice() {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <section
            aria-labelledby="theme-title"
            className="flex flex-col gap-[18px]"
        >
            <div className="flex flex-col">
                <h2
                    id="theme-title"
                    className="text-lg leading-[26px] font-semibold"
                >
                    Tema
                </h2>
                <p className="text-sm text-muted-foreground">
                    También lo cambias desde la barra superior, sin entrar aquí.
                </p>
            </div>
            <div className="flex flex-wrap items-stretch gap-3">
                {THEMES.map(({ value, label }) => {
                    const chosen = appearance === value;

                    return (
                        <button
                            key={value}
                            type="button"
                            aria-pressed={chosen}
                            onClick={() => updateAppearance(value)}
                            className={cn(
                                'flex flex-[1_1_180px] flex-col gap-2.5 rounded-xl border bg-card p-2.5 text-left transition-colors duration-150 ease-out hover:border-input focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                chosen &&
                                    'border-2 border-primary p-[9px] hover:border-primary',
                            )}
                        >
                            <ThemePreview theme={value} />
                            <span className="flex items-center gap-2">
                                <span
                                    aria-hidden
                                    className={cn(
                                        'inline-flex size-5 shrink-0 items-center justify-center rounded-full border-[1.5px] border-input bg-card',
                                        chosen &&
                                            'border-2 border-primary after:size-2.5 after:rounded-full after:bg-primary after:content-[""]',
                                    )}
                                />
                                <span className="text-sm font-medium">
                                    {label}
                                </span>
                            </span>
                        </button>
                    );
                })}
            </div>
        </section>
    );
}
