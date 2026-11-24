import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * On a phone, the save button stays at the bottom of the screen while the areas scroll (board Roles 7c, móvil), with how many
 * areas have access. From a wide screen up the summary column holds it instead.
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
        <div className="sticky bottom-0 z-20 -mx-4 flex items-center justify-between gap-3 border-t bg-card px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:hidden">
            <p className="text-sm text-muted-foreground">{status}</p>
            <Button type="submit" disabled={processing}>
                {processing && <Spinner />}
                {label}
            </Button>
        </div>
    );
}
