import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

type InertiaOptions = NonNullable<Parameters<typeof createInertiaApp>[0]>;
type SetupOptions = Parameters<NonNullable<InertiaOptions['setup']>>[0];

const setup = (options: SetupOptions) => {
    const App = options.App;

    createRoot(options.el as HTMLElement).render(<App {...options.props} />);
};

createInertiaApp({
    title: (title) => (title ? `${title} · GymPass QR` : 'GymPass QR'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.tsx', { eager: true }) as Record<string, ComponentType>;

        return pages[`./Pages/${name}.tsx`];
    },
    setup,
});
