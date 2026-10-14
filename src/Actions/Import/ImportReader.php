<?php

namespace Lathe\Actions\Import;

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

    public function count(): int
    {
        $count = 0;

        foreach ($this->rows() as $row) {
            $count++;
        }

        return $count;
    }
}
