<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    /**
     * @param  array{title: string, headings: string[], rows: array<int, array<string, mixed>>, total: int, generated_at: string}  $dataset
     */
    public function generate(array $dataset, string $absolutePath, string $orientation = 'portrait'): void
    {
        Pdf::loadView('reports.pdf', ['dataset' => $dataset])
            ->setPaper('a4', $orientation)
            ->save($absolutePath);
    }
}
