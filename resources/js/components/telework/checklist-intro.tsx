import { Info } from 'lucide-react';

/** What to read before answering: the instructions of Apéndice 3 and that answering does not shift responsibility (C-11). */
export default function ChecklistIntro({
    workplace,
    instructions,
    notice,
}: {
    workplace: string;
    instructions: string;
    notice: string;
}) {
    return (
        <header className="grid gap-3">
            <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                Lista de verificación de su lugar de trabajo
            </h1>
            <p className="text-base text-muted-foreground">{workplace}</p>
            <p className="whitespace-pre-line">{instructions}</p>
            <p className="flex gap-3 rounded-xl bg-accent p-4 text-base">
                <Info
                    aria-hidden
                    className="mt-0.5 size-5 shrink-0 text-primary"
                />
                {notice}
            </p>
        </header>
    );
}
