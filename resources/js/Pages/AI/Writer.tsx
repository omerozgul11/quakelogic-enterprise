import { useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ProposalWriterPanel, SavedSection } from '@/Components/proposal/ProposalWriterPanel';
import { PenLine, Search, FileText, UploadCloud, Loader2, ShieldAlert } from 'lucide-react';

interface ProposalRow {
    id: number;
    proposal_number: string | null;
    project_name: string | null;
    client: string | null;
    status: string | null;
    due_date: string | null;
    sections_count: number;
}

interface Selected {
    id: number;
    proposal_number: string | null;
    project_name: string | null;
    client: string | null;
    status: string | null;
    due_date: string | null;
}

interface Props {
    canWrite: boolean;
    canCreate: boolean;
    autodraft: boolean;
    proposals: ProposalRow[];
    selected: Selected | null;
    savedSections: SavedSection[];
    sections: Array<{ value: string; label: string }>;
    canEditStyle: boolean;
    aiProvider?: string;
    aiAvailable?: boolean;
}

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

function DocumentDropzone({ compact }: { compact?: boolean }) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [drag, setDrag] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const upload = async (files: FileList | null) => {
        if (!files || files.length === 0 || busy) return;
        setError(null);
        setBusy(true);
        const fd = new FormData();
        Array.from(files).forEach(f => fd.append('documents[]', f));
        try {
            const res = await fetch('/proposals/intake-draft', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: fd,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.proposal_id) {
                setBusy(false);
                setError(data?.message ?? 'Those files could not be read. Use PDF, JPG or PNG (max 100MB each).');
                return;
            }
            // Open the new proposal in the writer and auto-draft the full document.
            router.get('/ai/writer', { proposal: data.proposal_id, autodraft: 1 });
        } catch {
            setBusy(false);
            setError('Upload failed. Please try again.');
        }
    };

    return (
        <div>
            <input
                ref={inputRef}
                type="file"
                multiple
                accept="application/pdf,image/jpeg,image/png"
                className="hidden"
                onChange={e => upload(e.target.files)}
            />
            <button
                type="button"
                onClick={() => !busy && inputRef.current?.click()}
                onDragOver={e => { e.preventDefault(); setDrag(true); }}
                onDragLeave={() => setDrag(false)}
                onDrop={e => { e.preventDefault(); setDrag(false); upload(e.dataTransfer.files); }}
                disabled={busy}
                className={`flex w-full flex-col items-center justify-center rounded-2xl border-2 border-dashed text-center transition ${compact ? 'gap-1 px-4 py-5' : 'gap-2 px-6 py-10'} ${drag ? 'border-primary bg-primary/[0.06]' : 'border-border bg-secondary/30 hover:border-primary/50 hover:bg-secondary/50'} ${busy ? 'opacity-80' : ''}`}
            >
                {busy ? (
                    <>
                        <Loader2 className={`${compact ? 'h-5 w-5' : 'h-7 w-7'} animate-spin text-primary`} />
                        <span className="text-sm font-semibold text-foreground">Reading documents &amp; creating the proposal…</span>
                        <span className="text-xs text-muted-foreground">This can take a moment for large files.</span>
                    </>
                ) : (
                    <>
                        <span className={`bg-brand-gradient shadow-glow flex items-center justify-center rounded-xl ${compact ? 'h-9 w-9' : 'h-11 w-11'}`}>
                            <UploadCloud className={`${compact ? 'h-5 w-5' : 'h-6 w-6'} text-white`} />
                        </span>
                        <span className="text-sm font-semibold text-foreground">Dump bid sheets, spec sheets or an RFP</span>
                        {!compact && <span className="text-xs text-muted-foreground">QuakeAI reads them, creates a proposal with the details filled in, and drafts the full document — attached and ready to edit.</span>}
                        <span className="text-[11px] text-muted-foreground">Drag &amp; drop or click · PDF, JPG, PNG</span>
                    </>
                )}
            </button>
            {error && <p className="mt-2 text-sm text-destructive">{error}</p>}
        </div>
    );
}

