<?php
/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 * Version date 16-Nov-2015 
 */

require_once dirname(__FILE__) . '/config.php';

// Count errors
$errors = 0;

// Validate report_id and see if already exists
$report_id = (int)$_GET['report_id'];
if ($report_id != 0) {
	$report = LongitudinalReports::getReports($report_id);
	if (empty($report)) exit('0');
}

// Report title
$title = strip_tags(label_decode($_POST['__TITLE__']));
// User access rights
$user_access_users = $user_access_roles = $user_access_dags = array();
if (isset($_POST['user_access_users'])) {
	$user_access_users = $_POST['user_access_users'];
	if (!is_array($user_access_users)) $user_access_users = array($user_access_users);
}
if (isset($_POST['user_access_roles'])) {
	$user_access_roles = $_POST['user_access_roles'];
	if (!is_array($user_access_roles)) $user_access_roles = array($user_access_roles);
}
if (isset($_POST['user_access_dags'])) {
	$user_access_dags = $_POST['user_access_dags'];
	if (!is_array($user_access_dags)) $user_access_dags = array($user_access_dags);
}
$user_access = ($_POST['user_access_radio'] == 'SELECTED' 
				&& (count($user_access_users) + count($user_access_roles) + count($user_access_dags)) > 0) ? 'SELECTED' : 'ALL';
// Sort fields
$orderby_field1 = (isset($Proj->metadata[LongitudinalReports::getFieldFromEventField($_POST['sort'][0])])) ? $_POST['sort'][0] : '';
$orderby_sort1 = ($orderby_field1 == '') ? '' : $_POST['sortascdesc'][0];
$orderby_field2 = (isset($Proj->metadata[LongitudinalReports::getFieldFromEventField($_POST['sort'][1])])) ? $_POST['sort'][1] : '';
$orderby_sort2 = ($orderby_field2 == '') ? '' : $_POST['sortascdesc'][1];
$orderby_field3 = (isset($Proj->metadata[LongitudinalReports::getFieldFromEventField($_POST['sort'][2])])) ? $_POST['sort'][2] : '';
$orderby_sort3 = ($orderby_field3 == '') ? '' : $_POST['sortascdesc'][2];
// If the first or second sort field is blank, then skip it
if ($orderby_field2 == '' && $orderby_field3 != '') {
	$orderby_field2 = $orderby_field3;
	$orderby_sort2 = $orderby_sort3;
	$orderby_field3 = $orderby_sort3 = '';
}
if ($orderby_field1 == '' && $orderby_field2 != '') {
	$orderby_field1 = $orderby_field2;
	$orderby_sort1 = $orderby_sort2;
	$orderby_field2 = $orderby_field3;
	$orderby_sort2 = $orderby_sort3;
	$orderby_field3 = $orderby_sort3 = '';
}
// Options to include DAG names and/or survey fields in report
$outputDags = (isset($_POST['output_dags']) && $_POST['output_dags'] == 'on') ? '1' : '0';
$outputSurveyFields = (isset($_POST['output_survey_fields']) && $_POST['output_survey_fields'] == 'on') ? '1' : '0';

if (isset($_POST['output_schedule_dates'])) {
	$output_schedule_dates = $_POST['output_schedule_dates'];
	if (!is_array($output_schedule_dates)) $output_schedule_dates = array($output_schedule_dates);
}
if (isset($_POST['output_survey_urls'])) {
	$output_survey_urls = $_POST['output_survey_urls'];
	if (!is_array($output_survey_urls)) $output_survey_urls = array($output_survey_urls);
}

// Check for advanced logic or simple logic
$advanced_logic = '';
if (isset($_POST['advanced_logic']) && trim($_POST['advanced_logic']) != '') {
	$advanced_logic = $_POST['advanced_logic'];
} 

