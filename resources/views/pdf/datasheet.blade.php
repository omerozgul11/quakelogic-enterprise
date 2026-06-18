<!DOCTYPE html>
@php
    use Illuminate\Support\Str;

    // QuakeLogic brand palette (matches the proposal export).
    $orange = '#F26522';
    $navy   = '#262261';
    $ink    = '#2D2D2D';
    $muted  = '#6B7280';
    $rule   = '#E5E7EB';

    $logoPath = public_path('quakelogic-logo.png');
    $fReg  = resource_path('fonts/Raleway-Regular.ttf');
    $fSemi = resource_path('fonts/Raleway-SemiBold.ttf');
    $fBold = resource_path('fonts/Raleway-Bold.ttf');

    $product   = $d->product_name ?: 'Product';
    $tagline   = $sections['tagline'] ?? $d->tagline;
    $overview  = trim((string) ($sections['overview'] ?? ''));
    $features  = array_values(array_filter((array) ($sections['key_features'] ?? [])));
    $specs     = array_values(array_filter((array) ($sections['specifications'] ?? []), fn ($s) => is_array($s) && trim((string)($s['label'] ?? '')) !== ''));
    $apps      = array_values(array_filter((array) ($sections['applications'] ?? [])));
    $dateStr   = ($d->generated_at ?? $d->created_at ?? now())->format('F j, Y');
    $year      = now()->year;

    // Numbered body sections, in order, skipping any that are empty.
    $blocks = [];
    if ($overview !== '')   { $blocks[] = 'overview'; }
    if (count($specs))      { $blocks[] = 'specs'; }
    if (count($features))   { $blocks[] = 'features'; }
    if (count($apps))       { $blocks[] = 'apps'; }
@endphp
<html>
<head>
<meta charset="utf-8">
<style>
    @font-face { font-family: 'Raleway'; font-weight: 400; src: url('{{ $fReg }}')  format('truetype'); }
    @font-face { font-family: 'Raleway'; font-weight: 600; src: url('{{ $fSemi }}') format('truetype'); }
    @font-face { font-family: 'Raleway'; font-weight: 700; src: url('{{ $fBold }}') format('truetype'); }

    @page { margin: 1.15in 0.85in 0.95in 0.85in; }
    body { font-family: Helvetica, 'DejaVu Sans', sans-serif; font-size: 10.5pt; line-height: 1.5; color: {{ $ink }}; }

    /* ---- Cover ---- */
    .cover { text-align: center; page-break-after: always; }
    .cover .logo { width: 320px; margin: 0.3in auto 0.16in; }
    .cover .kicker { color: {{ $muted }}; font-size: 9pt; letter-spacing: 2.5px; text-transform: uppercase; margin-bottom: 0.45in; }
    .cover .title { font-family: 'Raleway'; font-weight: 700; color: {{ $navy }}; font-size: 22pt; line-height: 1.25; margin: 0 0.2in 6px; }
    .cover .model { color: {{ $orange }}; font-family: 'Raleway'; font-weight: 600; font-size: 12pt; margin-bottom: 10px; }
    .cover .tagline { color: {{ $orange }}; font-family: 'Raleway'; font-weight: 600; font-size: 13pt; margin: 4px 0 14px; }
    .cover .hero { margin: 10px auto 0; max-height: 320px; max-width: 78%; }
    .cover .band { margin-top: 0.55in; height: 64px; background: {{ $navy }}; position: relative; overflow: hidden; }
    .cover .band .stripe { position: absolute; top: 0; left: 0; width: 38%; height: 64px; background: {{ $orange }}; }
    .cover .ver { margin-top: 8px; text-align: right; color: {{ $muted }}; font-size: 8pt; }

    /* ---- Sections ---- */
    h2.section { font-family: 'Raleway'; font-weight: 700; color: {{ $navy }}; font-size: 15.5pt;
        text-transform: uppercase; letter-spacing: 0.3px; margin: 24px 0 8px; padding-bottom: 5px; border-bottom: 2px solid {{ $orange }}; }
    h2.section .num { color: {{ $orange }}; }
    p { margin: 0 0 9px; text-align: justify; }
    ul { margin: 0 0 9px; padding-left: 16px; }
    li { margin: 0 0 5px; }
    table { width: 100%; border-collapse: collapse; margin: 0 0 12px; font-size: 10pt; }
    th { background: {{ $navy }}; color: #fff; text-align: left; padding: 6px 9px; font-family: 'Raleway'; font-weight: 600; }
    td { padding: 6px 9px; border-bottom: 1px solid {{ $rule }}; vertical-align: top; }
    td.spec-label { font-weight: 700; color: {{ $navy }}; width: 38%; }
    tr:nth-child(even) td { background: #F5F5F7; }
</style>
</head>
<body>

{{-- ============ COVER ============ --}}
<div class="cover">
    <img class="logo" src="{{ $logoPath }}" alt="QuakeLogic">
    <div class="kicker">Product Datasheet</div>
    <div class="title">{{ strtoupper($product) }}</div>
    @if($d->model_number)<div class="model">{{ $d->model_number }}</div>@endif
    @if($tagline)<div class="tagline">{{ $tagline }}</div>@endif
    @if($heroImage)<img class="hero" src="{{ $heroImage }}" alt="{{ $product }}">@endif
    <div class="band"><div class="stripe"></div></div>
    <div class="ver">{{ strtoupper($dateStr) }} &nbsp;|&nbsp; {{ $d->ulid }}</div>
</div>

{{-- ============ BODY ============ --}}
@foreach($blocks as $i => $block)
    @if($block === 'overview')
        <h2 class="section"><span class="num">{{ $i + 1 }}</span>&nbsp;&nbsp;OVERVIEW</h2>
        @foreach(preg_split('/\n{2,}/', $overview) as $para)
            @if(trim($para) !== '')<p>{{ trim($para) }}</p>@endif
        @endforeach
    @elseif($block === 'specs')
        <h2 class="section"><span class="num">{{ $i + 1 }}</span>&nbsp;&nbsp;TECHNICAL SPECIFICATIONS</h2>
        <table>
            <thead><tr><th style="width:38%">Specification</th><th>Value</th></tr></thead>
            <tbody>
            @foreach($specs as $s)
                <tr><td class="spec-label">{{ $s['label'] }}</td><td>{{ $s['value'] ?? '' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @elseif($block === 'features')
        <h2 class="section"><span class="num">{{ $i + 1 }}</span>&nbsp;&nbsp;KEY FEATURES</h2>
        <ul>@foreach($features as $f)<li>{{ $f }}</li>@endforeach</ul>
    @elseif($block === 'apps')
        <h2 class="section"><span class="num">{{ $i + 1 }}</span>&nbsp;&nbsp;APPLICATIONS</h2>
        <ul>@foreach($apps as $a)<li>{{ $a }}</li>@endforeach</ul>
    @endif
@endforeach

</body>
</html>
