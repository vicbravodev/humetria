import { CloudOff } from 'lucide-react';
import OfflineFrame from '@/components/attendance/offline-frame';
import OfflineQueueCount from '@/components/attendance/offline-queue-count';
import { Button } from '@/components/ui/button';
import { useOnline } from '@/hooks/use-online';
import {
    LOSS_WARNING,
    SAVED_TITLE,
    savedText,
} from '@/lib/attendance/offline-text';

/**
 * The mark was made without a connection and is in the phone (it takes the place of «Listo»): its title and the hour the person
 * said, that RH does not have it yet, how many wait and what loses them. No server clock: there is none. The full sentence for a
 * screen reader. «Listo» is the one action.
 */
export default function PunchOfflineSaved({
    company,
    declared,
    pending,
    onDone,
}: {
    company: string;
    /** The hour the person said, HH:mm. */
    declared: string;
    pending: number;
    onDone: () => void;
}) {
    const online = useOnline();

    return (
        <OfflineFrame
            company={company}
            actions={
                <Button
                    className="h-14 w-full text-[17px] font-semibold"
                    onClick={onDone}
                >
                    Listo
                </Button>
            }
        >
            <div className="flex w-full max-w-md flex-col items-center gap-5 text-center">
                <p role="status" className="sr-only">
                    {SAVED_TITLE}. {savedText(declared)}
                </p>
                <div className="flex animate-in flex-col items-center gap-1.5 duration-200 ease-out fade-in slide-in-from-bottom-1">
                    <span
                        aria-hidden
                        className="flex size-14 items-center justify-center rounded-[14px] bg-accent text-primary"
                    >
                        <CloudOff className="size-7" />
                    </span>
                    <h1 className="mt-2 text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        {SAVED_TITLE}
                    </h1>
                    <p className="text-muted-foreground">
                        {savedText(declared)}
                    </p>
                </div>
                <OfflineQueueCount
                    pending={pending}
                    notices={[]}
                    online={online}
                />
                <p className="text-meta text-muted-foreground">
                    {LOSS_WARNING}
                </p>
            </div>
        </OfflineFrame>
    );
}
