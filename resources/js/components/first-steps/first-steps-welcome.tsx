import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { welcomeText } from '@/lib/first-steps/steps';

/** The first time an owner arrives: what the steps are and a way to start or leave them for later. */
export default function FirstStepsWelcome({
    open,
    total,
    onStart,
    onDismiss,
}: {
    open: boolean;
    total: number;
    onStart: () => void;
    onDismiss: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={(next) => !next && onDismiss()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Te acompañamos en tus primeros pasos
                    </DialogTitle>
                    <DialogDescription>{welcomeText(total)}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={onDismiss}>
                        Ahora no
                    </Button>
                    <Button onClick={onStart}>Empezar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
