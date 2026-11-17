import { Link } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

/**
 * The invitation beside the closed bubble: a short question with a real fact of the page, to ask Humi, decline it now or
 * for good. It never takes the focus (its slot announces it politely), and closes by itself when its bar ends (8 s; the cursor or
 * the focus on it stop the bar). Declined for good, it says where the suggestions turn on again.
 */
export default function HumiInvitation({
    message,
    assistantHref,
    onAccept,
    onNotNow,
    onNeverAgain,
    onClose,
}: {
    message: string;
    /** The page of Humi, where «Sugerencias de la burbuja» turns them on again. */
    assistantHref: string;
    onAccept: () => void;
    onNotNow: () => void;
    onNeverAgain: () => void;
    onClose: () => void;
}) {
    const [declined, setDeclined] = useState(false);

    return (
        <div className="humi-invite fixed right-[76px] bottom-[calc(var(--tab-bar-clearance)-4px)] z-40 flex w-80 max-w-[calc(100vw-92px)] flex-col rounded-[10px] border bg-card shadow-float md:bottom-3 print:hidden">
            <span
                aria-hidden
                className="absolute -right-[7px] bottom-[22px] size-3 rotate-45 border-t border-r bg-card"
            />
            <div className="relative flex flex-col gap-3 p-4 pb-3.5">
                <button
                    type="button"
                    onClick={onClose}
                    aria-label="Cerrar sugerencia"
                    className="absolute top-0 right-0 flex size-11 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring md:top-2 md:right-2 md:size-8"
                >
                    <X aria-hidden className="size-4" />
                </button>
                {declined ? (
                    <p className="pr-7 text-sm">
                        No te sugeriré más. Puedes volver a activarlas en{' '}
                        <Link
                            href={assistantHref}
                            className="font-medium text-primary underline underline-offset-2"
                        >
                            la página de Humi
                        </Link>
                        , en «Sugerencias de la burbuja».
                    </p>
                ) : (
                    <>
                        <p className="pr-7 text-base">{message}</p>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                className="min-h-11 md:min-h-9"
                                onClick={onAccept}
                            >
                                Preguntarle a Humi
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                className="min-h-11 md:min-h-9"
                                onClick={onNotNow}
                            >
                                Ahora no
                            </Button>
                        </div>
                        <button
                            type="button"
                            onClick={() => {
                                onNeverAgain();
                                setDeclined(true);
                            }}
                            className="min-h-11 self-start text-caption text-muted-foreground underline underline-offset-2 hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring md:min-h-6"
                        >
                            No volver a sugerir
                        </button>
                    </>
                )}
            </div>
            <div className="h-[3px] overflow-hidden rounded-b-[10px] bg-muted">
                <div
                    key={declined ? 'declined' : 'asking'}
                    data-testid="humi-invitation-timer"
                    onAnimationEnd={onClose}
                    className="humi-countdown h-full bg-primary"
                />
            </div>
        </div>
    );
}
