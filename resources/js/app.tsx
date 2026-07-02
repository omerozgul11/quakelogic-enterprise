import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { ImpersonationBanner } from '@/Components/layout/ImpersonationBanner';
import { installNumericInputGuard } from '@/Lib/numericInput';
import { installRowLinkNavigation } from '@/Lib/rowLinks';
import { Impersonation, SharedProps } from '@/Types';

const appName = import.meta.env.VITE_APP_NAME || 'QuakeLogic Proposals';

// Keep every number field to digits and a single dot (no letters, `e`, `+/-`).
installNumericInputGuard();

// Make whole `tr.row-link` rows clickable across every list view.
installRowLinkNavigation();

// Wraps the Inertia app so the impersonation banner is mounted once, above every
// page/layout, and re-reads the shared `impersonating` prop on each navigation.
function Root({ App, appProps }: { App: React.ComponentType<Record<string, unknown>>; appProps: Record<string, unknown> }) {
    const initial = (appProps.initialPage as { props?: SharedProps } | undefined)?.props?.impersonating ?? null;
    const [impersonating, setImpersonating] = useState<Impersonation | null>(initial);

    useEffect(() => {
        return router.on('navigate', event => {
            const props = (event.detail.page.props as unknown as SharedProps);
            setImpersonating(props.impersonating ?? null);
        });
    }, []);

    return (
        <>
            <App {...appProps} />
            {impersonating && <ImpersonationBanner data={impersonating} />}
        </>
    );
}

createInertiaApp({
    title: (title) => `${title} | ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(<Root App={App as unknown as React.ComponentType<Record<string, unknown>>} appProps={props as unknown as Record<string, unknown>} />);
    },
    progress: {
        color: '#3B82F6',
    },
});
