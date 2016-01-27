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
//$report = LongitudinalReports::getReports($report_id);
//if (empty($report)) exit('0');

// Copy the report and return the new report_id
$new_report_id = LongitudinalReports::copyReport($report_id);
if ($new_report_id === false) exit('0');

REDCap::logEvent("Copy longitudinal report", "report_id = $report_id, new_report_id = $new_report_id");

// Return HTML of updated report list and report_id
print json_encode(array('new_report_id'=>$new_report_id, 'html'=> LongitudinalReports::renderReportList()));