<?php
class ExcelExport
{
    private array $styles = [];
    private array $sheets = [];
    private string $title = '';
    private string $author = '';

    public function __construct(string $title = '', string $author = '')
    {
        $this->title  = $title;
        $this->author = $author;
        $this->registerDefaultStyles();
    }

    private function registerDefaultStyles(): void
    {
        $this->addStyle('brandBar', [
            'font' => 'Segoe UI', 'size' => 9, 'bold' => true, 'color' => '#FFFFFF',
            'bg' => '#0D3B0D', 'valign' => 'Center', 'halign' => 'Center',
        ]);
        $this->addStyle('brandName', [
            'font' => 'Segoe UI', 'size' => 9, 'bold' => true, 'color' => '#A5D6A7',
            'bg' => '#0D3B0D', 'valign' => 'Center',
        ]);
        $this->addStyle('title', [
            'font' => 'Segoe UI', 'size' => 16, 'bold' => true, 'color' => '#FFFFFF',
            'bg' => '#1B5E20', 'valign' => 'Center',
            'borderBottom' => '#4CAF50',
        ]);
        $this->addStyle('titleAccent', [
            'font' => 'Segoe UI', 'size' => 16, 'bold' => true, 'color' => '#A5D6A7',
            'bg' => '#1B5E20', 'valign' => 'Center', 'halign' => 'Right',
            'borderBottom' => '#4CAF50',
        ]);
        $this->addStyle('subtitle', [
            'font' => 'Segoe UI', 'size' => 10, 'color' => '#616161',
            'bg' => '#F1F8E9', 'valign' => 'Center',
            'borderBottom' => '#C8E6C9',
        ]);
        $this->addStyle('subtitleRight', [
            'font' => 'Segoe UI', 'size' => 10, 'bold' => true, 'color' => '#2E7D32',
            'bg' => '#F1F8E9', 'valign' => 'Center', 'halign' => 'Right',
            'borderBottom' => '#C8E6C9',
        ]);
        $this->addStyle('spacer', [
            'font' => 'Segoe UI', 'size' => 4, 'color' => '#FFFFFF',
        ]);

        $this->addStyle('sumLabel', [
            'font' => 'Segoe UI', 'size' => 9, 'bold' => true, 'color' => '#37474F',
            'bg' => '#ECEFF1', 'valign' => 'Center',
            'borderBottom' => '#CFD8DC', 'borderTop' => '#CFD8DC',
            'borderLeft' => '#CFD8DC', 'borderRight' => '#CFD8DC',
        ]);
        $this->addStyle('sumValue', [
            'font' => 'Segoe UI', 'size' => 9, 'bold' => true, 'color' => '#1B5E20',
            'bg' => '#F5F5F5', 'halign' => 'Center', 'valign' => 'Center',
            'borderBottom' => '#E0E0E0',
        ]);
        $this->addStyle('sumValueBig', [
            'font' => 'Segoe UI', 'size' => 18, 'bold' => true, 'color' => '#1B5E20',
            'bg' => '#E8F5E9', 'halign' => 'Center', 'valign' => 'Center',
            'borderBottom' => '#A5D6A7', 'borderTop' => '#A5D6A7',
            'borderLeft' => '#A5D6A7', 'borderRight' => '#A5D6A7',
        ]);
        $this->addStyle('sumIcon', [
            'font' => 'Segoe UI', 'size' => 8, 'color' => '#757575',
            'bg' => '#ECEFF1', 'halign' => 'Center', 'valign' => 'Center',
            'borderBottom' => '#CFD8DC', 'borderTop' => '#CFD8DC',
            'borderLeft' => '#CFD8DC', 'borderRight' => '#CFD8DC',
        ]);

        $this->addStyle('header', [
            'font' => 'Segoe UI', 'size' => 9, 'bold' => true, 'color' => '#FFFFFF',
            'bg' => '#1B5E20', 'halign' => 'Center', 'valign' => 'Center', 'wrap' => true,
            'borderBottom' => '#0D3B0D', 'borderLeft' => '#2E7D32', 'borderRight' => '#2E7D32',
        ]);

        foreach (['', 'Alt'] as $alt) {
            $bg = $alt ? '#F9FBF2' : null;
            $sfx = $alt ? 'Alt' : '';
            $this->addStyle("cell{$sfx}", [
                'font' => 'Segoe UI', 'size' => 9, 'color' => '#212121',
                'bg' => $bg, 'valign' => 'Center', 'wrap' => true,
                'borderBottom' => '#E8F5E9',
            ]);
            $this->addStyle("num{$sfx}", [
                'font' => 'Segoe UI', 'size' => 9, 'bold' => true, 'color' => '#1B5E20',
                'bg' => $bg, 'halign' => 'Right', 'valign' => 'Center', 'format' => '#,##0.00',
                'borderBottom' => '#E8F5E9',
            ]);
            $this->addStyle("code{$sfx}", [
                'font' => 'Consolas', 'size' => 9, 'bold' => true, 'color' => '#1B5E20',
                'bg' => $alt ? '#F1F8E9' : '#E8F5E9', 'halign' => 'Center', 'valign' => 'Center',
                'borderBottom' => '#C8E6C9',
            ]);
            $this->addStyle("year{$sfx}", [
                'font' => 'Segoe UI', 'size' => 9, 'bold' => true, 'color' => '#37474F',
                'bg' => $bg, 'halign' => 'Center', 'valign' => 'Center',
                'borderBottom' => '#E8F5E9',
            ]);
            $this->addStyle("date{$sfx}", [
                'font' => 'Segoe UI', 'size' => 8, 'color' => '#757575',
                'bg' => $bg, 'halign' => 'Center', 'valign' => 'Center',
                'borderBottom' => '#E8F5E9',
            ]);
            $this->addStyle("pct{$sfx}", [
                'font' => 'Segoe UI', 'size' => 9, 'bold' => true, 'color' => '#0D47A1',
                'bg' => $bg, 'halign' => 'Center', 'valign' => 'Center', 'format' => '0.0%',
                'borderBottom' => '#E8F5E9',
            ]);
        }

        $this->addStyle('verified', [
            'font' => 'Segoe UI', 'size' => 9, 'bold' => true, 'color' => '#1B5E20',
            'bg' => '#C8E6C9', 'halign' => 'Center', 'valign' => 'Center',
            'borderBottom' => '#A5D6A7',
        ]);
        $this->addStyle('pending', [
            'font' => 'Segoe UI', 'size' => 9, 'bold' => true, 'color' => '#E65100',
            'bg' => '#FFE0B2', 'halign' => 'Center', 'valign' => 'Center',
            'borderBottom' => '#FFCC80',
        ]);
        $this->addStyle('statusPlanned', [
            'font' => 'Segoe UI', 'size' => 9, 'bold' => true, 'color' => '#546E7A',
            'bg' => '#ECEFF1', 'halign' => 'Center', 'valign' => 'Center',
            'borderBottom' => '#CFD8DC',
        ]);
        $this->addStyle('statusOngoing', [
            'font' => 'Segoe UI', 'size' => 9, 'bold' => true, 'color' => '#1565C0',
            'bg' => '#BBDEFB', 'halign' => 'Center', 'valign' => 'Center',
            'borderBottom' => '#90CAF9',
        ]);
        $this->addStyle('statusCompleted', [
            'font' => 'Segoe UI', 'size' => 9, 'bold' => true, 'color' => '#1B5E20',
            'bg' => '#C8E6C9', 'halign' => 'Center', 'valign' => 'Center',
            'borderBottom' => '#A5D6A7',
        ]);

        $this->addStyle('section', [
            'font' => 'Segoe UI', 'size' => 11, 'bold' => true, 'color' => '#FFFFFF',
            'bg' => '#2E7D32', 'valign' => 'Center',
            'borderBottom' => '#1B5E20', 'borderTop' => '#1B5E20',
        ]);
        $this->addStyle('sectionAlt', [
            'font' => 'Segoe UI', 'size' => 11, 'bold' => true, 'color' => '#1B5E20',
            'bg' => '#E8F5E9', 'valign' => 'Center',
            'borderBottom' => '#A5D6A7', 'borderTop' => '#A5D6A7',
        ]);

        $this->addStyle('footer', [
            'font' => 'Segoe UI', 'size' => 8, 'italic' => true, 'color' => '#9E9E9E',
            'valign' => 'Center', 'borderTop' => '#E0E0E0',
        ]);
        $this->addStyle('footerBrand', [
            'font' => 'Segoe UI', 'size' => 8, 'bold' => true, 'color' => '#1B5E20',
            'bg' => '#F1F8E9', 'valign' => 'Center', 'halign' => 'Center',
            'borderTop' => '#C8E6C9', 'borderBottom' => '#C8E6C9',
        ]);
    }

