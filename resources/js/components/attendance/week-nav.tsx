import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** The week before and the one after (when it already started); opening another asks for the person's signature again. */
export default function WeekNav({
    previousUrl,
    nextUrl,
}: {
    previousUrl: string;
    nextUrl: string | null;
}) {
    return (
        <nav aria-label="Otras semanas" className="flex flex-col gap-2">
            <Button
                asChild
                variant="outline"
                size="lg"
                className="w-full text-base"
            >
                <Link href={previousUrl}>
                    <ChevronLeft aria-hidden className="size-5" />
                    Semana anterior
                </Link>
            </Button>
            {nextUrl !== null && (
                <Button
                    asChild
                    variant="outline"
                    size="lg"
                    className="w-full text-base"
                >
                    <Link href={nextUrl}>
                        Semana siguiente
                        <ChevronRight aria-hidden className="size-5" />
                    </Link>
                </Button>
            )}
        </nav>
    );
}
