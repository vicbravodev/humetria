import { Button } from '@/components/ui/button';

/** «¿No es usted?»: the browser forgets who marked in it, for the next person to mark as themselves. */
export default function NotYou({ onForget }: { onForget: () => void }) {
    return (
        <Button
            variant="outline"
            size="lg"
            className="self-start"
            onClick={onForget}
        >
            ¿No es usted?
        </Button>
    );
}
