<?php

namespace App\Exports;


use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;


class ExpenseReportExport implements  FromView, ShouldAutoSize, WithStyles,WithColumnWidths ,WithHeadings, WithEvents
{

    use Exportable;
    protected $data;

    public function __construct($data) {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('file-exports.expense-report', [
            'data' => $this->data,
        ]);
    }

    public function columnWidths(): array
    {
        return [
            // 'C' => 45,
        ];
    }

    public function styles(Worksheet $sheet) {
        $sheet->getStyle('A2:F2')->getFont()->setBold(true);
        $sheet->getStyle('A3:F3')->getFill()->applyFromArray([
            'fillType' => 'solid',
            'rotation' => 0,
            'color' => ['rgb' => '9F9F9F'],
        ]);

        $sheet->setShowGridlines(false);
        $styleArray = [
            'borders' => [
                'bottom' => ['borderStyle' => 'hair', 'color' => ['argb' => 'FFFF0000']],
                'top' => ['borderStyle' => 'hair', 'color' => ['argb' => 'FFFF0000']],
                'right' => ['borderStyle' => 'hair', 'color' => ['argb' => 'FF00FF00']],
                'left' => ['borderStyle' => 'hair', 'color' => ['argb' => 'FF00FF00']],
            ],
            'fillType' => 'solid',
            'rotation' => 0,
        ];
        $sheet->getStyle('A1:C1')->applyFromArray($styleArray);
        return [
            // Define the style for cells with data
            'A1:F'.$this->data['expenses']->count() +3 => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => '000000'], // Specify the color of the border (optional)
                    ],
                ],
            ],
        ];

    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->getStyle('A1:F1') // Adjust the range as per your needs
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getStyle('A2:C2')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getStyle('A3:C3')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getStyle('A4:C4')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $event->sheet->getStyle('A3:F'.$this->data['expenses']->count() +3)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getStyle('D2:F2')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);


                    $event->sheet->mergeCells('A1:F1');
                    $event->sheet->mergeCells('A2:C2');
                    $event->sheet->mergeCells('D2:F2');

                    $event->sheet->getDefaultRowDimension()->setRowHeight(30);
                    $event->sheet->getRowDimension(1)->setRowHeight(50);
                    $event->sheet->getRowDimension(2)->setRowHeight(100);
                },
        ];
    }
    public function headings(): array
    {
        return [
            '1'
        ];
    }
}