/*
// Set up all actions as a transaction to ensure everything is done here
db_query("SET AUTOCOMMIT=0");
db_query("BEGIN");

// Save report in reports table
if ($report_id != 0) {
	// Update
	$sqlr = $sql = "update redcap_reports set title = '".prep($title)."', user_access = '".prep($user_access)."', 
			orderby_field1 = ".checkNull($orderby_field1).", orderby_sort1 = ".checkNull($orderby_sort1).", 
			orderby_field2 = ".checkNull($orderby_field2).", orderby_sort2 = ".checkNull($orderby_sort2).", 
			orderby_field3 = ".checkNull($orderby_field3).", orderby_sort3 = ".checkNull($orderby_sort3).", 
			output_dags = ".checkNull($outputDags).", output_survey_fields = ".checkNull($outputSurveyFields).",
			advanced_logic = ".checkNull($advanced_logic)."
			where project_id = ".PROJECT_ID." and report_id = $report_id";
	if (!db_query($sql)) $errors++;
} else {
	// Get next report_order number
	$q = db_query("select max(report_order) from redcap_reports where project_id = ".PROJECT_ID);
	$new_report_order = db_result($q, 0);
	$new_report_order = ($new_report_order == '') ? 1 : $new_report_order+1;
	// Insert
	$sqlr = $sql = "insert into redcap_reports (project_id, title, user_access, orderby_field1, orderby_sort1, orderby_field2, 
			orderby_sort2, orderby_field3, orderby_sort3, output_dags, output_survey_fields, report_order)
			values (".PROJECT_ID.", '".prep($title)."', '".prep($user_access)."', ".checkNull($orderby_field1).",
			".checkNull($orderby_sort1).", ".checkNull($orderby_field2).", ".checkNull($orderby_sort2).",
			".checkNull($orderby_field3).", ".checkNull($orderby_sort3).", ".checkNull($outputDags).", 
			".checkNull($outputSurveyFields).", $new_report_order)";
	if (!db_query($sql)) $errors++;
	// Set new report_id
	$report_id = db_insert_id();
}

// USER ACCESS
$sql = "delete from redcap_reports_access_users where report_id = $report_id";
if (!db_query($sql)) $errors++;
foreach ($user_access_users as $this_user) {
	$sql = "insert into redcap_reports_access_users values ($report_id, '".prep($this_user)."')";
	if (!db_query($sql)) $errors++;
}
$sql = "delete from redcap_reports_access_roles where report_id = $report_id";
if (!db_query($sql)) $errors++;
foreach ($user_access_roles as $this_role_id) {
	$this_role_id = (int)$this_role_id;
	$sql = "insert into redcap_reports_access_roles values ($report_id, '".prep($this_role_id)."')";
	if (!db_query($sql)) $errors++;
}
$sql = "delete from redcap_reports_access_dags where report_id = $report_id";
if (!db_query($sql)) $errors++;
foreach ($user_access_dags as $this_group_id) {
	$this_group_id = (int)$this_group_id;
	$sql = "insert into redcap_reports_access_dags values ($report_id, '".prep($this_group_id)."')";
	if (!db_query($sql)) $errors++;
}
// FIELDS & LIMITERS
$sql = "delete from redcap_reports_fields where report_id = $report_id";
if (!db_query($sql)) $errors++;
$field_order = 1;
foreach ($_POST['field'] as $this_field) {
	if ($this_field == '' || !isset($Proj->metadata[$this_field])) continue;
	$sql = "insert into redcap_reports_fields (report_id, field_name, field_order) 
			values ($report_id, '".prep($this_field)."', ".($field_order++).")";
	if (!db_query($sql)) $errors++;
}*/
// Only do simple filter logic if not have advanced logic defined
if ($advanced_logic == '') {
    
        $limiterFields = array();
        
	foreach ($_POST['limiter'] as $key=>$limiterField) 
	{
            if ($limiterField !== '') {
                $limiterAttr = array();
                $this_field = LongitudinalReports::getFieldFromEventField($limiterField);
		
                if ($this_field == '' || !isset($Proj->metadata[$this_field])) continue;
		// Get event_id
/*		$limiter_event_id = ($longitudinal && isset($_POST['limiter_event'][$key])) ? $_POST['limiter_event'][$key] : '';	*/
		
                // Check if field is a Text field with MDY or DMY date validation. If so, convert to YMD format before saving.
		$limiter_value = $_POST['limiter_value'][$key];
		if ($limiter_value != '' && isset($Proj->metadata[$this_field]) && $Proj->metadata[$this_field]['element_type'] == 'text' 
			&& substr($Proj->metadata[$this_field]['element_validation_type'], 0, 4) == "date"
			&& (substr($Proj->metadata[$this_field]['element_validation_type'], -4) == "_dmy" || substr($Proj->metadata[$this_field]['element_validation_type'], -4) == "_mdy"))
		{
			$thisValType = $Proj->metadata[$this_field]['element_validation_type'];
			if (in_array($thisValType, array('date_mdy', 'datetime_mdy', 'datetime_seconds_mdy', 'date_dmy', 'datetime_dmy', 'datetime_seconds_dmy'))) {
				$limiter_value = DateTimeRC::datetimeConvert($limiter_value, substr($thisValType, -3), 'ymd');
			}
		}
                $limiterAttr['field_name'] = $limiterField;
                $limiterAttr['limiter_group_operator'] = $_POST['limiter_group_operator'][$key];
                $limiterAttr['limiter_operator'] = $_POST['limiter_operator'][$key];
                $limiterAttr['limiter_value'] = $limiter_value;
                
                $limiterFields[] = $limiterAttr;
            }
/*		$sql = "insert into redcap_reports_fields (report_id, field_name, field_order, limiter_group_operator, limiter_event_id, 
				limiter_operator, limiter_value) values ($report_id, '".prep($this_field)."', ".($field_order++).",
				".checkNull($_POST['limiter_group_operator'][$key]).", ".checkNull($limiter_event_id).", 
				".checkNull($_POST['limiter_operator'][$key]).", '".prep($limiter_value)."')";
		if (!db_query($sql)) $errors++;
*/	}
}/*
$sql = "delete from redcap_reports_filter_events where report_id = $report_id";
if (!db_query($sql)) $errors++;
if (isset($_POST['filter_events'])) {
	if (!is_array($_POST['filter_events'])) $_POST['filter_events'] = array($_POST['filter_events']);
	foreach ($_POST['filter_events'] as $this_event_id) {
		$this_event_id = (int)$this_event_id;
		$sql = "insert into redcap_reports_filter_events values ($report_id, '".prep($this_event_id)."')";
		if (!db_query($sql)) $errors++;
	}
}
$sql = "delete from redcap_reports_filter_dags where report_id = $report_id";
if (!db_query($sql)) $errors++;
if (isset($_POST['filter_dags'])) {
	if (!is_array($_POST['filter_dags'])) $_POST['filter_dags'] = array($_POST['filter_dags']);
	foreach ($_POST['filter_dags'] as $this_group_id) {
		$this_group_id = (int)$this_group_id;
		$sql = "insert into redcap_reports_filter_dags values ($report_id, '".prep($this_group_id)."')";
		if (!db_query($sql)) $errors++;
	}
}*/
if (isset($_POST['filter_dags'])) {
	$filter_dags = $_POST['filter_dags'];
	if (!is_array($filter_dags)) $filter_dags = array($filter_dags);
}


