import { usePage } from '@inertiajs/react';
import { lazy, Suspense, useState } from 'react';
import HumiLauncher from '@/components/assistant/humi-launcher';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useHumiShortcut } from '@/hooks/use-humi-shortcut';
import { useMediaQuery } from '@/hooks/use-media-query';
import { pageContextOf } from '@/lib/assistant/page-context';

// The panel (and the AI SDK it brings) is fetched the first time Humi opens, not with every page.
const HumiPanel = lazy(() => import('@/components/assistant/humi-panel'));

/**
 * Humi in every page of the panel for whoever may use it: the round button, and the panel opened from it (or from Ctrl or
 * Cmd + J) docked at the right of the page when the window is wide, as a sheet over it when it is not. The page behind keeps
 * working while it is docked.
 */
export default function HumiDock() {
    const { props, url } = usePage();
    const tenant = props.tenant;
    const wide = useMediaQuery('(min-width: 1280px)');
    const [open, setOpen] = useState(false);
    const [closedOnce, setClosedOnce] = useState(false);

    useHumiShortcut(() => setOpen((was) => !was));

    if (tenant === undefined || tenant === null || !tenant.can.use_assistant) {
        return null;
    }

    const close = () => {
        setOpen(false);
        setClosedOnce(true);
    };
    const panel = (onClose: (() => void) | null) => (
        <Suspense fallback={null}>
            <HumiPanel
                slug={tenant.slug}
                page={pageContextOf(url)}
                fullPage={false}
                onClose={onClose}
            />
        </Suspense>
    );

    return (
        <>
            {!open && (
                <HumiLauncher
                    takeFocus={closedOnce}
                    onOpen={() => setOpen(true)}
                />
            )}
            {open && wide && (
                <aside
                    aria-label="Humi"
                    className="humi-slide-in sticky top-0 h-svh w-[420px] shrink-0 border-l bg-background print:hidden"
                >
                    {panel(close)}
                </aside>
            )}
            {!wide && (
                <Sheet
                    open={open}
                    onOpenChange={(next) => (next ? setOpen(true) : close())}
                >
                    <SheetContent
                        side="right"
                        className="w-full gap-0 p-0 sm:max-w-[420px]"
                    >
                        <SheetTitle className="sr-only">Humi</SheetTitle>
                        <SheetDescription className="sr-only">
                            Asistente de cumplimiento de tu empresa
                        </SheetDescription>
                        {panel(null)}
                    </SheetContent>
                </Sheet>
            )}
        </>
    );
}
