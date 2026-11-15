/** Says, and sends with the file, the center whose card opened the import: the rows without a center go to it. */
export default function ImportDefaultCenter({
    center,
}: {
    center: { id: string; name: string } | null;
}) {
    if (center === null) {
        return null;
    }

    return (
        <>
            <input type="hidden" name="center" value={center.id} />
            <p
                data-test="import-default-center"
                className="rounded-lg bg-muted px-4 py-3 text-sm"
            >
                Las filas que dejen vacío el centro de trabajo se cargan en{' '}
                <span className="font-medium">{center.name}</span>.
            </p>
        </>
    );
}
