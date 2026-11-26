import { Button, Collapsible, CollapsibleContent, CollapsibleTrigger } from 'humetria';
import { ChevronRight } from 'lucide-react';

const Areas = () => (
    <ul className="grid gap-2 text-sm" style={{ paddingLeft: 12 }}>
        <li className="flex items-center justify-between">
            <span>Operaciones</span>
            <span className="tabular-nums text-muted-foreground">94 de 120</span>
        </li>
        <li className="flex items-center justify-between">
            <span>Almacén</span>
            <span className="tabular-nums text-muted-foreground">31 de 44</span>
        </li>
        <li className="flex items-center justify-between">
            <span>Recursos Humanos</span>
            <span className="tabular-nums text-muted-foreground">17 de 22</span>
        </li>
    </ul>
);

const Trigger = () => (
    <CollapsibleTrigger asChild>
        <Button variant="ghost" className="group/family w-full justify-between">
            Avance por área
            <ChevronRight className="transition-transform group-data-[state=open]/family:rotate-90" />
        </Button>
    </CollapsibleTrigger>
);

export const Open = () => (
    <Collapsible defaultOpen className="grid gap-2 max-w-sm">
        <Trigger />
        <CollapsibleContent>
            <Areas />
        </CollapsibleContent>
    </Collapsible>
);

export const Closed = () => (
    <Collapsible className="grid gap-2 max-w-sm">
        <Trigger />
        <CollapsibleContent>
            <Areas />
        </CollapsibleContent>
    </Collapsible>
);
