import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/** The two ways to save an announcement: on its date, or as a draft that never reaches a panel. Each sends its `publish`. */
export default function AnnouncementActions({
    processing,
}: {
    processing: boolean;
}) {
    return (
        <div className="flex flex-wrap gap-2.5">
            <Button
                type="submit"
                name="publish"
                value="1"
                disabled={processing}
            >
                {processing && <Spinner />}
                Programar aviso
            </Button>
            <Button
                type="submit"
                name="publish"
                value="0"
                variant="outline"
                disabled={processing}
            >
                Guardar borrador
            </Button>
        </div>
    );
}
