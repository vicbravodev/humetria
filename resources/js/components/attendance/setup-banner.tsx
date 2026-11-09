import { Link } from '@inertiajs/react';
import { Rocket } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import ProgressBar from '@/components/humetria/progress-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { deadlineText, setupProgress } from '@/lib/attendance/setup';
import { setup as setupRoute } from '@/routes/tenant/attendance';
import type { AttendanceSetup } from '@/types';

/** While the registro is not ready, a line on the overview with how far it is and the way to the puesta en marcha. */
export default function SetupBanner({ setup }: { setup: AttendanceSetup }) {
    const tenantSlug = useTenantSlug();
    const { done, total } = setupProgress(setup);

    if (done === total) {
        return null;
    }

    return (
        <section
            aria-labelledby="puesta-en-marcha"
            className="flex flex-wrap items-center gap-x-6 gap-y-4 card px-6 py-5"
        >
            <IconTile icon={Rocket} />
            <div className="flex min-w-60 flex-1 flex-col gap-2">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h2
                        id="puesta-en-marcha"
                        className="text-lg leading-6 font-semibold"
                    >
                        Puesta en marcha
                    </h2>
                    <Badge variant="warning">
                        Obligatorio desde el 1 de enero de 2027
                    </Badge>
                    <span className="text-sm font-medium">
                        {deadlineText(setup.daysLeft)}
                    </span>
                </div>
                <ProgressBar
                    value={done}
                    max={total}
                    label="Pasos de la puesta en marcha"
                />
                <p className="text-sm text-muted-foreground">
                    {`${done} de ${total} pasos listos: centros, horarios, teléfonos y política.`}
                </p>
            </div>
            <Button asChild>
                <Link href={setupRoute(tenantSlug)}>
                    Seguir con la puesta en marcha
                </Link>
            </Button>
        </section>
    );
}
