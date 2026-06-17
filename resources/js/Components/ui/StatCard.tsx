import { Link } from '@inertiajs/react';
import { ArrowUpRight, TrendingUp, TrendingDown } from 'lucide-react';
import { cn } from '@/Lib/utils';

export type StatTone = 'indigo' | 'emerald' | 'violet' | 'amber' | 'rose' | 'sky' | 'teal';

const TONES: Record<StatTone, { tile: string; glow: string; ring: string }> = {
    indigo: { tile: 'from-indigo-500 to-violet-500', glow: 'bg-indigo-500/10', ring: 'group-hover:ring-indigo-200' },
    emerald: { tile: 'from-emerald-500 to-teal-500', glow: 'bg-emerald-500/10', ring: 'group-hover:ring-emerald-200' },
    violet: { tile: 'from-violet-500 to-fuchsia-500', glow: 'bg-violet-500/10', ring: 'group-hover:ring-violet-200' },
    amber: { tile: 'from-amber-500 to-orange-500', glow: 'bg-amber-500/10', ring: 'group-hover:ring-amber-200' },
    rose: { tile: 'from-rose-500 to-red-500', glow: 'bg-rose-500/10', ring: 'group-hover:ring-rose-200' },
    sky: { tile: 'from-sky-500 to-blue-500', glow: 'bg-sky-500/10', ring: 'group-hover:ring-sky-200' },
    teal: { tile: 'from-teal-500 to-cyan-500', glow: 'bg-teal-500/10', ring: 'group-hover:ring-teal-200' },
};

interface StatCardProps {
    title: string;
    value: string | number;
    subtitle?: string;
    icon: React.ComponentType<{ className?: string }>;
    tone?: StatTone;
    href?: string;
    trend?: { value: string; direction: 'up' | 'down' };
}

export function StatCard({ title, value, subtitle, icon: Icon, tone = 'indigo', href, trend }: StatCardProps) {
    const t = TONES[tone];

    const body = (
        <div className="card-surface card-hover group relative h-full overflow-hidden p-5 ring-1 ring-transparent transition-all">
            <div className={cn('pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full blur-2xl transition-opacity', t.glow)} />

            <div className="relative flex items-start justify-between">
                <p className="text-sm font-medium text-muted-foreground">{title}</p>
                <div className={cn('flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-sm', t.tile)}>
                    <Icon className="h-5 w-5" />
                </div>
            </div>

            <p className="relative mt-3.5 text-[32px] font-bold leading-none tracking-[-0.03em] text-foreground">{value}</p>

            <div className="relative mt-2 flex items-center gap-2">
                {trend && (
                    <span
                        className={cn(
                            'inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-xs font-semibold',
                            trend.direction === 'up' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                        )}
                    >
                        {trend.direction === 'up' ? <TrendingUp className="h-3 w-3" /> : <TrendingDown className="h-3 w-3" />}
                        {trend.value}
                    </span>
                )}
                {subtitle && <p className="text-xs text-muted-foreground">{subtitle}</p>}
            </div>

            {href && (
                <div className="relative mt-3 inline-flex items-center gap-1 text-xs font-semibold text-primary opacity-0 transition-opacity group-hover:opacity-100">
                    View all
                    <ArrowUpRight className="h-3.5 w-3.5" />
                </div>
            )}
        </div>
    );

    if (href) return <Link href={href} className="block h-full">{body}</Link>;
    return body;
}