    private function addStyle(string $id, array $opts): void
    {
        $this->styles[$id] = $opts;
    }

    public function addSheet(string $name, array $columns, array $rows, array $options = []): void
    {
        $this->sheets[] = compact('name', 'columns', 'rows', 'options');
    }

    public function render(): string
    {
        ob_start();
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        ?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Title><?= $this->esc($this->title) ?></Title>
  <Author><?= $this->esc($this->author) ?></Author>
  <Created><?= date('c') ?></Created>
 </DocumentProperties>
 <Styles>
<?php foreach ($this->styles as $id => $o): ?>
  <Style ss:ID="<?= $id ?>">
   <Font ss:FontName="<?= $o['font'] ?? 'Segoe UI' ?>" ss:Size="<?= $o['size'] ?? 9 ?>"<?php
    if (!empty($o['bold'])) echo ' ss:Bold="1"';
    if (!empty($o['italic'])) echo ' ss:Italic="1"';
    if (!empty($o['color'])) echo ' ss:Color="' . $o['color'] . '"';
   ?>/>
<?php if (!empty($o['bg'])): ?>
   <Interior ss:Color="<?= $o['bg'] ?>" ss:Pattern="Solid"/>
<?php endif; ?>
   <Alignment<?php
    if (!empty($o['halign'])) echo ' ss:Horizontal="' . $o['halign'] . '"';
    if (!empty($o['valign'])) echo ' ss:Vertical="' . $o['valign'] . '"';
    if (!empty($o['wrap'])) echo ' ss:WrapText="1"';
   ?>/>
<?php if (!empty($o['format'])): ?>
   <NumberFormat ss:Format="<?= $o['format'] ?>"/>
<?php endif; ?>
<?php if (!empty($o['borderBottom']) || !empty($o['borderLeft']) || !empty($o['borderRight'])): ?>
   <Borders>
<?php foreach (['Bottom','Left','Right','Top'] as $pos):
    $key = 'border'.$pos;
    if (!empty($o[$key])): ?>
    <Border ss:Position="<?= $pos ?>" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="<?= $o[$key] ?>"/>
<?php endif; endforeach; ?>
   </Borders>
<?php endif; ?>
  </Style>
<?php endforeach; ?>
 </Styles>
<?php foreach ($this->sheets as $sheet): ?>
<?= $this->renderSheet($sheet) ?>
<?php endforeach; ?>
</Workbook>
<?php
        return ob_get_clean();
    }

