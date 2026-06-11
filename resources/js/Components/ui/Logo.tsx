import { cn } from '@/Lib/utils';

/**
 * QuakeLogic brand mark — the official "Q" logo tile.
 */
export function LogoMark({ className, size = 36 }: { className?: string; size?: number; mono?: boolean }) {
    return (
        <img
            src="/quakelogic-q-logo.png"
            width={size}
            height={size}
            alt="QuakeLogic"
            className={cn('shrink-0 rounded-[22%] object-contain', className)}
        />
    );
}

export function Logo({ className, dark = false }: { className?: string; dark?: boolean }) {
    return (
        <span className={cn('inline-flex items-center gap-2.5', className)}>
            <LogoMark size={34} />
            <span className="flex flex-col leading-none">
                <span className={cn('text-[17px] font-extrabold tracking-tight', dark ? 'text-white' : 'text-foreground')}>
                    QuakeLogic
                </span>
                <span className={cn('text-[10px] font-semibold uppercase tracking-[0.15em]', dark ? 'text-orange-100/90' : 'text-muted-foreground')}>
                    Enterprise
                </span>
            </span>
        </span>
    );
}
