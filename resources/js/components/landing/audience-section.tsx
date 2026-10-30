const audiences = [
    {
        title: 'Empresas',
        text: 'RH de una empresa con uno o varios centros de trabajo. Humetría le asigna a cada uno lo que le toca según su tamaño y su gente.',
    },
    {
        title: 'Grupos',
        text: 'Un grupo con varias razones sociales, todas en una sola cuenta.',
    },
    {
        title: 'Consultores',
        text: 'Atiende a varias empresas, cada una con sus datos aparte y tu logo en los informes.',
    },
] as const;

export default function AudienceSection() {
    return (
        <section
            aria-labelledby="para-quien"
            className="mx-auto flex max-w-[1200px] flex-col gap-8 px-4 pb-16 sm:px-6 lg:pb-24"
        >
            <h2
                id="para-quien"
                className="text-[28px] leading-9 font-semibold tracking-[-0.015em] sm:text-4xl sm:leading-[44px]"
            >
                Hecha para quien lleva RH
            </h2>

            <div className="flex flex-wrap gap-8">
                {audiences.map(({ title, text }) => (
                    <article
                        key={title}
                        className="flex flex-[1_1_240px] flex-col gap-1.5 border-t-2 border-foreground pt-[18px]"
                    >
                        <h3 className="text-lg leading-7 font-semibold">
                            {title}
                        </h3>
                        <p className="text-muted-foreground">{text}</p>
                    </article>
                ))}
            </div>
        </section>
    );
}
