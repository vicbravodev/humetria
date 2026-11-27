import { useState } from 'react';
import GuideLauncher from '@/components/demos/guide-launcher';
import GuideStatus from '@/components/demos/guide-status';
import GuideStepCard from '@/components/demos/guide-step-card';
import '@/components/humetria/spotlight-tour.css';
import { useDemoGuide } from '@/hooks/use-demo-guide';
import { useGuideSpotlight } from '@/hooks/use-guide-spotlight';
import { useOpenDialog } from '@/hooks/use-open-dialog';

/**
 * The guide of a demo session, over every page of the demo company while the superadmin presents (boards Rec-*): what to
 * do, what is going on and why it matters, moving on when the real fact happens. It folds into its pill, and steps aside to
 * the pill by itself while a dialog or a sheet is open, so it never covers a form. Nothing for anyone else.
 */
export default function GuideDock() {
    const [open, setOpen] = useState(true);
    const dialog = useOpenDialog();
    const { guide, celebration, skip } = useDemoGuide(open);
    useGuideSpotlight(guide?.current ?? null);

    if (guide === null) {
        return null;
    }

    if (dialog) {
        return <GuideStatus guide={guide} />;
    }

    return (
        <>
            {open && (
                <>
                    {/* On a phone the guide is a bottom sheet over a veil. */}
                    <div
                        aria-hidden
                        className="fixed inset-0 z-40 bg-black/48 md:hidden print:hidden"
                    />
                    <GuideStepCard
                        guide={guide}
                        celebration={celebration}
                        actions={{ skip, minimize: () => setOpen(false) }}
                    />
                </>
            )}
            <GuideLauncher
                guide={guide}
                open={open}
                onToggle={() => setOpen(!open)}
            />
        </>
    );
}
