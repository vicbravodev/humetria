import { Users } from 'lucide-react';

/** The one warning before creating a key: a family iCloud or Google account shares its keys with everyone in it. */
export default function KeySharedAccountNote() {
    return (
        <p className="flex items-start gap-3 rounded-xl bg-warning px-4 py-3 text-base text-warning-foreground">
            <Users aria-hidden className="mt-1 size-5 shrink-0" />
            Si comparte su cuenta de iCloud o de Google con su familia, ellos
            también tendrán esta llave. Úsela solo en una cuenta personal.
        </p>
    );
}
