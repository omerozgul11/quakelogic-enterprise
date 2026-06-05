import { cn, getStatusColor, statusLabel } from '@/Lib/utils';

interface StatusBadgeProps {
    status: string;
    label?: string;
    className?: string;
}

export function StatusBadge({ status, label, className }: StatusBadgeProps) {
    return (
        <span className={cn(
            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
            getStatusColor(status),
            className
        )}>
            {label ?? statusLabel(status)}
        </span>
    );
}
