<?php

namespace App\Services\Export;

use App\Support\Branding;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel version of a commercial document, built from the same data as its PDF (LetterheadDocument):
 * company header, document number and details, client / supplier, lines, totals, amount in words,
 * signature. Ready to send to a client and printable on one A4 page width.
 */
class DocumentSpreadsheet
{
    private const MONEY = '#,##0.00';
    // General: 1, 2.5 (a "#,##0.###" format shows "1." in Excel)
    private const QTY = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_GENERAL;

    public function download(array $doc): StreamedResponse
    {
        $spreadsheet = $this->build($doc);
        $file = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', \Illuminate\Support\Str::ascii($doc['file_name'])), '-') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $file, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function build(array $doc): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator(Branding::companyName())->setTitle($doc['title'] . ' ' . $doc['number']);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\\/?*\\[\\]:]/', '-', $doc['number']), 0, 31) ?: 'Document');
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $sheet->setShowGridlines(false);

        $primary = ltrim(Branding::colorPrimary(), '#');
        $priced = $doc['kind'] === 'priced';
        foreach (['A' => 46, 'B' => 12, 'C' => 16, 'D' => 18] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        // ---- Company header (logo on the left, name + contact lines next to it)
        $row = 1;
        if ($logo = Branding::logoFile()) {
            $drawing = new Drawing();
            $drawing->setPath($logo)->setHeight(58)->setCoordinates('A1')->setOffsetX(2)->setOffsetY(2)->setWorksheet($sheet);
            $sheet->getRowDimension(1)->setRowHeight(24);
            $sheet->getRowDimension(2)->setRowHeight(24);
            $row = 4;
        }
        $sheet->setCellValue("A{$row}", trim(Branding::companyName() . ' ' . Branding::legalForm()));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12)->getColor()->setRGB($primary);
        foreach (array_filter([Branding::contactLine(), implode(' - ', Branding::sloganLines())]) as $line) {
            $sheet->setCellValue('A' . (++$row), $line);
            $sheet->getStyle("A{$row}")->getFont()->setSize(8.5)->getColor()->setRGB('5B6B7F');
        }

        // ---- Title, number, details
        $row += 2;
        $sheet->setCellValue("A{$row}", $doc['title']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16)->getColor()->setRGB($primary);
        $sheet->setCellValue('A' . (++$row), 'N° ' . $doc['number']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
        if ($doc['draft']) {
            $sheet->setCellValue('A' . (++$row), 'Document provisoire, sans valeur comptable.');
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB('C0392B');
        }

        // Client / supplier box on the right, details on the left
        $top = $row + 2;
        $metaRow = $top;
        foreach ($doc['meta'] as $key => $value) {
            $sheet->setCellValue("A{$metaRow}", $key . ' : ' . $value);
            $metaRow++;
        }
        $partyRow = $top;
        $sheet->setCellValue("C{$partyRow}", $doc['party_label']);
        $sheet->getStyle("C{$partyRow}")->getFont()->setSize(8.5)->getColor()->setRGB('5B6B7F');
        $sheet->setCellValue('C' . (++$partyRow), $doc['party']['name']);
        $sheet->getStyle("C{$partyRow}")->getFont()->setBold(true)->setSize(11);
        foreach ($doc['party']['lines'] as $line) {
            $sheet->setCellValue('C' . (++$partyRow), $line);
        }
        $sheet->getStyle("C{$top}:D{$partyRow}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B8C4D1');

        // ---- Lines
        $row = max($metaRow, $partyRow) + 2;
        $single = !$priced && ($doc['single'] ?? false); // simple mode BL: one quantity column
        $headers = $priced ? ['Designation', 'Qte', 'P.U. HT', 'Total HT'] : ($single ? ['Designation', $doc['done_label']] : ['Designation', 'Qte commandee', $doc['done_label'], '']);
        $sheet->fromArray($headers, null, "A{$row}");
        $last = $priced ? 'D' : ($single ? 'B' : 'C');
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $primary]],
        ]);
        $sheet->getStyle("B{$row}:{$last}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $firstLine = $row + 1;

        foreach ($doc['lines'] as $line) {
            $row++;
            $sheet->setCellValue("A{$row}", $line['designation']);
            if ($priced) {
                $sheet->setCellValue("B{$row}", $line['quantity']);
                $sheet->setCellValue("C{$row}", $line['unit_price']);
                $sheet->setCellValue("D{$row}", $line['total']);
            } elseif ($single) {
                $sheet->setCellValue("B{$row}", $line['done']);
            } else {
                $sheet->setCellValue("B{$row}", $line['ordered']);
                $sheet->setCellValue("C{$row}", $line['done']);
            }
            if (($row - $firstLine) % 2 === 1) {
                $sheet->getStyle("A{$row}:{$last}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F6F9FC');
            }
        }
        if ($row >= $firstLine) {
            $sheet->getStyle("A{$firstLine}:A{$row}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("B{$firstLine}:B{$row}")->getNumberFormat()->setFormatCode(self::QTY);
            if (!$single) {
                $sheet->getStyle("C{$firstLine}:{$last}{$row}")->getNumberFormat()->setFormatCode($priced ? self::MONEY : self::QTY);
            }
            $sheet->getStyle("A{$firstLine}:{$last}{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR);
        }

        // ---- Totals
        if ($priced) {
            $currency = ' ' . $doc['currency'];
            $totals = [['Total HT', $doc['totals']['ht']], [$doc['totals']['tva_label'], $doc['totals']['tva']], ['Total TTC', $doc['totals']['ttc']]];
            if ($doc['payments']) {
                $totals[] = ['Deja regle', $doc['payments']['paid']];
                $totals[] = ['Reste a payer', $doc['payments']['balance']];
            }
            $row++;
            foreach ($totals as [$label, $amount]) {
                $row++;
                $sheet->setCellValue("C{$row}", $label);
                $sheet->setCellValue("D{$row}", (float) $amount);
                $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::MONEY . '"' . $currency . '"');
                $sheet->getStyle("C{$row}:D{$row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR);
                if ($label === 'Total TTC') {
                    $sheet->getStyle("C{$row}:D{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $primary]],
                    ]);
                }
            }
            if ($doc['amount_in_words']) {
                $row += 2;
                $sheet->mergeCells("A{$row}:D{$row}");
                $sheet->setCellValue("A{$row}", $doc['amount_in_words']);
                $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F7FB');
                $sheet->getRowDimension($row)->setRowHeight(30);
            }
        }

        foreach ($doc['notes'] as $note) {
            $row += 2;
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->setCellValue("A{$row}", $note);
            $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true);
        }

        // ---- Signature (same rule as the PDF: never on a draft)
        if ($doc['signature'] && ($path = \App\Support\Signature::path())) {
            $row += 2;
            $sheet->setCellValue("C{$row}", 'Cachet et signature');
            $sheet->getStyle("C{$row}")->getFont()->setSize(8.5)->getColor()->setRGB('3B4A5C');
            if ($doc['signature']['signatory']) {
                $sheet->setCellValue('C' . (++$row), $doc['signature']['signatory']);
                $sheet->getStyle("C{$row}")->getFont()->setBold(true);
            }
            $drawing = new Drawing();
            $drawing->setPath($path)->setHeight(80)->setCoordinates('C' . ($row + 1))->setOffsetX(4)->setWorksheet($sheet);
            $row += 6;
        }

        // ---- Legal footer
        $row += 1;
        foreach (Branding::footerLines() as $line) {
            $row++;
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->setCellValue("A{$row}", $line);
            $sheet->getStyle("A{$row}")->getFont()->setSize(8)->getColor()->setRGB('5B6B7F');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $this->printSetup($sheet, $row);

        return $spreadsheet;
    }

    private function printSetup(Worksheet $sheet, int $lastRow): void
    {
        $sheet->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setPrintArea("A1:D{$lastRow}");
        $sheet->getPageMargins()->setLeft(0.5)->setRight(0.5)->setTop(0.5)->setBottom(0.5);
    }
}
