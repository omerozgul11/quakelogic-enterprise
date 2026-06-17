import { router } from '@inertiajs/react';
import { cn } from '@/Lib/utils';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationProps {
    from?: number | null;
    to?: number | null;
    total: number;
    links: PaginationLink[];
}

export function Pagination({ from, to, total, links }: PaginationProps) {
    if (links.length <= 3) return null;

    return (
        <div className="flex flex-col items-center justify-between gap-3 border-t border-border px-4 py-3 sm:flex-row">
            <p className="text-sm text-muted-foreground">
                Showing <span className="font-medium text-foreground">{from ?? 0}</span>–
                <span className="font-medium text-foreground">{to ?? 0}</span> of{' '}
                <span className="font-medium text-foreground">{total}</span>
            </p>
            <div className="flex flex-wrap items-center gap-1">
                {links.map((link, i) => (
                    <button
                        key={i}
                        onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })}
                        disabled={!link.url}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                        className={cn(
                            'inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-sm font-medium transition-colors',
                            link.active
                                ? 'bg-brand-gradient text-white shadow-glow'
                                : 'text-muted-foreground hover:bg-secondary hover:text-foreground disabled:pointer-events-none disabled:opacity-40'
                        )}
                    />
                ))}
            </div>
        </div>
    );
}
