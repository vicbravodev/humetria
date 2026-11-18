import { Head } from '@inertiajs/react';
import ThemeChoice from '@/components/settings/theme-choice';

export default function Appearance() {
    return (
        <>
            <Head title="Apariencia · Ajustes" />
            <ThemeChoice />
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [{ title: 'Cuenta' }, { title: 'Configuración' }],
};
