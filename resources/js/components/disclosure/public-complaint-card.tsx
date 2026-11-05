import type { PublicBlockText, PublicChannel } from '@/types';

/**
 * «¿Algo no está bien en su trabajo?» (board V4-Pagina-Personas): the complaint first, with the button to the company's channel
 * as the channel says it (a full visit: that page keeps no session nor cookies), and what the company wrote of how to complain.
 */
export default function PublicComplaintCard({
    channel,
    block,
}: {
    channel: PublicChannel;
    block: PublicBlockText | null;
}) {
    return (
        <section
            aria-labelledby="complaint"
            className="flex flex-col gap-3 rounded-2xl bg-accent p-5"
        >
            <h2 id="complaint" className="text-lg leading-7 font-semibold">
                ¿Algo no está bien en su trabajo?
            </h2>
            <p className="text-sm">
                {channel.anonymousAllowed
                    ? 'Puede avisar sin dar su nombre. Nadie recibirá represalias por hacerlo.'
                    : 'El canal le pide que se identifique, y su aviso se atiende con confidencialidad.'}
            </p>
            {block !== null &&
                block.sections.map((section) => (
                    <p
                        key={section.text}
                        className="text-sm whitespace-pre-line"
                    >
                        {section.text}
                    </p>
                ))}
            <a
                href={channel.url}
                className="inline-flex min-h-12 items-center justify-center rounded-lg bg-primary px-5 text-base font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                Presentar una queja o denuncia
            </a>
        </section>
    );
}
