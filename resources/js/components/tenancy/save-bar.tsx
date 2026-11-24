import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * On a phone, the save button stays at the bottom of the screen while the areas scroll (board Roles 7c, móvil), with how many
 * areas have access: above the menu bar and above the round buttons of Humi and the first steps, which keep their places.
 * From a wide screen up the summary column holds it instead.
 */
export default function SaveBar({
    status,
    label,
    processing,
}: {
    status: ReactNode;
    label: string;
    processing: boolean;
}) {
    return (
        <div className="sticky bottom-[calc(var(--tab-bar-clearance)+3.75rem)] z-20 flex items-center justify-between gap-3 rounded-xl border bg-card py-2 pr-2 pl-4 shadow-float md:bottom-20 lg:hidden">
            <p className="text-sm text-muted-foreground">{status}</p>
            <Button type="submit" disabled={processing} data-test="save-bar">
                {processing && <Spinner />}
                {label}
            </Button>
        </div>
    );
}
