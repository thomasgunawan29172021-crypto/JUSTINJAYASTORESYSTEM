<?php

namespace App\Services;

use Illuminate\Support\Collection;

class CustomerExcelExport
{
    public function render(Collection $customers): string
    {
        $rows = [[
            'Nama', 'Kontak', 'Kota', 'Alamat', 'Sumber', 'Cabang', 'Brand terakhir',
            'Produk terakhir', 'Harga produk terakhir', 'Metode pembayaran terakhir',
            'Jumlah transaksi', 'Terdaftar',
        ]];

        foreach ($customers as $customer) {
            $purchase = $customer->latestPurchase;
            $rows[] = [
                $customer->name,
                $customer->contacts->pluck('value')->join(', '),
                $customer->city,
                $customer->address,
                $customer->source,
                $customer->branch?->name,
                $purchase?->items?->pluck('brand')->filter()->unique()->join(', '),
                $purchase?->items?->pluck('product_name')->join(', '),
                $purchase?->items?->max('unit_price'),
                $purchase ? (\App\Models\Purchase::PAYMENT_METHODS[$purchase->payment_method] ?? $purchase->payment_method) : null,
                $customer->purchases_count,
                $customer->created_at?->format('Y-m-d'),
            ];
        }

        return $this->zip([
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->rootRelationships(),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(),
            'xl/styles.xml' => $this->styles(),
            'xl/worksheets/sheet1.xml' => $this->worksheet($rows),
        ]);
    }

    private function worksheet(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        $xml .= '<cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="22" customWidth="1"/><col min="3" max="7" width="18" customWidth="1"/><col min="8" max="8" width="32" customWidth="1"/><col min="9" max="12" width="20" customWidth="1"/></cols><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="'.$excelRow.'">';
            foreach ($row as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1).$excelRow;
                $numeric = $rowIndex > 0 && in_array($columnIndex, [8, 10], true) && $value !== null && $value !== '';
                $style = $rowIndex === 0 ? 1 : ($columnIndex === 8 ? 2 : 0);
                if ($numeric) {
                    $xml .= '<c r="'.$reference.'" s="'.$style.'" t="n"><v>'.(float) $value.'</v></c>';
                } else {
                    $xml .= '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData><autoFilter ref="A1:L1"/></worksheet>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Pelanggan CRM" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FF065F46"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs></styleSheet>';
    }

    /** Build a standards-compliant ZIP archive in memory, so XLSX works without ext-zip. */
    private function zip(array $files): string
    {
        $body = '';
        $directory = '';
        $offset = 0;
        [$dosTime, $dosDate] = $this->dosTimestamp();

        foreach ($files as $name => $contents) {
            $compressed = gzdeflate($contents, 6);
            $crc = crc32($contents);
            $nameLength = strlen($name);
            $compressedLength = strlen($compressed);
            $length = strlen($contents);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, $dosTime, $dosDate, $crc, $compressedLength, $length, $nameLength, 0).$name.$compressed;
            $body .= $local;
            $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 8, $dosTime, $dosDate, $crc, $compressedLength, $length, $nameLength, 0, 0, 0, 0, 0, $offset).$name;
            $offset += strlen($local);
        }

        $count = count($files);
        return $body.$directory.pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($directory), strlen($body), 0);
    }

    private function dosTimestamp(): array
    {
        $now = getdate();
        return [(($now['hours'] & 0x1f) << 11) | (($now['minutes'] & 0x3f) << 5) | (($now['seconds'] >> 1) & 0x1f), ((max(1980, $now['year']) - 1980) << 9) | (($now['mon'] & 0xf) << 5) | ($now['mday'] & 0x1f)];
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
