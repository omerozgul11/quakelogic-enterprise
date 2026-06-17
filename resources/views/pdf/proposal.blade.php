<!DOCTYPE html>
@php
    use Illuminate\Support\Str;

    // QuakeLogic brand palette (sampled from the reference proposal).
    $orange = '#F26522';
    $navy   = '#262261';
    $ink    = '#2D2D2D';
    $muted  = '#6B7280';
    $rule   = '#E5E7EB';

    $fmt    = $style['format'] ?? [];
    $size   = (float) ($fmt['font_size'] ?? 10.5);
    $line   = (float) ($fmt['line_spacing'] ?? 1.5);

    $logoPath = public_path('quakelogic-logo.png');
    $fReg  = resource_path('fonts/Raleway-Regular.ttf');
    $fSemi = resource_path('fonts/Raleway-SemiBold.ttf');
    $fBold = resource_path('fonts/Raleway-Bold.ttf');

    $client      = $client ?? ($proposal->company?->name ?? $proposal->agency?->name);
    $project     = $proposal->project_name ?: 'Proposal';
    $coverTitle  = 'PROPOSAL FOR ' . strtoupper($project)
        . ($proposal->solicitation_number ? ' IN RESPONSE TO ' . strtoupper($proposal->solicitation_number) : '');
    $headerTitle = 'PROPOSAL - ' . strtoupper(Str::limit($project, 34, ''));
    $dateStr     = ($proposal->submission_date ?? $proposal->created_at ?? now())->format('F j, Y');
    $year        = now()->year;

    // Inline markdown: **bold**, and highlight [NEEDS: …] placeholders.
    $inline = function (string $s): string {
        $s = e($s);
        $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
        $s = preg_replace('/\[NEEDS:[^\]]*\]/', '<span class="needs">$0</span>', $s);
        return $s;
    };

    // Render a section's body to HTML: sub-headings (### or a stand-alone
    // **Heading** line), bullet lists, and paragraphs — line based so headings
    // never leak through as literal "###".
    $renderContent = function (string $content) use ($inline): string {
        $lines = preg_split('/\r?\n/', trim($content));
        $html = ''; $para = []; $bullets = [];
        $flushPara = function () use (&$para, &$html, $inline) {
            if ($para) { $html .= '<p>' . implode('<br>', array_map($inline, $para)) . '</p>'; $para = []; }
        };
        $flushBullets = function () use (&$bullets, &$html, $inline) {
            if ($bullets) {
                $html .= '<ul>' . implode('', array_map(fn ($b) => '<li>' . $inline($b) . '</li>', $bullets)) . '</ul>';
                $bullets = [];
            }
        };
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') { $flushPara(); $flushBullets(); continue; }
            if (preg_match('/^#{1,6}\s*(.+?):?$/', $t, $m) || preg_match('/^\*\*(.+?)\*\*:?$/', $t, $m)) {
                $flushPara(); $flushBullets();
                $html .= '<h3 class="subsection">' . e(trim($m[1])) . '</h3>';
            } elseif (preg_match('/^[\-\*\x{2022}]\s+(.*)$/u', $t, $m)) {
                $flushPara();
                $bullets[] = $m[1];
            } else {
                $flushBullets();
                $para[] = $t;
            }
        }
        $flushPara(); $flushBullets();
        return $html;
    };
