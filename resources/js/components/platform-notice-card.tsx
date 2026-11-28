import { CircleAlert, Megaphone, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useDismissed } from '@/hooks/use-dismissed';
import { cn } from '@/lib/utils';
import type { PlatformNotice } from '@/types';

/** One notice of the platform as the panel draws it, and its close button; closed, it stays closed in this browser. */
export default function PlatformNoticeCard({
    notice,
}: {
    notice: PlatformNotice;
}) {
    const [dismissed, dismiss] = useDismissed(`platform-notice:${notice.id}`);
    const alarm = notice.kind === 'maintenance' || notice.kind === 'incident';
    const Icon = alarm ? CircleAlert : Megaphone;

    if (dismissed && notice.dismissible) {
        return null;
    }

    return (
        <div
            data-slot="platform-notice"
            className="px-4 pt-4 md:px-10 md:pt-6 print:hidden"
        >
            <div
                role="status"
                className={cn(
                    'flex items-start gap-3 rounded-xl py-2 pr-2 pl-5',
                    alarm
                        ? 'bg-warning text-warning-foreground'
                        : 'bg-accent text-foreground',
                )}
            >
                <Icon aria-hidden className="mt-2.5 size-5 shrink-0" />
                <p className="min-w-0 flex-1 py-2 text-meta">
                    {notice.message}
                </p>
                {notice.dismissible && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-11 shrink-0"
                        onClick={dismiss}
                        aria-label="Cerrar aviso"
                    >
                        <X aria-hidden />
                    </Button>
                )}
            </div>
        </div>
    );
}
