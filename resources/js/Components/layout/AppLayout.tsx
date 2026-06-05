import { useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { SharedProps } from '@/Types';
import {
    LayoutDashboard, Target, FileText, Shield, Building2,
    Users, Bell, ChevronDown, Menu, X, Search, LogOut,
    Settings, TrendingUp, Zap, FileSearch, MessageSquare,
    DollarSign, BarChart3, Puzzle, User, ChevronRight
} from 'lucide-react';
import { cn } from '@/Lib/utils';

interface NavItem {
    label: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    permission?: string;
    children?: NavItem[];
}

const navigation: NavItem[] = [
    { label: 'Dashboard', href: '/', icon: LayoutDashboard },
    { label: 'Opportunities', href: '/opportunities', icon: Target, permission: 'view opportunities' },
    { label: 'Capture', href: '/capture', icon: Shield, permission: 'view capture plans' },
    { label: 'Proposals', href: '/proposals', icon: FileText, permission: 'view proposals' },
    { label: 'Documents', href: '/documents', icon: FileSearch, permission: 'view proposals' },
    {
        label: 'CRM', href: '/agencies', icon: Building2, permission: 'view crm',
        children: [
            { label: 'Agencies', href: '/agencies', icon: Building2 },
            { label: 'Companies', href: '/companies', icon: Building2 },
            { label: 'Contacts', href: '/contacts', icon: Users },
        ]
    },
    { label: 'Follow-Ups', href: '/follow-ups', icon: MessageSquare, permission: 'view follow ups' },
    { label: 'Commissions', href: '/commissions', icon: DollarSign, permission: 'view own commissions' },
    { label: 'Reports', href: '/reports', icon: BarChart3, permission: 'view dashboards' },
    { label: 'AI Assistant', href: '/ai', icon: Zap, permission: 'use ai assistant' },
    { label: 'Integrations', href: '/integrations', icon: Puzzle, permission: 'manage integrations' },
];

export function AppLayout({ children }: { children: React.ReactNode }) {
    const { auth, flash, notifications_count } = usePage<SharedProps>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [expandedItems, setExpandedItems] = useState<string[]>([]);
    const user = auth.user;

    const hasPermission = (permission?: string) => {
        if (!permission) return true;
        return user?.permissions?.includes(permission) ?? false;
    };

    const toggleExpanded = (label: string) => {
        setExpandedItems(prev =>
            prev.includes(label) ? prev.filter(i => i !== label) : [...prev, label]
        );
    };

    const handleLogout = () => {
        router.post('/logout');
    };

    const NavItems = ({ mobile = false }) => (
        <nav className="space-y-1">
            {navigation.filter(item => hasPermission(item.permission)).map(item => {
                const Icon = item.icon;
                const isExpanded = expandedItems.includes(item.label);
                const hasChildren = item.children && item.children.length > 0;

                if (hasChildren) {
                    return (
                        <div key={item.label}>
                            <button
                                onClick={() => toggleExpanded(item.label)}
                                className="flex items-center justify-between w-full px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900"
                            >
                                <span className="flex items-center gap-3">
                                    <Icon className="h-5 w-5 text-gray-500" />
                                    {item.label}
                                </span>
                                <ChevronRight className={cn("h-4 w-4 transition-transform", isExpanded && "rotate-90")} />
                            </button>
                            {isExpanded && (
                                <div className="ml-8 mt-1 space-y-1">
                                    {item.children!.map(child => (
                                        <Link
                                            key={child.href}
                                            href={child.href}
                                            className="block px-3 py-2 text-sm text-gray-600 rounded-md hover:bg-gray-100 hover:text-gray-900"
                                            onClick={() => mobile && setSidebarOpen(false)}
                                        >
                                            {child.label}
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>
                    );
                }

                return (
                    <Link
                        key={item.href}
                        href={item.href}
                        className="flex items-center gap-3 px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900"
                        onClick={() => mobile && setSidebarOpen(false)}
                    >
                        <Icon className="h-5 w-5 text-gray-500" />
                        {item.label}
                    </Link>
                );
            })}

            {user?.roles?.includes('Super Admin') && (
                <Link
                    href="/admin"
                    className="flex items-center gap-3 px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900"
                >
                    <Settings className="h-5 w-5 text-gray-500" />
                    Admin
                </Link>
            )}
        </nav>
    );

    return (
        <div className="min-h-screen bg-gray-50 flex">
            {/* Mobile sidebar overlay */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 lg:hidden" onClick={() => setSidebarOpen(false)}>
                    <div className="fixed inset-0 bg-gray-600 bg-opacity-75" />
                </div>
            )}

            {/* Mobile sidebar */}
            <div className={cn(
                "fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-xl transform transition-transform duration-300 ease-in-out lg:hidden",
                sidebarOpen ? "translate-x-0" : "-translate-x-full"
            )}>
                <div className="flex items-center justify-between h-16 px-4 border-b border-gray-200">
                    <span className="text-xl font-bold text-blue-600">QuakeLogic</span>
                    <button onClick={() => setSidebarOpen(false)}>
                        <X className="h-6 w-6 text-gray-500" />
                    </button>
                </div>
                <div className="p-4 overflow-y-auto h-full pb-20">
                    <NavItems mobile />
                </div>
            </div>

            {/* Desktop sidebar */}
            <div className="hidden lg:flex lg:flex-shrink-0">
                <div className="w-64 flex flex-col bg-white border-r border-gray-200">
                    <div className="flex items-center h-16 px-4 border-b border-gray-200">
                        <Link href="/" className="flex items-center gap-2">
                            <div className="h-8 w-8 bg-blue-600 rounded-lg flex items-center justify-center">
                                <TrendingUp className="h-5 w-5 text-white" />
                            </div>
                            <span className="text-lg font-bold text-gray-900">QuakeLogic</span>
                        </Link>
                    </div>
                    <div className="flex-1 overflow-y-auto p-4">
                        <NavItems />
                    </div>
                    <div className="border-t border-gray-200 p-4">
                        <div className="flex items-center gap-3">
                            <div className="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                <User className="h-5 w-5 text-blue-600" />
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-gray-900 truncate">{user?.name}</p>
                                <p className="text-xs text-gray-500 truncate">{user?.roles?.[0] ?? 'User'}</p>
                            </div>
                            <button onClick={handleLogout} className="text-gray-400 hover:text-gray-600">
                                <LogOut className="h-5 w-5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Main content */}
            <div className="flex-1 flex flex-col min-w-0">
                {/* Topbar */}
                <div className="bg-white border-b border-gray-200 h-16 flex items-center px-4 gap-4">
                    <button className="lg:hidden" onClick={() => setSidebarOpen(true)}>
                        <Menu className="h-6 w-6 text-gray-500" />
                    </button>

                    <div className="flex-1 max-w-lg">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                type="search"
                                placeholder="Search opportunities, proposals..."
                                className="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-3 ml-auto">
                        <button className="relative p-2 text-gray-400 hover:text-gray-600">
                            <Bell className="h-5 w-5" />
                            {notifications_count > 0 && (
                                <span className="absolute top-1 right-1 h-4 w-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
                                    {notifications_count > 9 ? '9+' : notifications_count}
                                </span>
                            )}
                        </button>

                        <Link href="/settings" className="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900">
                            <div className="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                <User className="h-5 w-5 text-blue-600" />
                            </div>
                            <span className="hidden md:block font-medium">{user?.name}</span>
                        </Link>
                    </div>
                </div>

                {/* Flash messages */}
                {(flash?.success || flash?.error || flash?.warning) && (
                    <div className="px-6 pt-4">
                        {flash.success && (
                            <div className="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
                                {flash.success}
                            </div>
                        )}
                        {flash.error && (
                            <div className="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
                                {flash.error}
                            </div>
                        )}
                        {flash.warning && (
                            <div className="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg text-sm">
                                {flash.warning}
                            </div>
                        )}
                    </div>
                )}

                {/* Page content */}
                <main className="flex-1 overflow-auto">
                    {children}
                </main>
            </div>
        </div>
    );
}
