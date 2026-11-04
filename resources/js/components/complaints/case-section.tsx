/** One block of what the person wrote, with its title. */
export default function CaseSection({
    title,
    text,
}: {
    title: string;
    text: string;
}) {
    return (
        <section className="flex flex-col gap-2">
            <h2 className="text-lg font-semibold">{title}</h2>
            <p className="text-lg leading-7 whitespace-pre-line">{text}</p>
        </section>
    );
}
