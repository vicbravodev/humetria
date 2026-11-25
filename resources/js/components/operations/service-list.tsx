import { Badge } from '@/components/ui/badge';
import type { Service } from '@/lib/operations';

/** The services behind the platform, one per row: what it is, what it says now and its state. */
export default function ServiceList({ services }: { services: Service[] }) {
    return (
        <section className="card">
            <header className="border-b px-5 py-4">
                <h2 className="text-base font-semibold">Servicios</h2>
            </header>
            <ul className="divide-y">
                {services.map(({ name, detail, state }) => (
                    <li
                        key={name}
                        className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 py-4"
                    >
                        <div className="min-w-0">
                            <p className="font-medium">{name}</p>
                            <p className="text-sm text-muted-foreground">
                                {detail}
                            </p>
                        </div>
                        <Badge variant={state.variant}>{state.label}</Badge>
                    </li>
                ))}
            </ul>
        </section>
    );
}
