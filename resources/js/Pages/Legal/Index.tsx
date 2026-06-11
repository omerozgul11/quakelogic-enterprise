import { Head, Link } from '@inertiajs/react';
import { Logo } from '@/Components/ui/Logo';
import { ArrowLeft, ShieldCheck } from 'lucide-react';

const YEAR = new Date().getFullYear();

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="border-t border-border py-6 first:border-t-0 first:pt-0">
            <h2 className="mb-2 text-lg font-semibold text-foreground">{title}</h2>
            <div className="space-y-3 text-sm leading-relaxed text-muted-foreground">{children}</div>
        </section>
    );
}

export default function LegalIndex() {
    return (
        <>
            <Head title="Legal · Terms & Copyright" />
            <div className="min-h-screen bg-background">
                <header className="border-b border-border">
                    <div className="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
                        <Logo />
                        <Link href="/" className="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
                            <ArrowLeft className="h-4 w-4" /> Back to app
                        </Link>
                    </div>
                </header>

                <main className="mx-auto max-w-3xl px-6 py-10">
                    <div className="mb-8">
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                            <ShieldCheck className="h-3.5 w-3.5" /> Legal &amp; Copyright
                        </span>
                        <h1 className="mt-3 text-3xl font-extrabold tracking-tight text-foreground">QuakeLogic Enterprise</h1>
                        <p className="mt-1 text-sm text-muted-foreground">© {YEAR} QuakeLogic Inc. All rights reserved.</p>
                    </div>

                    <Section title="Copyright Notice">
                        <p>
                            QuakeLogic Enterprise, including its software, source code, design, user interface, text,
                            graphics, logos, and all related content (collectively, the “Platform”), is the exclusive
                            property of QuakeLogic Inc. and is protected by United States and international copyright,
                            trademark, trade secret, and other intellectual property laws.
                        </p>
                        <p>
                            No part of the Platform may be copied, reproduced, modified, distributed, republished,
                            reverse-engineered, or used to create derivative works without the prior written permission
                            of QuakeLogic Inc. “QuakeLogic” and the QuakeLogic logo are trademarks of QuakeLogic Inc.
                        </p>
                    </Section>

                    <Section title="Terms of Use">
                        <p>
                            Access to and use of the Platform is restricted to authorized users acting on behalf of a
                            licensed organization. By accessing the Platform you agree to use it only for its intended
                            business purpose and in compliance with all applicable laws and your organization’s policies.
                        </p>
                        <p>
                            You are responsible for safeguarding your account credentials and for all activity that
                            occurs under your account. You may not attempt to gain unauthorized access to any part of the
                            Platform, interfere with its operation, or use it to store or transmit unlawful, infringing,
                            or malicious content. Accounts are provisioned and managed by your administrator.
                        </p>
                        <p>
                            The Platform is provided “as is” without warranties of any kind. To the maximum extent
                            permitted by law, QuakeLogic Inc. shall not be liable for any indirect, incidental, or
                            consequential damages arising from your use of the Platform. QuakeLogic Inc. may suspend or
                            terminate access that violates these terms.
                        </p>
                    </Section>

                    <Section title="Legal Protection &amp; Confidentiality Notice">
                        <p>
                            This system and its data are confidential and proprietary. Information contained in the
                            Platform — including opportunities, proposals, pricing, contacts, and business records — is
                            confidential business information and may not be disclosed to any unauthorized party.
                        </p>
                        <p>
                            Unauthorized access, use, or disclosure is strictly prohibited and may violate the Computer
                            Fraud and Abuse Act (18 U.S.C. § 1030) and other federal and state laws. Activity on this
                            system may be monitored and recorded for security and compliance purposes. By continuing to
                            use the Platform you consent to such monitoring.
                        </p>
                    </Section>

                    <Section title="Contact">
                        <p>
                            Questions about these terms or QuakeLogic Enterprise can be directed to QuakeLogic Inc. at{' '}
                            <a href="https://quakelogic.net" target="_blank" rel="noreferrer" className="font-medium text-primary hover:underline">quakelogic.net</a>.
                        </p>
                    </Section>

                    <p className="mt-8 border-t border-border pt-6 text-xs text-muted-foreground">
                        QuakeLogic Enterprise — © {YEAR} QuakeLogic Inc. All rights reserved.
                    </p>
                </main>
            </div>
        </>
    );
}
