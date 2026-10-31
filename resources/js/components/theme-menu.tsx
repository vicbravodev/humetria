import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { themeOptions } from '@/components/theme-switch';
import { useAppearance } from '@/hooks/use-appearance';

/** The theme on a phone: one button showing the current one, so the three-way switch doesn't squeeze the trail. */
export function ThemeMenu() {
    const { appearance, updateAppearance } = useAppearance();
    const current =
        themeOptions.find(({ value }) => value === appearance) ??
        themeOptions[2];

    if (current === undefined) {
        return null;
    }

    const Icon = current.icon;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Tema"
                    className="size-11 text-muted-foreground hover:text-foreground [&_svg]:size-[18px]"
                >
                    <Icon />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuRadioGroup
                    value={appearance}
                    onValueChange={(value) => {
                        const option = themeOptions.find(
                            (o) => o.value === value,
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