// Save report to Longitudinal Reports Data project

if ($report_id == 0) {
    // Adding new report - get eventid, next report id and next project report_order
    list($eventId, $report_id, $report_order) = LongitudinalReports::getNewReportIdAndOrder($_GET['pid']);

    
} else {
    // Existing report - just get eventidto include in api post
    $thisRpt = REDCap::getData(
                    LR_REPORT_DATA_PROJECT_ID, 
                    'array',  // return_format
                    $report_id,     // records 
                    'report_order');// fields 

    $eventId = current(array_keys($thisRpt[$report_id]));
    $report_order = $thisRpt[$report_id][$eventId]['report_order'];
}

$fields = array();
foreach ($_POST['field'] as $f) {
    if (isset($f) && trim($f) !== '') {
        $fields[] = $f;
    }
}

$reportData = array();
$now = new DateTime();

$reportData['report_id'] = $report_id;
$reportData['project_id'] = $Proj->project_id;
$reportData['title'] = $_POST['__TITLE__'];
$reportData['report_order'] = $report_order;
$reportData['user_access'] = $user_access;
$reportData['user_access_dags'] = json_encode($user_access_dags);
$reportData['user_access_roles'] = json_encode($user_access_roles);
$reportData['user_access_users'] = json_encode($user_access_users);
$reportData['fields'] = json_encode($fields);
$reportData['output_dags'] = $_POST['output_dags'];
$reportData['output_survey_fields'] = $_POST['output_survey_fields'];
$reportData['output_schedule_dates'] = json_encode($output_schedule_dates);
$reportData['output_survey_urls'] = json_encode($output_survey_urls);
$reportData['limiter_fields'] = json_encode($limiterFields);
$reportData['advanced_logic'] = $_POST['advanced_logic'];
$reportData['filter_dags'] = json_encode($filter_dags);
$reportData['orderby_field1'] = $orderby_field1;
$reportData['orderby_sort1'] = $orderby_sort1;
$reportData['orderby_field2'] = $orderby_field2;
$reportData['orderby_sort2'] = $orderby_sort2;
$reportData['orderby_field3'] = $orderby_field3;
$reportData['orderby_sort3'] = $orderby_sort3;
$reportData['update_by'] = $userid;
$reportData['update_at'] = $now->format('Y-m-d H:i:s');
$reportData['report_complete'] = '2';

$success = true;

$data = array();
$data[] = $reportData; // Can handle multiple records - not needed here

$success = LongitudinalReports::save($data);


// If there are errors, then roll back all changes
if (!$success) { //$errors > 0) {
	// Errors occurred, so undo any changes made
//	db_query("ROLLBACK");
	// Return '0' for error
	exit('0');
} else {	
	// Logging
	$log_descrip = ($_GET['report_id'] != 0) ? "Edit longitudinal report" : "Create longitudinal report";
	REDCap::logEvent($log_descrip, "report_id = $report_id: ".print_r($reportData, true));
	// Commit changes
//	db_query("COMMIT");
	// Response
	$dialog_title = 	RCView::img(array('src'=>'tick.png', 'style'=>'vertical-align:middle')) .
						RCView::span(array('style'=>'color:green;vertical-align:middle'), $lang['report_builder_01']);
	$dialog_content = 	RCView::div(array('style'=>'font-size:14px;'),
							$lang['report_builder_73'] . " \"" . 
							RCView::span(array('style'=>'font-weight:bold;'), RCView::escape($title)) . 
							"\" " . $lang['report_builder_74']
						);
	// Output JSON response
	print json_encode(array('report_id'=>$report_id, 'newreport'=>($_GET['report_id'] == 0 ? 1 : 0), 
							'title'=>$dialog_title, 'content'=>$dialog_content));
}
