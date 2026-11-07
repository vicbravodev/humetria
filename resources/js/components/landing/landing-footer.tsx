import Logo from '@/components/humetria/logo';
import { ThemeSwitch } from '@/components/theme-switch';

const year = new Date().getFullYear();

export default function LandingFooter() {
    return (
        <footer className="mx-auto flex max-w-[1240px] flex-wrap items-center justify-between gap-4 px-4 py-8 sm:px-6">
            <Logo className="h-6" />
            <div className="flex flex-wrap items-center gap-4">
                <p className="text-caption text-muted-foreground">
                    Hecho en México · {year} Humetría
                </p>
                <ThemeSwitch />
            </div>
        </footer>
    );
}
