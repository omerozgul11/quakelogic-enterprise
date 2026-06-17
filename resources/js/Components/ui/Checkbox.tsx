import { Check, Minus } from 'lucide-react';
import { cn } from '@/Lib/utils';

interface CheckboxProps {
    checked: boolean;
    onChange: (checked: boolean) => void;
    /** Renders a dash instead of a tick — for a "some, not all" master checkbox. */
    indeterminate?: boolean;
    disabled?: boolean;
    className?: string;
    title?: string;
    ariaLabel?: string;
}

/**
 * A theme-aware checkbox. The native input is visually hidden (but kept for
 * focus/keyboard/a11y) and a styled box reflects its state using design-system
 * tokens, so it looks right in both light and dark mode — unlike a raw
 * <input type="checkbox"> which renders with the browser's default chrome.
 */
export function Checkbox({ checked, onChange, indeterminate = false, disabled = false, className, title, ariaLabel }: CheckboxProps) {
    const active = checked || indeterminate;
    return (
        <label
            title={title}
            className={cn(
                'relative inline-flex h-4 w-4 shrink-0 items-center justify-center',
                disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                className,
            )}
        >
            <input
                type="checkbox"
                className="peer sr-only"
                checked={checked}
                disabled={disabled}
                aria-label={ariaLabel}
                onChange={e => onChange(e.target.checked)}
            />
            <span
                aria-hidden
                className={cn(
                    'flex h-4 w-4 items-center justify-center rounded-[5px] border transition-colors',
                    active
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-card text-transparent peer-hover:border-primary/60',
                    'peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 peer-focus-visible:ring-offset-1 peer-focus-visible:ring-offset-card',
                )}
            >
                {indeterminate
                    ? <Minus className="h-3 w-3" strokeWidth={3.5} />
                    : <Check className="h-3 w-3" strokeWidth={3.5} />}
            </span>
        </label>
    );
}
