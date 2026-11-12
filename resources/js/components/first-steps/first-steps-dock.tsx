import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import FirstStepsDone from '@/components/first-steps/first-steps-done';
import FirstStepsLauncher from '@/components/first-steps/first-steps-launcher';
import FirstStepsPanel from '@/components/first-steps/first-steps-panel';
import FirstStepsTour from '@/components/first-steps/first-steps-tour';
import FirstStepsWelcome from '@/components/first-steps/first-steps-welcome';
import { Popover, PopoverContent } from '@/components/ui/popover';
import { useFirstStepsTour } from '@/hooks/use-first-steps-tour';
import { OPEN_FIRST_STEPS } from '@/lib/first-steps/open';
import { hide, welcome } from '@/routes/tenant/first-steps';

/** The "Primeros pasos" button beside Humi, its panel, the welcome and the closing message; nothing for who has no steps. */
export default function FirstStepsDock() {
    const { props } = usePage();
    const { firstSteps, tenant, impersonator } = props;
    const finished = firstSteps?.steps.every((step) => step.done) === true;
    const [open, setOpen] = useState(finished);
    const { start } = useFirstStepsTour();

    useEffect(() => {
        const show = () => setOpen(true);
        window.addEventListener(OPEN_FIRST_STEPS, show);

        return () => window.removeEventListener(OPEN_FIRST_STEPS, show);
    }, []);

    // When the last step gets done the panel opens on its own to say so.
    const [wasFinished, setWasFinished] = useState(finished);
    if (finished !== wasFinished) {
        setWasFinished(finished);
        setOpen(open || finished);
    }

    if (firstSteps === null || tenant === null) {
        return null;
    }

    const post = (url: string) =>
        router.post(url, {}, { preserveScroll: true, preserveState: true });
    const seen = () => post(welcome.url(tenant.slug));

    return (
        <>
            <FirstStepsTour />
            <FirstStepsWelcome
                open={
                    !firstSteps.welcomed &&
                    !firstSteps.hidden &&
                    impersonator === null
                }
                total={firstSteps.steps.length}
                onStart={() => {
                    seen();
                    setOpen(true);
                }}
                onDismiss={seen}
            />
            {!firstSteps.hidden && (
                <Popover open={open} onOpenChange={setOpen}>
                    <FirstStepsLauncher steps={firstSteps.steps} />
                    <PopoverContent
                        side="top"
                        aria-label="Lista de primeros pasos"
                        align="end"
                        className="max-h-(--radix-popover-content-available-height) w-[min(22rem,calc(100vw-2rem))] overflow-y-auto"
                    >
                        {finished ? (
                            <FirstStepsDone onClose={() => setOpen(false)} />
                        ) : (
                            <FirstStepsPanel
                                firstSteps={firstSteps}
                                onGuide={(step) => {
                                    setOpen(false);
                                    start(step);
                                }}
                                onHide={() => {
                                    setOpen(false);
                                    router.post(hide.url(tenant.slug));
                                }}
                            />
                        )}
                    </PopoverContent>
                </Popover>
            )}
        </>
    );
}
