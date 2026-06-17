<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\ProposalTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Phase 7 — seeds a starter proposal template library per organization. Content
 * is tailored to QuakeLogic's seismic / monitoring product domain and uses
 * [bracketed] placeholders writers fill in per proposal. Idempotent.
 */
class ProposalTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Organization::all() as $org) {
            $author = User::where('organization_id', $org->id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))
                ->value('id');

            foreach ($this->templates() as $tpl) {
                ProposalTemplate::firstOrCreate(
                    ['organization_id' => $org->id, 'category' => $tpl['category'], 'title' => $tpl['title']],
                    ['created_by' => $author, 'content' => trim($tpl['content']), 'is_active' => true],
                );
            }
        }
    }

    /** @return array<int,array{category:string,title:string,content:string}> */
    private function templates(): array
    {
        return [
            [
                'category' => 'company_profile',
                'title' => 'QuakeLogic Company Profile',
                'content' => <<<'TXT'
QuakeLogic is a provider of earthquake early warning (EEW), structural health monitoring (SHM), seismic instrumentation, infrasound, and environmental monitoring systems for government, research, utility, transportation, and nuclear clients worldwide.

We design, manufacture, integrate, install, and support turnkey monitoring networks — from single-station deployments to nationwide early-warning systems.

Core Capabilities
- Earthquake Early Warning (EEW) systems and alerting
- Structural Health Monitoring (SHM) for buildings, bridges, and dams
- Strong-motion and broadband seismic networks
- Nuclear facility seismic monitoring (SMS) and automatic shutdown systems
- Shake table and laboratory instrumentation
- Infrasound and environmental monitoring
- Real-time data acquisition, telemetry, and analytics software

Why QuakeLogic
- Proven hardware, including the Palert and PX product families
- End-to-end delivery: design, supply, installation, commissioning, training, and support
- Standards-aligned engineering with documented quality control
- Responsive U.S.-based technical support

Registrations & Compliance
- SAM.gov registered (CAGE: [CAGE], UEI: [UEI])
- [ISO 9001 / additional certifications]
- Insurance and bonding available upon request
TXT,
            ],
            [
                'category' => 'technical_narrative',
                'title' => 'Technical Approach — Seismic Monitoring System',
                'content' => <<<'TXT'
Technical Approach — [Project Name] ([Solicitation #])

1. Understanding of Requirements
QuakeLogic understands that [Agency] requires [summarize scope]. Our solution is engineered to meet or exceed every requirement in the solicitation, with full traceability captured in our compliance matrix.

2. Proposed System Architecture
- Field layer: [sensors/instruments, e.g. Palert F330 accelerographs] sited per [standard].
- Acquisition & telemetry: [digitizers/dataloggers] with redundant communications via [cellular/fiber/satellite].
- Processing & alerting: real-time processing with configurable thresholds and multi-channel notification.
- Data management: secure storage, visualization dashboards, and open data formats ([miniSEED]).

3. Standards & Interoperability
The system aligns with [applicable standards] and integrates with existing [Agency] infrastructure via [protocols/APIs].

4. Schedule & Milestones
Design → Procurement → Factory Acceptance Test (FAT) → Installation → Site Acceptance Test (SAT) → Training → Operations & Support.

5. Risk Management
Key risks ([site access, communications, schedule]) are mitigated through [approach].
TXT,
            ],
            [
                'category' => 'qa_qc',
                'title' => 'Quality Assurance / Quality Control Statement',
                'content' => <<<'TXT'
Quality Assurance / Quality Control Statement

QuakeLogic operates a documented quality management program [aligned with ISO 9001] governing the design, manufacture, integration, installation, and support of all delivered systems.

Inspection & Test
- Incoming inspection of all components against specification.
- In-process inspection at defined hold points.
- Factory Acceptance Test (FAT): full functional verification before shipment, witnessed by [Agency] on request.
- Site Acceptance Test (SAT): on-site verification of installed performance against acceptance criteria.

Calibration & Traceability
All sensors are calibrated with traceability to [NIST / national standards]; calibration certificates are delivered with each unit.

Documentation
Each project includes an Inspection & Test Plan (ITP), test records, as-built documentation, and a final QA/QC report.

Nonconformance
Nonconformances are logged, dispositioned, and corrected under a formal corrective-action process, with [Agency] notification where required.
TXT,
            ],
            [
                'category' => 'warranty',
                'title' => 'Standard Warranty Statement',
                'content' => <<<'TXT'
Standard Warranty Statement

QuakeLogic warrants all supplied hardware to be free from defects in materials and workmanship for [12 / 24] months from the date of [Site Acceptance / delivery].

Coverage
- Repair or replacement of defective hardware at no charge.
- Software updates and fixes for the warranted version for [period].
- Technical support during the warranty period per the Support Plan.

Service Process
Warranty claims are initiated via RMA. QuakeLogic will [advance-ship / repair-and-return] within [X business days] of approval.

Exclusions
The warranty does not cover damage from misuse, unauthorized modification, accident, lightning/surge beyond rated protection, or acts of nature. Consumables and third-party items carry their original manufacturer's warranty.

Extended Warranty
Multi-year extended warranty and full-service maintenance agreements are available — see the Support Plan.
TXT,
            ],
            [
                'category' => 'training_plan',
                'title' => 'Training Plan',
                'content' => <<<'TXT'
Training Plan

QuakeLogic will train [Agency] personnel to operate and maintain the delivered system.

Courses
1. Operator Training — system overview, dashboards, alerts, daily operations, reporting.
2. Maintenance Training — preventive maintenance, diagnostics, field replacement, calibration checks.
3. Administrator Training — configuration, user management, integrations, data export.

Delivery
- On-site, instructor-led at [location], and/or live remote sessions.
- Hands-on exercises on the installed system.
- Class size up to [N] per session; sessions recorded on request.

Materials
Each trainee receives training manuals, quick-reference guides, and access to QuakeLogic's online knowledge base. A train-the-trainer option is available.

Schedule
Training is delivered following SAT and prior to handover, on a schedule coordinated with [Agency].
TXT,
            ],
            [
                'category' => 'installation_plan',
                'title' => 'Installation & Commissioning Plan',
                'content' => <<<'TXT'
Installation & Commissioning Plan

1. Site Survey
QuakeLogic conducts a pre-installation survey of each site to confirm siting, power, communications, mounting, and access, documenting site-specific requirements.

2. Installation
- Sensor/instrument mounting per manufacturer and [standard] guidance (vault, pier, or structure as applicable).
- Power and surge protection; backup power ([UPS / solar]) where specified.
- Communications provisioning and security hardening.
- Cable management, labeling, and weatherproofing.

3. Commissioning
- Power-on and connectivity verification.
- Sensor orientation, leveling, and noise checks.
- End-to-end data flow and alert verification.

4. Acceptance
Site Acceptance Test (SAT) executed against agreed criteria; punch-list items resolved before sign-off.

5. Safety & Site Rules
All work performed in compliance with [Agency] site safety requirements and applicable regulations.
TXT,
            ],
            [
                'category' => 'support_plan',
                'title' => 'Technical Support & Maintenance Plan',
                'content' => <<<'TXT'
Technical Support & Maintenance Plan

Support Tiers
- Standard: business-hours email and phone support, software updates.
- Premium: extended-hours support, priority response, remote monitoring, annual preventive maintenance visit.

Service Levels (target)
- Critical (system down): response within [4] business hours.
- Major (degraded): response within [1] business day.
- Minor / how-to: response within [2] business days.

Preventive Maintenance
Scheduled inspections, calibration checks, firmware/software updates, and health reporting [annually / semi-annually].

Remote Monitoring
QuakeLogic can proactively monitor system health and alert on faults before they affect operations.

Spares & Logistics
Recommended spare-parts kits and optional on-site spares to minimize downtime. RMA turnaround per the Warranty Statement.

Renewals
Support agreements are offered in [1 / 3 / 5]-year terms with predictable pricing.
TXT,
            ],
            [
                'category' => 'other',
                'title' => 'Past Performance Summary',
                'content' => <<<'TXT'
Past Performance Summary

[Project / Contract Name] — [Client / Agency]
- Period of Performance: [start] – [end]
- Contract Value: [$amount]
- Scope: [systems delivered, e.g. EEW network of N stations; SHM for X structures].
- Role: Prime / Subcontractor
- Outcome: [delivered on time and budget; performance metrics; client satisfaction].
- Reference: [name, title, phone, email]

[Repeat per relevant project. Prioritize projects similar in scope, size, and agency type to the current opportunity.]
TXT,
            ],
        ];
    }
}
