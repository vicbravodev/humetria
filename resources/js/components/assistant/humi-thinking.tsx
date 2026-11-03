import HumiAvatar from '@/components/assistant/humi-avatar';
import { Skeleton } from '@/components/ui/skeleton';

/** What shows between sending a message and the first word of the answer. */
export default function HumiThinking() {
    return (
        <div className="humi-enter flex gap-2.5" role="status">
            <HumiAvatar size={28} expression="calcula" animated />
            <div className="flex flex-1 flex-col gap-2 pt-1">
                <span className="text-sm text-muted-foreground">Pensando</span>
                <Skeleton className="h-3 w-full" />
                <Skeleton className="h-3 w-2/3" />
            </div>
        </div>
    );
}
