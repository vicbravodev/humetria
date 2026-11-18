import { Button } from '@/components/ui/button';

/** On a phone, the way to the demo stays at hand at the bottom of the screen. */
export default function StickyDemoBar() {
    return (
        <div className="sticky bottom-0 z-10 border-t bg-background p-3 md:hidden">
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
