import { Link } from '@inertiajs/react';
import {
    Building2,
    CalendarClock,
    ShieldCheck,
    Smartphone,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import ProgressBar from '@/components/humetria/progress-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { setupHref } from '@/lib/attendance/setup';
import type { SetupStep } from '@/lib/attendance/setup';
import { cn } from '@/lib/utils';

const ICONS: Record<SetupStep['key'], LucideIcon> = {
    centers: Building2,
    schedules: CalendarClock,
    devices: Smartphone,
    policy: ShieldCheck,
};

/**
 * One of the four things to do before the first mark (board A1): its count out of the total, a bar, what is left in a
 * sentence and the button to the page where it is done. The step to do now is outlined and has the primary button.
 */
export default function SetupStepCard({
    step,
    current,
}: {
    step: SetupStep;
    /** The first step still open: the one «Sigue aquí». */
    current: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <article
            className={cn(
                'flex min-w-0 flex-col gap-3.5 card p-[18px]',
                current && 'border-primary ring-1 ring-primary',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <IconTile icon={ICONS[step.key]} />
                {step.complete ? (
                    <Badge variant="success">Listo</Badge>
                ) : (
                    current && <Badge>Sigue aquí</Badge>
                )}
            </div>
            <div className="flex flex-col gap-0.5">
                <h3 className="font-semibold">{step.title}</h3>
                <p className="flex items-baseline gap-2">
                    <span className="text-[28px] leading-[34px] font-semibold tabular-nums">
                        {step.done}
                    </span>
                    <span className="text-muted-foreground">
                        de {step.total}
                    </span>
                </p>
            </div>
            <ProgressBar
                value={step.done}
                max={step.total}
                label={step.title}
            />
            <p className="min-h-10 text-sm text-pretty text-muted-foreground">
                {step.text}
            </p>
            {step.action !== null && (
                <Button asChild variant={current ? 'default' : 'outline'}>
                    <Link href={setupHref(tenantSlug, step.target)}>
                        {step.action}
                    </Link>
                </Button>
            )}
        </article>
    );
}
