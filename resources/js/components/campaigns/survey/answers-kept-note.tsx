import { Lock } from 'lucide-react';

/** Under the questions: the answers wait on the device until sent, and in an anonymous guide nobody of the company sees them. */
export default function AnswersKeptNote({
    identified,
}: {
    identified: boolean;
}) {
    return (
        <p className="pt-2 text-center text-base text-muted-foreground">
            <Lock
                aria-hidden="true"
                className="mr-1.5 inline size-4 align-[-2px]"
            />
            Sus respuestas se guardan en este dispositivo hasta que las envíe.
            {!identified && ' Nadie de su empresa puede ver lo que contesta.'}
        </p>
    );
}
