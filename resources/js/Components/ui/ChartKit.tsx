import { formatCurrency } from '@/Lib/utils';

/** Distinct, hue-separated palette tuned to the QuakeLogic orange brand. */
export const CHART_COLORS = ['#f26522', '#6366f1', '#10b981', '#f59e0b', '#ec4899', '#06b6d4', '#a855f7', '#ef4444'];

/** Vertical gradient <defs> for bar/area fills — reference as fill="url(#cg-0)". */
export function ChartGradients() {
    return (
        <defs>
            {CHART_COLORS.map((c, i) => (
                <linearGradient key={i} id={`cg-${i}`} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={c} stopOpacity={0.95} />
                    <stop offset="100%" stopColor={c} stopOpacity={0.5} />
                </linearGradient>
            ))}
        </defs>
    );
}

interface TooltipProps {
    active?: boolean;
    payload?: Array<{ name?: string; value?: number; color?: string; fill?: string; payload?: Record<string, unknown> }>;
    label?: string | number;
    currency?: boolean;
    nameKey?: string;
}

/** Theme-aware tooltip (works in light & dark) used via content={<ChartTooltip ... />}. */
export function ChartTooltip({ active, payload, label, currency = false, nameKey }: TooltipProps) {
    if (!active || !payload?.length) return null;
    const fmt = (v: number | undefined) => (currency ? formatCurrency(v ?? 0) : (typeof v === 'number' ? v.toLocaleString() : v));
    return (
        <div className="rounded-xl border border-border bg-card/95 px-3 py-2 shadow-lift backdrop-blur-sm">
            {label !== undefined && label !== '' && (
                <p className="mb-1 text-xs font-semibold text-foreground">{label}</p>
            )}
            <div className="space-y-0.5">
                {payload.map((p, i) => (
                    <div key={i} className="flex items-center gap-2 text-xs">
                        <span className="h-2 w-2 shrink-0 rounded-full" style={{ background: p.color || p.fill }} />
                        <span className="text-muted-foreground">{p.name ?? (nameKey ? String(p.payload?.[nameKey] ?? '') : '')}</span>
                        <span className="ml-auto pl-3 font-semibold text-foreground">{fmt(p.value)}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

/** Muted axis tick + grid colors that read on both light and dark backgrounds. */
export const AXIS_TICK = { fontSize: 11, fill: 'hsl(var(--muted-foreground))' };
export const GRID_STROKE = 'hsl(var(--border))';
