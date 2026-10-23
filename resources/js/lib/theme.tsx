import { useCallback, useState } from 'react';

type Theme = 'light' | 'dark';

const STORAGE_KEY = 'gympass-theme';

function currentTheme(): Theme {
    const stored = window.localStorage.getItem(STORAGE_KEY);

    if (stored === 'light' || stored === 'dark') {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function applyStoredTheme(): void {
    document.documentElement.dataset.theme = currentTheme();
}

export function useTheme() {
    const [theme, setTheme] = useState<Theme>(() => currentTheme());

    const toggle = useCallback(() => {
        setTheme((previous) => {
            const next: Theme = previous === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = next;
            window.localStorage.setItem(STORAGE_KEY, next);

            return next;
        });
    }, []);

    return { theme, toggle };
}

export function ThemeToggle({ className = '' }: { className?: string }) {
    const { theme, toggle } = useTheme();

    return (
        <button
            type="button"
            onClick={toggle}
            aria-label={theme === 'dark' ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'}
            className={`min-h-11 rounded-[8px] border border-[var(--line)] px-3 text-[13px] font-semibold ${className}`}
        >
            {theme === 'dark' ? 'Mode terang' : 'Mode gelap'}
        </button>
    );
}
