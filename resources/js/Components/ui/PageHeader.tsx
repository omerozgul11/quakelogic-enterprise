import { cn } from '@/Lib/utils';

interface PageHeaderProps {
    title: string;
    description?: string;
    eyebrow?: string;
    icon?: React.ComponentType<{ className?: string }>;
    actions?: React.ReactNode;
    className?: string;
}

export function PageHeader({ title, description, eyebrow, icon: Icon, actions, className }: PageHeaderProps) {
    return (
        <div className={cn('animate-rise mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between', className)}>
            <div className="flex items-center gap-4">
                {Icon && (
                    <div className="bg-brand-gradient shadow-glow flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl">
                        <Icon className="h-[22px] w-[22px] text-white" />
                    </div>
                )}
                <div>
                    {eyebrow && (
                        <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-primary">{eyebrow}</p>
                    )}
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">{title}</h1>
                    {description && <p className="mt-0.5 text-sm text-muted-foreground">{description}</p>}
                </div>
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2.5">{actions}</div>}
        </div>
    );
}
