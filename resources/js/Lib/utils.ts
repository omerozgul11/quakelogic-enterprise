import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * The platform presents every date and time in US Pacific time (PST/PDT),
 * regardless of the viewer's browser timezone. Timestamps are still stored in
 * UTC — this only affects display and day-difference calculations.
 */
export const APP_TZ = 'America/Los_Angeles';

/** [year, month, day] of a Date as it falls in Pacific time. */
function pstYmd(d: Date): [number, number, number] {
    const s = new Intl.DateTimeFormat('en-CA', { timeZone: APP_TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).format(d);
    const [y, m, day] = s.split('-').map(Number);
    return [y, m, day];
}

/** The short Pacific timezone label for a given moment, e.g. "PST" or "PDT". */
export function pacificLabel(d: Date = new Date()): string {
    const part = new Intl.DateTimeFormat('en-US', { timeZone: APP_TZ, timeZoneName: 'short' })
        .formatToParts(d).find(p => p.type === 'timeZoneName');
    return part?.value ?? 'PT';
}

export function formatCurrency(value?: number | string | null, currency = 'USD'): string {
    if (value == null) return '—';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(Number(value));
}

export function formatDate(date?: string | null): string {
    if (!date) return '—';
    // A date-only value (YYYY-MM-DD) is a literal calendar date — render it as-is
    // (in UTC) so a due/submission date never drifts a day when shown in Pacific
    // time. Full timestamps are rendered in Pacific time.
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date);
    const d = m ? new Date(Date.UTC(+m[1], +m[2] - 1, +m[3])) : new Date(date);
    return d.toLocaleDateString('en-US', {
        timeZone: m ? 'UTC' : APP_TZ,
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export function formatDateTime(date?: string | null): string {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        timeZone: APP_TZ,
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatTime(date?: string | null): string {
    if (!date) return '—';
    return new Date(date).toLocaleTimeString('en-US', { timeZone: APP_TZ, hour: 'numeric', minute: '2-digit' });
}

/**
 * Calendar date (as a UTC-midnight timestamp, for stable day-diffing) of an ISO
 * date/datetime string. Date-only strings (YYYY-MM-DD) are read as the literal
 * calendar date they encode — ignoring timezone — so a due/added date never
 * drifts by a day. This is what makes "Yesterday" actually mean yesterday
 * instead of "less than 24 hours ago".
 */
function calendarMs(date: string): number {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(date);
    if (m) return Date.UTC(+m[1], +m[2] - 1, +m[3]);
    const [y, mo, d] = pstYmd(new Date(date));
    return Date.UTC(y, mo - 1, d);
}

/** Today in Pacific time, expressed as a UTC-midnight timestamp for day-diffing. */
function todayMs(): number {
    const [y, mo, d] = pstYmd(new Date());
    return Date.UTC(y, mo - 1, d);
}

export function formatRelativeDate(date?: string | null): string {
    if (!date) return '—';
    const days = Math.round((todayMs() - calendarMs(date)) / 86400000);
    if (days <= 0) return 'Today';
    if (days === 1) return 'Yesterday';
    if (days < 7) return `${days} days ago`;
    return formatDate(date);
}

export function formatPercent(value?: number | null): string {
    if (value == null) return '—';
    return `${Number(value).toFixed(1)}%`;
}

/**
 * Generate a strong, readable password. Guarantees at least one lowercase,
 * uppercase, digit and symbol, and avoids visually ambiguous characters
 * (0/O, 1/l/I) so it can be read aloud or copied without confusion.
 */
export function generatePassword(length = 16): string {
    const lower = 'abcdefghijkmnpqrstuvwxyz';
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const digits = '23456789';
    const symbols = '!@#$%^&*?-_=+';
    const all = lower + upper + digits + symbols;
    const len = Math.max(12, length);

    const pick = (set: string) => set[Math.floor(Math.random() * set.length)];
    const chars = [pick(lower), pick(upper), pick(digits), pick(symbols)];
    while (chars.length < len) chars.push(pick(all));

    // Fisher–Yates shuffle so the guaranteed characters aren't always first.
    for (let i = chars.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }
    return chars.join('');
}

export function getDaysUntil(date?: string | null): number | null {
    if (!date) return null;
    // Compare calendar dates (not a rolling 24h window) so a deadline that is
    // "today" stays "Due today" all day, and yesterday reads as overdue.
    return Math.round((calendarMs(date) - todayMs()) / 86400000);
}

export function getDueDateLabel(date?: string | null): string {
    const days = getDaysUntil(date);
    if (days == null) return '—';
    if (days < 0) return `${Math.abs(days)}d overdue`;
    if (days === 0) return 'Due today';
    if (days === 1) return 'Due tomorrow';
    return `${days}d remaining`;
}

export function getDueDateColor(date?: string | null): string {
    const days = getDaysUntil(date);
    if (days == null) return 'text-gray-500';
    if (days < 0) return 'text-red-600 font-semibold';
    if (days <= 7) return 'text-orange-600 font-semibold';
    if (days <= 14) return 'text-yellow-600';
    return 'text-gray-600';
}

/** Days elapsed since a date (positive once the date is in the past, PST). */
export function getDaysSince(date?: string | null): number | null {
    const until = getDaysUntil(date);
    return until == null ? null : -until;
}

/** Color for days-since-due on submitted work: 0–30 green, 31–60 yellow, 60+ red. */
export function getElapsedColor(days: number): string {
    if (days <= 30) return 'text-emerald-600 font-medium';
    if (days <= 60) return 'text-yellow-600 font-medium';
    return 'text-red-600 font-semibold';
}

const STATUS_COLORS: Record<string, string> = {
    new: 'bg-blue-100 text-blue-800',
    monitoring: 'bg-yellow-100 text-yellow-800',
    qualified: 'bg-indigo-100 text-indigo-800',
    no_bid: 'bg-red-100 text-red-800',
    pursuing: 'bg-purple-100 text-purple-800',
    proposal_in_progress: 'bg-orange-100 text-orange-800',
    submitted: 'bg-cyan-100 text-cyan-800',
    under_evaluation: 'bg-teal-100 text-teal-800',
    awarded: 'bg-green-100 text-green-800',
    completed: 'bg-teal-100 text-teal-800',
    lost: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-100 text-gray-600',
    archived: 'bg-slate-100 text-slate-600',
    draft: 'bg-gray-100 text-gray-700',
    in_progress: 'bg-blue-100 text-blue-800',
    under_review: 'bg-yellow-100 text-yellow-800',
    pending: 'bg-orange-100 text-orange-800',
    clarification_requested: 'bg-amber-100 text-amber-800',
    negotiation: 'bg-purple-100 text-purple-800',
    discovery: 'bg-blue-100 text-blue-800',
    qualification: 'bg-indigo-100 text-indigo-800',
    pursuit: 'bg-purple-100 text-purple-800',
    proposal_development: 'bg-orange-100 text-orange-800',
    submission: 'bg-yellow-100 text-yellow-800',
    evaluation: 'bg-teal-100 text-teal-800',
    award: 'bg-green-100 text-green-800',
    execution: 'bg-emerald-100 text-emerald-800',
};

export function getStatusColor(status: string): string {
    return STATUS_COLORS[status] ?? 'bg-gray-100 text-gray-700';
}

export function statusLabel(status: string): string {
    return status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

const SOURCE_LABELS: Record<string, string> = {
    sam_gov: 'SAM',
    bidprime: 'BidPrime',
    govwin: 'GovWin IQ',
    manual: 'Manual',
    merx: 'MERX',
};

export function sourceLabel(source?: string | null): string {
    if (!source) return '—';
    return SOURCE_LABELS[source] ?? source.replace(/_/g, ' ').toUpperCase();
}

export function getInitials(name?: string | null): string {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

const AVATAR_GRADIENTS = [
    'from-indigo-500 to-violet-500',
    'from-blue-500 to-cyan-500',
    'from-emerald-500 to-teal-500',
    'from-fuchsia-500 to-pink-500',
    'from-amber-500 to-orange-500',
    'from-rose-500 to-red-500',
    'from-sky-500 to-indigo-500',
    'from-violet-500 to-purple-500',
];

export function avatarGradient(seed?: string | null): string {
    if (!seed) return AVATAR_GRADIENTS[0];
    let hash = 0;
    for (let i = 0; i < seed.length; i++) hash = (hash * 31 + seed.charCodeAt(i)) >>> 0;
    return AVATAR_GRADIENTS[hash % AVATAR_GRADIENTS.length];
}
