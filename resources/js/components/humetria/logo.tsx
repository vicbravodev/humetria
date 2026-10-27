import { cn } from '@/lib/utils';

const files = {
    logo: ['humetria-horizontal-color', 'humetria-horizontal-color-oscuro'],
    symbol: ['humetria-simbolo-color', 'humetria-simbolo-color-oscuro'],
    seal: ['humetria-sello-hecho-con-gris', 'humetria-sello-hecho-con-blanco'],
} as const;

export default function Logo({
    kind = 'logo',
    className,
}: {
    kind?: 'logo' | 'symbol' | 'seal';
    className?: string;
}) {
    const [light, dark] = files[kind];

    return (
        <>
            <img
                src={`/brand/${light}.svg`}
                alt="Humetría"
                className={cn('w-auto dark:hidden', className)}
            />
            <img
                src={`/brand/${dark}.svg`}
                alt="Humetría"
                className={cn('hidden w-auto dark:block', className)}
            />
        </>
    );
}
