<?php
/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 * Version date 16-Nov-2015 
 */

require_once dirname(__FILE__) . '/config.php';

// Create array of all field validation types and their attributes
$allValTypes = getValTypes();
// Operator drop-down list (>, <, =, etc.)
print LongitudinalReports::outputLimiterOperatorDropdown($_POST['field_name'], '', $allValTypes);
// Value text box OR drop-down list (if multiple choice)
print LongitudinalReports::outputLimiterValueTextboxOrDropdown($_POST['field_name'], '');