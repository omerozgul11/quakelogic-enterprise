import { cn } from '@/Lib/utils';

interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
    hover?: boolean;
}

export function Card({ className, hover, children, ...props }: CardProps) {
    return (
        <div className={cn('card-surface', hover && 'card-hover', className)} {...props}>
            {children}
        </div>
    );
}

export function CardHeader({ className, children, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div className={cn('flex items-center justify-between gap-3 px-5 py-4', className)} {...props}>
            {children}
        </div>
    );
}

export function CardTitle({ className, children, ...props }: React.HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h2 className={cn('text-base font-semibold tracking-tight text-foreground', className)} {...props}>
            {children}
        </h2>
    );
}

export function CardContent({ className, children, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div className={cn('px-5 pb-5', className)} {...props}>
            {children}
        </div>
    );
}
