import { Button } from '@/components/ui/button';
import { useElementInView } from '@/hooks/use-element-in-view';
import { cn } from '@/lib/utils';

/** On a phone, the way to the demo stays at hand at the bottom of the screen, and steps aside while the form is on it. */
export default function StickyDemoBar() {
    const formInView = useElementInView('demo-request-form');

    return (
        <div
            data-test="sticky-demo-bar"
            inert={formInView}
            className={cn(
                'sticky bottom-0 z-10 border-t bg-background p-3 transition-opacity duration-200 motion-reduce:transition-none md:hidden',
                formInView && 'pointer-events-none opacity-0',
            )}
        >
            <Button asChild size="lg" className="w-full rounded-full">
                <a
                    href="#agenda-una-demo"
                    data-track="cta_click"
                    data-track-target="sticky_demo"
                >
                    Agenda una demo
                </a>
            </Button>
        </div>
    );
}
