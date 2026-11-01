import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Home, HeartPulse, TriangleAlert } from 'lucide-react';
import PortalNoticeDialog from '@/components/telework/portal/portal-notice-dialog';
import PortalNoticesList from '@/components/telework/portal/portal-notices-list';
import PortalShell from '@/components/telework/portal/portal-shell';
import { NOTICE_KINDS } from '@/lib/telework/notices';
import { show } from '@/routes/portal';
import type { NoticeKindValue, Paginated, PortalNotice } from '@/types';

const ICONS = {
    condition_change: TriangleAlert,
    address_change: Home,
    work_risk: HeartPulse,
} satisfies Record<NoticeKindValue, typeof Home>;

export default function PortalNotices({
    token,
    company,
    name,
    today,
    states,
    notices,
}: {
    token: string;
    company: string;
    name: string;
    today: string;
    states: Record<string, string>;
    notices: Paginated<PortalNotice>;
}) {
    return (
        <PortalShell company={company}>
            <Head title="Sus avisos" />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <Link
                    href={show.url({ token })}
                    className="touch-hit flex w-fit items-center gap-1.5 text-base font-medium text-primary"
                >
                    <ArrowLeft aria-hidden className="size-4" />
                    Volver a mi teletrabajo
                </Link>
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        Sus avisos por escrito
                    </h1>
                    <p className="text-muted-foreground">
                        Aquí avisa a su empresa y a la Comisión de Seguridad e
                        Higiene. Cada aviso lleva la fecha en que lo escribe, la
                        fecha en que ocurrió y su descripción.
                    </p>
                </div>
                <ul className="grid gap-3">
                    {NOTICE_KINDS.map((kind) => {
                        const Icon = ICONS[kind.value];

                        return (
                            <li
                                key={kind.value}
                                className="flex items-start gap-3.5 rounded-xl border bg-card p-4"
                            >
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                                    <Icon aria-hidden className="size-5" />
                                </span>
                                <div className="flex min-w-0 flex-1 flex-col gap-1">
                                    <p className="text-base font-semibold">
                                        {kind.title}
                                    </p>
                                    <p className="text-base text-muted-foreground">
                                        {kind.help}
                                    </p>
                                    <div className="mt-2">
                                        <PortalNoticeDialog
                                            token={token}
                                            kind={kind.value}
                                            title={kind.title}
                                            name={name}
                                            today={today}
                                            states={states}
                                        />
                                    </div>
                                </div>
                            </li>
                        );
                    })}
                </ul>
                <PortalNoticesList notices={notices} />
            </div>
        </PortalShell>
    );
}
