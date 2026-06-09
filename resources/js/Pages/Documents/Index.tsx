import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { formatDate } from '@/Lib/utils';
import { FileText, FileSpreadsheet, FileImage, File, Download } from 'lucide-react';

interface ProposalFile {
    id: number;
    display_name: string;
    document_type: string | null;
    file_size_formatted: string;
    mime_type: string;
    version: number;
    created_at: string;
    uploaded_by_user: { id: number; name: string } | null;
    proposal: { id: number; proposal_number: string; project_name: string } | null;
}

interface Props {
    files: {
        data: ProposalFile[];
        total: number;
        current_page: number;
        last_page: number;
    };
}

const MIME_ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
    'application/pdf': FileText,
    'application/msword': FileText,
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': FileText,
    'application/vnd.ms-excel': FileSpreadsheet,
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': FileSpreadsheet,
    'application/vnd.ms-powerpoint': FileSpreadsheet,
    'image/png': FileImage,
    'image/jpeg': FileImage,
};

export default function DocumentsIndex({ files }: Props) {
    return (
        <AppLayout>
            <Head title="Documents" />
            <div className="p-6">
                <PageHeader
                    icon={FileText}
                    title="Documents"
                    description={`${files.total} ${files.total === 1 ? 'file' : 'files'} across all proposals`}
                />

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">File</th>
                                    <th className="th">Proposal</th>
                                    <th className="th">Type</th>
                                    <th className="th">Size</th>
                                    <th className="th">Uploaded</th>
                                    <th className="th">By</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {files.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7}>
                                            <EmptyState
                                                icon={FileText}
                                                title="No documents uploaded yet"
                                                description="Files attached to your proposals will appear here."
                                            />
                                        </td>
                                    </tr>
                                ) : files.data.map(file => {
                                    const FileIcon = MIME_ICONS[file.mime_type] ?? File;
                                    return (
                                        <tr key={file.id} className="row-link">
                                            <td className="td">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-border bg-secondary/60">
                                                        <FileIcon className="h-[18px] w-[18px] text-muted-foreground" />
                                                    </div>
                                                    <div className="min-w-0">
                                                        <span className="block max-w-xs truncate font-medium text-foreground">{file.display_name}</span>
                                                        {file.version > 1 && <span className="text-xs text-muted-foreground">v{file.version}</span>}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="td">
                                                {file.proposal ? (
                                                    <Link href={`/proposals/${file.proposal.id}`} className="font-mono text-sm text-primary hover:underline">
                                                        {file.proposal.proposal_number}
                                                    </Link>
                                                ) : '—'}
                                            </td>
                                            <td className="td">
                                                {file.document_type ? <span className="chip">{file.document_type}</span> : <span className="text-muted-foreground">—</span>}
                                            </td>
                                            <td className="td">
                                                <span className="chip">{file.file_size_formatted}</span>
                                            </td>
                                            <td className="td text-muted-foreground">{formatDate(file.created_at)}</td>
                                            <td className="td text-muted-foreground">{file.uploaded_by_user?.name ?? '—'}</td>
                                            <td className="td">
                                                {file.proposal && (
                                                    <a href={`/proposals/${file.proposal.id}/files/${file.id}/download`}
                                                        className="text-muted-foreground transition-colors hover:text-primary">
                                                        <Download className="h-4 w-4" />
                                                    </a>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
