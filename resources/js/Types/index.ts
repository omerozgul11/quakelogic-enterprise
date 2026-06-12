export interface UserPreferences {
    display: { theme: 'system' | 'light' | 'dark'; density: 'comfortable' | 'compact' };
    dashboard: { default_view: 'personal' | 'executive' };
    channels: { new_proposal: boolean; new_opportunity: boolean; desktop: boolean; sound: boolean };
}

export interface User {
    id: number;
    name: string;
    email: string;
    title?: string;
    avatar_url?: string;
    organization_id: number;
    roles: string[];
    permissions: string[];
    preferences?: UserPreferences;
}

export interface Organization {
    id: number;
    name: string;
    slug: string;
}

export interface Opportunity {
    id: number;
    ulid: string;
    title: string;
    solicitation_number?: string;
    source: string;
    external_id?: string;
    status: string;
    agency_name?: string;
    naics_code?: string;
    estimated_value?: number;
    probability_of_win?: number;
    due_date?: string;
    posted_date?: string;
    description?: string;
    notes?: string;
    go_no_go_decision?: string;
    assigned_to?: User;
    owner?: User;
    agency?: Agency;
    created_at: string;
    updated_at: string;
}

export interface ProposalSubmission {
    id: number;
    ulid: string;
    proposal_number: string;
    project_name: string;
    solicitation_number?: string;
    status: string;
    proposal_value?: number;
    award_value?: number;
    currency?: string;
    due_date?: string;
    submission_date?: string;
    award_date?: string;
    loss_reason?: string;
    owner?: User;
    proposal_manager?: User;
    agency?: Agency;
    company?: Company;
    created_at: string;
    updated_at: string;
}

export interface Agency {
    id: number;
    name: string;
    acronym?: string;
    agency_type?: string;
    email?: string;
    phone?: string;
    website?: string;
    city?: string;
    state?: string;
}

export interface Company {
    id: number;
    name: string;
    company_type?: string;
    industry?: string;
    email?: string;
    phone?: string;
    website?: string;
    city?: string;
    state?: string;
}

export interface Contact {
    id: number;
    first_name: string;
    last_name: string;
    full_name?: string;
    title?: string;
    department?: string | null;
    email?: string;
    phone?: string;
    mobile?: string | null;
    linkedin_url?: string | null;
    notes?: string | null;
    is_decision_maker: boolean;
    is_key_contact: boolean;
    last_contact_date?: string | null;
    next_follow_up_date?: string | null;
    created_at?: string;
    agency?: Agency;
    company?: Company;
}

export interface Commission {
    id: number;
    ulid: string;
    type: string;
    base_amount: number;
    rate?: number;
    commission_amount: number;
    status: string;
    period_month?: string;
    user?: User;
    proposal?: ProposalSubmission;
    created_at: string;
}

export interface AiAnalysis {
    id: number;
    analysis_type: string;
    ai_provider: string;
    status: string;
    output?: Record<string, unknown>;
    human_decision?: string;
    created_at: string;
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

export interface FlashMessages {
    success?: string;
    error?: string;
    warning?: string;
    celebrate?: string | null;
}

export interface NotificationItem {
    id: string;
    type: string;
    title: string;
    message?: string | null;
    url?: string | null;
    icon?: string;
    read: boolean;
    created_at: string | null;
}

export interface AppLink {
    key: string;
    name: string;
    description: string;
    icon: string;
    url: string;
    current: boolean;
}

export interface SharedProps {
    auth: { user: User | null };
    flash: FlashMessages;
    app: { name: string; version: string; switcher: AppLink[] };
    notifications_count: number;
    notifications: NotificationItem[];
}

export type StatusColor = 'blue' | 'green' | 'red' | 'yellow' | 'gray' | 'purple' | 'indigo' | 'orange' | 'teal' | 'cyan' | 'amber' | 'slate' | 'emerald';
