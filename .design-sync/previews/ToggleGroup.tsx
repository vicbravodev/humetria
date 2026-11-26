import { ToggleGroup, ToggleGroupItem } from 'humetria';
import { Monitor, Moon, Sun } from 'lucide-react';

const THEMES = [
    { value: 'light', label: 'Claro', icon: Sun },
    { value: 'dark', label: 'Oscuro', icon: Moon },
    { value: 'system', label: 'Sistema', icon: Monitor },
];

export const ThemeSwitch = () => (
    <ToggleGroup type="single" aria-label="Tema" defaultValue="light" className="relative gap-0 rounded-lg border bg-muted p-0.5" style={{ width: 'fit-content' }}>
        <span aria-hidden className="absolute top-0.5 left-0.5 h-9 w-11 rounded-md bg-card shadow-raised" />
        {THEMES.map(({ value, label, icon: Icon }) => (
            <ToggleGroupItem
                key={value}
                value={value}
                aria-label={label}
                title={label}
                className="relative h-9 w-11 min-w-11 rounded-md px-0 text-muted-foreground first:rounded-md last:rounded-md hover:bg-transparent hover:text-foreground data-[state=on]:bg-transparent data-[state=on]:text-foreground [&_svg]:size-[18px]"
            >
                <Icon />
            </ToggleGroupItem>
        ))}
    </ToggleGroup>
);

export const PeriodOutline = () => (
    <ToggleGroup type="single" variant="outline" defaultValue="month" aria-label="Periodo">
        <ToggleGroupItem value="week">Semana</ToggleGroupItem>
        <ToggleGroupItem value="month">Mes</ToggleGroupItem>
        <ToggleGroupItem value="year">Año</ToggleGroupItem>
    </ToggleGroup>
);

export const ShiftsMultiple = () => (
    <ToggleGroup type="multiple" variant="outline" defaultValue={['morning', 'night']} aria-label="Turnos">
        <ToggleGroupItem value="morning">Matutino</ToggleGroupItem>
        <ToggleGroupItem value="evening">Vespertino</ToggleGroupItem>
        <ToggleGroupItem value="night">Nocturno</ToggleGroupItem>
    </ToggleGroup>
);

export const Disabled = () => (
    <ToggleGroup type="single" variant="outline" defaultValue="month" disabled aria-label="Periodo">
        <ToggleGroupItem value="week">Semana</ToggleGroupItem>
        <ToggleGroupItem value="month">Mes</ToggleGroupItem>
        <ToggleGroupItem value="year">Año</ToggleGroupItem>
    </ToggleGroup>
);
