<?php

namespace App\Services\Reporting;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Renders a report (title + tabular sections) to downloadable CSV, XLSX, or
 * DOCX. PDF stays in Blade templates (see resources/views/pdf) since it has
 * its own layout. Sections are format-agnostic:
 *
 *   ['title' => 'Performance by User', 'headers' => [...], 'rows' => [[...], ...]]
 */
class ReportDownloadService
{
    public function download(string $format, string $filename, string $title, string $subtitle, array $sections): StreamedResponse
    {
        return match ($format) {
            'csv' => $this->csv($filename, $title, $subtitle, $sections),
            'xlsx' => $this->xlsx($filename, $title, $subtitle, $sections),
            'docx' => $this->docx($filename, $title, $subtitle, $sections),
            default => abort(404),
        };
    }

    private function csv(string $filename, string $title, string $subtitle, array $sections): StreamedResponse
    {
        return response()->streamDownload(function () use ($title, $subtitle, $sections) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$title]);
            fputcsv($out, [$subtitle]);
            foreach ($sections as $section) {
                fputcsv($out, []);
                fputcsv($out, [$section['title']]);
                fputcsv($out, $section['headers']);
                foreach ($section['rows'] as $row) {
                    fputcsv($out, $row);
                }
            }
            fclose($out);
        }, "{$filename}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function xlsx(string $filename, string $title, string $subtitle, array $sections): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach (array_values($sections) as $i => $section) {
            $sheet = $spreadsheet->createSheet($i);
            // Sheet titles: max 31 chars, no special characters.
            $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]+/', '', $section['title']), 0, 31));

            $sheet->setCellValue('A1', $title);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
            $sheet->setCellValue('A2', $subtitle);
            $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('64748B');

            $headerRow = 4;
            foreach (array_values($section['headers']) as $col => $header) {
                $sheet->getCell([$col + 1, $headerRow])->setValue($header);
            }
            $headerRange = 'A' . $headerRow . ':' . $sheet->getCell([count($section['headers']), $headerRow])->getCoordinate();
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F1F5F9');

            $r = $headerRow + 1;
            foreach ($section['rows'] as $row) {
                foreach (array_values($row) as $col => $value) {
                    $sheet->getCell([$col + 1, $r])->setValue($value);
                }
                $r++;
            }

            foreach (range(1, count($section['headers'])) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function docx(string $filename, string $title, string $subtitle, array $sections): StreamedResponse
    {
        $body = $this->docxHeading($title, 32, 'C75D24')
            . $this->docxParagraph($subtitle, 18, '64748B');

        foreach ($sections as $section) {
            $body .= $this->docxHeading($section['title'], 24, '1E293B');
            $body .= $this->docxTable($section['headers'], $section['rows']);
            $body .= '<w:p/>';
        }

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $body
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr>'
            . '</w:body></w:document>';

        $tmp = tempnam(sys_get_temp_dir(), 'qlx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>');
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return response()->streamDownload(function () use ($tmp) {
            readfile($tmp);
            @unlink($tmp);
        }, "{$filename}.docx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    private function docxHeading(string $text, int $halfPoints, string $color): string
    {
        return '<w:p><w:pPr><w:spacing w:before="240" w:after="80"/></w:pPr>'
            . '<w:r><w:rPr><w:b/><w:color w:val="' . $color . '"/><w:sz w:val="' . $halfPoints . '"/></w:rPr>'
            . '<w:t xml:space="preserve">' . $this->xml($text) . '</w:t></w:r></w:p>';
    }

    private function docxParagraph(string $text, int $halfPoints, string $color): string
    {
        return '<w:p><w:r><w:rPr><w:color w:val="' . $color . '"/><w:sz w:val="' . $halfPoints . '"/></w:rPr>'
            . '<w:t xml:space="preserve">' . $this->xml($text) . '</w:t></w:r></w:p>';
    }

    private function docxTable(array $headers, array $rows): string
    {
        $borders = '<w:tblBorders>'
            . '<w:top w:val="single" w:sz="4" w:color="CBD5E1"/><w:bottom w:val="single" w:sz="4" w:color="CBD5E1"/>'
            . '<w:left w:val="single" w:sz="4" w:color="CBD5E1"/><w:right w:val="single" w:sz="4" w:color="CBD5E1"/>'
            . '<w:insideH w:val="single" w:sz="4" w:color="E2E8F0"/><w:insideV w:val="single" w:sz="4" w:color="E2E8F0"/>'
            . '</w:tblBorders>';

        $xml = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/>' . $borders . '</w:tblPr>';

        $xml .= '<w:tr>';
        foreach ($headers as $header) {
            $xml .= '<w:tc><w:tcPr><w:shd w:val="clear" w:fill="F1F5F9"/></w:tcPr>'
                . '<w:p><w:r><w:rPr><w:b/><w:sz w:val="17"/><w:color w:val="475569"/></w:rPr>'
                . '<w:t xml:space="preserve">' . $this->xml((string) $header) . '</w:t></w:r></w:p></w:tc>';
        }
        $xml .= '</w:tr>';

        foreach ($rows as $row) {
            $xml .= '<w:tr>';
            foreach ($row as $value) {
                $xml .= '<w:tc><w:p><w:r><w:rPr><w:sz w:val="18"/></w:rPr>'
                    . '<w:t xml:space="preserve">' . $this->xml((string) $value) . '</w:t></w:r></w:p></w:tc>';
            }
            $xml .= '</w:tr>';
        }

        return $xml . '</w:tbl>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
