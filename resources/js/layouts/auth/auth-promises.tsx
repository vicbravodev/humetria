import { Check } from 'lucide-react';

// True of every tool on the platform, not only NOM-035.
const PROMISES = [
    {
        title: 'Instrumentos oficiales',
        detail: 'Copiados tal cual de la norma.',
    },
    {
        title: 'Resultados por grupo',
        detail: 'Nunca por persona, nunca con nombre.',
    },
    {
        title: 'Informes listos',
        detail: 'Para presentarlos ante la STPS.',
    },
];

/** The jade side of the access screens: the "h" of the logo drawn large and three promises. Hidden on narrow screens. */
export default function AuthPromises() {
    return (
        <aside className="relative m-2 hidden flex-1 flex-col justify-center overflow-hidden rounded-[18px] bg-accent p-16 lg:flex">
            <svg
                viewBox="0 0 64 64"
                aria-hidden
                className="absolute -right-[90px] -bottom-[110px] size-[420px] opacity-90"
            >
                <path
                    d="M12 6V58M12 36C12 17 52 17 52 36V58"
                    fill="none"
                    strokeWidth={10}
                    strokeLinecap="round"
                    className="stroke-card opacity-70"
                />
                <circle
                    cx={27}
                    cy={47}
                    r={7.5}
                    className="fill-card opacity-70"
                />
                <circle
                    cx={37}
                    cy={47}
                    r={7.5}
                    className="fill-card opacity-45"
                />
            </svg>
            <div className="relative flex max-w-[440px] flex-col gap-8">
                <p className="text-[32px] leading-10 font-semibold tracking-[-0.015em]">
                    Cumple con la STPS y cuida a tu gente, sin complicarte.
                </p>
                <ul className="flex flex-col gap-[18px]">
                    {PROMISES.map((promise) => (
                        <li key={promise.title} className="flex gap-3.5">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-card text-primary">
                                <Check
                                    aria-hidden
                                    className="size-4"
                                    strokeWidth={2.25}
                                />
                            </span>
                            <span className="flex flex-col">
                                <span className="font-medium">
                                    {promise.title}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {promise.detail}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </aside>
    );
}
