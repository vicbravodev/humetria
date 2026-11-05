import { Link } from '@inertiajs/react';
import { useState } from 'react';
import ChoiceField from '@/components/humetria/choice-field';
import AppliesResult from '@/components/tenancy/applies-result';
import { Button } from '@/components/ui/button';
import { nom035Result, RANGES } from '@/lib/applicability';
import type { Range } from '@/lib/applicability';
import type { NavItem } from '@/types';

/**
 * NOM-035 in «¿Qué te pide cada norma en este centro?» (board V2-Aplica-Otras): it applies everywhere, and the size of the
 * center, marked from the people registered there, decides the guides.
 */
export default function AppliesNom035({
    center,
    people,
    range,
    routeHref,
}: {
    center: string;
    people: number;
    range: Range;
    routeHref: NavItem['href'];
}) {
    const [size, setSize] = useState(range);

    return (
        <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_420px] lg:gap-12">
            <div className="flex flex-col gap-3.5">
                <p className="text-[17px] leading-[26px] text-muted-foreground">
                    La NOM-035 aplica a todos los centros de trabajo. El número
                    de personas decide qué cuestionarios contestan.
                </p>
                <ChoiceField
                    name="range"
                    legend="¿Cuántas personas trabajan en este centro?"
                    options={RANGES}
                    value={size}
                    onChange={setSize}
                    size="lg"
                />
                <p className="text-sm text-muted-foreground">
                    Tienes {people} registradas en {center}. Lo marcamos por ti;
                    las guías de cada evaluación salen de las personas que
                    registres.
                </p>
            </div>
            <AppliesResult
                result={nom035Result(size, people)}
                action={
                    <Button size="lg" asChild>
                        <Link href={routeHref}>Ver mi ruta</Link>
                    </Button>
                }
            />
        </div>
    );
}
