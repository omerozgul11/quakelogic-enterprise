import { Head, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { NotificationItem } from '@/Types';
import { Bell, FileText, Target, Trash2, CheckCheck, ShoppingCart } from 'lucide-react';

interface Paginated<T> {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
    notifications: Paginated<NotificationItem>;
    unreadCount: number;
}

const ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
    'file-text': FileText,
    target: Target,
    'shopping-cart': ShoppingCart,
    bell: Bell,
};

function timeAgo(iso: string | null): string {
    if (!iso) return '';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    const days = Math.floor(hrs / 24);
    return days < 7 ? `${days}d ago` : new Date(iso).toLocaleDateString();
}

export default function NotificationsIndex({ notifications, unreadCount }: Props) {
    const open = (n: NotificationItem) => {
        if (n.url) {
            router.post(`/notifications/${n.id}/read`, { follow: true }, { preserveScroll: true });
        } else if (!n.read) {
            router.post(`/notifications/${n.id}/read`, {}, { preserveScroll: true });
        }
    };

    const remove = (e: React.MouseEvent, id: string) => {
        e.stopPropagation();
        router.delete(`/notifications/${id}`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Notifications" />
            <div className="mx-auto max-w-3xl p-6">
                <PageHeader
                    icon={Bell}
                    title="Notifications"
                    description={unreadCount > 0 ? `${unreadCount} unread` : 'You\'re all caught up'}
                    actions={
                        unreadCount > 0 ? (
                            <Button icon={CheckCheck} variant="secondary" onClick={() => router.post('/notifications/read-all', {}, { preserveScroll: true })}>
                                Mark all read
                            </Button>
                        ) : undefined
                    }
                />

                <Card className="overflow-hidden">
                    {notifications.data.length === 0 ? (
                        <EmptyState icon={Bell} title="No notifications" description="Alerts about new proposals and opportunities will show up here." />
                    ) : (
                        <div className="divide-y divide-border">
                            {notifications.data.map(n => {
                                const Icon = ICONS[n.icon ?? 'bell'] ?? Bell;
                                return (
                                    <div
                                        key={n.id}
                                        onClick={() => open(n)}
                                        className={`group flex cursor-pointer items-start gap-4 px-5 py-4 transition-colors hover:bg-secondary/50 ${n.read ? '' : 'bg-primary/[0.04]'}`}
                                    >
                                        <div className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${n.read ? 'bg-secondary text-muted-foreground' : 'bg-primary/15 text-primary'}`}>
                                            <Icon className="h-[18px] w-[18px]" />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-semibold text-foreground">{n.title}</p>
                                            {n.message && <p className="mt-0.5 text-sm text-muted-foreground">{n.message}</p>}
                                            <p className="mt-1 text-xs text-muted-foreground/70">{timeAgo(n.created_at)}</p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {!n.read && <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" />}
                                            <button onClick={e => remove(e, n.id)} title="Delete" className="text-muted-foreground opacity-0 transition-opacity hover:text-destructive group-hover:opacity-100">
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                    <Pagination from={notifications.from} to={notifications.to} total={notifications.total} links={notifications.links} />
                </Card>
            </div>
        </AppLayout>
    );
}
