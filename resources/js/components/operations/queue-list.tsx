import { count } from '@/lib/operations';
import type { QueueLoad } from '@/types';

/** Each queue Horizon watches: how many jobs wait in it and how long until it is clear. */
export default function QueueList({ queues }: { queues: QueueLoad[] }) {
    return (
        <section className="card">
            <header className="border-b px-5 py-4">
                <h2 className="text-base font-semibold">Colas</h2>
            </header>
            {queues.length === 0 ? (
                <p className="px-5 py-4 text-sm text-muted-foreground">
                    Horizon no reporta colas ahora.
                </p>
            ) : (
                <ul className="divide-y">
                    {queues.map((queue) => (
                        <li
                            key={queue.name}
                            className="flex items-center justify-between gap-4 px-5 py-3"
                        >
                            <span className="font-medium">{queue.name}</span>
                            <span className="text-sm text-muted-foreground tabular-nums">
                                {count(queue.length)} esperando · {queue.wait} s
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
