import { ShareMeter } from 'humetria';

const Row = ({ n, text, value, max }: { n: number; text: string; value: number; max: number }) => (
    <li className="grid gap-2 py-3">
        <p className="leading-5">
            <span className="text-muted-foreground tabular-nums">{n}.</span> {text}
        </p>
        <ShareMeter value={value} max={max} label={`Respuestas desfavorables a ${n}. ${text}`} />
        <p className="text-sm text-muted-foreground tabular-nums">
            {value} de {max} desfavorables ({Math.round((value / max) * 100)} %)
        </p>
    </li>
);

export const ItemRow = () => (
    <ul className="flex max-w-[520px] flex-col">
        <Row n={13} text="En mi trabajo debo atender asuntos pendientes cuando estoy en casa" value={47} max={164} />
    </ul>
);

export const Shares = () => (
    <ul className="flex max-w-[520px] flex-col">
        <Row n={2} text="Mi trabajo me exige hacer mucho esfuerzo físico" value={9} max={164} />
        <Row n={5} text="Por la cantidad de trabajo que tengo debo quedarme tiempo adicional" value={71} max={164} />
        <Row n={27} text="Mi jefe ayuda a solucionar los problemas que se presentan en el trabajo" value={128} max={164} />
    </ul>
);

export const Empty = () => (
    <div className="flex max-w-[520px] flex-col gap-2">
        <ShareMeter value={0} max={164} label="Sin respuestas desfavorables" />
        <p className="text-sm text-muted-foreground tabular-nums">0 de 164 desfavorables (0 %)</p>
    </div>
);
