<?php

if (!class_exists('ZpgcSimplePdf')) {
    class ZpgcSimplePdf
    {
        private $pageW = 612.0;
        private $pageH = 792.0;
        private $pages = [];
        private $pageImages = [];
        private $images = [];

        public function addPage()
        {
            $this->pages[] = "";
            $this->pageImages[] = [];
        }

        private function pageIndex()
        {
            if ($this->pages === []) {
                $this->addPage();
            }
            return count($this->pages) - 1;
        }

        public function text($x, $yFromTop, $size, $str)
        {
            $i = $this->pageIndex();
            $y = $this->pageH - $yFromTop;
            $esc = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $str);
            $this->pages[$i] .= sprintf("BT /F1 %.2f Tf %.2f %.2f Td (%s) Tj ET\n", $size, $x, $y, $esc);
        }

        public function jpeg($x, $yFromTop, $w, $h, $bytes, $pxW, $pxH)
        {
            $i = $this->pageIndex();
            $id = count($this->images) + 1;
            $this->images[$id] = [
                'w' => (int) $pxW,
                'h' => (int) $pxH,
                'data' => (string) $bytes,
            ];
            $this->pageImages[$i][] = $id;
            $y = $this->pageH - $yFromTop - $h;
            $this->pages[$i] .= sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /Im%d Do Q\n", $w, $h, $x, $y, $id);
        }

        public function build()
        {
            if ($this->pages === []) {
                $this->addPage();
            }
            $objs = [];
            $objs[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
            $pageCount = count($this->pages);
            $pageObjNums = [];
            $next = 3;
            for ($p = 0; $p < $pageCount; $p++) {
                $pageObjNums[$p] = $next;
                $next += 1;
            }
            $contentObjNums = [];
            for ($p = 0; $p < $pageCount; $p++) {
                $contentObjNums[$p] = $next;
                $next += 1;
            }
            $fontObj = $next;
            $next += 1;
            $imageObjNums = [];
            foreach ($this->images as $id => $_img) {
                $imageObjNums[$id] = $next;
                $next += 1;
            }

            $kids = [];
            for ($p = 0; $p < $pageCount; $p++) {
                $kids[] = $pageObjNums[$p] . ' 0 R';
            }
            $objs[] = '2 0 obj
<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . " >>
endobj
";

            for ($p = 0; $p < $pageCount; $p++) {
                $xobjects = '';
                foreach ($this->pageImages[$p] as $imgId) {
                    $xobjects .= '/Im' . $imgId . ' ' . $imageObjNums[$imgId] . ' 0 R ';
                }
                $res = '/Font << /F1 ' . $fontObj . ' 0 R >>';
                if ($xobjects !== '') {
                    $res .= ' /XObject << ' . trim($xobjects) . ' >>';
                }
                $objs[] = $pageObjNums[$p] . " 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageW} {$this->pageH}] /Contents {$contentObjNums[$p]} 0 R /Resources << {$res} >> >>
endobj
";
            }

            for ($p = 0; $p < $pageCount; $p++) {
                $stream = $this->pages[$p];
                $len = strlen($stream);
                $objs[] = $contentObjNums[$p] . " 0 obj
<< /Length {$len} >>
stream
{$stream}endstream
endobj
";
            }

            $objs[] = $fontObj . " 0 obj
<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>
endobj
";

            foreach ($this->images as $id => $img) {
                $len = strlen($img['data']);
                $objs[] = $imageObjNums[$id] . " 0 obj\n<< /Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$len} >>\nstream\n" . $img['data'] . "\nendstream\nendobj\n";
            }

            $pdf = "%PDF-1.4\n";
            $offsets = [0];
            foreach ($objs as $obj) {
                $offsets[] = strlen($pdf);
                $pdf .= $obj;
            }
            $xref = strlen($pdf);
            $n = count($offsets);
            $pdf .= "xref\n0 {$n}\n";
            $pdf .= "0000000000 65535 f \n";
            for ($i = 1; $i < $n; $i++) {
                $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
            }
            $pdf .= "trailer\n<< /Size {$n} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
            return $pdf;
        }
    }
}
