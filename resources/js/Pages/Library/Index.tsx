import { Head, router, useForm, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Select } from '@/Components/ui/Select';
import { Modal, ConfirmDialog } from '@/Components/ui/Modal';
import {
    FolderOpen, Folder, Upload, FolderPlus, Search, X, ChevronRight, Home,
    FileText, Image as ImageIcon, FileSpreadsheet, FileArchive, Presentation,
    File as FileIcon, Lock, Sparkles, Link2, Pencil, Trash2,
} from 'lucide-react';

interface FolderCard { id: number; name: string; visibility: string; documents_count: number; children_count: number }
interface DocCard {
    id: number; ulid: string; display_name: string; mime_type: string | null; extension: string | null;
    size_label: string; visibility: string; ai_indexed: boolean; version: number; links_count: number;
    updated_at: string | null; uploader: { name: string } | null; show_url: string;
}
interface Crumb { id: number; name: string }
interface Props {
    folders: FolderCard[];
    documents: DocCard[];
    current: { id: number; name: string; visibility: string } | null;
    breadcrumbs: Crumb[];
    search: string;
    can: { manage: boolean };
}

const VIS_OPTS = [
    { value: 'shared', label: 'Shared — everyone in the org' },
    { value: 'private', label: 'Private — only me' },
];

function fileIcon(ext: string | null, mime: string | null) {
    const e = (ext || '').toLowerCase();
    const m = (mime || '').toLowerCase();
    if (m.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'heic'].includes(e)) return ImageIcon;
    if (['xls', 'xlsx', 'csv', 'ods'].includes(e)) return FileSpreadsheet;
    if (['ppt', 'pptx', 'odp'].includes(e)) return Presentation;
    if (['zip', 'rar', '7z', 'gz'].includes(e)) return FileArchive;
    if (['pdf', 'doc', 'docx', 'txt', 'md', 'rtf', 'odt'].includes(e) || m.includes('pdf')) return FileText;
    return FileIcon;
}

function timeAgo(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso).getTime();
    const s = Math.floor((Date.now() - d) / 1000);
    if (s < 60) return 'just now';
    const m = Math.floor(s / 60); if (m < 60) return `${m}m ago`;
    const h = Math.floor(m / 60); if (h < 24) return `${h}h ago`;
    const day = Math.floor(h / 24); if (day < 30) return `${day}d ago`;
    return new Date(iso).toLocaleDateString();
}

