import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Impersonation } from '@/Types';
import { ShieldAlert, LogOut } from 'lucide-react';

/**
 * App-wide banner shown whenever an admin is impersonating another user. Mounted
 * once in app.tsx (above every page/layout) so it is always visible, with a
 * one-click return to the admin's own account.
 */
export function ImpersonationBanner({ data }: { data: Impersonation }) {
    const [leaving, setLeaving] = useState(false);
    const stop = () => {
        setLeaving(true);
        router.post('/impersonate/stop', {}, { onFinish: () => setLeaving(false) });
    };

    return (
        <div className="fixed inset-x-0 bottom-0 z-[200] border-t border-amber-300 bg-amber-500 text-amber-950 shadow-[0_-6px_24px_-12px_rgba(0,0,0,0.4)] dark:border-amber-400 dark:bg-amber-500">
            <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-3 gap-y-1.5 px-4 py-2.5 text-sm sm:justify-between">
                <span className="inline-flex items-center gap-2 font-medium">
                    <ShieldAlert className="h-4 w-4 shrink-0" />
                    Viewing as <span className="font-bold">{data.user?.name ?? 'user'}</span>
                    <span className="hidden opacity-80 sm:inline">· impersonated by {data.impersonator?.name ?? 'an admin'}</span>
                </span>
                <button
                    onClick={stop}
                    disabled={leaving}
                    className="inline-flex items-center gap-1.5 rounded-md bg-amber-950 px-3 py-1.5 text-xs font-semibold text-amber-50 transition-colors hover:bg-amber-900 disabled:opacity-60"
                >
                    <LogOut className="h-3.5 w-3.5" />
                    {leaving ? 'Returning…' : 'Return to your account'}
                </button>
            </div>
        </div>
    );
}
