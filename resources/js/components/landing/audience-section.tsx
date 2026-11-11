import Reveal from '@/components/landing/reveal';

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
];

export default function AudienceSection() {
    return (
        <section
            aria-labelledby="para-quien"
            className="mx-auto flex max-w-[1240px] flex-col gap-10 px-4 py-20 sm:px-6"
        >
            <h2
                id="para-quien"
                className="text-[32px] leading-[1.1] font-semibold tracking-[-0.025em] sm:text-[44px]"
            >
                Hecha para quien lleva RH
            </h2>

            <div className="flex flex-wrap gap-8">
                {audiences.map(({ title, text }, index) => (
                    <Reveal
                        key={title}
                        delay={index * 0.08}
                        className="flex flex-[1_1_240px]"
                    >
                        <article className="flex flex-col gap-1.5 border-t-2 border-foreground pt-[18px]">
                            <h3 className="text-lg leading-7 font-semibold">
                                {title}
                            </h3>
                            <p className="text-muted-foreground">{text}</p>
                        </article>
                    </Reveal>
                ))}
            </div>
            <p
                data-test="unions"
                className="max-w-[64ch] text-muted-foreground"
            >
                ¿Representas a un sindicato? Compensación tiene su propia cuenta
                para tu lado de la mesa, con tus datos aparte de los de la
                empresa.
            </p>
        </section>
    );
}
