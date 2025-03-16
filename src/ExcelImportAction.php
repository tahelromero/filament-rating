<?php

namespace EightyNine\ExcelImport;

use EightyNine\ExcelImport\Concerns\HasExcelImportAction;
use Filament\Actions\Action;

class ExcelImportAction extends Action
{
    use HasExcelImportAction;

    protected string $importClass = DefaultImport::class;
}
