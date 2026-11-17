import { BadgeCheck, Clock } from 'lucide-react';
import type { SsoDomain } from '@/types';

/** Each domain with what to publish in its DNS, and whether it is proven. */
export default function DomainList({ domains }: { domains: SsoDomain[] }) {
    if (domains.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Aún no hay dominios: agrégalos arriba y guarda para ver qué
                publicar en el DNS.
            </p>
        );
    }

    return (
        <ul className="grid gap-4">
            {domains.map((domain) => (
                <li key={domain.id} className="grid gap-3 card p-5">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-medium">{domain.domain}</span>
                        <span className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                            {domain.verified ? (
                                <BadgeCheck
                                    aria-hidden
                                    className="size-4 text-primary"
                                />
                            ) : (
                                <Clock aria-hidden className="size-4" />
                            )}
                            {domain.verified ? 'Verificado' : 'Falta verificar'}
                        </span>
                    </div>
                    {!domain.verified && (
                        <dl className="grid gap-1 text-sm">
                            <dt className="text-muted-foreground">
                                Registro TXT en el DNS de {domain.domain}
                            </dt>
                            <dd className="font-mono break-all">
                                {domain.record_name}
                            </dd>
                            <dd className="font-mono break-all">
                                {domain.record_value}
                            </dd>
                        </dl>
                    )}
                </li>
            ))}
        </ul>
    );
}