@endphp
<html>
<head>
<meta charset="utf-8">
<style>
    @font-face { font-family: 'Raleway'; font-weight: 400; font-style: normal; src: url('{{ $fReg }}')  format('truetype'); }
    @font-face { font-family: 'Raleway'; font-weight: 600; font-style: normal; src: url('{{ $fSemi }}') format('truetype'); }
    @font-face { font-family: 'Raleway'; font-weight: 700; font-style: normal; src: url('{{ $fBold }}') format('truetype'); }

    /* Top margin leaves room for the drawn running header; bottom for the footer. */
    @page { margin: 1.15in 0.85in 0.95in 0.85in; }

    body { font-family: Helvetica, 'DejaVu Sans', sans-serif; font-size: {{ $size }}pt; line-height: {{ $line }}; color: {{ $ink }}; }

    /* ---- Cover ---- */
    .cover { text-align: center; page-break-after: always; }
    .cover .logo { width: 340px; margin: 0.35in auto 0.18in; }
    .cover .tagline { color: {{ $muted }}; font-size: 9pt; letter-spacing: 2.5px; text-transform: uppercase; margin-bottom: 0.5in; }
    .cover .title { font-family: 'Raleway'; font-weight: 700; color: {{ $navy }}; font-size: 17pt; line-height: 1.35; margin: 0 0.2in 0.5in; }
    .cover .submitted { color: {{ $orange }}; font-family: 'Raleway'; font-weight: 600; font-size: 12pt; margin-bottom: 6px; }
    .cover .client { color: {{ $orange }}; font-family: 'Raleway'; font-weight: 700; font-size: 16pt; margin-bottom: 10px; }
    .cover .cdate { color: {{ $orange }}; font-family: 'Raleway'; font-weight: 600; font-size: 12pt; }
    .cover .band { margin-top: 0.7in; height: 70px; background: {{ $navy }}; position: relative; overflow: hidden; }
    .cover .band .stripe { position: absolute; top: 0; left: 0; width: 38%; height: 70px; background: {{ $orange }}; }
    .cover .ver { margin-top: 8px; text-align: right; color: {{ $muted }}; font-size: 8pt; }

    /* ---- Section headings ---- */
    h2.section { font-family: 'Raleway'; font-weight: 700; color: {{ $navy }}; font-size: {{ $size + 5 }}pt;
        text-transform: uppercase; letter-spacing: 0.3px; margin: 26px 0 4px; padding-bottom: 5px; border-bottom: 2px solid {{ $orange }}; }
    h2.section .num { color: {{ $orange }}; }
    h3.subsection { font-family: 'Raleway'; font-weight: 700; color: {{ $orange }}; font-size: {{ $size + 1.5 }}pt; margin: 16px 0 5px; }

    p { margin: 0 0 9px; text-align: justify; }
    ul { margin: 0 0 9px; padding-left: 16px; }
    li { margin: 0 0 4px; }
    .needs { color: {{ $orange }}; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin: 0 0 12px; font-size: {{ $size - 0.5 }}pt; }
    th { background: {{ $navy }}; color: #fff; text-align: left; padding: 6px 8px; font-family: 'Raleway'; font-weight: 600; }
    td { padding: 6px 8px; border-bottom: 1px solid {{ $rule }}; vertical-align: top; }
    tr:nth-child(even) td { background: #F5F5F7; }
</style>
</head>
<body>

{{-- The running header (logo + title) and footer (copyright / confidential /
     page numbers), drawn on every page except the cover, are registered as a
     page_script in ProposalDocumentService (more reliable than inline php). --}}

{{-- ============ COVER ============ --}}
<div class="cover">
    <img class="logo" src="{{ $logoPath }}" alt="QuakeLogic">
    <div class="tagline">AI-Powered Disaster Risk Management Solutions</div>
    <div class="title">{{ $coverTitle }}</div>
    @if($client)
        <div class="submitted">Proposal Submitted To</div>
        <div class="client">{{ strtoupper($client) }}</div>
    @endif
    <div class="cdate">{{ strtoupper($dateStr) }}</div>
    <div class="band"><div class="stripe"></div></div>
    <div class="ver">{{ $proposal->proposal_number }} &nbsp;|&nbsp; Ver 1</div>
</div>

{{-- ============ BODY ============ --}}
@foreach($sections as $i => $section)
    <h2 class="section"><span class="num">{{ $i + 1 }}</span>&nbsp;&nbsp;{{ strtoupper($section->heading) }}</h2>
    {!! $renderContent((string) $section->content) !!}
@endforeach

</body>
</html>
