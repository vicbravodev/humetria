import { Toggle } from 'humetria';
import { Bell, Filter, Star } from 'lucide-react';

export const PendingOnly = () => (
    <div className="flex items-center gap-2">
        <Toggle variant="outline" defaultPressed aria-label="Solo pendientes">
            <Filter />
            Solo pendientes
        </Toggle>
        <Toggle variant="outline" aria-label="Con recordatorio">
            <Bell />
            Con recordatorio
        </Toggle>
    </div>
);

export const Sizes = () => (
    <div className="flex items-center gap-2">
        <Toggle size="sm" defaultPressed aria-label="Destacar centro">
            <Star />
        </Toggle>
        <Toggle size="default" defaultPressed aria-label="Destacar centro">
            <Star />
        </Toggle>
        <Toggle size="lg" defaultPressed aria-label="Destacar centro">
            <Star />
        </Toggle>
    </div>
);

export const Disabled = () => (
    <Toggle variant="outline" disabled aria-label="Solo pendientes">
        <Filter />
        Solo pendientes
    </Toggle>
);
