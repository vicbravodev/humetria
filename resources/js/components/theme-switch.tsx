import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

// The three themes, shared by the switch and its phone menu.
export const themeOptions: {
    value: Appearance;
    label: string;
    icon: LucideIcon;
}[] = [
    { value: 'light', label: 'Claro', icon: Sun },
    { value: 'dark', label: 'Oscuro', icon: Moon },
    { value: 'system', label: 'Sistema', icon: Monitor },
];

/** Light, dark or system theme, always at hand in the app frame. A raised chip slides under the chosen one. */
export function ThemeSwitch() {
    const { appearance, updateAppearance } = useAppearance();
    const chosen = themeOptions.findIndex(({ value }) => value === appearance);

    return (
        <ToggleGroup
            type="single"
            aria-label="Tema"
            value={appearance}
            onValueChange={(value) => {
                const option = themeOptions.find((o) => o.value === value);

                if (option !== undefined) {
                    updateAppearance(option.value);
                }
            }}
            className="relative gap-0 rounded-lg border bg-muted p-0.5"
        >
            <span
                aria-hidden
                className="absolute top-0.5 left-0.5 h-9 w-10 rounded-md bg-card shadow-raised transition-transform duration-200 ease-out motion-reduce:transition-none"
                style={{ transform: `translateX(${chosen * 100}%)` }}
            />
            {themeOptions.map(({ value, label, icon: Icon }) => (
                <ToggleGroupItem
                    key={value}
                    value={value}
                    aria-label={label}
                    title={label}
                    className="relative h-9 w-10 min-w-10 rounded-md px-0 text-muted-foreground transition-colors duration-150 ease-out before:absolute before:inset-x-0 before:-inset-y-1 before:content-[''] first:rounded-md last:rounded-md hover:bg-transparent hover:text-foreground data-[state=on]:bg-transparent data-[state=on]:text-foreground [&_svg]:size-[18px]"
                >
                    <Icon />
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
