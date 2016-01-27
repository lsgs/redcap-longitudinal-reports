<?php
/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 * Version date 16-Nov-2015 
 */

require_once dirname(__FILE__) . '/config.php';

// Validate ids
if (!isset($_POST['report_ids'])) exit('0');

// Remove comma on end
if (substr($_POST['report_ids'], -1) == ',') $_POST['report_ids'] = substr($_POST['report_ids'], 0, -1);

// Create array of report_ids
$new_report_ids = explode(",", $_POST['report_ids']);

// Get existing list of reports to validate and compare number of items
$old_report_ids = array_keys(LongitudinalReports::getReportNames());

// Determine if any new report_ids were maliciously added
$extra_report_ids = array_diff($new_report_ids, $old_report_ids);
if (!empty($extra_report_ids)) exit('0');

// Determine if any new reports were added by another user simultaneously and are not in this list
$append_report_ids = array_diff($old_report_ids, $new_report_ids);

/*// Set all report_orders to null
$sql = "update redcap_reports set report_order = null where project_id = $project_id";
db_query($sql);*/
// Loop through report_ids and set new report_order
$report_order = 1;
$import = array();
foreach ($new_report_ids as $this_report_id) {
/*	$sql = "update redcap_reports set report_order = ".$report_order++." 
			where project_id = $project_id and report_id = $this_report_id";
	db_query($sql);*/
    $rpt = array();
    $rpt['report_id'] = $this_report_id;
    $rpt['report_order'] = $report_order++;
    $import[] = $rpt;
}
// Deal with orphaned report_ids added simultaneously by other user while this user reorders
foreach ($append_report_ids as $this_report_id) {
/*	$sql = "update redcap_reports set report_order = ".$report_order++." 
			where project_id = $project_id and report_id = $this_report_id";
	db_query($sql);*/
    $rpt = array();
    $rpt['report_id'] = $this_report_id;
    $rpt['report_order'] = $report_order++;
    $import[] = $rpt;
}

$success = LongitudinalReports::save($import);

if (!($success)) exit('0');

// Logging
REDCap::logEvent("Reorder longitudinal reports", "report_id = ".$_POST['report_ids']);

// Return Value: If there are some extra reports that exist that are not currently in the list, then refresh the user's page
print (!empty($append_report_ids)) ? '2' : '1';
