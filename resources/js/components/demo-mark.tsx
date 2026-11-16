import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * Wraps every page: in the demo company it adds the «DEMO» mark, fixed so it shows on screen and repeats on each printed
 * sheet, so nobody takes a demo report for a real diagnosis. Any other company, or no company, renders the page alone.
 */
export default function DemoMark({ children }: { children: ReactNode }) {
    const { demo } = usePage().props;

    return (
        <>
            {children}
            {demo && (
                <div
                    data-slot="demo-mark"
                    className="pointer-events-none fixed top-2 left-1/2 z-50 -translate-x-1/2 rounded-full border-2 border-primary bg-background px-3 py-0.5 text-sm font-semibold tracking-widest text-primary"
                >
                    DEMO
                </div>
            )}
        </>
    );
}
