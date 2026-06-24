import { useState } from 'react';
import { Select } from '@/Components/ui/Select';

interface Option {
    value: string;
    label: string;
}

interface Props {
    value: string;
    onChange: (value: string) => void;
    /** Known + previously-used carriers, supplied by the server. */
    options: Option[];
    className?: string;
}

const CUSTOM = '__custom__';

/**
 * Carrier picker: choose a known carrier (UPS, FedEx, DHL) or one already used in
 * the org, or pick "Add a carrier…" to type a custom freight carrier by name
 * (e.g. J.B. Hunt). Carriers are stored as a free-text column, so any name works;
 * only UPS is auto-tracked — the rest are tracked manually.
 */
export function CarrierField({ value, onChange, options, className }: Props) {
    const inList = options.some(o => o.value === value);
    const [custom, setCustom] = useState(!inList && value.trim() !== '');

    if (custom) {
        return (
            <div className="space-y-1.5">
                <input
                    autoFocus
                    value={value}
                    onChange={e => onChange(e.target.value)}
                    className="input"
                    placeholder="Carrier name (e.g. J.B. Hunt)"
                />
                <button
                    type="button"
                    onClick={() => { setCustom(false); onChange(options[0]?.value ?? 'ups'); }}
                    className="text-xs font-medium text-muted-foreground hover:text-foreground"
                >
                    ← Choose a listed carrier
                </button>
            </div>
        );
    }

    return (
        <Select
            value={inList ? value : (options[0]?.value ?? '')}
            onChange={v => {
                if (v === CUSTOM) { setCustom(true); onChange(''); }
                else onChange(v);
            }}
            options={[...options, { value: CUSTOM, label: '➕ Add a carrier…' }]}
            className={className}
        />
    );
}
