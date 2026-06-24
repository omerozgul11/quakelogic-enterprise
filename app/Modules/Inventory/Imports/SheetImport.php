<?php

namespace App\Modules\Inventory\Imports;

/**
 * Marker import used with Excel::toArray() to read an uploaded spreadsheet as
 * raw rows (no heading transform), so the product importer can map columns
 * itself and keep any unrecognised ones.
 */
class SheetImport
{
}
