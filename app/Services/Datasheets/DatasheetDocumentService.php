<?php

namespace App\Services\Datasheets;

use App\Models\Datasheet;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a generated Datasheet into a QuakeLogic-branded PDF (dompdf + Blade)
 * with the cover logo, brand colours/fonts, the first product photo, and the
 * running header/footer — mirroring ProposalDocumentService's PDF path.
 */
class DatasheetDocumentService
{
    public function pdf(Datasheet $datasheet): Response
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.datasheet', [
            'd' => $datasheet,
            'sections' => $datasheet->sections ?? [],
            'heroImage' => $this->heroImage($datasheet),
        ])->setPaper('letter');

        // Render first so pages exist, then stamp the running header/footer.
        $pdf->render();
        $this->registerHeaderFooter($pdf->getDomPDF(), $datasheet);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->filename($datasheet) . '.pdf"',
        ]);
    }

    /** First product image as a base64 data URI (dompdf has remote images off). */
    private function heroImage(Datasheet $d): ?string
    {
        $first = $d->mediaOfKind('image')[0] ?? null;
        if (! $first) {
            return null;
        }
        try {
            $bytes = Storage::disk($first['disk'] ?? 'local')->get($first['path']);
            if ($bytes) {
                return 'data:' . ($first['mime'] ?? 'image/png') . ';base64,' . base64_encode($bytes);
            }
        } catch (\Throwable) {
            // no hero image
        }

        return null;
    }

    private function filename(Datasheet $d): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]+/', '_', 'QuakeLogic-Datasheet-' . ($d->product_name ?: 'Product'));
    }

    private function registerHeaderFooter(\Dompdf\Dompdf $dompdf, Datasheet $d): void
    {
        $logo = public_path('quakelogic-logo.png');
        $title = 'DATASHEET - ' . strtoupper(Str::limit($d->product_name ?: 'Product', 34, ''));
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

            $canvas->text(61, 40, $title, $bold, 8, $dark);
            if (is_file($logo)) {
                $canvas->image($logo, $w - 61 - 122, 33, 122, 19.8);
            }

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
}
