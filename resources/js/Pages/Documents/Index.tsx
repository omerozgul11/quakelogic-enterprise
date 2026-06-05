import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { formatDate } from '@/Lib/utils';
import { FileText, Download, ExternalLink } from 'lucide-react';

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

const MIME_ICONS: Record<string, string> = {
    'application/pdf': '📄',
    'application/msword': '📝',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': '📝',
    'application/vnd.ms-excel': '📊',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': '📊',
    'application/vnd.ms-powerpoint': '📊',
    'image/png': '🖼️',
    'image/jpeg': '🖼️',
};

export default function DocumentsIndex({ files }: Props) {
    return (
        <AppLayout>
            <Head title="Documents" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Documents</h1>
                        <p className="text-gray-500 mt-1">{files.total} files across all proposals</p>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-200 bg-gray-50">
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">File</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Proposal</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Type</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Size</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Uploaded</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">By</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {files.data.length === 0 ? (
                                <tr><td colSpan={7} className="text-center py-12 text-gray-500">
                                    <FileText className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                                    <p>No documents uploaded yet.</p>
                                </td></tr>
                            ) : files.data.map(file => (
                                <tr key={file.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <span className="text-lg">{MIME_ICONS[file.mime_type] ?? '📎'}</span>
                                            <span className="text-sm font-medium text-gray-900 max-w-xs truncate">{file.display_name}</span>
                                            {file.version > 1 && <span className="text-xs text-gray-400">v{file.version}</span>}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {file.proposal ? (
                                            <Link href={`/proposals/${file.proposal.id}`} className="text-sm text-blue-600 hover:underline font-mono">
                                                {file.proposal.proposal_number}
                                            </Link>
                                        ) : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{file.document_type ?? '—'}</td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{file.file_size_formatted}</td>
                                    <td className="px-4 py-3 text-sm text-gray-500">{formatDate(file.created_at)}</td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{file.uploaded_by_user?.name ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        {file.proposal && (
                                            <a href={`/proposals/${file.proposal.id}/files/${file.id}/download`}
                                                className="text-gray-400 hover:text-blue-600">
                                                <Download className="h-4 w-4" />
                                            </a>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