export default function LibraryIndex({ folders, documents, current, breadcrumbs, search, can }: Props) {
    const [q, setQ] = useState(search);
    const [uploadOpen, setUploadOpen] = useState(false);
    const [folderOpen, setFolderOpen] = useState(false);
    const [renaming, setRenaming] = useState<FolderCard | null>(null);
    const [deletingFolder, setDeletingFolder] = useState<FolderCard | null>(null);
    const firstRender = useRef(true);

    const upload = useForm<{ files: File[]; folder_id: number | null; visibility: string; description: string }>({
        files: [], folder_id: current?.id ?? null, visibility: current?.visibility === 'private' ? 'private' : 'shared', description: '',
    });
    const folderForm = useForm<{ name: string; parent_id: number | null; visibility: string }>({
        name: '', parent_id: current?.id ?? null, visibility: current?.visibility === 'private' ? 'private' : 'shared',
    });
    const renameForm = useForm<{ name: string }>({ name: '' });

    // Debounced search across the whole library.
    useEffect(() => {
        if (firstRender.current) { firstRender.current = false; return; }
        const t = setTimeout(() => {
            router.get('/library', { q: q || undefined, folder: q ? undefined : current?.id }, { preserveState: true, preserveScroll: true, replace: true });
        }, 350);
        return () => clearTimeout(t);
    }, [q]);

    const goFolder = (id: number | null) => router.get('/library', { folder: id ?? undefined }, { preserveScroll: true });

    const submitUpload = (e: React.FormEvent) => {
        e.preventDefault();
        upload.transform(d => ({ ...d, folder_id: current?.id ?? null }));
        upload.post('/library/upload', { forceFormData: true, preserveScroll: true, onSuccess: () => { upload.reset('files', 'description'); setUploadOpen(false); } });
    };
    const submitFolder = (e: React.FormEvent) => {
        e.preventDefault();
        folderForm.transform(d => ({ ...d, parent_id: current?.id ?? null }));
        folderForm.post('/library/folders', { preserveScroll: true, onSuccess: () => { folderForm.reset('name'); setFolderOpen(false); } });
    };
    const submitRename = (e: React.FormEvent) => {
        e.preventDefault();
        if (!renaming) return;
        renameForm.put(`/library/folders/${renaming.id}`, { preserveScroll: true, onSuccess: () => setRenaming(null) });
    };

    const empty = folders.length === 0 && documents.length === 0;

    return (
        <AppLayout>
            <Head title="Document Library" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={FolderOpen}
                    title="Document Library"
                    description="A shared space for your team's documents — upload anything, organise into folders, link files to proposals, POs and projects, and let QuakeAI read the shared ones."
                    actions={can.manage && (
                        <div className="flex gap-2">
                            <Button variant="secondary" onClick={() => setFolderOpen(true)} icon={FolderPlus}>New Folder</Button>
                            <Button onClick={() => setUploadOpen(true)} icon={Upload}>Upload</Button>
                        </div>
                    )}
                />

                {/* Breadcrumbs + search */}
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <nav className="flex items-center gap-1 text-sm text-muted-foreground">
                        <button onClick={() => goFolder(null)} className="flex items-center gap-1 rounded-md px-2 py-1 hover:bg-secondary hover:text-foreground"><Home className="h-3.5 w-3.5" /> Library</button>
                        {breadcrumbs.map(c => (
                            <span key={c.id} className="flex items-center gap-1">
                                <ChevronRight className="h-3.5 w-3.5 opacity-50" />
                                <button onClick={() => goFolder(c.id)} className="rounded-md px-2 py-1 hover:bg-secondary hover:text-foreground">{c.name}</button>
                            </span>
                        ))}
                        {current?.visibility === 'private' && <span className="ml-1 inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-600"><Lock className="h-3 w-3" /> Private</span>}
                    </nav>
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            value={q}
                            onChange={e => setQ(e.target.value)}
                            placeholder="Search the library…"
                            className="input h-9 w-64 max-w-[60vw] pl-8 pr-8"
                        />
                        {q && <button onClick={() => setQ('')} className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"><X className="h-4 w-4" /></button>}
                    </div>
                </div>

                {empty ? (
                    <Card className="p-2">
                        <EmptyState
                            icon={FolderOpen}
                            title={search ? 'Nothing matched your search' : 'This folder is empty'}
                            description={search ? 'Try a different term.' : 'Upload documents or create a folder to get started.'}
                            action={can.manage && !search && <Button onClick={() => setUploadOpen(true)} icon={Upload}>Upload a document</Button>}
                        />
                    </Card>
                ) : (
                    <div className="space-y-6">
                        {/* Folders */}
                        {folders.length > 0 && (
                            <div>
                                <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Folders</h3>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    {folders.map(f => (
                                        <Card key={f.id} className="group flex items-center gap-3 p-3">
                                            <button onClick={() => goFolder(f.id)} className="flex min-w-0 flex-1 items-center gap-3 text-left">
                                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-gradient/10 text-primary"><Folder className="h-5 w-5" /></span>
                                                <span className="min-w-0">
                                                    <span className="flex items-center gap-1.5">
                                                        <span className="truncate text-sm font-semibold text-foreground">{f.name}</span>
                                                        {f.visibility === 'private' && <Lock className="h-3 w-3 shrink-0 text-amber-500" />}
                                                    </span>
                                                    <span className="block text-[11px] text-muted-foreground">{f.documents_count} file{f.documents_count === 1 ? '' : 's'}{f.children_count ? ` · ${f.children_count} folder${f.children_count === 1 ? '' : 's'}` : ''}</span>
                                                </span>
                                            </button>
                                            {can.manage && (
                                                <div className="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                                    <button onClick={() => { setRenaming(f); renameForm.setData('name', f.name); }} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Rename"><Pencil className="h-3.5 w-3.5" /></button>
                                                    <button onClick={() => setDeletingFolder(f)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-3.5 w-3.5" /></button>
                                                </div>
                                            )}
                                        </Card>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Documents */}
                        {documents.length > 0 && (
                            <div>
                                <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Documents</h3>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    {documents.map(d => {
                                        const Icon = fileIcon(d.extension, d.mime_type);
                                        return (
                                            <Link key={d.id} href={d.show_url} className="group">
                                                <Card className="flex h-full flex-col p-3 transition-shadow hover:shadow-md">
                                                    <div className="mb-2 flex items-start gap-3">
                                                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-secondary text-primary"><Icon className="h-5 w-5" /></span>
                                                        <div className="min-w-0 flex-1">
                                                            <p className="truncate text-sm font-semibold text-foreground" title={d.display_name}>{d.display_name}</p>
                                                            <p className="truncate text-[11px] text-muted-foreground">{(d.extension || '').toUpperCase()}{d.size_label !== '—' ? ` · ${d.size_label}` : ''}{d.version > 1 ? ` · v${d.version}` : ''}</p>
                                                        </div>
                                                    </div>
                                                    <div className="mt-auto flex flex-wrap items-center gap-1.5 pt-1">
                                                        {d.visibility === 'private'
                                                            ? <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-600"><Lock className="h-2.5 w-2.5" /> Private</span>
                                                            : d.ai_indexed && <span className="inline-flex items-center gap-1 rounded-full bg-violet-500/10 px-1.5 py-0.5 text-[10px] font-medium text-violet-600" title="Readable by QuakeAI"><Sparkles className="h-2.5 w-2.5" /> AI</span>}
                                                        {d.links_count > 0 && <span className="inline-flex items-center gap-1 rounded-full bg-secondary px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"><Link2 className="h-2.5 w-2.5" /> {d.links_count}</span>}
                                                        <span className="ml-auto text-[10px] text-muted-foreground">{timeAgo(d.updated_at)}</span>
                                                    </div>
                                                </Card>
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Upload */}
            <Modal open={uploadOpen} onClose={() => setUploadOpen(false)} title={`Upload to ${current ? current.name : 'Library'}`}>
                <form onSubmit={submitUpload} className="space-y-4">
                    <div>
                        <label className="label">Files *</label>
                        <input type="file" multiple onChange={e => upload.setData('files', Array.from(e.target.files ?? []))} className="input" />
                        <p className="mt-1 text-xs text-muted-foreground">PDF, Office docs, images, text, spreadsheets, presentations or zip. Up to 100&nbsp;MB each.</p>
                        {upload.errors.files && <p className="mt-1 text-xs text-destructive">{upload.errors.files}</p>}
                        {(upload.errors as Record<string, string>)['files.0'] && <p className="mt-1 text-xs text-destructive">{(upload.errors as Record<string, string>)['files.0']}</p>}
                    </div>
                    <div>
                        <label className="label">Visibility</label>
                        <Select value={upload.data.visibility} onChange={v => upload.setData('visibility', v)} options={VIS_OPTS} />
                        {current?.visibility === 'private' && <p className="mt-1 text-xs text-amber-600">This folder is private, so uploads here stay private.</p>}
                    </div>
                    <div>
                        <label className="label">Description (optional)</label>
                        <textarea className="input" rows={2} value={upload.data.description} onChange={e => upload.setData('description', e.target.value)} placeholder="What is this document?" />
                    </div>
                    {upload.progress && <div className="h-1.5 w-full overflow-hidden rounded-full bg-secondary"><div className="h-full bg-brand-gradient" style={{ width: `${upload.progress.percentage}%` }} /></div>}
                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="secondary" onClick={() => setUploadOpen(false)}>Cancel</Button>
                        <Button type="submit" disabled={upload.processing || upload.data.files.length === 0}>Upload</Button>
                    </div>
                </form>
            </Modal>

            {/* New folder */}
            <Modal open={folderOpen} onClose={() => setFolderOpen(false)} title={`New folder in ${current ? current.name : 'Library'}`}>
                <form onSubmit={submitFolder} className="space-y-4">
                    <div>
                        <label className="label">Folder name *</label>
                        <input className="input" value={folderForm.data.name} onChange={e => folderForm.setData('name', e.target.value)} required autoFocus />
                        {folderForm.errors.name && <p className="mt-1 text-xs text-destructive">{folderForm.errors.name}</p>}
                    </div>
                    <div>
                        <label className="label">Visibility</label>
                        <Select value={folderForm.data.visibility} onChange={v => folderForm.setData('visibility', v)} options={VIS_OPTS} />
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="secondary" onClick={() => setFolderOpen(false)}>Cancel</Button>
                        <Button type="submit" disabled={folderForm.processing}>Create</Button>
                    </div>
                </form>
            </Modal>

            {/* Rename folder */}
            <Modal open={!!renaming} onClose={() => setRenaming(null)} title="Rename folder">
                <form onSubmit={submitRename} className="space-y-4">
                    <input className="input" value={renameForm.data.name} onChange={e => renameForm.setData('name', e.target.value)} required autoFocus />
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={() => setRenaming(null)}>Cancel</Button>
                        <Button type="submit" disabled={renameForm.processing}>Save</Button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                open={!!deletingFolder}
                onClose={() => setDeletingFolder(null)}
                onConfirm={() => deletingFolder && router.delete(`/library/folders/${deletingFolder.id}`, { preserveScroll: true, onFinish: () => setDeletingFolder(null) })}
                title="Delete folder?"
                message={deletingFolder ? `"${deletingFolder.name}" will be removed. Any documents or sub-folders inside it move up one level — nothing is deleted.` : ''}
            />
        </AppLayout>
    );
}
