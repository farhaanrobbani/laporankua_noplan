<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class WordService
{
    /**
     * @param  array{title: string, headings: string[], rows: array<int, array<string, mixed>>, total: int, generated_at: string}  $dataset
     */
    public function generate(array $dataset, string $absolutePath): void
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        $section->addText($dataset['title'], ['bold' => true, 'size' => 16]);
        $section->addText('Total '.$dataset['total'].' baris · Dibuat '.$dataset['generated_at'], ['size' => 9, 'color' => '666666']);
        $section->addTextBreak();

        $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999']);

        $table->addRow();
        foreach ($dataset['headings'] as $heading) {
            $table->addCell(2000)->addText($heading, ['bold' => true, 'size' => 10]);
        }

        foreach ($dataset['rows'] as $row) {
            $table->addRow();
            foreach ($dataset['headings'] as $heading) {
                $table->addCell(2000)->addText((string) ($row[$heading] ?? ''), ['size' => 10]);
            }
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);
    }
}
