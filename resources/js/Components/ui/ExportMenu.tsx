import { useState } from 'react';
import { cn } from '@/Lib/utils';
import { Download, ChevronDown, FileText, FileSpreadsheet, FileType, Table } from 'lucide-react';

interface Format {
    key: string;
    label: string;
    description: string;
    icon: React.ComponentType<{ className?: string }>;
    accent: string;
}

const FORMATS: Format[] = [
    { key: 'pdf', label: 'PDF', description: 'Print-ready, branded report', icon: FileType, accent: 'bg-rose-500/10 text-rose-600 dark:text-rose-400' },
    { key: 'docx', label: 'Word (.docx)', description: 'Editable document', icon: FileText, accent: 'bg-blue-500/10 text-blue-600 dark:text-blue-400' },
    { key: 'xlsx', label: 'Excel (.xlsx)', description: 'One sheet per section', icon: FileSpreadsheet, accent: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' },
    { key: 'csv', label: 'CSV', description: 'Plain comma-separated values', icon: Table, accent: 'bg-amber-500/10 text-amber-600 dark:text-amber-400' },
];

interface Props {
    /** Download URL with `{format}` placeholder, e.g. /reports/users/download/{format}?period=year */
    urlTemplate: string;
    label?: string;
}

export function ExportMenu({ urlTemplate, label = 'Export' }: Props) {
    const [open, setOpen] = useState(false);

    const pick = (format: string) => {
        setOpen(false);
        window.location.href = urlTemplate.replace('{format}', format);
    };

    return (
        <div className="relative">
            <button
                onClick={() => setOpen(v => !v)}
                className="inline-flex h-10 items-center gap-2 rounded-lg border border-orange-300 bg-card px-4 text-sm font-medium text-orange-700 transition-colors hover:bg-orange-50 dark:border-orange-800/70 dark:text-orange-300 dark:hover:bg-orange-950/40"
            >
                <Download className="h-4 w-4" />
                {label}
                <ChevronDown className={cn('h-4 w-4 transition-transform', open && 'rotate-180')} />
            </button>

            {open && (
                <>
                    <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
                    <div className="animate-scale-in absolute right-0 top-12 z-20 w-64 overflow-hidden rounded-xl border border-border bg-card py-1.5 shadow-xl ring-1 ring-black/5 dark:ring-white/10">
                        <p className="px-4 pb-1.5 pt-1 text-[11px] font-bold uppercase tracking-wider text-muted-foreground/70">
                            Download as
                        </p>
                        {FORMATS.map(f => {
                            const Icon = f.icon;
                            return (
                                <button
                                    key={f.key}
                                    onClick={() => pick(f.key)}
                                    className="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-secondary"
                                >
                                    <span className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-lg', f.accent)}>
                                        <Icon className="h-[18px] w-[18px]" />
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block text-sm font-medium text-foreground">{f.label}</span>
                                        <span className="block text-xs text-muted-foreground">{f.description}</span>
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </>
            )}
        </div>
    );
}
