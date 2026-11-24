import { Head } from '@inertiajs/react';

/** «Permisos adicionales» of one person: placeholder until the page is drawn (Task 7b); the server already sends its props. */
export default function MemberPermissions() {
    return (
        <>
            <Head title="Permisos adicionales" />
            <h1 className="px-4 py-6 text-xl font-semibold">
                Permisos adicionales
            </h1>
        </>
    );
}
