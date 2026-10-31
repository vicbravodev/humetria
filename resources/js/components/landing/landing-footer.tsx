import Logo from '@/components/humetria/logo';

const year = new Date().getFullYear();

export default function LandingFooter() {
    return (
        <footer className="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-4 px-4 py-8 sm:px-6">
            <Logo className="h-6" />
            <p className="text-[13px] leading-[18px] text-muted-foreground">
                Hecho en México · {year} Humetría
            </p>
        </footer>
    );
}
