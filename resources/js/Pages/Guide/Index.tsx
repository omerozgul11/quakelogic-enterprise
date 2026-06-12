import { Head } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { cn } from '@/Lib/utils';
import { BookOpen } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Section {
    id: string;
    title: string;
    body: React.ReactNode;
}

const P = ({ children }: { children: React.ReactNode }) => (
    <p className="mb-3 text-sm leading-relaxed text-muted-foreground">{children}</p>
);
const B = ({ children }: { children: React.ReactNode }) => (
    <strong className="font-semibold text-foreground">{children}</strong>
);
const UL = ({ items }: { items: React.ReactNode[] }) => (
    <ul className="mb-3 list-disc space-y-1.5 pl-5 text-sm leading-relaxed text-muted-foreground">
        {items.map((it, i) => <li key={i}>{it}</li>)}
    </ul>
);

const SECTIONS: Section[] = [
    {
        id: 'getting-started',
        title: 'Getting Started',
        body: (
            <>
                <P>QuakeLogic Proposals tracks the full life of a government bid: finding the opportunity, writing and submitting the proposal, following up, and recording the win.</P>
                <UL items={[
                    <>Use the <B>sidebar</B> to move between areas. What you see depends on your role — if a section is missing, you don't have permission for it.</>,
                    <>The <B>search bar</B> at the top finds proposals, opportunities, contacts, and companies from anywhere.</>,
                    <>Toggle <B>dark mode</B> with the sun/moon button in the header. The bell shows notifications.</>,
                    <>Your profile and password live under <B>Settings</B> (bottom of the sidebar).</>,
                ]} />
            </>
        ),
    },
    {
        id: 'dashboard',
        title: 'Dashboard',
        body: (
            <>
                <P>The dashboard is your daily snapshot. The stat bubbles mean:</P>
                <UL items={[
                    <><B>My Submissions / My Awards</B> — proposals you own that were submitted or won.</>,
                    <><B>Pipeline Value</B> — total value of your open (not yet decided) proposals.</>,
                    <><B>My / Total Submitted Value</B> — dollar value of submitted proposals: yours, and the whole company's.</>,
                    <><B>My / Company Earnings (YTD)</B> — value of contracts awarded this calendar year. This updates automatically the moment a proposal is dragged to <B>Awarded</B> on the kanban board.</>,
                    <><B>Follow-ups Due</B> — scheduled or overdue follow-ups assigned to you.</>,
                ]} />
                <P>Executives also get an <B>Executive View</B> with win rates, monthly trends, and top performers.</P>
            </>
        ),
    },
    {
        id: 'opportunities',
        title: 'Opportunities & Keywords',
        body: (
            <>
                <P>The Opportunities page is a live feed of open government solicitations, refreshed automatically from SAM.gov when you log in and every 5 minutes while the page is open. Opportunities whose response deadline has passed are removed automatically (unless you're actively pursuing them or already started a proposal).</P>
                <UL items={[
                    <><B>Personal keywords</B> — add your own keywords with the dashed "+ add keyword" chip. They're private to you; other users can't see them. Each keyword also steers the SAM.gov sync: the platform searches SAM's full text (titles <em>and</em> descriptions) for your keywords and imports any still-open matches.</>,
                    <>If a keyword shows nothing, there may genuinely be no <em>open</em> solicitations for it right now — SAM.gov's own site keeps showing notices for weeks after their deadlines pass; this platform hides them.</>,
                    <><B>Sorting</B> — click any column header (price, name, due date, agency…) to sort.</>,
                    <><B>Search</B> — the search box matches titles, descriptions, agencies, solicitation and NAICS numbers.</>,
                ]} />
            </>
        ),
    },
    {
        id: 'applications',
        title: 'Applications (Kanban Board)',
        body: (
            <>
                <P>The Applications board shows every proposal as a card in a stage column. Drag a card to change its status — invalid jumps (e.g. Draft straight to Awarded) are rejected.</P>
                <UL items={[
                    <>The stage flow is: <B>Draft → In Progress → Under Review → Submitted → Pending / Clarification / Negotiation → Awarded or Lost</B>, with <B>Completed</B> after Awarded for finished contracts.</>,
                    <>Dropping a card on <B>Submitted</B> stamps the submission date; dropping it on <B>Awarded</B> stamps the award date and value, which immediately feeds Earnings on the dashboard and reports.</>,
                    <>Use the <B>Kanban | List</B> toggle in the header to switch to a flat list with a status dropdown per row — handy on smaller screens.</>,
                ]} />
            </>
        ),
    },
    {
        id: 'proposals',
        title: 'Proposals & Documents',
        body: (
            <>
                <P>Create a proposal from the Proposals page or straight from an opportunity. Each proposal gets a unique number (QL-YYYY-NNNN) automatically.</P>
                <UL items={[
                    <><B>Upload the proposal document</B> (PDF/Word) and the platform extracts text, key details, and any contacts (names with an email or phone) it finds.</>,
                    <><B>Submission method</B> — mark how the proposal goes out: email, portal, or mail (multiple allowed).</>,
                    <><B>Preview</B> — click any file to preview it in the browser; Word documents are shown as extracted text.</>,
                    <>All files are private — downloads go through secure, signed links.</>,
                ]} />
            </>
        ),
    },
    {
        id: 'crm',
        title: 'Companies, Contacts & Follow-Ups',
        body: (
            <>
                <P>The Relationships section is a lightweight CRM.</P>
                <UL items={[
                    <><B>Contacts</B> — each contact has a profile card (like an online business card) with phone, email, company, and their follow-up history. Add, edit, or delete from the list or profile.</>,
                    <><B>Companies/Agencies</B> — the organizations you work with, with their contacts attached.</>,
                    <><B>Follow-Ups</B> — one follow-up per proposal (not per contact). Click one to see its details; mark it Sent or Responded from there. Uploading a proposal schedules a review follow-up automatically.</>,
                ]} />
            </>
        ),
    },
    {
        id: 'market-pricing',
        title: 'Market Pricing',
        body: (
            <>
                <P>Market Pricing pulls <B>past awarded contracts</B> from SAM.gov so you can see what similar projects actually went for and calibrate your own price. Search by keyword and/or NAICS code; the page shows the median, highest, and lowest award amounts plus each winning bidder.</P>
            </>
        ),
    },
    {
        id: 'reports',
        title: 'Reports',
        body: (
            <>
                <P>The Reports section has two views:</P>
                <UL items={[
                    <><B>Reports & Analytics</B> — monthly proposal activity, commissions, and the biggest open opportunities.</>,
                    <><B>Team Performance</B> — per-user proposals created/submitted/won, win rate, submitted value, earnings, and open pipeline, for this month, quarter, year, or all time. Use <B>Export CSV</B> to take the table into Excel.</>,
                ]} />
            </>
        ),
    },
    {
        id: 'admin',
        title: 'Admin & Activity Log',
        body: (
            <>
                <P>Super Admins get three extra tools:</P>
                <UL items={[
                    <><B>Admin</B> — create users, assign roles (including custom roles), deactivate accounts.</>,
                    <><B>Team Activity</B> — what each employee currently owns and has delivered.</>,
                    <><B>Activity Log</B> — who did what, by day/week/month/year, in counts and in dollars.</>,
                ]} />
            </>
        ),
    },
    {
        id: 'tips',
        title: 'Tips & FAQ',
        body: (
            <>
                <UL items={[
                    <><B>My keyword shows no results.</B> The platform only lists solicitations that are still open. Check Market Pricing to see past awards for that keyword instead.</>,
                    <><B>Earnings look wrong.</B> Earnings count contracts with an award date in the current calendar year. The award value defaults to the proposal value when a card is dragged to Awarded — edit the proposal to set the exact contract amount.</>,
                    <><B>A drag on the kanban bounced back.</B> That stage change isn't allowed from the current stage — move through the intermediate stage first.</>,
                    <><B>Need a fresh SAM.gov pull?</B> It happens automatically on login and every few minutes on the Opportunities page; adding a new keyword triggers an immediate targeted pull.</>,
                ]} />
            </>
        ),
    },
];

export default function GuideIndex() {
    const [active, setActive] = useState(SECTIONS[0].id);
    const refs = useRef<Record<string, HTMLElement | null>>({});

    useEffect(() => {
        const observer = new IntersectionObserver(
            entries => {
                const visible = entries.filter(e => e.isIntersecting);
                if (visible.length > 0) {
                    // Highlight the top-most visible section.
                    const top = visible.sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
                    setActive(top.target.id);
                }
            },
            { rootMargin: '-80px 0px -60% 0px', threshold: 0 },
        );
        Object.values(refs.current).forEach(el => el && observer.observe(el));
        return () => observer.disconnect();
    }, []);

    const jump = (id: string) => {
        setActive(id);
        refs.current[id]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    return (
        <AppLayout>
            <Head title="User Guide" />
            <div className="mx-auto max-w-6xl p-6">
                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <BookOpen className="h-5 w-5" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">User Guide</h1>
                        <p className="text-sm text-muted-foreground">How to use QuakeLogic Proposals, section by section.</p>
                    </div>
                </div>

                <div className="flex gap-8">
                    {/* Headings (left) */}
                    <nav className="hidden w-56 shrink-0 md:block">
                        <div className="sticky top-24 space-y-0.5">
                            {SECTIONS.map(s => (
                                <button
                                    key={s.id}
                                    onClick={() => jump(s.id)}
                                    className={cn(
                                        'block w-full rounded-lg border-l-2 px-3 py-2 text-left text-sm transition-colors',
                                        active === s.id
                                            ? 'border-primary bg-primary/[0.06] font-semibold text-primary'
                                            : 'border-transparent text-muted-foreground hover:bg-secondary hover:text-foreground',
                                    )}
                                >
                                    {s.title}
                                </button>
                            ))}
                        </div>
                    </nav>

                    {/* Body (middle) */}
                    <div className="min-w-0 flex-1 space-y-10 pb-16">
                        {SECTIONS.map(s => (
                            <section
                                key={s.id}
                                id={s.id}
                                ref={el => { refs.current[s.id] = el; }}
                                className="scroll-mt-24"
                            >
                                <h2 className="mb-3 border-b border-border pb-2 text-lg font-bold text-foreground">{s.title}</h2>
                                {s.body}
                            </section>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
