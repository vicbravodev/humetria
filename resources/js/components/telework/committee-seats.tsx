import { Link } from '@inertiajs/react';
import type { CommitteeSeat } from '@/types';

/** The aside of the Comisión's queue (board V2-Comision): what it may see, and who sits on each of its centers. */
export default function CommitteeSeats({ seats }: { seats: CommitteeSeat[] }) {
    return (
        <aside className="flex flex-col gap-4">
            <section
                aria-labelledby="sees"
                className="flex flex-col gap-1.5 rounded-2xl bg-accent p-5"
            >
                <h2 id="sees" className="font-semibold">
                    Qué puedes ver aquí
                </h2>
                <p className="text-sm">
                    Solo lo que le toca a la Comisión de tus centros. No ves
                    resultados de NOM-035 ni datos de salud de nadie.
                </p>
            </section>
            {seats.map((seat) => (
                <section
                    key={seat.id}
                    aria-label={`Integrantes de ${seat.name}`}
                    className="flex flex-col gap-2 card p-5"
                >
                    <h2 className="font-semibold">Integrantes · {seat.name}</h2>
                    <ul className="text-sm">
                        {seat.members.map((member) => (
                            <li key={member}>{member}</li>
                        ))}
                    </ul>
                    <Link
                        href={seat.href}
                        className="touch-hit self-start text-sm font-medium text-primary hover:underline"
                    >
                        Ver la ruta del centro
                    </Link>
                </section>
            ))}
        </aside>
    );
}
