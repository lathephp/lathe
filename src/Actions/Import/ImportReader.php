<?php

namespace Lathe\Actions\Import;

use DateTimeInterface;
use Generator;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Streams the rows of a csv or xlsx file one at a time, numbered as the person sees them:
 * the header is row 1 and data starts at row 2. Only the first sheet of a workbook is read.
 */
class ImportReader
{
    public function __construct(protected string $path)
    {
        //
    }

    /**
     * The data rows, keyed by row number.
     *
     * @return Generator<int, list<mixed>>
     */
    public function rows(): Generator
    {
        $reader = strtolower(pathinfo($this->path, PATHINFO_EXTENSION)) === 'xlsx' ? new XlsxReader : new CsvReader;
        $reader->open($this->path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $number = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    if (++$number > 1) {
                        yield $number => array_values($row->toArray());
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * The header and the first rows, read without going through the rest of the file. A date
     * cell of a workbook reads as text, so the preview encodes as JSON.
     *
     * @return array{headers: list<string>, preview: list<list<mixed>>}
     */
    public function preview(int $rows = 5): array
    {
        $reader = strtolower(pathinfo($this->path, PATHINFO_EXTENSION)) === 'xlsx' ? new XlsxReader : new CsvReader;
        $reader->open($this->path);
        $headers = null;
        $preview = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn (mixed $cell) => $cell instanceof DateTimeInterface ? $cell->format('Y-m-d H:i:s') : $cell, array_values($row->toArray()));

                    if ($headers === null) {
                        $headers = array_map(fn (mixed $cell) => is_scalar($cell) ? trim((string) $cell) : '', $cells);
                    } elseif (count($preview) < $rows) {
                        $preview[] = $cells;
                    } else {
                        break;
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        $headers ??= [];

        // A header row of blank cells only counts as no header.
        if (array_filter($headers, fn (string $header) => $header !== '') === []) {
            $headers = [];
        }

        return ['headers' => $headers, 'preview' => $preview];
    }

    public function count(): int
    {
        $count = 0;

        foreach ($this->rows() as $row) {
            $count++;
        }

        return $count;
    }
}
