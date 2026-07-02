import { Head, router, useForm, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { Modal, ConfirmDialog } from '@/Components/ui/Modal';
import {
    ArrowLeft, Download, ExternalLink, Info, Link2, History, Lock, Sparkles,
    Trash2, Plus, Check, RotateCcw, Search, FileText, Upload,
} from 'lucide-react';

interface Uploader { name: string }
interface DocDetail {
    id: number; ulid: string; display_name: string; description: string | null;
    mime_type: string | null; extension: string | null; size_label: string;
    visibility: string; ai_indexed: boolean; version: number; links_count: number;
    original_filename: string; checksum: string | null; created_at: string | null; updated_at: string | null;
    uploader: Uploader | null; owner: Uploader | null; folder: { id: number; name: string } | null;
    is_previewable_natively: boolean;
}
interface Version {
    id: number; version: number; is_current_version: boolean; size_label: string;
    original_filename: string; uploaded_by: string | null; created_at: string | null; trashed: boolean; download_url: string;
}
interface LinkRow { id: number; type: string; type_label: string; label: string; url: string | null; note: string | null; exists: boolean }
interface LinkTarget { type: string; label: string }
interface Props {
    document: DocDetail;
    versions: Version[];
    links: LinkRow[];
    linkTargets: LinkTarget[];
    previewUrl: string;
    downloadUrl: string;
    can: { manage: boolean };
}

type Tab = 'details' | 'links' | 'versions';

export default function LibraryShow({ document, versions, links, linkTargets, previewUrl, downloadUrl, can }: Props) {
    const [tab, setTab] = useState<Tab>('details');
    const [deleting, setDeleting] = useState(false);
    const backHref = document.folder ? `/library?folder=${document.folder.id}` : '/library';

    return (
        <AppLayout>
            <Head title={document.display_name} />
            <div className="p-4 sm:p-6">
                <div className="mb-4 flex items-center justify-between gap-3">
                    <Link href={backHref} className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"><ArrowLeft className="h-4 w-4" /> Back to library</Link>
                    <div className="flex gap-2">
                        <a href={previewUrl} target="_blank" rel="noreferrer"><Button variant="secondary" icon={ExternalLink}>Open</Button></a>
                        <a href={downloadUrl}><Button icon={Download}>Download</Button></a>
                    </div>
                </div>

                <div className="flex flex-col gap-4 lg:flex-row">
                    {/* Large preview */}
                    <Card className="flex min-h-[60vh] flex-1 flex-col overflow-hidden p-0 lg:min-h-[76vh]">
                        <div className="flex items-center gap-2 border-b border-border px-4 py-2.5">
                            <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                            <p className="min-w-0 flex-1 truncate text-sm font-semibold text-foreground">{document.display_name}</p>
                            <span className="shrink-0 text-xs text-muted-foreground">{(document.extension || '').toUpperCase()}{document.size_label !== '—' ? ` · ${document.size_label}` : ''}</span>
                        </div>
                        <iframe title={document.display_name} src={previewUrl} className="h-full w-full flex-1 border-0 bg-white" />
                    </Card>

                    {/* Right rail: tabs */}
                    <div className="w-full shrink-0 lg:w-[380px]">
                        <Card className="p-0">
                            <div className="flex border-b border-border">
                                {([['details', 'Details', Info], ['links', 'Links', Link2], ['versions', 'Versions', History]] as [Tab, string, typeof Info][]).map(([key, label, Icon]) => (
                                    <button
                                        key={key}
                                        onClick={() => setTab(key)}
                                        className={`flex flex-1 items-center justify-center gap-1.5 px-3 py-2.5 text-sm font-medium transition-colors ${tab === key ? 'border-b-2 border-primary text-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                                    >
                                        <Icon className="h-4 w-4" /> {label}
                                        {key === 'links' && links.length > 0 && <span className="rounded-full bg-secondary px-1.5 text-[10px]">{links.length}</span>}
                                    </button>
                                ))}
                            </div>
                            <div className="p-4">
                                {tab === 'details' && <DetailsTab document={document} can={can.manage} onDelete={() => setDeleting(true)} />}
                                {tab === 'links' && <LinksTab document={document} links={links} targets={linkTargets} can={can.manage} />}
                                {tab === 'versions' && <VersionsTab document={document} versions={versions} can={can.manage} />}
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={deleting}
                onClose={() => setDeleting(false)}
                onConfirm={() => router.delete(`/library/documents/${document.id}`)}
                title="Delete document?"
                message={`"${document.display_name}" will be moved to trash. Any links to it are removed.`}
            />
        </AppLayout>
    );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-3 py-1.5 text-sm">
            <span className="shrink-0 text-muted-foreground">{label}</span>
            <span className="min-w-0 text-right font-medium text-foreground">{children}</span>
        </div>
    );
}

function DetailsTab({ document, can, onDelete }: { document: DocDetail; can: boolean; onDelete: () => void }) {
    const form = useForm({ display_name: document.display_name, description: document.description ?? '' });
    const [dirty, setDirty] = useState(false);

    const save = (e: React.FormEvent) => { e.preventDefault(); form.put(`/library/documents/${document.id}`, { preserveScroll: true, onSuccess: () => setDirty(false) }); };
    const patch = (payload: Record<string, unknown>) => router.patch(`/library/documents/${document.id}`, payload, { preserveScroll: true });

    return (
        <div className="space-y-4">
            {can ? (
                <form onSubmit={save} className="space-y-3">
                    <div>
                        <label className="label">Name</label>
                        <input className="input" value={form.data.display_name} onChange={e => { form.setData('display_name', e.target.value); setDirty(true); }} />
                    </div>
                    <div>
                        <label className="label">Description</label>
                        <textarea className="input" rows={3} value={form.data.description} onChange={e => { form.setData('description', e.target.value); setDirty(true); }} placeholder="Add a description…" />
                    </div>
                    {dirty && <Button type="submit" disabled={form.processing} className="w-full">Save changes</Button>}
                </form>
            ) : (
                <div>
                    <p className="text-sm font-semibold text-foreground">{document.display_name}</p>
                    {document.description && <p className="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{document.description}</p>}
                </div>
            )}

            {/* Toggles */}
            <div className="space-y-2 rounded-lg border border-border p-3">
                <label className="flex items-center justify-between gap-2 text-sm">
                    <span className="flex items-center gap-1.5 text-foreground"><Lock className="h-4 w-4 text-amber-500" /> Private (only me)</span>
                    <input type="checkbox" disabled={!can} className="h-4 w-4 rounded border-border" checked={document.visibility === 'private'} onChange={e => patch({ visibility: e.target.checked ? 'private' : 'shared' })} />
                </label>
                <label className="flex items-center justify-between gap-2 text-sm">
                    <span className="flex items-center gap-1.5 text-foreground"><Sparkles className="h-4 w-4 text-violet-500" /> Readable by QuakeAI</span>
                    <input type="checkbox" disabled={!can || document.visibility === 'private'} className="h-4 w-4 rounded border-border" checked={document.ai_indexed && document.visibility !== 'private'} onChange={e => patch({ ai_indexed: e.target.checked })} />
                </label>
                {document.visibility === 'private' && <p className="text-[11px] text-muted-foreground">Private documents are never sent to the AI.</p>}
            </div>

            <div className="rounded-lg border border-border p-3">
                <Row label="Type">{(document.extension || '').toUpperCase() || document.mime_type || '—'}</Row>
                <Row label="Size">{document.size_label}</Row>
                <Row label="Version">v{document.version}</Row>
                {document.folder && <Row label="Folder"><Link href={`/library?folder=${document.folder.id}`} className="text-primary hover:underline">{document.folder.name}</Link></Row>}
                <Row label="Uploaded by">{document.uploader?.name ?? '—'}</Row>
                <Row label="Added">{document.created_at ? new Date(document.created_at).toLocaleDateString() : '—'}</Row>
                <Row label="Original file"><span className="truncate" title={document.original_filename}>{document.original_filename}</span></Row>
            </div>

            {can && (
                <Button variant="secondary" onClick={onDelete} icon={Trash2} className="w-full !text-destructive hover:!bg-destructive/10">Delete document</Button>
            )}
        </div>
    );
}

function LinksTab({ document, links, targets, can }: { document: DocDetail; links: LinkRow[]; targets: LinkTarget[]; can: boolean }) {
    const [adding, setAdding] = useState(false);
    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">Attached to {links.length} record{links.length === 1 ? '' : 's'}</p>
                {can && <Button variant="secondary" onClick={() => setAdding(true)} icon={Plus} className="!px-2 !py-1 text-xs">Attach</Button>}
            </div>

            {links.length === 0 ? (
                <p className="rounded-lg border border-dashed border-border p-4 text-center text-sm text-muted-foreground">Not linked to anything yet. Attach this document to a proposal, PO, project and more.</p>
            ) : (
                <ul className="space-y-2">
                    {links.map(l => (
                        <li key={l.id} className="flex items-start gap-2 rounded-lg border border-border p-2.5">
                            <span className="mt-0.5 rounded-md bg-secondary px-1.5 py-0.5 text-[10px] font-medium uppercase text-muted-foreground">{l.type_label}</span>
                            <div className="min-w-0 flex-1">
                                {l.url && l.exists
                                    ? <a href={l.url} className="block truncate text-sm font-medium text-primary hover:underline" title={l.label}>{l.label}</a>
                                    : <span className={`block truncate text-sm font-medium ${l.exists ? 'text-foreground' : 'text-muted-foreground line-through'}`} title={l.label}>{l.label}</span>}
                                {l.note && <p className="truncate text-[11px] text-muted-foreground">{l.note}</p>}
                            </div>
                            {can && <button onClick={() => router.delete(`/library/links/${l.id}`, { preserveScroll: true })} className="shrink-0 rounded-md p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Remove link"><Trash2 className="h-3.5 w-3.5" /></button>}
                        </li>
                    ))}
                </ul>
            )}

            <AttachModal open={adding} onClose={() => setAdding(false)} documentId={document.id} targets={targets} />
        </div>
    );
}

function AttachModal({ open, onClose, documentId, targets }: { open: boolean; onClose: () => void; documentId: number; targets: LinkTarget[] }) {
    const [type, setType] = useState(targets[0]?.type ?? 'proposal');
    const [q, setQ] = useState('');
    const [results, setResults] = useState<{ id: number; label: string }[]>([]);
    const [picked, setPicked] = useState<{ id: number; label: string } | null>(null);
    const [note, setNote] = useState('');
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => { if (!open) { setQ(''); setResults([]); setPicked(null); setNote(''); } }, [open]);

    useEffect(() => {
        if (!open) return;
        setPicked(null);
        setLoading(true);
        const t = setTimeout(async () => {
            try {
                const res = await fetch(`/library/link-search?type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const data = await res.json();
                setResults(data.results ?? []);
            } catch { setResults([]); } finally { setLoading(false); }
        }, 300);
        return () => clearTimeout(t);
    }, [type, q, open]);

    const submit = () => {
        if (!picked) return;
        setSubmitting(true);
        router.post(`/library/documents/${documentId}/links`, { linkable_type: type, linkable_id: picked.id, note: note || undefined }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <Modal open={open} onClose={onClose} title="Attach to a record">
            <div className="space-y-3">
                <div>
                    <label className="label">Record type</label>
                    <Select value={type} onChange={v => { setType(v); setQ(''); }} options={targets.map(t => ({ value: t.type, label: t.label }))} />
                </div>
                <div>
                    <label className="label">Find the record</label>
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input className="input pl-8" value={q} onChange={e => setQ(e.target.value)} placeholder="Search by name or number…" autoFocus />
                    </div>
                </div>
                <div className="max-h-56 overflow-y-auto rounded-lg border border-border">
                    {loading ? <p className="p-3 text-center text-sm text-muted-foreground">Searching…</p>
                        : results.length === 0 ? <p className="p-3 text-center text-sm text-muted-foreground">No matches.</p>
                            : results.map(r => (
                                <button key={r.id} onClick={() => setPicked(r)} className={`flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-secondary ${picked?.id === r.id ? 'bg-secondary' : ''}`}>
                                    <span className="min-w-0 truncate text-foreground">{r.label}</span>
                                    {picked?.id === r.id && <Check className="h-4 w-4 shrink-0 text-primary" />}
                                </button>
                            ))}
                </div>
                <div>
                    <label className="label">Note (optional)</label>
                    <input className="input" value={note} onChange={e => setNote(e.target.value)} placeholder="Why is this linked?" />
                </div>
                <div className="flex justify-end gap-2 pt-1">
                    <Button variant="secondary" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit} disabled={!picked || submitting}>Attach</Button>
                </div>
            </div>
        </Modal>
    );
}

function VersionsTab({ document, versions, can }: { document: DocDetail; versions: Version[]; can: boolean }) {
    const form = useForm<{ file: File | null }>({ file: null });
    const upload = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        form.setData('file', file);
        form.transform(() => ({ file }));
        form.post(`/library/documents/${document.id}/versions`, { forceFormData: true });
    };

    return (
        <div className="space-y-3">
            {can && (
                <label className="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-border p-3 text-sm text-muted-foreground hover:border-primary hover:text-foreground">
                    <Upload className="h-4 w-4" /> Upload a new version
                    <input type="file" className="hidden" onChange={upload} />
                </label>
            )}
            {form.progress && <div className="h-1.5 w-full overflow-hidden rounded-full bg-secondary"><div className="h-full bg-brand-gradient" style={{ width: `${form.progress.percentage}%` }} /></div>}

            <ul className="space-y-2">
                {versions.map(v => (
                    <li key={v.id} className={`flex items-center gap-2 rounded-lg border p-2.5 ${v.is_current_version ? 'border-primary/40 bg-primary/5' : 'border-border'}`}>
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-secondary text-xs font-semibold text-foreground">v{v.version}</span>
                        <div className="min-w-0 flex-1">
                            <p className="flex items-center gap-1.5 text-sm font-medium text-foreground">
                                {v.is_current_version && <span className="rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary">Current</span>}
                                {v.size_label}
                            </p>
                            <p className="truncate text-[11px] text-muted-foreground">{v.uploaded_by ?? '—'} · {v.created_at ? new Date(v.created_at).toLocaleDateString() : ''}</p>
                        </div>
                        <a href={v.download_url} className="shrink-0 rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Download"><Download className="h-3.5 w-3.5" /></a>
                        {can && !v.is_current_version && (
                            <button onClick={() => router.post(`/library/documents/${document.id}/versions/${v.id}/restore`, {}, { preserveScroll: true })} className="shrink-0 rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Make current"><RotateCcw className="h-3.5 w-3.5" /></button>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
