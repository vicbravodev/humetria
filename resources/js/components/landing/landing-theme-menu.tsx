import { Moon, Sun } from 'lucide-react';
import { themeOptions } from '@/components/theme-switch';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';

/** The theme as a quiet icon in the header: it shows the one in use, and opens Claro, Oscuro or Sistema. */
export default function LandingThemeMenu() {
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const Icon = resolvedAppearance === 'dark' ? Moon : Sun;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                aria-label="Tema"
                data-test="theme-menu"
                className="flex size-10 items-center justify-center rounded-full text-foreground transition-[color,background-color,transform] duration-150 ease-out hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring active:scale-[0.94] data-[state=open]:bg-muted data-[state=open]:text-foreground"
            >
                <Icon aria-hidden className="size-[18px]" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuRadioGroup
                    value={appearance}
                    onValueChange={(value) => {
                        const option = themeOptions.find(
                            (candidate) => candidate.value === value,
                        );

                        if (option !== undefined) {
                            updateAppearance(option.value);
                        }
                    }}
                >
                    {themeOptions.map(({ value, label, icon: ItemIcon }) => (
                        <DropdownMenuRadioItem
                            key={value}
                            value={value}
                            className="min-h-11"
                        >
                            <ItemIcon className="size-4" />
                            {label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
