import { useEffect, useState } from 'react';
import { CalendarClock, CheckCircle2, AlertTriangle } from 'lucide-react';
import { formatDate } from '@/Lib/utils';

export interface Countdown {
    deadline: string;
    due_date: string;
    submitted: boolean;
    submission_date: string | null;
}

interface Props {
    countdown: Countdown;
}

interface Remaining {
    past: boolean;
    total: number;
    days: number;
    hours: number;
    minutes: number;
    seconds: number;
}

function remainingTo(target: number): Remaining {
    const ms = target - Date.now();
    const c = Math.max(0, ms);
    return {
        past: ms <= 0,
        total: ms,
        days: Math.floor(c / 86_400_000),
        hours: Math.floor((c % 86_400_000) / 3_600_000),
        minutes: Math.floor((c % 3_600_000) / 60_000),
        seconds: Math.floor((c % 60_000) / 1_000),
    };
}

const Tile = ({ value, label, accent }: { value: number; label: string; accent: string }) => (
    <div className="flex flex-col items-center rounded-xl border border-border bg-card px-2 py-2.5 sm:px-4">
        <span className={`text-2xl font-bold tabular-nums sm:text-3xl ${accent}`}>
            {String(value).padStart(2, '0')}
        </span>
        <span className="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">{label}</span>
    </div>
);

export function ProposalCountdown({ countdown }: Props) {
    const target = new Date(countdown.deadline).getTime();
    const [t, setT] = useState<Remaining>(() => remainingTo(target));

    useEffect(() => {
        if (countdown.submitted) return;
        setT(remainingTo(target));
        const id = setInterval(() => setT(remainingTo(target)), 1000);
        return () => clearInterval(id);
    }, [target, countdown.submitted]);

    // Already submitted — no countdown, just confirm it's in.
    if (countdown.submitted) {
        return (
            <div className="mb-6 flex items-center gap-3 rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200">
                <CheckCircle2 className="h-6 w-6 shrink-0" />
                <div>
                    <p className="text-sm font-semibold">Submitted{countdown.submission_date ? ` on ${formatDate(countdown.submission_date)}` : ''}</p>
                    <p className="text-xs opacity-80">Deadline was {formatDate(countdown.due_date)}.</p>
                </div>
            </div>
        );
    }

    // Deadline has passed without a submission.
    if (t.past) {
        return (
            <div className="mb-6 flex items-center gap-3 rounded-2xl border border-red-300 bg-red-50 p-4 text-red-800 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
                <AlertTriangle className="h-6 w-6 shrink-0" />
                <div>
                    <p className="text-sm font-semibold">Submission deadline passed</p>
                    <p className="text-xs opacity-80">The {formatDate(countdown.due_date)} deadline has passed and this proposal isn't marked submitted.</p>
                </div>
            </div>
        );
    }

    // Urgency: under a day = red, under three days = amber, otherwise calm.
    const accent = t.total <= 86_400_000
        ? 'text-red-600 dark:text-red-400'
        : t.total <= 3 * 86_400_000
            ? 'text-amber-600 dark:text-amber-400'
            : 'text-primary';

    return (
        <div className="mb-6 rounded-2xl border border-border bg-gradient-to-br from-secondary/60 to-card p-4 sm:p-5">
            <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                <CalendarClock className="h-4 w-4 text-primary" />
                Time left to submit
                <span className="ml-auto text-xs font-normal text-muted-foreground">Due {formatDate(countdown.due_date)}</span>
            </div>
            <div className="grid grid-cols-4 gap-2 sm:gap-3">
                <Tile value={t.days} label="Days" accent={accent} />
                <Tile value={t.hours} label="Hours" accent={accent} />
                <Tile value={t.minutes} label="Min" accent={accent} />
                <Tile value={t.seconds} label="Sec" accent={accent} />
            </div>
        </div>
    );
}
