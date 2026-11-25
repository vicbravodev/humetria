import BarMeter from '@/components/humetria/bar-meter';
import { funnel } from '@/lib/operations-messaging';
import type { ChannelTraffic } from '@/types';

/** The WhatsApp of the day, from sent to read: read is a floor, whoever turns off read receipts never shows as read. */
export default function DeliveryFunnel({
    traffic,
}: {
    traffic: ChannelTraffic | undefined;
}) {
    return (
        <section className="card">
            <header className="border-b px-5 py-4">
                <h2 className="text-base font-semibold">
                    WhatsApp de hoy, del envío a la lectura
                </h2>
            </header>
            <ul className="grid gap-3 px-5 py-4">
                {funnel(traffic).map((step, index) => (
                    <li
                        key={step.label}
                        className="grid grid-cols-[96px_1fr_48px] items-center gap-3"
                    >
                        <span className="text-sm text-muted-foreground">
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
