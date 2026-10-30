import ThemeMiniature from '@/components/settings/theme-miniature';

/** The light, dark or "same as your device" look, the last one split down the middle. */
export default function ThemePreview({
    theme,
}: {
    theme: 'light' | 'dark' | 'system';
}) {
    if (theme !== 'system') {
        return <ThemeMiniature dark={theme === 'dark'} />;
    }

    return (
        <span className="relative block">
            <ThemeMiniature dark={false} />
            <span className="absolute inset-0 [clip-path:inset(0_0_0_50%)]">
                <ThemeMiniature dark />
            </span>
        </span>
    );
}
