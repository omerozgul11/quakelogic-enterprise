import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { Clock, Play, Square } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { cn } from '@/Lib/utils';

interface OpenShift {
    id: number;
    clock_in_iso: string;
    clock_in: string;
}

interface Status {
    open: OpenShift | null;
    today_minutes: number;
}

function fmtClock(totalSeconds: number): string {
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    return `${h}h ${String(m).padStart(2, '0')}m ${String(s).padStart(2, '0')}s`;
}

function fmtMinutes(mins: number): string {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return `${h}h ${String(m).padStart(2, '0')}m`;
}

export function TimeClockWidget() {
    const [status, setStatus] = useState<Status | null>(null);
    const [busy, setBusy] = useState(false);
    const [elapsed, setElapsed] = useState(0); // seconds in the current open shift
    const timer = useRef<number | null>(null);

    const load = async () => {
        try {
            const { data } = await axios.get<Status>('/crm/time-clock/status');
            setStatus(data);
        } catch {
            /* leave status null — the widget simply shows the clocked-out state */
        }
    };

    useEffect(() => { load(); }, []);

    // Tick the open shift every second so the elapsed time stays live.
    const openId = status?.open?.id ?? null;
    const openIso = status?.open?.clock_in_iso ?? null;
    useEffect(() => {
        if (timer.current) { window.clearInterval(timer.current); timer.current = null; }
        if (openIso) {
            const start = new Date(openIso).getTime();
            const tick = () => setElapsed(Math.max(0, Math.floor((Date.now() - start) / 1000)));
            tick();
            timer.current = window.setInterval(tick, 1000);
        } else {
            setElapsed(0);
        }
        return () => { if (timer.current) window.clearInterval(timer.current); };
    }, [openId, openIso]);

    const punch = async (dir: 'in' | 'out') => {
        setBusy(true);
        try {
            const { data } = await axios.post<Status>(`/crm/time-clock/${dir}`);
            setStatus(data);
        } finally {
            setBusy(false);
        }
    };

    const open = status?.open ?? null;
    const todaySeconds = (status?.today_minutes ?? 0) * 60 + (open ? elapsed : 0);

    return (
        <div className="card-surface flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-3">
                <span className={cn('flex h-11 w-11 shrink-0 items-center justify-center rounded-full', open ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-secondary text-muted-foreground')}>
                    <Clock className="h-5 w-5" />
                </span>
                <div>
                    {open ? (
                        <>
                            <p className="text-sm font-semibold text-foreground">
                                <span className="mr-1.5 inline-block h-2 w-2 animate-pulse rounded-full bg-emerald-500 align-middle" />
                                Clocked in · since {open.clock_in}
                            </p>
                            <p className="font-mono text-xl font-bold tabular-nums text-foreground">{fmtClock(elapsed)}</p>
                        </>
                    ) : (
                        <>
                            <p className="text-sm font-semibold text-foreground">You're clocked out</p>
                            <p className="text-xs text-muted-foreground">Clock in to start tracking your shift.</p>
                        </>
                    )}
                </div>
            </div>

            <div className="flex items-center justify-between gap-5 sm:justify-end">
                <div className="text-right">
                    <p className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/70">Today</p>
                    <p className="font-mono text-sm font-semibold tabular-nums text-foreground">{fmtMinutes(Math.floor(todaySeconds / 60))}</p>
                </div>
                {open ? (
                    <Button variant="secondary" icon={Square} disabled={busy} onClick={() => punch('out')}>
                        {busy ? 'Saving…' : 'Clock out'}
                    </Button>
                ) : (
                    <Button icon={Play} disabled={busy} onClick={() => punch('in')}>
                        {busy ? 'Saving…' : 'Clock in'}
                    </Button>
                )}
            </div>
        </div>
    );
}
