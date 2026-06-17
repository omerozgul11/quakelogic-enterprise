import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { Button } from '@/Components/ui/Button';
import { Modal } from '@/Components/ui/Modal';
import { EmptyState } from '@/Components/ui/EmptyState';
import { AiChatPanel } from '@/Components/ai/AiChatPanel';
import { Sparkles, ExternalLink, Clock, CheckCircle, AlertCircle, Target, BarChart3, FileText, ArrowRight, History, PenLine, Loader2 } from 'lucide-react';
import { formatDateTime } from '@/Lib/utils';

type SubjectKind = 'opportunity' | 'proposal';
interface SubjectOption { id: number; label: string }
interface QuickAction { label: string; desc: string; type: string; icon: React.ComponentType<{ className?: string }>; defaultSubject: SubjectKind }

interface HistoryRow {
    id: number;
    type: string;
    type_label: string;
    status: string;
    preview: string;
    subject_label: string | null;
    subject_url: string | null;
    by: string | null;
    created_at: string | null;
}

interface Props {
    history?: HistoryRow[];
    historyTypes?: Array<{ value: string; label: string }>;
    filters?: { type?: string | null };
    aiProvider?: string;
    aiAvailable?: boolean;
    subjects?: { opportunity: SubjectOption[]; proposal: SubjectOption[] };
}

const STATUS_META: Record<string, { icon: React.ComponentType<{ className?: string }>; cls: string; label: string }> = {
    pending: { icon: Clock, cls: 'text-amber-500', label: 'Pending' },
    processing: { icon: Clock, cls: 'text-sky-500', label: 'Processing' },
    needs_review: { icon: AlertCircle, cls: 'text-amber-500', label: 'Needs review' },
    completed: { icon: CheckCircle, cls: 'text-emerald-500', label: 'Completed' },
    failed: { icon: AlertCircle, cls: 'text-destructive', label: 'Failed' },
    cancelled: { icon: AlertCircle, cls: 'text-muted-foreground', label: 'Cancelled' },
};

const QUICK_ACTIONS: QuickAction[] = [
    { label: 'Go/No-Go Analysis', desc: 'AI bid decision recommendation', type: 'go_no_go', icon: Target, defaultSubject: 'opportunity' },
    { label: 'Win Probability', desc: 'Estimate win likelihood', type: 'win_probability', icon: BarChart3, defaultSubject: 'opportunity' },
    { label: 'Proposal Summary', desc: 'Executive summary from a proposal', type: 'proposal_summary', icon: FileText, defaultSubject: 'proposal' },
];

const SUBJECT_LABELS: Record<SubjectKind, string> = { opportunity: 'Opportunity', proposal: 'Proposal' };

