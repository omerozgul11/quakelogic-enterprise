import { cn } from '@/Lib/utils';

interface EmptyStateProps {
    icon: React.ComponentType<{ className?: string }>;
    title: string;
    description?: string;
    action?: React.ReactNode;
    className?: string;
}

export function EmptyState({ icon: Icon, title, description, action, className }: EmptyStateProps) {
    return (
        <div className={cn('flex flex-col items-center justify-center px-6 py-14 text-center', className)}>
            <div className="relative mb-4">
                <div className="absolute inset-0 rounded-2xl bg-primary/10 blur-xl" />
                <div className="relative flex h-14 w-14 items-center justify-center rounded-2xl border border-border bg-secondary/60">
                    <Icon className="h-7 w-7 text-muted-foreground" />
                </div>
            </div>
            <p className="text-base font-semibold text-foreground">{title}</p>
            {description && <p className="mt-1 max-w-sm text-sm text-muted-foreground">{description}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
