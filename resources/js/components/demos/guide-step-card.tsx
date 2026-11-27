import { ChevronDown } from 'lucide-react';
import GuideCelebration from '@/components/demos/guide-celebration';
import GuideFinished from '@/components/demos/guide-finished';
import GuideStep from '@/components/demos/guide-step';
import { Button } from '@/components/ui/button';
import type { DemoGuide } from '@/types/demo-guide';

/**
 * The open guide (boards Rec-02, Rec-05, Rec-13): the line that celebrates, then the current step or the end of the
 * session. A card at the bottom right on a computer, a bottom sheet on a phone.
 */
export default function GuideStepCard({
    guide,
    celebration,
    actions,
}: {
    guide: DemoGuide;
    celebration: string | null;
    actions: { skip: (step: string) => void; minimize: () => void };
}) {
    return (
        <section
            id="demo-guide"
            aria-label="Guía de la demo"
            className="fixed inset-x-0 bottom-0 z-50 flex max-h-[85dvh] flex-col gap-4 overflow-y-auto rounded-t-2xl bg-popover px-4 pt-3 pb-5 text-popover-foreground shadow-float max-md:pr-10 md:inset-x-auto md:right-4 md:bottom-20 md:z-40 md:w-[380px] md:rounded-xl md:border md:p-5 group-has-[aside[aria-label=Humi]]/sidebar-wrapper:md:right-[436px] print:hidden"
        >
            <span
                aria-hidden
                className="h-1 w-10 shrink-0 self-center rounded-full bg-border md:hidden"
            />
            <Button
                variant="ghost"
                size="icon"
                aria-label="Minimizar la guía"
                onClick={actions.minimize}
                // On a phone the «DEMO» tab runs down the right edge: the button stays clear of it.
                className="absolute top-2 right-2 text-muted-foreground max-md:right-10 max-md:size-11"
            >
                <ChevronDown aria-hidden />
            </Button>
            <GuideCelebration text={celebration} />
            {guide.current === null ? (
                <GuideFinished />
            ) : (
                <GuideStep current={guide.current} onSkip={actions.skip} />
            )}
        </section>
    );
}
