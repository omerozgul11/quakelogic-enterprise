<?php

namespace App\Services\Crm;

use App\Models\Crm\Project;
use App\Services\Ai\AiProviderInterface;
use Illuminate\Support\Carbon;

/**
 * Builds a pre-departure "field briefing" for a project by assembling its field
 * data (site, safety, equipment, shipments, execution, travel, contacts) into a
 * structured snapshot and asking the configured AI provider to summarise it.
 *
 * Provider-agnostic: uses the bound AiProviderInterface, which degrades to the
 * Fake provider when no live model is configured (so this never hard-fails).
 */
class FieldBriefingService
{
    public function __construct(private AiProviderInterface $ai) {}

    public function generate(Project $project): string
    {
        $project->loadMissing([
            'company:id,name', 'owner:id,name', 'projectManager:id,name',
            'sites', 'siteContacts', 'equipment', 'shipments',
            'executionRecords.performer:id,name', 'checklists.items', 'travel.traveler:id,name',
        ]);

        $system = "You are an operations coordinator at QuakeLogic preparing a field engineer for a customer-site "
            ."equipment installation. Using ONLY the facts provided, write a concise, practical PRE-DEPARTURE FIELD "
            ."BRIEFING in Markdown. Use these sections (skip one only if it has no data): ## Summary, ## Site & Access, "
            ."## Safety, ## Equipment, ## Logistics & Shipments, ## Travel, ## Key Contacts, ## Open Issues & Risks, "
            ."## Action Items. Prefer short bullet points. Where important data is missing, flag it as "
            ."'Not captured — confirm before travel.' Never invent facts.";

        return trim($this->ai->complete($system, "Project field data:\n\n".$this->buildContext($project)));
    }

    private function buildContext(Project $p): string
    {
        $lines = [];
        $lines[] = "PROJECT: {$p->name}".($p->project_number ? " ({$p->project_number})" : '');
        $lines[] = 'Status: '.$p->status->label();
        $lines[] = 'Client: '.($p->company?->name ?? 'Internal');
        $lines[] = 'Owner: '.($p->owner?->name ?? '—').' · Project manager: '.($p->projectManager?->name ?? '—');
        $lines[] = 'Start: '.($p->start_date?->toDateString() ?? '—').' · Due: '.($p->due_date?->toDateString() ?? '—');
        if ($p->description) {
            $lines[] = "Scope: {$p->description}";
        }
        if ($p->specs) {
            $lines[] = "Specifications: {$p->specs}";
        }

        foreach ($p->sites as $s) {
            $lines[] = "\nSITE: {$s->name}".($s->is_primary ? ' [PRIMARY]' : '');
            $this->kv($lines, 'Address', $s->address);
            $this->kv($lines, 'Access', $s->access_instructions);
            $this->kv($lines, 'Working hours', $s->working_hours);
            $this->kv($lines, 'Loading dock', $s->loading_dock);
            $this->kv($lines, 'Parking', $s->parking);
            $this->tri($lines, 'Badge required', $s->badge_required);
            $this->tri($lines, 'Escort required', $s->escort_required);
            $this->kv($lines, 'PPE required', $s->ppe_required);
            $this->tri($lines, 'Forklift on site', $s->forklift_available);
            $this->tri($lines, 'Crane on site', $s->crane_available);
            $this->tri($lines, 'Power available', $s->power_available);
            $this->kv($lines, 'Environmental', $s->environmental_conditions);
            $this->kv($lines, 'Hazards', $s->hazards);
            $this->tri($lines, 'High voltage', $s->high_voltage);
            $this->tri($lines, 'Confined space', $s->confined_space);
            $this->kv($lines, 'Lockout/tagout', $s->lockout_tagout);
            $this->kv($lines, 'Chemical hazards', $s->chemical_hazards);
            $this->kv($lines, 'Nearest hospital', trim($s->nearest_hospital.' '.$s->hospital_phone));
            $this->kv($lines, 'Emergency assembly', $s->emergency_assembly_point);
        }

        foreach ($p->siteContacts as $c) {
            $lines[] = 'CONTACT'.($c->is_emergency ? ' [EMERGENCY]' : '').": {$c->category->label()} — {$c->name}"
                .($c->title ? ", {$c->title}" : '').' · '.trim(($c->phone ?? '').' '.($c->mobile ?? '').' '.($c->email ?? ''));
        }

        foreach ($p->equipment as $e) {
            $lines[] = "EQUIPMENT: {$e->name}".($e->quantity > 1 ? " ×{$e->quantity}" : '')
                .($e->model ? " · model {$e->model}" : '').($e->serial_number ? " · S/N {$e->serial_number}" : '')
                .($e->weight ? " · {$e->weight}" : '').($e->installation_location ? " · install at {$e->installation_location}" : '');
            $this->kv($lines, '  Rigging', $e->rigging_instructions);
            $this->kv($lines, '  Calibration', trim(($e->calibration_status ?? '').($e->calibration_due ? ' due '.$e->calibration_due->toDateString() : '')));
        }

        foreach ($p->shipments as $s) {
            $carrier = $s->carrier?->label() ?? 'carrier n/a';
            $lines[] = "SHIPMENT: {$carrier}".($s->tracking_number ? " {$s->tracking_number}" : '')
                .' · '.($s->status?->label() ?? '').($s->crate_number ? " · crate {$s->crate_number}" : '')
                .($s->expected_arrival ? ' · ETA '.$s->expected_arrival->toDateString() : '');
            if ($s->shock_indicator === 'tripped' || $s->tilt_indicator === 'tripped') {
                $lines[] = '  WARNING: damage indicator tripped (shock='.($s->shock_indicator ?? 'n/a').', tilt='.($s->tilt_indicator ?? 'n/a').')';
            }
        }

        foreach ($p->executionRecords as $r) {
            $lines[] = "EXECUTION: {$r->type->label()} — {$r->title} · {$r->status->label()}"
                .($r->scheduled_date ? ' · scheduled '.$r->scheduled_date->toDateString() : '')
                .($r->performer ? ' · '.$r->performer->name : '');
            $this->kv($lines, '  Outcome', $r->outcome);
        }

        foreach ($p->checklists as $cl) {
            $done = $cl->items->where('is_done', true)->count();
            $lines[] = "CHECKLIST: {$cl->title} ({$done}/{$cl->items->count()} done)";
            foreach ($cl->items as $it) {
                $lines[] = '  ['.($it->is_done ? 'x' : ' ')."] {$it->text}";
            }
        }

        foreach ($p->travel as $t) {
            $lines[] = "TRAVEL: {$t->type->label()} — {$t->title}"
                .($t->traveler?->name ? ' · '.$t->traveler->name : ($t->traveler_name ? ' · '.$t->traveler_name : ''))
                .($t->start_at ? ' · '.$t->start_at->format('M j, H:i') : '')
                .($t->status ? ' · '.$t->status : '');
        }

        return implode("\n", $lines);
    }

    /** @param array<int,string> $lines */
    private function kv(array &$lines, string $label, ?string $value): void
    {
        $value = trim((string) $value);
        if ($value !== '') {
            $lines[] = "{$label}: {$value}";
        }
    }

    /** @param array<int,string> $lines */
    private function tri(array &$lines, string $label, ?bool $value): void
    {
        if ($value !== null) {
            $lines[] = "{$label}: ".($value ? 'Yes' : 'No');
        }
    }
}
