import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import FirstStepsDone from '@/components/first-steps/first-steps-done';
import FirstStepsLauncher from '@/components/first-steps/first-steps-launcher';
import FirstStepsPanel from '@/components/first-steps/first-steps-panel';
import '@/components/first-steps/first-steps-tour.css';
import FirstStepsWelcome from '@/components/first-steps/first-steps-welcome';
import { Popover, PopoverContent } from '@/components/ui/popover';
import { useFirstStepsActions } from '@/hooks/use-first-steps-actions';
import { useFirstStepsTour } from '@/hooks/use-first-steps-tour';
import { OPEN_FIRST_STEPS } from '@/lib/first-steps/open';

/** The "Primeros pasos" button beside Humi, its panel, the welcome and the closing message; nothing for who has no steps. */
export default function FirstStepsDock() {
    const { props } = usePage();
    const { firstSteps, tenant } = props;
    const finished = firstSteps?.steps.every((step) => step.done) === true;
    const [open, setOpen] = useState(finished);
    // The one tour runner of the panel layout (a page without the dock mounts the bare tour component itself).
    const { start } = useFirstStepsTour();
    const { welcomePending, seen, hide } = useFirstStepsActions();

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

    return (
        <>
            <FirstStepsWelcome
                open={welcomePending}
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
                        // Opened from the user menu, the menu hands focus back to its trigger as it closes: that focus
                        // move is not the person leaving the panel, so only a click outside or Escape closes it.
                        onFocusOutside={(event) => event.preventDefault()}
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
                                onHide={
                                    hide === undefined
                                        ? undefined
                                        : () => {
                                              setOpen(false);
                                              hide();
                                          }
                                }
                            />
                        )}
                    </PopoverContent>
                </Popover>
            )}
        </>
    );
}
