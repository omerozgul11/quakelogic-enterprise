import { Head, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { NumberInput } from '@/Components/ui/NumberInput';
import { PenLine, ArrowLeft } from 'lucide-react';

interface StyleFormat {
    font: string;
    heading_font: string;
    font_size: number | string;
    line_spacing: number | string;
    margin_inches: number | string;
    accent_color: string;
}

interface StyleProfile {
    tone: string;
    voice: string;
    company_background: string;
    win_themes: string;
    writing_rules: string;
    format: StyleFormat;
}

interface Props {
    style: StyleProfile;
}

export default function ProposalStylePage({ style }: Props) {
    const { data, setData, post, processing, recentlySuccessful } = useForm({
        tone: style.tone ?? '',
        voice: style.voice ?? '',
        company_background: style.company_background ?? '',
        win_themes: style.win_themes ?? '',
        writing_rules: style.writing_rules ?? '',
        format: {
            font: style.format?.font ?? 'Calibri',
            heading_font: style.format?.heading_font ?? 'Calibri',
            font_size: String(style.format?.font_size ?? 11),
            line_spacing: String(style.format?.line_spacing ?? 1.15),
            margin_inches: String(style.format?.margin_inches ?? 1),
            accent_color: style.format?.accent_color ?? '1F4E79',
        },
    });

    const setFmt = (key: keyof StyleFormat, value: string) => setData('format', { ...data.format, [key]: value });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/proposal-style', { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Proposal Style Profile" />
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <PageHeader
                    icon={PenLine}
                    title="Proposal Style Profile"
                    description="Teach the AI Proposal Writer how your proposals should read and look. It uses this — plus your past proposals — for every draft, and the export applies the formatting."
                    actions={<Button href="/settings" variant="secondary" icon={ArrowLeft}>Back to settings</Button>}
                />

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader><CardTitle>Voice &amp; tone</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="label">Tone</label>
                                <input className="input" value={data.tone} onChange={e => setData('tone', e.target.value)}
                                    placeholder="e.g. Formal, confident, technical but accessible" />
                            </div>
                            <div>
                                <label className="label">Voice / point of view</label>
                                <input className="input" value={data.voice} onChange={e => setData('voice', e.target.value)}
                                    placeholder="e.g. First person plural (we/our)" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Content the writer should know</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="label">Company background</label>
                                <textarea className="input" rows={4} value={data.company_background} onChange={e => setData('company_background', e.target.value)}
                                    placeholder="Who you are, core capabilities, certifications, differentiators. The writer draws on this so it never has to guess your company facts." />
                            </div>
                            <div>
                                <label className="label">Win themes</label>
                                <textarea className="input" rows={3} value={data.win_themes} onChange={e => setData('win_themes', e.target.value)}
                                    placeholder="Recurring themes to weave in (e.g. proven seismic-systems delivery, on-time installation, lifecycle support)." />
                            </div>
                            <div>
                                <label className="label">Writing rules</label>
                                <textarea className="input" rows={4} value={data.writing_rules} onChange={e => setData('writing_rules', e.target.value)}
                                    placeholder="Do's and don'ts, preferred terminology, things to always/never say, section conventions." />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Document formatting</CardTitle>
                            <p className="text-xs text-muted-foreground">Applied when you export a proposal to Word or PDF.</p>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                <div>
                                    <label className="label">Body font</label>
                                    <input className="input" value={data.format.font} onChange={e => setFmt('font', e.target.value)} placeholder="Calibri" />
                                </div>
                                <div>
                                    <label className="label">Heading font</label>
                                    <input className="input" value={data.format.heading_font} onChange={e => setFmt('heading_font', e.target.value)} placeholder="Calibri" />
                                </div>
                                <div>
                                    <label className="label">Body size (pt)</label>
                                    <NumberInput className="input" value={String(data.format.font_size)} onChange={e => setFmt('font_size', e.target.value)} />
                                </div>
                                <div>
                                    <label className="label">Line spacing</label>
                                    <NumberInput className="input" value={String(data.format.line_spacing)} onChange={e => setFmt('line_spacing', e.target.value)} />
                                </div>
                                <div>
                                    <label className="label">Margins (in)</label>
                                    <NumberInput className="input" value={String(data.format.margin_inches)} onChange={e => setFmt('margin_inches', e.target.value)} />
                                </div>
                                <div>
                                    <label className="label">Accent color (hex)</label>
                                    <div className="flex items-center gap-2">
                                        <span className="h-8 w-8 shrink-0 rounded-lg border border-border" style={{ backgroundColor: `#${(data.format.accent_color || '').replace('#', '')}` }} />
                                        <input className="input" value={data.format.accent_color} onChange={e => setFmt('accent_color', e.target.value.replace('#', ''))} placeholder="1F4E79" />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-end gap-3">
                        {recentlySuccessful && <span className="text-sm text-emerald-600">Saved.</span>}
                        <Button type="submit" disabled={processing}>{processing ? 'Saving…' : 'Save style profile'}</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
