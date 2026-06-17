import React from 'react';

interface NumberInputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type' | 'inputMode'> {
    /** Allow a single decimal point (default true — money, rates, percentages). */
    allowDecimal?: boolean;
    /** Allow a leading minus sign (default false). */
    allowNegative?: boolean;
}

const EDITING_KEYS = new Set([
    'Backspace', 'Delete', 'Tab', 'Enter', 'Escape',
    'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End',
]);

/**
 * A text input restricted to numeric entry: you can type the value directly,
 * but only number keys — plus an optional single decimal point and leading
 * minus — are accepted, and non-numeric pastes are rejected. Drop-in for
 * `<input type="number" />` (same value/onChange/className/placeholder props).
 *
 * Use ONLY where a number is entered — never on text fields.
 */
export function NumberInput({ allowDecimal = true, allowNegative = false, onKeyDown, onPaste, ...props }: NumberInputProps) {
    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        onKeyDown?.(e);
        if (e.defaultPrevented) return;
        // Let editor shortcuts (copy/paste/select-all/undo) and navigation through.
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        if (EDITING_KEYS.has(e.key) || e.key.length > 1) return;
        if (/[0-9]/.test(e.key)) return;

        const value = e.currentTarget.value;
        if (allowDecimal && e.key === '.' && !value.includes('.')) return;
        if (allowNegative && e.key === '-' && e.currentTarget.selectionStart === 0 && !value.includes('-')) return;

        e.preventDefault();
    };

    const handlePaste = (e: React.ClipboardEvent<HTMLInputElement>) => {
        onPaste?.(e);
        if (e.defaultPrevented) return;
        const text = e.clipboardData.getData('text').trim();
        const pattern = allowDecimal
            ? (allowNegative ? /^-?\d*\.?\d*$/ : /^\d*\.?\d*$/)
            : (allowNegative ? /^-?\d*$/ : /^\d*$/);
        if (!pattern.test(text)) e.preventDefault();
    };

    return (
        <input
            type="text"
            inputMode={allowDecimal ? 'decimal' : 'numeric'}
            onKeyDown={handleKeyDown}
            onPaste={handlePaste}
            {...props}
        />
    );
}
