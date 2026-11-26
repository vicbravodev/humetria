import { ProgressBar } from 'humetria';

const Block = ({ title, value, max, note }: { title: string; value: number; max: number; note?: string }) => (
    <div className="flex w-full max-w-[240px] flex-col gap-2">
        <p className="flex justify-between gap-3 text-sm">
            <span className="font-medium">{title}</span>
            {note !== undefined && <span className="text-muted-foreground">{note}</span>}
        </p>
        <ProgressBar value={value} max={max} label={title} />
    </div>
);

export const Disclosure = () => <Block title="5 de 8 listas" value={5} max={8} note="≈ 12 min lo demás" />;

export const States = () => (
    <div className="flex flex-col gap-5">
        <Block title="0 de 186 contestaron" value={0} max={186} />
        <Block title="142 de 186 contestaron" value={142} max={186} />
        <Block title="Muestra completa: 186 de 186" value={186} max={186} />
    </div>
);
