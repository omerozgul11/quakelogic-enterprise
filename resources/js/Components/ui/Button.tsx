import { Link } from '@inertiajs/react';
import { cn } from '@/Lib/utils';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'success';
type Size = 'sm' | 'md' | 'lg';

const VARIANTS: Record<Variant, string> = {
    primary: 'bg-brand-gradient text-white shadow-sm hover:brightness-95 active:scale-[0.98]',
    secondary: 'border border-orange-300 bg-card text-orange-700 hover:bg-orange-50 dark:border-orange-800/70 dark:text-orange-300 dark:hover:bg-orange-950/40 active:scale-[0.98]',
    ghost: 'text-muted-foreground hover:bg-secondary hover:text-foreground active:scale-[0.98]',
    danger: 'bg-destructive text-white shadow-sm hover:brightness-95 active:scale-[0.98]',
    success: 'bg-green-600 text-white shadow-sm hover:bg-green-700 active:scale-[0.98]',
};

const SIZES: Record<Size, string> = {
    sm: 'h-9 gap-1.5 px-3.5 text-sm',
    md: 'h-10 gap-2 px-4 text-sm',
    lg: 'h-11 gap-2 px-5 text-[15px]',
};

const BASE =
    'group inline-flex select-none items-center justify-center rounded-lg font-medium transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:ring-offset-1 disabled:pointer-events-none disabled:opacity-50';

type IconType = React.ComponentType<{ className?: string }>;

interface CommonProps {
    variant?: Variant;
    size?: Size;
    icon?: IconType;
    iconRight?: IconType;
    className?: string;
    children?: React.ReactNode;
}

type ButtonProps = CommonProps &
    Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, keyof CommonProps> & { href?: undefined };

type LinkProps = CommonProps & { href: string; onClick?: () => void };

export function Button(props: ButtonProps | LinkProps) {
    const { variant = 'primary', size = 'md', icon: Icon, iconRight: IconRight, className, children } = props;
    const classes = cn(BASE, VARIANTS[variant], SIZES[size], className);

    const inner = (
        <>
            {Icon && <Icon className="h-4 w-4" />}
            {children}
            {IconRight && <IconRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />}
        </>
    );

    if ('href' in props && props.href) {
        return (
            <Link href={props.href} onClick={props.onClick} className={classes}>
                {inner}
            </Link>
        );
    }

    const { variant: _v, size: _s, icon: _i, iconRight: _ir, className: _c, children: _ch, ...rest } =
        props as ButtonProps;
    return (
        <button className={classes} {...rest}>
            {inner}
        </button>
    );
}
