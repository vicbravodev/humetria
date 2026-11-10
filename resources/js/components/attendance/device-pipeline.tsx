import { ChevronRight, Link2, Send, Smartphone } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Fragment } from 'react';
import { stageNames } from '@/lib/attendance/devices';
import type { AttendanceDevicesProps } from '@/types';

const STAGES: {
    key: keyof AttendanceDevicesProps['pipeline'];
    label: string;
    icon: LucideIcon;
    tone: string;
}[] = [
    {
        key: 'none',
        label: 'Sin enlace',
        icon: Link2,
        tone: 'bg-warning text-warning-foreground',
    },
    {
        key: 'sent',
        label: 'Enlace enviado',
        icon: Send,
        tone: 'bg-accent text-primary',
    },
    {
        key: 'linked',
        label: 'Con teléfono',
        icon: Smartphone,
        tone: 'bg-risk-bajo-bg text-risk-bajo-fg',
    },
];

/** «Avance del alta» (board A4): how many people are with no link, with a link out, and with a phone, and who they are. */
export default function DevicePipeline({
    pipeline,
}: {
    pipeline: AttendanceDevicesProps['pipeline'];
}) {
    return (
        <section
            aria-labelledby="device-pipeline"
            className="flex flex-wrap items-stretch gap-x-3 gap-y-5 card p-6"
        >
            <h2
                id="device-pipeline"
                className="w-full text-meta font-medium text-muted-foreground"
            >
                Avance del alta
            </h2>
            {STAGES.map(({ key, label, icon: Icon, tone }, index) => (
                <Fragment key={key}>
                    {index > 0 && (
                        <ChevronRight
                            aria-hidden
                            className="mt-3 size-5 shrink-0 self-start text-muted-foreground max-sm:hidden"
                        />
                    )}
                    <div className="flex min-w-48 flex-1 items-start gap-3.5">
                        <span
                            aria-hidden
                            className={`inline-flex size-10 shrink-0 items-center justify-center rounded-lg ${tone}`}
                        >
                            <Icon className="size-5" />
                        </span>
                        <div className="flex min-w-0 flex-col">
                            <span className="text-3xl leading-9 font-semibold tabular-nums">
                                {pipeline[key].count}
                            </span>
                            <span className="font-medium">{label}</span>
                            <span className="text-sm text-muted-foreground">
                                {stageNames(pipeline[key])}
                            </span>
                        </div>
                    </div>
                </Fragment>
            ))}
        </section>
    );
}
