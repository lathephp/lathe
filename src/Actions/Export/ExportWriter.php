<?php

namespace Lathe\Actions\Export;

use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/**
 * Writes rows to a local temp file, CSV or XLSX. Every value is a text cell, so a value
 * that starts with "=" stays text and never becomes a spreadsheet formula.
 */
class ExportWriter
{
    protected string $path;

    protected WriterInterface $writer;

    protected bool $open = true;

    /**
     * @param  'csv'|'xlsx'  $type
     */
    public function __construct(string $type)
    {
        $path = tempnam(sys_get_temp_dir(), 'lathe-export-');

        if ($path === false) {
            throw new RuntimeException('No temp file could be made for the export.');
        }

        $this->path = $path;
        $this->writer = $type === 'xlsx' ? new XlsxWriter : new CsvWriter;
        $this->writer->openToFile($path);
    }

    /**
     * @param  list<string>  $values
     */
    public function addRow(array $values): void
    {
        $this->writer->addRow(new Row(array_map(fn (string $value) => new StringCell($value), $values)));
    }

    /**
     * Closes the file and answers its path.
     */
    public function close(): string
    {
        if ($this->open) {
            $this->writer->close();
            $this->open = false;
        }

        return $this->path;
    }

    /**
     * Closes and removes the temp file, written or not.
     */
    public function delete(): void
    {
        if (is_file($this->close())) {
            unlink($this->path);
        }
    }
}
