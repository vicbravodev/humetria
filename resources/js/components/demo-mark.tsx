import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * Wraps every page: in the demo company it adds the «DEMO» mark, fixed so it shows on screen and repeats on each printed
 * sheet, so nobody takes a demo report for a real diagnosis. Any other company, or no company, renders the page alone.
 * On screen it is a narrow tab on the right edge, halfway down: clear of the impersonation banner and the top bar, the
 * logos and titles of the standalone pages, the phone's bottom bar and the floating pills of Humi and the first steps. Its text runs top to bottom, so `px` pads its length and `py` its width.
 * On a phone the content of the page (its `main`) keeps the tab's width clear, so the tab never covers a card or a word.
 * On paper it turns into a large, faint diagonal watermark across the middle of each sheet: the tab would sit on the text
 * of every page. It stays above the content because the report's cards are opaque and would hide anything behind them;
 * at a tenth of its opacity the text under it still reads.
 */
export default function DemoMark({ children }: { children: ReactNode }) {
    const { demo } = usePage().props;

    return (
        <>
            {demo ? (
                // On a phone the tab would sit on the right edge of the cards and the text: the content of every page keeps its width clear.
                <div className="contents max-md:[&_main]:pr-10">{children}</div>
            ) : (
                children
            )}
            {demo && (
                <div
                    data-slot="demo-mark"
                    className="pointer-events-none fixed top-1/2 right-0 z-50 -translate-y-1/2 rounded-l-md border border-r-0 border-primary bg-background px-2 py-0.5 text-xs font-semibold tracking-widest text-primary [writing-mode:vertical-rl] print:top-1/2 print:right-auto print:left-1/2 print:-translate-x-1/2 print:-rotate-30 print:border-0 print:bg-transparent print:p-0 print:text-9xl print:opacity-10 print:[writing-mode:horizontal-tb]"
                >
                    DEMO
                </div>
            )}
        </>
    );
}
