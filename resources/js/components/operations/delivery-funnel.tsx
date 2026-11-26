import BarMeter from '@/components/humetria/bar-meter';
import { funnel } from '@/lib/operations-messaging';
import type { ChannelTraffic } from '@/types';

/** The messages of a range, from sent to read: read is a floor, whoever turns off read receipts never shows as read. */
export default function DeliveryFunnel({
    title,
    traffic,
}: {
    title: string;
    traffic: ChannelTraffic | undefined;
}) {
    return (
        <section className="card p-0">
            <header className="border-b px-6 py-4">
                <h2 className="text-base font-semibold">{title}</h2>
            </header>
            <ul className="grid gap-2.5 px-6 py-5">
                {funnel(traffic).map((step, index) => (
                    <li
                        key={step.label}
                        className="grid grid-cols-[92px_1fr_44px] items-center gap-3"
                    >
                        <span className="text-muted-foreground">
                            {step.label}
                        </span>
                        <BarMeter
                            percent={step.percent}
                            index={index}
                            muted={step.label === 'Rechazados'}
                            tooltip={`${step.label}: ${step.value}`}
                        />
                        <span className="text-right font-medium tabular-nums">
                            {step.value}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
