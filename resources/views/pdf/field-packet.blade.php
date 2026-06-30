@php
    /** @var \App\Models\Crm\Project $project */
    $navy = '#262261';
    $orange = '#F26522';
    $tri = fn ($v) => $v === null ? '—' : ($v ? 'Yes' : 'No');
    $d = fn ($v) => $v ? $v->format('M j, Y') : '—';
    $briefingHtml = function (?string $md) {
        if (! $md) return '';
        $out = '';
        foreach (preg_split('/\r?\n/', $md) as $line) {
            $t = trim($line);
            if ($t === '') { continue; }
            $t = e($t);
            $t = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $t);
            if (str_starts_with($t, '## ')) {
                $out .= '<div class="bh">'.substr($t, 3).'</div>';
            } elseif (str_starts_with($t, '# ')) {
                $out .= '<div class="bh">'.substr($t, 2).'</div>';
            } elseif (preg_match('/^[-*]\s+(.*)$/', $t, $m)) {
                $out .= '<div class="bl">• '.$m[1].'</div>';
            } else {
                $out .= '<div class="bp">'.$t.'</div>';
            }
        }
        return $out;
    };
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: Helvetica, Arial, sans-serif; }
    body { margin: 0; color: #1f2433; font-size: 11px; line-height: 1.45; }
    .bar { background: {{ $navy }}; color: #fff; padding: 18px 28px; }
    .bar .tag { color: {{ $orange }}; font-size: 11px; font-weight: bold; letter-spacing: 2px; }
    .bar h1 { margin: 4px 0 2px; font-size: 22px; }
    .bar .meta { font-size: 11px; color: #c9cce0; }
    .wrap { padding: 18px 28px; }
    h2 { color: {{ $navy }}; font-size: 13px; border-bottom: 2px solid {{ $orange }}; padding-bottom: 3px; margin: 18px 0 8px; }
    h3 { color: {{ $navy }}; font-size: 12px; margin: 12px 0 4px; }
    table { width: 100%; border-collapse: collapse; }
    td, th { text-align: left; vertical-align: top; padding: 4px 6px; }
    .facts td { border-bottom: 1px solid #eceef5; }
    .facts td.k { color: #6b7280; width: 28%; }
    .grid td { width: 50%; }
    .data { border: 1px solid #e5e7eb; }
    .data th { background: #f3f4f8; color: {{ $navy }}; font-size: 10px; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; }
    .data td { border-bottom: 1px solid #f0f1f6; font-size: 10.5px; }
    .pill { background: #eef0f8; color: {{ $navy }}; border-radius: 8px; padding: 1px 6px; font-size: 9px; font-weight: bold; }
    .warn { color: #b91c1c; font-weight: bold; }
    .muted { color: #6b7280; }
    .card { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; margin-bottom: 8px; }
    .sec { page-break-inside: avoid; }
    .bh { color: {{ $navy }}; font-weight: bold; font-size: 11.5px; margin: 8px 0 2px; }
    .bl { margin: 1px 0 1px 8px; }
    .bp { margin: 2px 0; }
    .briefing { background: #f9fafc; border: 1px solid #e5e7eb; border-left: 3px solid {{ $orange }}; border-radius: 4px; padding: 10px 14px; }
</style>
</head>
<body>
    <div class="bar">
        <div class="tag">QUAKELOGIC · FIELD PACKET</div>
        <h1>{{ $project->name }}</h1>
        <div class="meta">
            {{ $project->project_number }} · {{ $project->status->label() }}
            · {{ $project->company?->name ?? 'Internal' }}
            · Prepared {{ $generatedAt->format('M j, Y') }} by {{ $generatedBy }}
        </div>
    </div>

    <div class="wrap">
        <table class="facts">
            <tr><td class="k">Owner</td><td>{{ $project->owner?->name ?? '—' }}</td><td class="k">Project manager</td><td>{{ $project->projectManager?->name ?? '—' }}</td></tr>
            <tr><td class="k">Start</td><td>{{ $d($project->start_date) }}</td><td class="k">Due</td><td>{{ $d($project->due_date) }}</td></tr>
            @if($project->reference_numbers)<tr><td class="k">References</td><td colspan="3">{{ $project->reference_numbers }}</td></tr>@endif
        </table>

        @if($project->description)
            <h2>Scope</h2>
            <div>{{ $project->description }}</div>
        @endif

        @if($project->ai_briefing)
            <h2>Field Briefing</h2>
            <div class="briefing">{!! $briefingHtml($project->ai_briefing) !!}</div>
        @endif

        {{-- Sites & safety --}}
        @if($project->sites->isNotEmpty())
            <h2>Sites &amp; Safety</h2>
            @foreach($project->sites as $s)
                <div class="card sec">
                    <h3>{{ $s->name }} @if($s->is_primary)<span class="pill">PRIMARY</span>@endif</h3>
                    @if($s->address)<div class="muted">{{ $s->address }}</div>@endif
                    <table class="grid">
                        <tr>
                            <td>
                                <b>Access:</b> {{ $s->access_instructions ?: '—' }}<br>
                                <b>Hours:</b> {{ $s->working_hours ?: '—' }} · <b>Dock:</b> {{ $s->loading_dock ?: '—' }}<br>
                                <b>Parking:</b> {{ $s->parking ?: '—' }}<br>
                                <b>Badge:</b> {{ $tri($s->badge_required) }} · <b>Escort:</b> {{ $tri($s->escort_required) }} · <b>PPE:</b> {{ $s->ppe_required ?: '—' }}
                            </td>
                            <td>
                                <b>Forklift:</b> {{ $tri($s->forklift_available) }} · <b>Crane:</b> {{ $tri($s->crane_available) }} · <b>Power:</b> {{ $tri($s->power_available) }}<br>
                                <b>High voltage:</b> {{ $tri($s->high_voltage) }} · <b>Confined space:</b> {{ $tri($s->confined_space) }}<br>
                                <b>Hazards:</b> {{ $s->hazards ?: '—' }}<br>
                                <b>Hospital:</b> {{ trim($s->nearest_hospital.' '.$s->hospital_phone) ?: '—' }} · <b>Assembly:</b> {{ $s->emergency_assembly_point ?: '—' }}
                            </td>
                        </tr>
                    </table>
                </div>
            @endforeach
        @endif

        {{-- Contacts --}}
        @if($project->siteContacts->isNotEmpty())
            <h2>Key Contacts</h2>
            <table class="data">
                <tr><th>Role</th><th>Name</th><th>Company</th><th>Phone / mobile</th><th>Email</th></tr>
                @foreach($project->siteContacts as $c)
                    <tr>
                        <td>{{ $c->category->label() }}@if($c->is_emergency) <span class="warn">EMG</span>@endif</td>
                        <td>{{ $c->name }}@if($c->title)<br><span class="muted">{{ $c->title }}</span>@endif</td>
                        <td>{{ $c->company ?: '—' }}</td>
                        <td>{{ trim(($c->phone ?? '').' '.($c->mobile ?? '')) ?: '—' }}</td>
                        <td>{{ $c->email ?: '—' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        {{-- Equipment --}}
        @if($project->equipment->isNotEmpty())
            <h2>Equipment</h2>
            <table class="data">
                <tr><th>Item</th><th>Model / Serial</th><th>Weight</th><th>Install at</th><th>Calibration</th></tr>
                @foreach($project->equipment as $e)
                    <tr>
                        <td>{{ $e->name }}@if($e->quantity > 1) ×{{ $e->quantity }}@endif</td>
                        <td>{{ $e->model ?: '—' }}@if($e->serial_number)<br><span class="muted">S/N {{ $e->serial_number }}</span>@endif</td>
                        <td>{{ $e->weight ?: '—' }}</td>
                        <td>{{ $e->installation_location ?: '—' }}</td>
                        <td>{{ $e->calibration_status ?: '—' }}@if($e->calibration_due)<br><span class="muted">due {{ $e->calibration_due->format('M j, Y') }}</span>@endif</td>
                    </tr>
                @endforeach
            </table>
        @endif

        {{-- Shipments --}}
        @if($project->shipments->isNotEmpty())
            <h2>Shipments</h2>
            <table class="data">
                <tr><th>Carrier</th><th>Tracking</th><th>Status</th><th>Crate / ETA</th><th>Indicators</th></tr>
                @foreach($project->shipments as $s)
                    <tr>
                        <td>{{ $s->carrier?->label() ?? '—' }}</td>
                        <td>{{ $s->tracking_number ?: '—' }}</td>
                        <td>{{ $s->status?->label() ?? '—' }}</td>
                        <td>{{ $s->crate_number ?: '—' }}@if($s->expected_arrival)<br><span class="muted">ETA {{ $s->expected_arrival->format('M j') }}</span>@endif</td>
                        <td>
                            @php $sh = $s->shock_indicator; $ti = $s->tilt_indicator; @endphp
                            <span class="{{ $sh === 'tripped' ? 'warn' : '' }}">Shock: {{ $sh ?: '—' }}</span><br>
                            <span class="{{ $ti === 'tripped' ? 'warn' : '' }}">Tilt: {{ $ti ?: '—' }}</span>
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif

        {{-- Execution --}}
        @if($project->executionRecords->isNotEmpty())
            <h2>Execution</h2>
            <table class="data">
                <tr><th>Type</th><th>Title</th><th>Status</th><th>Scheduled</th><th>Engineer</th></tr>
                @foreach($project->executionRecords as $r)
                    <tr>
                        <td>{{ $r->type->label() }}</td>
                        <td>{{ $r->title }}</td>
                        <td>{{ $r->status->label() }}</td>
                        <td>{{ $d($r->scheduled_date) }}</td>
                        <td>{{ $r->performer?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        {{-- Checklists --}}
        @if($project->checklists->isNotEmpty())
            <h2>Checklists</h2>
            @foreach($project->checklists as $cl)
                <div class="card sec">
                    <h3>{{ $cl->title }} <span class="muted">({{ $cl->items->where('is_done', true)->count() }}/{{ $cl->items->count() }})</span></h3>
                    @foreach($cl->items as $it)
                        <div>{{ $it->is_done ? '☑' : '☐' }} {{ $it->text }}</div>
                    @endforeach
                </div>
            @endforeach
        @endif

        {{-- Sign-offs --}}
        @if($project->signoffs->isNotEmpty())
            <h2>Sign-offs</h2>
            @foreach($project->signoffs as $so)
                <div class="card sec">
                    <h3>{{ $so->type->label() }} — {{ $so->signer_name }}@if($so->signer_title) <span class="muted">{{ $so->signer_title }}</span>@endif</h3>
                    @if($so->statement)<div class="muted">&ldquo;{{ $so->statement }}&rdquo;</div>@endif
                    @if($so->signature_data)<img src="{{ $so->signature_data }}" style="height: 56px; border: 1px solid #e5e7eb; margin-top: 4px;" alt="signature">@endif
                    <div class="muted" style="margin-top: 4px;">Signed {{ $so->signed_at?->format('M j, Y g:i A') }}@if($so->executionRecord) · re: {{ $so->executionRecord->title }}@endif</div>
                </div>
            @endforeach
        @endif

        {{-- Travel --}}
        @if($project->travel->isNotEmpty())
            <h2>Travel</h2>
            <table class="data">
                <tr><th>Type</th><th>Detail</th><th>Traveler</th><th>When</th><th>Confirmation</th></tr>
                @foreach($project->travel as $t)
                    <tr>
                        <td>{{ $t->type->label() }}</td>
                        <td>{{ $t->title }}@if($t->from_location || $t->to_location)<br><span class="muted">{{ trim(($t->from_location ?? '').' → '.($t->to_location ?? ''), ' →') }}</span>@endif</td>
                        <td>{{ $t->traveler?->name ?? $t->traveler_name ?? '—' }}</td>
                        <td>{{ $t->start_at ? $t->start_at->format('M j, H:i') : '—' }}</td>
                        <td>{{ $t->confirmation_number ?: '—' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
</body>
</html>
