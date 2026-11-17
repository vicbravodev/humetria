import { Button } from '@/components/ui/button';
import { show } from '@/routes/complaints/follow-up';

/** The two ways in, stuck to the bottom of the start (board QuejaInicio): to present one, or to see how one goes. */
export default function IntroActions({
    code,
    onStart,
}: {
    code: string;
    onStart: () => void;
}) {
    return (
        <>
            <Button size="lg" className="w-full" onClick={onStart}>
                Presentar una queja
            </Button>
            <Button size="lg" variant="outline" className="w-full" asChild>
                <a href={show.url({ code })}>Ya presenté una: ver cómo va</a>
            </Button>
        </>
    );
}
