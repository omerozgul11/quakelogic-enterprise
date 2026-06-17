import { cn } from '@/Lib/utils';

type PillColor = 'gray' | 'blue' | 'indigo' | 'green' | 'red' | 'amber';

const COLORS: Record<PillColor, string> = {
    gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    blue: 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300',
    indigo: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-300',
    green: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
    red: 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
};

export function Pill({ color, label, className }: { color: string; label: string; className?: string }) {
    return (
        <span className={cn(
            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
            COLORS[(color as PillColor)] ?? COLORS.gray,
            className,
        )}>
            {label}
        </span>
    );
}
