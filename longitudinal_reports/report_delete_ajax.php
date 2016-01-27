<?php
/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 * Version date 16-Nov-2015 
 */

require_once dirname(__FILE__) . '/config.php';

// Validate id
if (!isset($_POST['report_id'])) exit('0');
$report_id = $_POST['report_id'];

$success = LongitudinalReports::deleteReport($report_id);

if ($success) {
    REDCap::logEvent("Delete longitudinal report", "report_id = $report_id");
}
print ($success === false) ? '0' : '1';