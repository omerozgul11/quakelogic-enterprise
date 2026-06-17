import { useEffect, useRef, useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { PenLine, Loader2, Copy, Check, Save, Trash2, FileText, FileType, Settings2, ArrowLeft, Sparkles, AlertTriangle } from 'lucide-react';

interface SectionOption { value: string; label: string }
export interface SavedSection { id: number; section_key: string; heading: string; content: string }

interface Props {
    proposalId: number;
    sections: SectionOption[];
    savedSections: SavedSection[];
    canEdit: boolean;
    canEditStyle: boolean;
    /** When true, auto-draft every not-yet-written section on mount (used after a document dump). */
    autoStart?: boolean;
}

type Step = 'idle' | 'questions' | 'drafting' | 'draft';

interface BulkState { running: boolean; current: string | null; done: number; total: number; failed: string[] }

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

async function postJson(url: string, body: unknown) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    return { ok: res.ok, data: await res.json().catch(() => ({})) };
}

export function ProposalWriterPanel({ proposalId, sections, savedSections, canEdit, canEditStyle, autoStart }: Props) {
    const [step, setStep] = useState<Step>('idle');
    const [active, setActive] = useState<SectionOption | null>(null);
    const [questions, setQuestions] = useState<string[]>([]);
    const [answers, setAnswers] = useState<Record<number, string>>({});
    const [text, setText] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);
    const [saved, setSaved] = useState<SavedSection[]>(savedSections);
    const [bulk, setBulk] = useState<BulkState>({ running: false, current: null, done: 0, total: 0, failed: [] });
    const autoFired = useRef(false);

    if (!canEdit) return null;

    const reset = () => { setStep('idle'); setActive(null); setQuestions([]); setAnswers({}); setText(''); setError(null); };
    const hasSection = (key: string) => saved.some(s => s.section_key === key);

    const persist = async (sectionValue: string, label: string, content: string): Promise<boolean> => {
        const { ok, data } = await postJson(`/proposals/${proposalId}/sections`, { section_key: sectionValue, heading: label, content });
        if (ok && data.section) {
            setSaved(prev => {
                const others = prev.filter(s => s.section_key !== data.section.section_key);
                return [...others, data.section as SavedSection];
            });
            return true;
        }
        return false;
    };

    const startSection = async (s: SectionOption) => {
        if (busy || bulk.running) return;
        setActive(s); setError(null); setQuestions([]); setAnswers({}); setText('');
        setStep('questions'); setBusy(true);
        const { ok, data } = await postJson(`/proposals/${proposalId}/draft-section/questions`, { section: s.value });
        setBusy(false);
        if (!ok) { setError(data.error ?? 'Could not prepare questions.'); return; }
        const qs: string[] = data.questions ?? [];
        setQuestions(qs);
        if (qs.length === 0) draftNow(s, []); // nothing to ask → draft straight away
    };

    const draftNow = async (s: SectionOption, qs: string[]) => {
        setStep('drafting'); setBusy(true); setError(null);
        const payload = qs.map((q, i) => ({ question: q, answer: answers[i] ?? '' })).filter(a => a.answer.trim() !== '');
        const { ok, data } = await postJson(`/proposals/${proposalId}/draft-section`, { section: s.value, answers: payload });
        setBusy(false);
        if (!ok) { setError(data.error ?? 'Could not draft that section.'); setStep('questions'); return; }
        setText(data.text ?? ''); setStep('draft');
    };

    const save = async () => {
        if (!active || !text.trim()) return;
        setBusy(true);
        const ok = await persist(active.value, active.label, text);
        setBusy(false);
        if (ok) reset(); else setError('Could not save that section.');
    };

    const remove = async (s: SavedSection) => {
        if (!confirm(`Remove "${s.heading}" from this proposal?`)) return;
        const res = await fetch(`/proposals/${proposalId}/sections/${s.id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (res.ok) setSaved(prev => prev.filter(x => x.id !== s.id));
    };

    // Draft every section that isn't written yet, in sequence (respects AI rate
    // limits). Already-saved sections are kept so manual edits aren't clobbered.
    const draftFull = async () => {
        if (bulk.running) return;
        reset();
        const todo = sections.filter(s => !hasSection(s.value));
        if (todo.length === 0) { setError('Every section is already drafted — delete one to redraft it.'); return; }
        setError(null);
        setBulk({ running: true, current: null, done: 0, total: todo.length, failed: [] });
        const failed: string[] = [];
        for (let i = 0; i < todo.length; i++) {
            const s = todo[i];
            setBulk(b => ({ ...b, current: s.label, done: i }));
            const { ok, data } = await postJson(`/proposals/${proposalId}/draft-section`, { section: s.value, answers: [] });
            if (!ok || !data.text) { failed.push(s.label); continue; }
            const okSave = await persist(s.value, s.label, data.text);
            if (!okSave) failed.push(s.label);
        }
        setBulk({ running: false, current: null, done: todo.length, total: todo.length, failed });
    };

    // Auto-draft once on mount when arriving straight from a document dump.
    useEffect(() => {
        if (autoStart && !autoFired.current && canEdit) {
            autoFired.current = true;
            draftFull();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [autoStart]);

    const copy = () => navigator.clipboard?.writeText(text).then(() => { setCopied(true); setTimeout(() => setCopied(false), 1500); });

    const sectionStatus = (key: string): 'done' | 'current' | 'pending' => {
        if (hasSection(key)) return 'done';
        if (bulk.running && bulk.current && sections.find(s => s.value === key)?.label === bulk.current) return 'current';
        return 'pending';
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2"><PenLine className="h-4 w-4 text-primary" /> Proposal Writer</CardTitle>
                <div className="flex items-center gap-3">
                    {step === 'idle' && !bulk.running && (
                        <button type="button" onClick={draftFull} className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:opacity-80">
                            <Sparkles className="h-3.5 w-3.5" /> Draft full document
                        </button>
                    )}
                    {canEditStyle && (
                        <a href="/settings/proposal-style" className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-primary">
                            <Settings2 className="h-3.5 w-3.5" /> Writing style
                        </a>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                {/* Full-document drafting progress */}
                {bulk.running && (
                    <div className="mb-4 rounded-xl border border-primary/20 bg-primary/[0.05] p-3">
                        <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                            <Loader2 className="h-4 w-4 animate-spin text-primary" />
                            Drafting full document — {bulk.done}/{bulk.total}{bulk.current ? ` · ${bulk.current}…` : ''}
                        </div>
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            {sections.map(s => {
                                const st = sectionStatus(s.value);
                                return (
                                    <span key={s.value} className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium ${st === 'done' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : st === 'current' ? 'bg-primary/15 text-primary' : 'bg-secondary text-muted-foreground'}`}>
                                        {st === 'done' && <Check className="h-3 w-3" />}{st === 'current' && <Loader2 className="h-3 w-3 animate-spin" />}{s.label}
                                    </span>
                                );
                            })}
                        </div>
                    </div>
                )}

                {!bulk.running && bulk.total > 0 && bulk.done > 0 && (
                    <div className={`mb-4 rounded-xl border p-3 text-sm ${bulk.failed.length ? 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300' : 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300'}`}>
                        <div className="flex items-center gap-2 font-medium">
                            {bulk.failed.length ? <AlertTriangle className="h-4 w-4" /> : <Check className="h-4 w-4" />}
                            Document drafted — {bulk.total - bulk.failed.length}/{bulk.total} sections.
                        </div>
                        {bulk.failed.length > 0 && (
                            <p className="mt-1 text-xs">Couldn't draft: {bulk.failed.join(', ')}. Try those individually below.</p>
                        )}
                        <p className="mt-1 text-xs opacity-80">Review each section, fill any <code>[NEEDS: …]</code> notes, then export to Word or PDF.</p>
                    </div>
                )}

                {step === 'idle' && !bulk.running && (
                    <>
                        <p className="mb-3 text-xs text-muted-foreground">
                            Draft the whole document at once, or pick a single section. The writer asks what it needs (it won't invent facts),
                            writes from your uploaded solicitation/spec sheets in your style profile, and you save sections to assemble the document.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {sections.map(s => {
                                const done = hasSection(s.value);
                                return (
                                    <button key={s.value} type="button" onClick={() => startSection(s)} disabled={busy}
                                        className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition disabled:opacity-60 ${done ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300' : 'border-border bg-card text-muted-foreground hover:bg-secondary hover:text-foreground'}`}>
                                        {done && <Check className="h-3.5 w-3.5" />}{s.label}
                                    </button>
                                );
                            })}
                        </div>
                    </>
                )}

                {(step === 'questions' || step === 'drafting') && active && (
                    <div>
                        <div className="mb-3 flex items-center gap-2">
                            <button type="button" onClick={reset} className="text-muted-foreground hover:text-foreground" title="Back"><ArrowLeft className="h-4 w-4" /></button>
                            <span className="text-sm font-semibold text-foreground">{active.label}</span>
                        </div>
                        {busy && step === 'questions' && (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground"><Loader2 className="h-4 w-4 animate-spin" /> Working out what I need to ask…</div>
                        )}
                        {!busy && questions.length > 0 && (
                            <>
                                <p className="mb-2 text-xs text-muted-foreground">Answer what you can — anything you leave blank, I'll mark <code>[NEEDS: …]</code> instead of guessing.</p>
                                <div className="space-y-3">
                                    {questions.map((q, i) => (
                                        <div key={i}>
                                            <label className="mb-1 block text-sm font-medium text-foreground">{q}</label>
                                            <textarea rows={2} className="input w-full" value={answers[i] ?? ''} onChange={e => setAnswers(a => ({ ...a, [i]: e.target.value }))} />
                                        </div>
                                    ))}
                                </div>
                                <div className="mt-3 flex gap-2">
                                    <Button size="sm" onClick={() => draftNow(active, questions)} disabled={busy}>Draft section</Button>
                                    <Button size="sm" variant="secondary" onClick={reset}>Cancel</Button>
                                </div>
                            </>
                        )}
                        {step === 'drafting' && (
                            <div className="mt-2 flex items-center gap-2 text-sm text-muted-foreground"><Loader2 className="h-4 w-4 animate-spin" /> Drafting {active.label}…</div>
                        )}
                    </div>
                )}

                {step === 'draft' && active && (
                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <button type="button" onClick={reset} className="text-muted-foreground hover:text-foreground" title="Back"><ArrowLeft className="h-4 w-4" /></button>
                                <span className="text-sm font-semibold text-foreground">{active.label}</span>
                            </div>
                            <div className="flex items-center gap-1">
                                <Button size="sm" variant="ghost" icon={copied ? Check : Copy} onClick={copy}>{copied ? 'Copied' : 'Copy'}</Button>
                                <Button size="sm" icon={Save} onClick={save} disabled={busy}>Save to proposal</Button>
                            </div>
                        </div>
                        <textarea value={text} onChange={e => setText(e.target.value)} rows={14} className="input w-full font-mono text-sm leading-relaxed" />
                    </div>
                )}

                {error && <p className="mt-3 text-sm text-destructive">{error}</p>}

                {/* Saved sections + export */}
                {saved.length > 0 && (
                    <div className="mt-5 border-t border-border pt-4">
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-xs font-bold uppercase tracking-wide text-muted-foreground">Proposal document ({saved.length})</p>
                            <div className="flex items-center gap-1.5">
                                <a href={`/proposals/${proposalId}/export/docx`} className="inline-flex items-center gap-1 rounded-lg border border-border px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-secondary"><FileType className="h-3.5 w-3.5" /> Word</a>
                                <a href={`/proposals/${proposalId}/export/pdf`} className="inline-flex items-center gap-1 rounded-lg border border-border px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-secondary"><FileText className="h-3.5 w-3.5" /> PDF</a>
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            {[...saved].sort((a, b) => sections.findIndex(s => s.value === a.section_key) - sections.findIndex(s => s.value === b.section_key)).map(s => (
                                <div key={s.id} className="flex items-start gap-2.5 rounded-xl border border-border px-3 py-2">
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-foreground">{s.heading}</p>
                                        <p className="truncate text-xs text-muted-foreground">{s.content.replace(/\s+/g, ' ').slice(0, 120)}</p>
                                    </div>
                                    <button type="button" onClick={() => remove(s)} className="shrink-0 text-muted-foreground hover:text-destructive" title="Remove"><Trash2 className="h-3.5 w-3.5" /></button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