    private function renderSheet(array $sheet): string
    {
        ob_start();
        $cols    = $sheet['columns'];
        $rows    = $sheet['rows'];
        $opts    = $sheet['options'];
        $colCount = count($cols);
        $freezeRow = $opts['freezeRow'] ?? null;
        $headerRows = $opts['headerRows'] ?? [];
        ?>
 <Worksheet ss:Name="<?= $this->esc($sheet['name']) ?>">
  <Table ss:DefaultRowHeight="20">
<?php foreach ($cols as $c): ?>
   <Column ss:Width="<?= $c['width'] ?? 100 ?>"/>
<?php endforeach; ?>

<?php foreach ($headerRows as $hr): ?>
<?= $hr ?>
<?php endforeach; ?>

   <Row ss:Height="28">
<?php foreach ($cols as $c): ?>
    <Cell ss:StyleID="header"><Data ss:Type="String"><?= $this->esc($c['label']) ?></Data></Cell>
<?php endforeach; ?>
   </Row>

<?php foreach ($rows as $i => $row): ?>
   <Row ss:Height="22">
<?php foreach ($row as $cell):
    $style = $cell['style'] ?? 'cell';
    $type  = $cell['type']  ?? 'String';
    $merge = isset($cell['merge']) ? ' ss:MergeAcross="'.$cell['merge'].'"' : '';
    $val   = $cell['value'] ?? '';
?>
    <Cell ss:StyleID="<?= $style ?>"<?= $merge ?>><Data ss:Type="<?= $type ?>"><?= $type === 'String' ? $this->esc((string)$val) : $val ?></Data></Cell>
<?php endforeach; ?>
   </Row>
<?php endforeach; ?>

   <Row ss:Height="6"><Cell ss:StyleID="spacer"/></Row>
   <Row ss:Height="22">
    <Cell ss:StyleID="footerBrand" ss:MergeAcross="<?= $colCount - 1 ?>"><Data ss:Type="String">T.C. S&#304;L&#304;VR&#304; BELED&#304;YES&#304; — SECAP Portal&#305;</Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:StyleID="footer" ss:MergeAcross="<?= $colCount - 1 ?>"><Data ss:Type="String"><?= $this->esc($opts['footer'] ?? '') ?></Data></Cell>
   </Row>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
<?php if ($freezeRow): ?>
   <FreezePanes/><FrozenNoSplit/>
   <SplitHorizontal><?= $freezeRow ?></SplitHorizontal>
   <TopRowBottomPane><?= $freezeRow ?></TopRowBottomPane>
   <ActivePane>2</ActivePane>
<?php endif; ?>
   <Print><ValidPrinterInfo/><PaperSizeIndex>9</PaperSizeIndex><Scale>65</Scale></Print>
  </WorksheetOptions>
<?php if ($freezeRow): ?>
  <AutoFilter x:Range="R<?= $freezeRow ?>C1:R<?= $freezeRow ?>C<?= $colCount ?>" xmlns="urn:schemas-microsoft-com:office:excel"/>
<?php endif; ?>
 </Worksheet>
<?php
        return ob_get_clean();
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    public function download(string $filename): void
    {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        echo $this->render();
        exit;
    }
}
