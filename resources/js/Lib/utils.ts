import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
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
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export function formatDateTime(date?: string | null): string {
    if (!date) return '—';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatPercent(value?: number | null): string {
    if (value == null) return '—';
    return `${Number(value).toFixed(1)}%`;
}

export function getDaysUntil(date?: string | null): number | null {
    if (!date) return null;
    const diff = new Date(date).getTime() - new Date().getTime();
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
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
