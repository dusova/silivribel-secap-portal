<?php

declare(strict_types=1);

final class PdfReport
{
    public static function download(string $title, string $subtitle, array $columns, array $rows, string $filename): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            http_response_code(500);
            exit('PDF üretimi için PHP GD eklentisi gerekir.');
        }

        $pages = self::renderPages($title, $subtitle, $columns, $rows);
        $pdf = self::buildPdf($pages);

        header_remove('X-Powered-By');
        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private static function renderPages(string $title, string $subtitle, array $columns, array $rows): array
    {
        $font = self::fontPath();
        $width = 1600;
        $height = 1131;
        $margin = 54;
        $headerHeight = 132;
        $rowHeight = 46;
        $rowsPerPage = max(1, (int) floor(($height - $headerHeight - $margin - 58) / $rowHeight));
        $chunks = array_chunk($rows ?: [self::emptyRow($columns)], $rowsPerPage);
        $pages = [];
        $totalPages = count($chunks);

        foreach ($chunks as $pageIndex => $chunk) {
            $img = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($img, 255, 255, 255);
            $ink = imagecolorallocate($img, 17, 24, 39);
            $muted = imagecolorallocate($img, 102, 112, 133);
            $line = imagecolorallocate($img, 229, 231, 235);
            $soft = imagecolorallocate($img, 245, 247, 251);
            $green = imagecolorallocate($img, 15, 159, 110);
            imagefilledrectangle($img, 0, 0, $width, $height, $white);
            imagefilledrectangle($img, 0, 0, $width, 14, $green);

            self::text($img, $font, 24, $margin, 62, 'T.C. SİLİVRİ BELEDİYESİ', $green);
            self::text($img, $font, 36, $margin, 108, $title, $ink);
            self::text($img, $font, 18, $margin, 144, $subtitle, $muted);
            self::text($img, $font, 16, $width - 220, 64, 'Sayfa ' . ($pageIndex + 1) . '/' . $totalPages, $muted);

            $tableTop = 184;
            $tableWidth = $width - ($margin * 2);
            imagefilledrectangle($img, $margin, $tableTop, $width - $margin, $tableTop + $rowHeight, $soft);
            imagerectangle($img, $margin, $tableTop, $width - $margin, $tableTop + $rowHeight, $line);

            $x = $margin;
            foreach ($columns as $col) {
                $cw = (int) round($tableWidth * (float)($col['ratio'] ?? 0.1));
                self::text($img, $font, 15, $x + 10, $tableTop + 29, self::fit((string)$col['label'], max(8, (int)($cw / 9))), $muted);
                imageline($img, $x, $tableTop, $x, $tableTop + $rowHeight, $line);
                $x += $cw;
            }
            imageline($img, $width - $margin, $tableTop, $width - $margin, $tableTop + $rowHeight, $line);

            $y = $tableTop + $rowHeight;
            foreach ($chunk as $idx => $row) {
                if ($idx % 2 === 1) {
                    imagefilledrectangle($img, $margin, $y, $width - $margin, $y + $rowHeight, imagecolorallocate($img, 250, 251, 252));
                }
                imagerectangle($img, $margin, $y, $width - $margin, $y + $rowHeight, $line);
                $x = $margin;
                foreach ($columns as $col) {
                    $cw = (int) round($tableWidth * (float)($col['ratio'] ?? 0.1));
                    $key = (string)$col['key'];
                    $value = array_key_exists($key, $row) ? (string)$row[$key] : '';
                    self::text($img, $font, 14, $x + 10, $y + 29, self::fit($value, max(8, (int)($cw / 8.5))), $ink);
                    imageline($img, $x, $y, $x, $y + $rowHeight, $line);
                    $x += $cw;
                }
                imageline($img, $width - $margin, $y, $width - $margin, $y + $rowHeight, $line);
                $y += $rowHeight;
            }

            self::text($img, $font, 13, $margin, $height - 32, 'SECAP Portalı tarafından otomatik oluşturuldu - ' . date('d.m.Y H:i'), $muted);

            ob_start();
            imagejpeg($img, null, 88);
            $pages[] = ob_get_clean();
            imagedestroy($img);
        }

        return $pages;
    }

    private static function buildPdf(array $jpegPages): string
    {
        $objects = [];
        $pageKids = [];
        $widthPt = 842;
        $heightPt = 595;

        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = 'PAGES_PLACEHOLDER';

        foreach ($jpegPages as $i => $jpeg) {
            $imgNo = count($objects) + 1;
            $objects[] = "<< /Type /XObject /Subtype /Image /Width 1600 /Height 1131 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";
            $content = "q\n{$widthPt} 0 0 {$heightPt} 0 0 cm\n/Im{$i} Do\nQ";
            $contentNo = count($objects) + 1;
            $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
            $pageNo = count($objects) + 1;
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$widthPt} {$heightPt}] /Resources << /XObject << /Im{$i} {$imgNo} 0 R >> >> /Contents {$contentNo} 0 R >>";
            $pageKids[] = $pageNo . ' 0 R';
        }

        $objects[1] = '<< /Type /Pages /Kids [' . implode(' ', $pageKids) . '] /Count ' . count($pageKids) . ' >>';

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n{$obj}\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
        return $pdf;
    }

    private static function text($img, ?string $font, int $size, int $x, int $y, string $text, int $color): void
    {
        if ($font && is_file($font)) {
            imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
            return;
        }
        imagestring($img, 3, $x, $y - 14, $text, $color);
    }

    private static function fontPath(): ?string
    {
        foreach ([
            APP_ROOT . '/varliklar/fontlar/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial.ttf',
            'C:/Windows/Fonts/arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    private static function fit(string $text, int $width): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?: '';
        if (mb_strwidth($text, 'UTF-8') <= $width) {
            return $text;
        }
        return mb_strimwidth($text, 0, max(1, $width - 3), '...', 'UTF-8');
    }

    private static function emptyRow(array $columns): array
    {
        $row = [];
        foreach ($columns as $index => $column) {
            $key = (string) ($column['key'] ?? $index);
            $row[$key] = $index === 0 ? 'Kayıt bulunamadı.' : '';
        }
        return $row;
    }
}
