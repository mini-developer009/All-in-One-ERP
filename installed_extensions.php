<?php

/* List of installed additional extensions. If extensions are added to the list manually
	make sure they have unique and so far never used extension_ids as a keys,
	and $next_extension_id is also updated. More about format of this file yo will find in 
	FA extension system documentation.
*/

$next_extension_id = 14; // unique id for next installed extension

$installed_extensions = array (
  1 => 
  array (
    'name' => 'Inventory Items CSV Import',
    'package' => 'import_items',
    'version' => '2.4.0-3',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/import_items',
  ),
  2 => 
  array (
    'name' => 'Import Multiple Journal Entries',
    'package' => 'import_multijournalentries',
    'version' => '2.4.0-3',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/import_multijournalentries',
  ),
  3 => 
  array (
    'name' => 'Import Transactions',
    'package' => 'import_transactions',
    'version' => '2.4.0-6',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/import_transactions',
  ),
  4 => 
  array (
    'name' => 'Annual balance breakdown report',
    'package' => 'rep_annual_balance_breakdown',
    'version' => '2.4.0-4',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/rep_annual_balance_breakdown',
  ),
  5 => 
  array (
    'name' => 'Annual expense breakdown report',
    'package' => 'rep_annual_expense_breakdown',
    'version' => '2.4.0-2',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/rep_annual_expense_breakdown',
  ),
  6 => 
  array (
    'name' => 'Cash Flow Statement Report',
    'package' => 'rep_cash_flow_statement',
    'version' => '2.4.0-3',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/rep_cash_flow_statement',
  ),
  7 => 
  array (
    'name' => 'Dated Stock Sheet',
    'package' => 'rep_dated_stock',
    'version' => '2.4.0-1',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/rep_dated_stock',
  ),
  8 => 
  array (
    'name' => 'Inventory History',
    'package' => 'rep_inventory_history',
    'version' => '2.4.0-1',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/rep_inventory_history',
  ),
  9 => 
  array (
    'name' => 'Sales Summary Report',
    'package' => 'rep_sales_summary',
    'version' => '2.4.0-1',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/rep_sales_summary',
  ),
  10 => 
  array (
    'name' => 'Bank Statement w/ Reconcile',
    'package' => 'rep_statement_reconcile',
    'version' => '2.4.0-1',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/rep_statement_reconcile',
  ),
  11 => 
  array (
    'name' => 'Tax inquiry and detailed report on cash basis',
    'package' => 'rep_tax_cash_basis',
    'version' => '2.4.0-2',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/rep_tax_cash_basis',
  ),
  12 => 
  array (
    'name' => 'Check Printing based on Tom Hallman, USA',
    'package' => 'rep_check_print',
    'version' => '2.4.0-3',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/rep_check_print',
  ),
  13 => 
  array (
    'name' => 'FrontHrm',
    'package' => 'fronthrm',
    'version' => '2.4.0-10',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/FrontHrm',
  ),
);
