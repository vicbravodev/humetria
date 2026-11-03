import { FileText } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ProgramAction } from '@/types';

/** The control of the progress of one action (num. 8.4 d): every step, oldest first. Nothing in it is edited or deleted. */
export default function HistoryDialog({
    open,
    onOpenChange,
    action,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    action: ProgramAction;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                <DialogTitle>Historial de avances</DialogTitle>
                <DialogDescription>{action.title}</DialogDescription>
                {action.history.length === 0 ? (
                    <p className="text-muted-foreground">
                        Aún no hay avances registrados.
                    </p>
                ) : (
                    <ol className="grid gap-4">
                        {action.history.map((step, index) => (
                            <li
                                key={`${step.onLabel}-${index}`}
                                className="grid gap-1 border-l-2 border-primary pl-4"
                            >
                                <p className="font-medium">
                                    {step.status.label}
                                    <span className="font-normal text-muted-foreground">
                                        {' '}
                                        · {step.onLabel}
                                    </span>
                                </p>
                                {step.note !== null && (
                                    <p className="text-sm whitespace-pre-line">
                                        {step.note}
                                    </p>
                                )}
                                {step.evidenceUrl !== null && (
                                    <a
                                        href={step.evidenceUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-1.5 text-sm font-medium text-primary underline-offset-4 hover:underline"
                                    >
                                        <FileText
                                            aria-hidden
                                            className="size-4"
                                        />
                                        Abrir evidencia
                                    </a>
                                )}
                                {step.hasEvidence &&
                                    step.evidenceUrl === null && (
                                        <p className="text-sm text-muted-foreground">
                                            Tiene un archivo de evidencia, que
                                            solo abre quien escribe el Programa.
                                        </p>
                                    )}
                            </li>
                        ))}
                    </ol>
                )}
            </DialogContent>
        </Dialog>
    );
}
