<?php

namespace App\Services\Proposals;

use App\Models\Organization;
use App\Models\ProposalSubmission;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/**
 * Assembles a proposal's saved sections into a downloadable Word (.docx) or PDF
 * document, applying the org's Style Profile formatting (fonts, size, line
 * spacing, margins, accent color). DOCX is built as raw OOXML (Word honours the
 * exact fonts); PDF is rendered via Blade + dompdf (fonts approximate).
 */
class ProposalDocumentService
{
    public function __construct(private readonly ProposalStyleService $styles) {}

    /** @param Collection<int,\App\Models\ProposalSection> $sections */
    public function download(ProposalSubmission $proposal, Collection $sections, string $format): Response
    {
        $org = Organization::find($proposal->organization_id);
        $style = $org ? $this->styles->get($org) : ProposalStyleService::DEFAULTS;
        $proposal->loadMissing('company:id,name', 'agency:id,name');

        return $format === 'pdf'
            ? $this->pdf($proposal, $sections, $style)
            : $this->docx($proposal, $sections, $style);
    }

    private function filename(ProposalSubmission $p): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]+/', '_', $p->proposal_number . '-' . ($p->project_name ?: 'proposal'));
    }

    /** @param Collection<int,\App\Models\ProposalSection> $sections */
    private function pdf(ProposalSubmission $proposal, Collection $sections, array $style): Response
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.proposal', [
            'proposal' => $proposal,
            'sections' => $sections,
            'style' => $style,
            'client' => $proposal->company?->name ?? $proposal->agency?->name,
        ])->setPaper('letter');

        // Render first so the pages exist, THEN stamp the running header/footer:
        // dompdf's page_script runs the callback immediately over existing pages,
        // so registering it before render() would draw onto nothing.
        $pdf->render();
        $this->registerHeaderFooter($pdf->getDomPDF(), $proposal);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->filename($proposal) . '.pdf"',
        ]);
    }

    /**
     * Draw the running header (doc title + logo) and footer (copyright /
     * confidential / page X of Y) on every page except the cover. Registered as
     * a canvas page_script closure — far more reliable than inline php-in-pdf.
     */
    private function registerHeaderFooter(\Dompdf\Dompdf $dompdf, ProposalSubmission $proposal): void
    {
        $logo = public_path('quakelogic-logo.png');
        $title = 'PROPOSAL - ' . strtoupper(\Illuminate\Support\Str::limit($proposal->project_name ?: 'Proposal', 34, ''));
        $year = (string) now()->year;

        $dompdf->getCanvas()->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($logo, $title, $year) {
            if ($pageNumber < 2) {
                return; // keep the cover clean
            }
            $w = $canvas->get_width();
            $h = $canvas->get_height();
            $gray = [0.42, 0.45, 0.50];
            $dark = [0.12, 0.12, 0.16];
            $bold = $fontMetrics->getFont('Helvetica', 'bold');
            $reg = $fontMetrics->getFont('Helvetica', 'normal');

            // Header.
            $canvas->text(61, 40, $title, $bold, 8, $dark);
            if (is_file($logo)) {
                $canvas->image($logo, $w - 61 - 122, 33, 122, 19.8);
            }

            // Footer (three segments).
            $fy = $h - 38;
            $left = "\u{00A9}{$year} QUAKELOGIC  All Rights Reserved.";
            $mid = 'PROPRIETARY AND CONFIDENTIAL';
            $right = "Page {$pageNumber} of {$pageCount}";
            $canvas->text(61, $fy, $left, $bold, 7.5, $gray);
            $midW = $fontMetrics->getTextWidth($mid, $bold, 7.5);
            $canvas->text(($w - $midW) / 2, $fy, $mid, $bold, 7.5, $gray);
            $rW = $fontMetrics->getTextWidth($right, $reg, 7.5);
            $canvas->text($w - 61 - $rW, $fy, $right, $reg, 7.5, $gray);
        });
    }

    // QuakeLogic brand palette (matches the reference proposal + the PDF export).
    private const NAVY = '262261';
    private const ORANGE = 'F26522';
    private const INK = '2D2D2D';
    private const MUTED = '6B7280';

    /** @param Collection<int,\App\Models\ProposalSection> $sections */
    private function docx(ProposalSubmission $proposal, Collection $sections, array $style): Response
    {
        $fmt = $style['format'];
        // Brand fonts are fixed to match the QuakeLogic template (Raleway
        // headings, Helvetica Neue body); size + line spacing follow the profile.
        $bodyFont = 'Helvetica Neue';
        $headFont = 'Raleway';
        $bodyHalf = (int) round(((float) ($fmt['font_size'] ?: 10.5)) * 2);          // half-points
        $line = (int) round(((float) ($fmt['line_spacing'] ?: 1.5)) * 240);          // 240 = single

        $client = $proposal->company?->name ?? $proposal->agency?->name;
        $project = $proposal->project_name ?: 'Proposal';
        $coverTitle = 'PROPOSAL FOR ' . mb_strtoupper($project)
            . ($proposal->solicitation_number ? ' IN RESPONSE TO ' . mb_strtoupper($proposal->solicitation_number) : '');
        $dateStr = ($proposal->submission_date ?? $proposal->created_at ?? now())->format('F j, Y');
        $hasLogo = is_file(public_path('quakelogic-logo.png'));

        // ---------- Cover ----------
        $body = '';
        if ($hasLogo) {
            $body .= $this->para('', ['runs' => $this->logoDrawing('rIdLogo', 3.6, 101), 'align' => 'center', 'before' => 240, 'after' => 140]);
        }
        $body .= $this->para('AI-POWERED DISASTER RISK MANAGEMENT SOLUTIONS', ['half' => $bodyHalf - 3, 'color' => self::MUTED, 'font' => $headFont, 'align' => 'center', 'letter' => 30, 'after' => 520]);
        $body .= $this->para($coverTitle, ['half' => $bodyHalf + 13, 'color' => self::NAVY, 'font' => $headFont, 'bold' => true, 'align' => 'center', 'after' => 460, 'line' => 312]);
        if ($client) {
            $body .= $this->para('Proposal Submitted To', ['half' => $bodyHalf + 3, 'color' => self::ORANGE, 'font' => $headFont, 'bold' => true, 'align' => 'center', 'after' => 60]);
            $body .= $this->para(mb_strtoupper($client), ['half' => $bodyHalf + 11, 'color' => self::ORANGE, 'font' => $headFont, 'bold' => true, 'align' => 'center', 'after' => 140]);
        }
        $body .= $this->para(mb_strtoupper($dateStr), ['half' => $bodyHalf + 3, 'color' => self::ORANGE, 'font' => $headFont, 'bold' => true, 'align' => 'center', 'after' => 0]);
        $body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

        // ---------- Sections ----------
        $n = 0;
        foreach ($sections as $section) {
            $body .= $this->sectionHeading(++$n, (string) $section->heading, $bodyHalf + 9, $headFont, $line);
            $body .= $this->renderBodyXml((string) $section->content, $bodyHalf, $line, $bodyFont, $headFont);
        }

        $sectPr = '<w:sectPr>'
            . '<w:headerReference w:type="default" r:id="rIdHdr"/>'
            . '<w:headerReference w:type="first" r:id="rIdHdrF"/>'
            . '<w:footerReference w:type="default" r:id="rIdFtr"/>'
            . '<w:footerReference w:type="first" r:id="rIdFtrF"/>'
            . '<w:pgSz w:w="12240" w:h="15840"/>'
            . '<w:pgMar w:top="1656" w:right="1224" w:bottom="1368" w:left="1224" w:header="720" w:footer="576" w:gutter="0"/>'
            . '<w:titlePg/>'
            . '</w:sectPr>';

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document ' . $this->ooxmlNs() . '><w:body>' . $body . $sectPr . '</w:body></w:document>';

        $tmp = tempnam(sys_get_temp_dir(), 'qlp');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
            . '<Override PartName="/word/header2.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
            . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
            . '<Override PartName="/word/footer2.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>');

        $relImage = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
        $relHeader = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/header';
        $relFooter = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer';
        $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rIdHdr" Type="' . $relHeader . '" Target="header1.xml"/>'
            . '<Relationship Id="rIdHdrF" Type="' . $relHeader . '" Target="header2.xml"/>'
            . '<Relationship Id="rIdFtr" Type="' . $relFooter . '" Target="footer1.xml"/>'
            . '<Relationship Id="rIdFtrF" Type="' . $relFooter . '" Target="footer2.xml"/>'
            . ($hasLogo ? '<Relationship Id="rIdLogo" Type="' . $relImage . '" Target="media/logo.png"/>' : '')
            . '</Relationships>';

        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/_rels/document.xml.rels', $docRels);
        $zip->addFromString('word/header1.xml', $this->headerXml($project, $bodyFont, $hasLogo));
        $zip->addFromString('word/header2.xml', $this->emptyPart('hdr'));
        $zip->addFromString('word/footer1.xml', $this->footerXml($bodyFont));
        $zip->addFromString('word/footer2.xml', $this->emptyPart('ftr'));
        if ($hasLogo) {
            $zip->addFromString('word/_rels/header1.xml.rels',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rIdHdrLogo" Type="' . $relImage . '" Target="media/logo.png"/>'
                . '</Relationships>');
            $zip->addFromString('word/media/logo.png', (string) file_get_contents(public_path('quakelogic-logo.png')));
        }
        $zip->close();

        $contents = (string) file_get_contents($tmp);
        @unlink($tmp);

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $this->filename($proposal) . '.docx"',
        ]);
    }

    /** Shared OOXML namespace declarations (document/header/footer roots). */
    private function ooxmlNs(): string
    {
        return 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
            . 'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
            . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            . 'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';
    }

    /** Inline picture run for the logo at a given width (inches). */
    private function logoDrawing(string $rId, float $widthIn, int $id): string
    {
        $cx = (int) round($widthIn * 914400);
        $cy = (int) round($widthIn * (120 / 739) * 914400); // logo aspect 739x120
        return '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<wp:docPr id="' . $id . '" name="logo' . $id . '"/>'
            . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:nvPicPr><pic:cNvPr id="' . $id . '" name="logo' . $id . '.png"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . $rId . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
    }

    /**
     * Build a paragraph. Options: half, color, font, bold, align(left|center|justify),
     * before, after, line, letter(char spacing), ind([left,hanging]), bottomBorder(color),
     * runs(pre-built run XML — overrides text).
     *
     * @param array<string,mixed> $o
     */
    private function para(string $text, array $o = []): string
    {
        $half = $o['half'] ?? 21;
        $color = $o['color'] ?? self::INK;
        $font = $o['font'] ?? 'Helvetica Neue';
        $align = $o['align'] ?? 'left';
        $pPr = '<w:pPr><w:spacing w:before="' . ($o['before'] ?? 0) . '" w:after="' . ($o['after'] ?? 160) . '" w:line="' . ($o['line'] ?? 360) . '" w:lineRule="auto"/>';
        if ($align === 'center') {
            $pPr .= '<w:jc w:val="center"/>';
        } elseif ($align === 'justify') {
            $pPr .= '<w:jc w:val="both"/>';
        }
        if (! empty($o['ind'])) {
            $pPr .= '<w:ind w:left="' . $o['ind'][0] . '" w:hanging="' . $o['ind'][1] . '"/>';
        }
        if (! empty($o['bottomBorder'])) {
            $pPr .= '<w:pBdr><w:bottom w:val="single" w:sz="18" w:space="3" w:color="' . $o['bottomBorder'] . '"/></w:pBdr>';
        }
        $pPr .= '</w:pPr>';

        if (isset($o['runs'])) {
            $inner = $o['runs'];
        } else {
            $rPr = '<w:rPr><w:rFonts w:ascii="' . $this->xml($font) . '" w:hAnsi="' . $this->xml($font) . '" w:cs="' . $this->xml($font) . '"/>'
                . (! empty($o['bold']) ? '<w:b/>' : '') . '<w:color w:val="' . $color . '"/>'
                . '<w:sz w:val="' . $half . '"/><w:szCs w:val="' . $half . '"/>'
                . (! empty($o['letter']) ? '<w:spacing w:val="' . $o['letter'] . '"/>' : '') . '</w:rPr>';
            $inner = '<w:r>' . $rPr . '<w:t xml:space="preserve">' . $this->xml($text) . '</w:t></w:r>';
        }

        return '<w:p>' . $pPr . $inner . '</w:p>';
    }

    /** Numbered section heading: orange number + navy uppercase title + orange rule. */
    private function sectionHeading(int $n, string $title, int $half, string $font, int $line): string
    {
        $rPr = fn (string $color) => '<w:rPr><w:rFonts w:ascii="' . $this->xml($font) . '" w:hAnsi="' . $this->xml($font) . '"/>'
            . '<w:b/><w:color w:val="' . $color . '"/><w:sz w:val="' . $half . '"/><w:szCs w:val="' . $half . '"/></w:rPr>';
        $runs = '<w:r>' . $rPr(self::ORANGE) . '<w:t xml:space="preserve">' . $n . '   </w:t></w:r>'
            . '<w:r>' . $rPr(self::NAVY) . '<w:t xml:space="preserve">' . $this->xml(mb_strtoupper($title)) . '</w:t></w:r>';

        return $this->para('', ['runs' => $runs, 'before' => 300, 'after' => 80, 'line' => $line, 'bottomBorder' => self::ORANGE]);
    }

    /** Render section content (sub-headings, bullets, paragraphs, inline bold, [NEEDS:]). */
    private function renderBodyXml(string $content, int $bodyHalf, int $line, string $bodyFont, string $headFont): string
    {
        $lines = preg_split('/\r?\n/', trim($content)) ?: [];
        $xml = '';
        $para = [];
        $bullets = [];

        $flushPara = function () use (&$para, &$xml, $bodyHalf, $line, $bodyFont) {
            if (! $para) {
                return;
            }
            $runs = '';
            foreach ($para as $i => $ln) {
                if ($i > 0) {
                    $runs .= '<w:r><w:br/></w:r>';
                }
                $runs .= $this->inlineRuns($ln, $bodyHalf, $bodyFont, self::INK);
            }
            $xml .= $this->para('', ['runs' => $runs, 'half' => $bodyHalf, 'line' => $line, 'align' => 'justify', 'after' => 140]);
            $para = [];
        };
        $flushBullets = function () use (&$bullets, &$xml, $bodyHalf, $line, $bodyFont) {
            if (! $bullets) {
                return;
            }
            foreach ($bullets as $b) {
                $bullet = '<w:r><w:rPr><w:rFonts w:ascii="' . $this->xml($bodyFont) . '" w:hAnsi="' . $this->xml($bodyFont) . '"/>'
                    . '<w:color w:val="' . self::ORANGE . '"/><w:sz w:val="' . $bodyHalf . '"/></w:rPr>'
                    . '<w:t xml:space="preserve">' . "\u{2022}  " . '</w:t></w:r>';
                $xml .= $this->para('', ['runs' => $bullet . $this->inlineRuns($b, $bodyHalf, $bodyFont, self::INK), 'half' => $bodyHalf, 'line' => $line, 'after' => 60, 'ind' => [360, 260]]);
            }
            $bullets = [];
        };

        foreach ($lines as $ln) {
            $t = trim($ln);
            if ($t === '') {
                $flushPara();
                $flushBullets();
            } elseif (preg_match('/^#{1,6}\s*(.+?):?$/u', $t, $m) || preg_match('/^\*\*(.+?)\*\*:?$/u', $t, $m)) {
                $flushPara();
                $flushBullets();
                $xml .= $this->para(trim($m[1]), ['half' => $bodyHalf + 3, 'color' => self::ORANGE, 'font' => $headFont, 'bold' => true, 'before' => 160, 'after' => 60, 'line' => $line]);
            } elseif (preg_match('/^[\-\*\x{2022}]\s+(.*)$/u', $t, $m)) {
                $flushPara();
                $bullets[] = $m[1];
            } else {
                $flushBullets();
                $para[] = $t;
            }
        }
        $flushPara();
        $flushBullets();

        return $xml;
    }

    /** Run XML for a line of text, honouring **bold** and highlighting [NEEDS: …]. */
    private function inlineRuns(string $text, int $half, string $font, string $color): string
    {
        $parts = preg_split('/(\*\*.+?\*\*|\[NEEDS:[^\]]*\])/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';
        foreach ($parts as $p) {
            $bold = false;
            $c = $color;
            if (preg_match('/^\*\*(.+?)\*\*$/u', $p, $m)) {
                $p = $m[1];
                $bold = true;
            } elseif (preg_match('/^\[NEEDS:[^\]]*\]$/u', $p)) {
                $bold = true;
                $c = self::ORANGE;
            }
            $out .= '<w:r><w:rPr><w:rFonts w:ascii="' . $this->xml($font) . '" w:hAnsi="' . $this->xml($font) . '"/>'
                . ($bold ? '<w:b/>' : '') . '<w:color w:val="' . $c . '"/><w:sz w:val="' . $half . '"/><w:szCs w:val="' . $half . '"/></w:rPr>'
                . '<w:t xml:space="preserve">' . $this->xml($p) . '</w:t></w:r>';
        }

        return $out;
    }

    /** Running header: doc title (left) + logo (right). */
    private function headerXml(string $project, string $font, bool $hasLogo): string
    {
        $title = 'PROPOSAL - ' . mb_strtoupper(\Illuminate\Support\Str::limit($project, 34, ''));
        $titleRun = '<w:r><w:rPr><w:rFonts w:ascii="' . $this->xml($font) . '" w:hAnsi="' . $this->xml($font) . '"/>'
            . '<w:b/><w:color w:val="' . self::INK . '"/><w:sz w:val="15"/></w:rPr>'
            . '<w:t xml:space="preserve">' . $this->xml($title) . '</w:t></w:r>';
        $logo = $hasLogo ? '<w:r><w:tab/></w:r>' . $this->logoDrawing('rIdHdrLogo', 1.5, 201) : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:hdr ' . $this->ooxmlNs() . '>'
            . '<w:p><w:pPr><w:tabs><w:tab w:val="right" w:pos="9792"/></w:tabs><w:spacing w:after="0"/></w:pPr>'
            . $titleRun . $logo . '</w:p></w:hdr>';
    }

    /** Running footer: copyright | confidential | Page X of Y. */
    private function footerXml(string $font): string
    {
        $year = now()->year;
        $txt = fn (string $t, bool $bold) => '<w:r><w:rPr><w:rFonts w:ascii="' . $this->xml($font) . '" w:hAnsi="' . $this->xml($font) . '"/>'
            . ($bold ? '<w:b/>' : '') . '<w:color w:val="' . self::MUTED . '"/><w:sz w:val="15"/></w:rPr>'
            . '<w:t xml:space="preserve">' . $this->xml($t) . '</w:t></w:r>';
        $fld = fn (string $instr) => '<w:fldSimple w:instr=" ' . $instr . ' "><w:r><w:rPr><w:rFonts w:ascii="' . $this->xml($font) . '" w:hAnsi="' . $this->xml($font) . '"/>'
            . '<w:color w:val="' . self::MUTED . '"/><w:sz w:val="15"/></w:rPr><w:t>1</w:t></w:r></w:fldSimple>';

        $left = $txt("\u{00A9}{$year} QUAKELOGIC  All Rights Reserved.", true);
        $mid = $txt('PROPRIETARY AND CONFIDENTIAL', true);
        $right = $txt('Page ', false) . $fld('PAGE') . $txt(' of ', false) . $fld('NUMPAGES');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:ftr ' . $this->ooxmlNs() . '>'
            . '<w:p><w:pPr><w:tabs><w:tab w:val="center" w:pos="4896"/><w:tab w:val="right" w:pos="9792"/></w:tabs><w:spacing w:before="0" w:after="0"/></w:pPr>'
            . $left . '<w:r><w:tab/></w:r>' . $mid . '<w:r><w:tab/></w:r>' . $right . '</w:p></w:ftr>';
    }

    /** Empty first-page header/footer so the cover stays clean. */
    private function emptyPart(string $kind): string
    {
        $el = $kind === 'hdr' ? 'w:hdr' : 'w:ftr';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<' . $el . ' ' . $this->ooxmlNs() . '><w:p/></' . $el . '>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