export default function AiIndex({ history = [], historyTypes = [], filters = {}, aiProvider = 'unknown', aiAvailable = true, subjects = { opportunity: [], proposal: [] } }: Props) {
    const isDemo = aiProvider === 'fake' || aiProvider === 'unknown';

    const filterType = (v: string) =>
        router.get('/ai', v ? { type: v } : {}, { preserveState: true, preserveScroll: true, replace: true });

    // Quick-action runner: pick a subject, then POST /ai/analyze (which redirects
    // to the analysis result page on success).
    const [active, setActive] = useState<QuickAction | null>(null);
    const form = useForm<{ analysis_type: string; subject_type: SubjectKind; subject_id: string; additional_context: string }>({
        analysis_type: '', subject_type: 'opportunity', subject_id: '', additional_context: '',
    });

    const openAction = (a: QuickAction) => {
        setActive(a);
        form.clearErrors();
        form.setData({ analysis_type: a.type, subject_type: a.defaultSubject, subject_id: '', additional_context: '' });
    };
    const close = () => { setActive(null); form.reset(); };
    const setKind = (kind: SubjectKind) => form.setData(d => ({ ...d, subject_type: kind, subject_id: '' }));
    const run = () => {
        if (!form.data.subject_id) return;
        // subject_id stays a string; Laravel's `integer` rule accepts numeric
        // strings, so no transform is needed (and chaining transform().post()
        // is unreliable in this Inertia version).
        form.post('/ai/analyze', { preserveScroll: true });
    };

    const subjectOptions = subjects[form.data.subject_type] ?? [];

    return (
        <AppLayout>
            <Head title="Ask QuakeAI" />
            <div className="flex flex-col p-4 sm:p-6 lg:h-[calc(100vh-7rem)] lg:overflow-hidden">
                <PageHeader
                    icon={Sparkles}
                    eyebrow={isDemo ? 'Demo mode' : undefined}
                    title="Ask QuakeAI"
                    description="Chat with QuakeBot, or run a focused analysis from an opportunity or proposal."
                />

                <div className="grid grid-cols-1 gap-6 lg:min-h-0 lg:flex-1 lg:grid-cols-2">
                    {/* LEFT — chat */}
                    <div className="min-h-0 lg:h-full">
                        <AiChatPanel provider={aiProvider} available={aiAvailable} />
                    </div>

                    {/* RIGHT — proposal writer + quick actions + AI history */}
                    <div className="sidebar-scroll space-y-6 lg:min-h-0 lg:overflow-y-auto lg:pr-1">
                        <Link href="/ai/writer" className="block">
                            <Card hover className="group flex items-center gap-4 p-5">
                                <span className="bg-brand-gradient shadow-glow flex h-11 w-11 shrink-0 items-center justify-center rounded-xl">
                                    <PenLine className="h-5 w-5 text-white" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <h3 className="text-sm font-semibold text-foreground">Proposal Writer</h3>
                                    <p className="mt-0.5 text-xs text-muted-foreground">Draft full proposal sections with AI — in your style, grounded in your past work.</p>
                                </div>
                                <ArrowRight className="h-4 w-4 shrink-0 text-primary transition-transform group-hover:translate-x-0.5" />
                            </Card>
                        </Link>

                        <div className="stagger grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {QUICK_ACTIONS.map(action => {
                                const Icon = action.icon;
                                return (
                                    <button key={action.type} type="button" onClick={() => openAction(action)} className="block w-full text-left">
                                        <Card hover className="animate-rise h-full p-5">
                                            <div className="bg-brand-gradient shadow-glow mb-3 flex h-10 w-10 items-center justify-center rounded-xl">
                                                <Icon className="h-5 w-5 text-white" />
                                            </div>
                                            <h3 className="text-sm font-semibold text-foreground">{action.label}</h3>
                                            <p className="mt-1 text-xs text-muted-foreground">{action.desc}</p>
                                            <span className="mt-3 inline-flex items-center gap-1 text-xs font-medium text-primary">
                                                Run analysis <ArrowRight className="h-3.5 w-3.5" />
                                            </span>
                                        </Card>
                                    </button>
                                );
                            })}
                        </div>

                        {/* AI History */}
                        <Card>
                            <CardHeader className="flex flex-row items-start justify-between gap-3">
                                <div>
                                    <CardTitle className="flex items-center gap-2"><History className="h-4 w-4 text-primary" /> AI History</CardTitle>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Everything QuakeAI has generated for your team — Go/No-Go calls, win-probability estimates, summaries,
                                        follow-up drafts and proposal sections. Click any item to reopen it.
                                    </p>
                                </div>
                                {historyTypes.length > 0 && (
                                    <Select
                                        value={filters.type ?? ''}
                                        onChange={filterType}
                                        options={historyTypes}
                                        placeholder="All types"
                                        className="w-40 shrink-0"
                                        size="sm"
                                    />
                                )}
                            </CardHeader>
                            <CardContent>
                                {history.length === 0 ? (
                                    <EmptyState
                                        icon={Sparkles}
                                        title="Nothing yet"
                                        description="Run a Go/No-Go or win-probability from an opportunity, or draft a proposal section, and it'll show up here."
                                    />
                                ) : (
                                    <div className="space-y-2">
                                        {history.map(row => {
                                            const meta = STATUS_META[row.status] ?? STATUS_META.pending;
                                            const StatusIcon = meta.icon;
                                            return (
                                                <Link key={row.id} href={`/ai/${row.id}`} className="block rounded-xl border border-border p-3 transition-colors hover:bg-secondary/50">
                                                    <div className="flex items-center gap-2">
                                                        <span className="chip">{row.type_label}</span>
                                                        <span className={`ml-auto inline-flex items-center gap-1 text-[11px] font-medium ${meta.cls}`}>
                                                            <StatusIcon className="h-3.5 w-3.5" /> {meta.label}
                                                        </span>
                                                    </div>
                                                    {row.subject_label && (
                                                        <p className="mt-1.5 text-sm font-medium text-foreground">{row.subject_label}</p>
                                                    )}
                                                    {row.preview && row.preview !== '—' && (
                                                        <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{row.preview}</p>
                                                    )}
                                                    <div className="mt-1.5 flex items-center gap-2 text-[11px] text-muted-foreground">
                                                        <span>{formatDateTime(row.created_at)}</span>
                                                        {row.by && <span>· {row.by}</span>}
                                                        {row.subject_url && (
                                                            <span
                                                                role="link"
                                                                tabIndex={0}
                                                                onClick={e => { e.preventDefault(); e.stopPropagation(); router.visit(row.subject_url!); }}
                                                                className="ml-auto inline-flex cursor-pointer items-center gap-1 font-medium text-primary hover:underline"
                                                            >
                                                                Open record <ExternalLink className="h-3 w-3" />
                                                            </span>
                                                        )}
                                                    </div>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <Modal
                open={active !== null}
                onClose={close}
                title={active?.label}
                description={active?.desc}
                footer={
                    <>
                        <Button variant="secondary" onClick={close}>Cancel</Button>
                        <Button onClick={run} disabled={!form.data.subject_id || form.processing} icon={form.processing ? Loader2 : Sparkles}>
                            {form.processing ? 'Running…' : 'Run analysis'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <div>
                        <label className="label">Run against</label>
                        <div className="flex gap-2">
                            {(['opportunity', 'proposal'] as SubjectKind[]).map(kind => (
                                <button
                                    key={kind}
                                    type="button"
                                    onClick={() => setKind(kind)}
                                    className={`inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium transition ${form.data.subject_type === kind ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-card text-muted-foreground hover:bg-secondary hover:text-foreground'}`}
                                >
                                    {SUBJECT_LABELS[kind]}
                                    <span className="text-[11px] opacity-70">({subjects[kind]?.length ?? 0})</span>
                                </button>
                            ))}
                        </div>
                    </div>

                    <div>
                        <label className="label">{SUBJECT_LABELS[form.data.subject_type]}</label>
                        {subjectOptions.length > 0 ? (
                            <Select
                                value={form.data.subject_id}
                                onChange={v => form.setData('subject_id', v)}
                                placeholder={`Select a ${form.data.subject_type}…`}
                                options={subjectOptions.map(s => ({ value: String(s.id), label: s.label }))}
                                className="w-full"
                            />
                        ) : (
                            <p className="rounded-lg border border-border bg-secondary/40 px-3 py-2 text-sm text-muted-foreground">
                                No {form.data.subject_type === 'opportunity' ? 'open opportunities' : 'proposals'} available to analyze.
                            </p>
                        )}
                        {form.errors.subject_id && <p className="mt-1 text-xs text-destructive">{form.errors.subject_id}</p>}
                    </div>

                    <div>
                        <label className="label">Extra context <span className="font-normal text-muted-foreground">(optional)</span></label>
                        <textarea
                            value={form.data.additional_context}
                            onChange={e => form.setData('additional_context', e.target.value)}
                            rows={3}
                            className="input w-full"
                            placeholder="Anything else the AI should weigh — incumbency, strategy, constraints…"
                        />
                    </div>

                    {isDemo && (
                        <p className="text-xs text-muted-foreground">
                            Demo mode: the active AI provider will return illustrative output.
                        </p>
                    )}
                </div>
            </Modal>
        </AppLayout>
    );
}
