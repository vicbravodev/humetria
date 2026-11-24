import { useState } from 'react';
import HomeAccount from '@/components/worker-access/home-account';
import HomePending from '@/components/worker-access/home-pending';
import HomeStart from '@/components/worker-access/home-start';
import PortalTabBar from '@/components/worker-access/portal-tab-bar';
import type { PortalTab } from '@/components/worker-access/portal-tab-bar';
import WorkerFrame from '@/components/worker-access/worker-frame';
import { pendingTotal, splitSections } from '@/lib/worker-access/portal';
import type { WorkerSection } from '@/types';

/**
 * The person's portal (findings 3 and 29): three tabs, the same for everyone. Inicio, with what waits for them first; Pendientes,
 * what waits across their tools; Cuenta, their key for this device and «Salir». Each tab fades in as it opens.
 */
export default function HomeScreen({
    company,
    name,
    slug,
    sections,
    keyUrl,
    hasKey,
    leaveUrl,
}: {
    company: string;
    name: string;
    slug: string;
    sections: WorkerSection[];
    keyUrl: string | null;
    hasKey: boolean;
    leaveUrl: string;
}) {
    const [tab, setTab] = useState<PortalTab>('start');
    const { waiting, tools } = splitSections(sections);
    const pending = tab === 'pending' && waiting.length === 0;

    return (
        <WorkerFrame
            company={company}
            centered={pending}
            bar={
                <PortalTabBar
                    current={tab}
                    pending={pendingTotal(sections)}
                    onSelect={setTab}
                />
            }
        >
            <div
                key={tab}
                className="flex w-full animate-in flex-col gap-6 duration-200 ease-out fade-in"
            >
                {tab === 'start' && (
                    <HomeStart
                        company={company}
                        name={name}
                        slug={slug}
                        waiting={waiting}
                        tools={tools}
                    />
                )}
                {tab === 'pending' && <HomePending waiting={waiting} />}
                {tab === 'account' && (
                    <HomeAccount
                        company={company}
                        name={name}
                        slug={slug}
                        keyUrl={keyUrl}
                        hasKey={hasKey}
                        leaveUrl={leaveUrl}
                    />
                )}
            </div>
        </WorkerFrame>
    );
}