export default function AiWriter({ canWrite, canCreate, autodraft, proposals, selected, savedSections, sections, canEditStyle, aiProvider = 'unknown', aiAvailable = true }: Props) {
    const [q, setQ] = useState('');
    const isDemo = aiProvider === 'fake' || aiProvider === 'unknown';

    const filtered = useMemo(() => {
        const t = q.trim().toLowerCase();
        if (!t) return proposals;
        return proposals.filter(p =>
            [p.project_name, p.proposal_number, p.client].some(v => (v ?? '').toLowerCase().includes(t)));
    }, [proposals, q]);

    const pick = (id: number) => {
        if (selected?.id === id) return;
        router.get('/ai/writer', { proposal: id }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Proposal Writer" />
            <div className="flex flex-col p-4 sm:p-6 lg:h-[calc(100vh-7rem)] lg:overflow-hidden">
                <PageHeader
                    icon={PenLine}
                    eyebrow={isDemo ? 'Demo mode' : (aiAvailable ? undefined : 'AI offline')}
                    title="Proposal Writer"
                    description="Dump your bid/spec sheets and QuakeAI creates the proposal and drafts the document — or pick an existing proposal and draft section by section."
                />

                {!canWrite ? (
                    <Card className="p-8">
                        <EmptyState
                            icon={ShieldAlert}
                            title="You don't have edit access to any proposals"
                            description="The Proposal Writer drafts into a proposal you can edit. Ask an admin to make you the owner, manager or a team member of a proposal to start writing."
                        />
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 gap-6 lg:min-h-0 lg:flex-1 lg:grid-cols-[20rem_1fr]">
                        {/* LEFT — start from documents + proposal picker */}
                        <div className="flex min-h-0 flex-col">
                            {canCreate && (
                                <div className="mb-3">
                                    <DocumentDropzone compact />
                                </div>
                            )}
                            <div className="relative mb-3">
                                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <input
                                    value={q}
                                    onChange={e => setQ(e.target.value)}
                                    placeholder="Search proposals…"
                                    className="input w-full pl-9"
                                />
                            </div>
                            <div className="sidebar-scroll -mr-1 flex-1 space-y-1.5 overflow-y-auto pr-1 lg:min-h-0">
                                {filtered.length === 0 ? (
                                    <p className="px-1 py-6 text-center text-sm text-muted-foreground">No proposals match.</p>
                                ) : filtered.map(p => {
                                    const on = selected?.id === p.id;
                                    return (
                                        <button
                                            key={p.id}
                                            type="button"
                                            onClick={() => pick(p.id)}
                                            className={`w-full rounded-xl border px-3 py-2.5 text-left transition ${on
                                                ? 'border-primary/30 bg-primary/[0.08] ring-1 ring-inset ring-primary/20'
                                                : 'border-border bg-card hover:bg-secondary/60'}`}
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className={`truncate text-sm font-semibold ${on ? 'text-primary' : 'text-foreground'}`}>
                                                    {p.project_name || 'Untitled proposal'}
                                                </span>
                                                {p.sections_count > 0 && (
                                                    <span className="shrink-0 rounded-full bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
                                                        {p.sections_count} ✓
                                                    </span>
                                                )}
                                            </div>
                                            <div className="mt-0.5 flex items-center gap-1.5 truncate text-xs text-muted-foreground">
                                                {p.proposal_number && <span className="font-mono">{p.proposal_number}</span>}
                                                {p.client && <span className="truncate">· {p.client}</span>}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        {/* RIGHT — writer */}
                        <div className="sidebar-scroll min-h-0 space-y-4 lg:overflow-y-auto lg:pr-1">
                            {!selected ? (
                                <div className="space-y-4">
                                    {canCreate && (
                                        <Card className="p-6">
                                            <DocumentDropzone />
                                        </Card>
                                    )}
                                    <Card className="flex items-center justify-center p-8">
                                        <EmptyState
                                            icon={PenLine}
                                            title={canCreate ? 'Or pick an existing proposal' : 'Pick a proposal to start drafting'}
                                            description="Choose a proposal on the left. QuakeAI asks what it needs (it won't invent facts), writes from your uploaded documents in your org's style, then lets you save and export the document."
                                        />
                                    </Card>
                                </div>
                            ) : (
                                <>
                                    <Card className="p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <h2 className="truncate text-base font-bold text-foreground">{selected.project_name || 'Untitled proposal'}</h2>
                                                <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                                    {selected.proposal_number && <span className="font-mono">{selected.proposal_number}</span>}
                                                    {selected.client && <span>· {selected.client}</span>}
                                                    {selected.status && <span>· {selected.status}</span>}
                                                    {selected.due_date && <span>· due {selected.due_date}</span>}
                                                </div>
                                            </div>
                                            <Button href={`/proposals/${selected.id}`} variant="secondary" size="sm" icon={FileText}>Open proposal</Button>
                                        </div>
                                    </Card>

                                    <ProposalWriterPanel
                                        key={selected.id}
                                        proposalId={selected.id}
                                        sections={sections}
                                        savedSections={savedSections}
                                        canEdit={true}
                                        canEditStyle={canEditStyle}
                                        autoStart={autodraft}
                                    />
                                </>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
