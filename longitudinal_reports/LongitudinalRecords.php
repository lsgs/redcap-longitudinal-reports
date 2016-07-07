<?php
/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 * Version date 16-Nov-2015 
 */

/**
 * LongitudinalRecords class
 * Altered for Longitudinal Reports from redcap_v6.4.3/Classes/Records.php 
 */
class LongitudinalRecords
{
	// Return count of all records in project
	public static function getRecordCount()
	{
		global $Proj;
		// If RECORD_COUNT constant is defined, then return it, else query the data table
		if (defined("RECORD_COUNT")) {
			// Return count
			return RECORD_COUNT;
		} else {
			// Query to get record count from table
			$sql = "select count(distinct(record)) from redcap_data where project_id = ".$Proj->project_id." 
					and field_name = '" . prep($Proj->table_pk) . "'";
			$q = db_query($sql);
			if (!$q) return false;
			// Set record count
			$record_count = db_result($q, 0);
			// Define the result as the constant RECORD_COUNT only for project-level pages
			// (do NOT do this for the cron job OR in a plugin, which likely uses this for several different projects)
			if (!defined("CRON") && !defined("PLUGIN") && defined("PROJECT_ID")) {
				define("RECORD_COUNT", $record_count);
			}
			// Return count
			return $record_count;
		}
	}
	
	
	// Return list of all record names as an array for EACH arm (assuming multiple arms)
	public static function getRecordListPerArm($records_input=array())
	{
		global $Proj;
		// Put list in array (arm is first key and record name is second key)
		$records = array();
		// Query to get resources from table
		$sql = "select distinct a.arm_id, a.arm_num, d.record 
				from redcap_data d, redcap_events_metadata e, redcap_events_arms a 
				where a.project_id = ".PROJECT_ID." and a.project_id = d.project_id 
				and a.arm_id = e.arm_id and e.event_id = d.event_id and d.field_name = '" . prep($Proj->table_pk) . "'";
		if (!empty($records_input)) $sql .= " and d.record in (" . prep_implode($records_input) . ")";
		$q = db_query($sql);
		if (!$q) return false;
		if (db_num_rows($q) > 0) {
			while ($row = db_fetch_assoc($q)) {
				// Arm is first key and record name is second key in array
				$records[$row['arm_num']][$row['record']] = true;
			}
		}
		// Sort by arm
		ksort($records);
		foreach ($records as $this_arm=>&$records2) {
			// Sort by record name within each arm
			natcaseksort($records2);
		}
		// Return record list
		return $records;
	}
	
	// Return list of all record names as an "array" or as a "csv" string.
	// Can also set ordering by record and whether or not to sql-escape each record name surrounding by apostrophes.
	public static function getRecordList($project_id=null, $filterByGroupID=null, $filterByDDEuser=false)
	{
		global $double_data_entry, $user_rights;
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;
		// Determine if using Double Data Entry and if DDE user (if so, add --# to end of Study ID when querying data table)
		$isDDEuser = false; // default
		if ($filterByDDEuser) {
			$isDDEuser = ($double_data_entry && isset($user_rights['double_data']) && $user_rights['double_data'] != 0);
		}
		// Set "record" field in query if a DDE user
		$record_dde_field = ($isDDEuser) ? "substr(record,1,length(record)-3) as record" : "record";
		$record_dde_where = ($isDDEuser) ? "and record like '%--{$user_rights['double_data']}'" : "";
		// Filter by DAG, if applicable
		$dagSql = "";
		if ($filterByGroupID != '') {
			$dagSql = "and record in (" . pre_query("SELECT record FROM redcap_data where project_id = $project_id 
					   and field_name = '__GROUPID__' AND value = '".prep($filterByGroupID)."'") . ")"; 
		}
		// Put list in array
		$records = array();
		// Query to get resources from table
		$sql = "select distinct $record_dde_field from redcap_data where project_id = $project_id 
				and field_name = '" . prep(self::getTablePK($project_id)) . "' $record_dde_where $dagSql";
		$q = db_query($sql);
		if (!$q) return false;
		if (db_num_rows($q) > 0) {
			while ($row = db_fetch_assoc($q)) {
				// Un-html-escape record name (just in case)
				$row['record'] = html_entity_decode($row['record'], ENT_QUOTES);
				// Add record name to array
				$records[] = $row['record'];
			}
		}
		// Order records
		natcasesort($records);
		// Return record list
		return array_values($records);
	}
	
	
	// Return name of record identifier variable (i.e. "table_pk") in a given project
	public static function getTablePK($project_id=null)
	{
		// First, if project-level variables are defined, then there's no need to query the database table
		if (defined('PROJECT_ID') && !defined('PLUGIN')) {
			// Get table_pk from global scope variable UNLESS we're in a plugin, in which case 
			// we can't assume the $Proj is the right project we need for this (e.g. if using getData(project_id))
			global $Proj;
			$metadata_fields = array_keys($Proj->metadata);
			return $metadata_fields[0];
		}
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;
		// Query metadata table
		$sql = "select field_name from redcap_metadata where project_id = $project_id 
				order by field_order limit 1";
		$q = db_query($sql);
		if ($q && db_num_rows($q) > 0) {
			// Return field name
			return db_result($q, 0);
		} else {
			// Return false is query fails or doesn't exist
			return false;
		}
	}
	
	
	// Get list of all records (or specific ones) with their Form Status for all forms/events
	// If user is in a DAG, then limits results to only their DAG.
	// if user is a DDE user, then limits results to only their DDE records (i.e. ending in --1 or --2).
	public static function getFormStatus($project_id=null, $records=array())
	{
		global $user_rights, $double_data_entry;
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;
		// Get array list of form_names
		$allForms = self::getFormNames($project_id);
		// Get table_pk
		$table_pk = self::getTablePK($project_id);
		// Determine if using Double Data Entry and if DDE user (if so, add --# to end of Study ID when querying data table)
		$isDDEuser = ($double_data_entry && isset($user_rights['double_data']) && $user_rights['double_data'] != 0);
		// Create "where" clause for records provided, if provided
		$recordSql = (is_array($records) && !empty($records)) ? "and d.record in (" . prep_implode($records) . ")" : "";
		// Limit by DAGs, if in a DAG
		$dagSql = "";
		if (isset($user_rights['group_id']) && $user_rights['group_id'] != "") {
			$dagSql = "and d.record in (" . pre_query("SELECT record FROM redcap_data where project_id = $project_id 
					   and field_name = '__GROUPID__' AND value = '".$user_rights['group_id']."'") . ")"; 
		}
		// Set "record" field in query if a DDE user
		$record_dde_where = ($isDDEuser) ? "and d.record like '%--{$user_rights['double_data']}'" : "";
		// Query to get resources from table
		$sql = "select distinct d.record, d.event_id, m.form_name, if(d2.value is null, '0', d2.value) as value 
				from (redcap_data d, redcap_metadata m) left join redcap_data d2 
				on d2.project_id = m.project_id and d2.record = d.record and d2.event_id = d.event_id 
				and d2.field_name = concat(m.form_name, '_complete')
				where d.project_id = $project_id and d.project_id = m.project_id and m.element_type != 'calc' and m.field_name != '$table_pk'
				and d.field_name = m.field_name and m.form_name in (".prep_implode($allForms).")
				$recordSql $dagSql $record_dde_where
				order by m.field_order";
		$q = db_query($sql);
		if (!$q) return false;
		// Array to collect the record data
		$data = array();
		while ($row = db_fetch_assoc($q)) 
		{
			// If record is not in the array yet, prefill forms with blanks
			if (!isset($data[$row['record']][$row['event_id']])) {
				foreach ($allForms as $this_form) {
					$data[$row['record']][$row['event_id']][$this_form] = '';
				}
			}
			// Add the form values to array (ignore table_pk value since it was only used as a record placeholder anyway)
			$data[$row['record']][$row['event_id']][$row['form_name']] = $row['value'];
		}
		// Order by record
		natcaseksort($data);
		// Return array of form status data for records
		return $data;		
	}
	
	
	// Return form_names as array of all instruments in a given project
	public static function getFormNames($project_id=null)
	{
		// First, if project-level variables are defined, then there's no need to query the database table
		if (defined('PROJECT_ID')) {
			// Get table_pk from global scope variable
			global $Proj;
			return array_keys($Proj->forms);
		}
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;
		// Query metadata table
		$sql = "select distinct form_name from redcap_metadata where project_id = $project_id 
				order by field_order";
		$q = db_query($sql);
		if (!$q) return false;
		// Return form_names
		$forms = array();
		while ($row = db_fetch_assoc($q)) {
			$forms[] = $row['form_name'];
		}
		return $forms;
	}
	
	
	// Return the Data Access Group group_id for a record. If record not in a DAG, return false.
	public static function getRecordGroupId($project_id=null, $record=null)
	{
		// Verify project_id as numeric
		if (!is_numeric($project_id)) return false;
		// Make sure record is not null
		if ($record == null) return false;
		// Query data table
		$sql = "select d.value from redcap_data d, redcap_data_access_groups g 
				where d.project_id = $project_id and g.project_id = d.project_id and d.record = '".prep($record)."'
				and d.field_name = '__GROUPID__' and d.value = g.group_id limit 1";
		$q = db_query($sql);
		if (!$q || ($q && !db_num_rows($q))) return false;
		// Get group_id
		$group_id = db_result($q, 0);
		// Return group_id
		return $group_id;
	}

	// Obtain custom record label & secondary unique field labels for ALL records.
	// Limit by array of record names. If provide $records parameter as a single record string, then return string (not array).
	// Return array with record name as key and label as value.
	// If $arm == 'all', then get labels for the first event in EVERY arm (assuming multiple arms),
	// and also return 
	public static function getCustomRecordLabelsSecondaryFieldAllRecords($records=array(), $removeHtml=false, $arm=null, $boldSecondaryPkValue=false, $cssClass='crl')
	{
		global $is_child, $secondary_pk, $custom_record_label, $Proj;
		// Determine which arm to pull these values for
		if ($arm == 'all' && $Proj->longitudinal && $Proj->multiple_arms) {
			// If project has more than one arm, then get first event_id of each arm
			$event_ids = array();
			foreach (array_keys($Proj->events) as $this_arm) {
				$event_ids[] = $Proj->getFirstEventIdArm($this_arm);
			}
		} else {
			// Get arm
			if ($arm === null) $arm = getArm();
			// Get event_id of first event of the given arm
			$event_ids = array($Proj->getFirstEventIdArm(is_numeric($arm) ? $arm : getArm()));
		}
		// Place all records/labels in array
		$extra_record_labels = array();
		// If $records is a string, then convert to array
		$singleRecordName = null;
		if (!is_array($records)) {
			$singleRecordName = $records;
			$records = array($records);
		}
		// Set flag to limit records
		$limitRecords = !empty($records);
		// Customize the Record ID pulldown menus using the SECONDARY_PK appended on end, if set.
		if ($secondary_pk != '' && !$is_child)
		{
			// Get validation type of secondary unique field
			$val_type = $Proj->metadata[$secondary_pk]['element_validation_type'];
			$convert_date_format = (substr($val_type, 0, 5) == 'date_' && (substr($val_type, -4) == '_mdy' || substr($val_type, -4) == '_mdy'));
			// Set secondary PK field label
			$secondary_pk_label = $Proj->metadata[$secondary_pk]['element_label'];
			// PIPING: Obtain saved data for all piping receivers used in secondary PK label
			if (strpos($secondary_pk_label, '[') !== false && strpos($secondary_pk_label, ']') !== false) {
				// Get fields in the label
				$secondary_pk_label_fields = array_keys(getBracketedFields($secondary_pk_label, true, true, true));
				// If has at least one field piped in the label, then get all the data for these fields and insert one at a time below
				if (!empty($secondary_pk_label_fields)) {
					$piping_record_data = Records::getData('array', $records, $secondary_pk_label_fields, $event_ids);
				}
			}
			// Get back-end data for the secondary PK field
			$sql = "select record, event_id, value from redcap_data 
					where project_id = ".PROJECT_ID." and field_name = '$secondary_pk' 
					and event_id in (" . prep_implode($event_ids) . ")";
			if ($limitRecords) {
				$sql .= " and record in (" . prep_implode($records) . ")";
			}
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) 
			{
				// Set the label for this loop (label may be different if using piping in it)
				if (isset($piping_record_data)) {
					// Piping: pipe record data into label for each record
					$this_secondary_pk_label = Piping::replaceVariablesInLabel($secondary_pk_label, $row['record'], $event_ids, $piping_record_data);
				} else {
					// Static label for all records
					$this_secondary_pk_label = $secondary_pk_label;
				}
				// If the secondary unique field is a date/time field in MDY or DMY format, then convert to that format
				if ($convert_date_format) {
					$row['value'] = DateTimeRC::datetimeConvert($row['value'], 'ymd', substr($val_type, -3));
				}
				// Set text value
				$this_string = "(" . remBr($this_secondary_pk_label . " " . 
							   ($boldSecondaryPkValue ? "<b>" : "") .
							   filter_tags(label_decode($row['value']))) . 
							   ($boldSecondaryPkValue ? "</b>" : "") . 
							   ")";
				// Add HTML around string (unless specified otherwise)
				$extra_record_labels[$Proj->eventInfo[$row['event_id']]['arm_num']][$row['record']] = ($removeHtml) ? $this_string : RCView::span(array('class'=>$cssClass), $this_string);
			}
			db_free_result($q);
		}
		// [Retrieval of ALL records] If Custom Record Label is specified (such as "[last_name], [first_name]"), then parse and display
		// ONLY get data from FIRST EVENT
		if (!empty($custom_record_label)) 
		{
			// Loop through each event (will only be one UNLESS we are attempting to get label for multiple arms)
			$customRecordLabelsArm = array();
			foreach ($event_ids as $this_event_id) {
				$customRecordLabels = getCustomRecordLabels($custom_record_label, $this_event_id, ($singleRecordName ? $records[0]: null));
				if (!is_array($customRecordLabels)) $customRecordLabels = array($records[0]=>$customRecordLabels);
				$customRecordLabelsArm[$Proj->eventInfo[$this_event_id]['arm_num']] = $customRecordLabels;
			}
			foreach ($customRecordLabelsArm as $this_arm=>&$customRecordLabels)
			{
				foreach ($customRecordLabels as $this_record=>$this_custom_record_label)
				{
					// If limiting by records, ignore if not in $records array
					if ($limitRecords && !in_array($this_record, $records)) continue;
					// Set text value
					$this_string = remBr(filter_tags(label_decode($this_custom_record_label)));
					// Add initial space OR add placeholder
					if (isset($extra_record_labels[$this_arm][$this_record])) {
						$extra_record_labels[$this_arm][$this_record] .= ' ';
					} else {
						$extra_record_labels[$this_arm][$this_record] = '';
					}
					// Add HTML around string (unless specified otherwise)
					$extra_record_labels[$this_arm][$this_record] .= ($removeHtml) ? $this_string : RCView::span(array('class'=>$cssClass), $this_string);
				}
			}
		}
		// If we're not collecting multiple arms here, then remove arm key
		if ($arm != 'all') {
			$extra_record_labels = array_shift($extra_record_labels);
		}
		// Return string (single record only)
		if ($singleRecordName != null) {
			return (isset($extra_record_labels[$singleRecordName])) ? $extra_record_labels[$singleRecordName] : '';
		} else {
			// Return array 
			return $extra_record_labels;
		}
	}

	// Make sure that there is a case sensitivity issue with the record name. Check value with back-end value.
	// Return the true back-end value as it is already stored.
	public static function checkRecordNameCaseSensitive($record)
	{
		global $table_pk;
		// Make sure record is a string
		$record = "$record";
		// Query to get back-end record name
		$sql = "select trim(record) from redcap_data where project_id = " . PROJECT_ID . " and field_name = '$table_pk' 
				and record = '" . prep($record) . "' limit 1";
		$q = db_query($sql);
		if (db_num_rows($q) > 0)
		{
			$backEndRecordName = "".db_result($q, 0);
			if ($backEndRecordName != "" && $backEndRecordName !== $record)
			{
				// They don't match, return the back-end value.
				return $backEndRecordName;
			}
		}
		// Return same value submitted. Trim it, just in case.
		return trim($record);
	}
	
	/**
	 * GET DATA FOR RECORDS
	 * [@param int $project_id - (optional) Manually supplied project_id for this project.]
	 * @param string $returnFormat - Default 'array'. Return record data in specified format (array, csv, json, xml).
	 * @param string/array $records - if provided as a string, will convert to an array internally.
	 * @param string/array $fields - if provided as a string, will convert to an array internally.
	 * @param string/array $events - if provided as a string, will convert to an array internally.
	 * @param string/array $groups - if provided as a string, will convert to an array internally.
	 * @param bool $combine_checkbox_values is only an option for $returnFormat csv, json, and xml, in which it determines whether 
	 * checkbox option values are returned as multiple fields with triple underscores or as a combined single field with all *checked* 
	 * options as comma-delimited (e.g., "1,3,4" if only choices 1, 3, and 4 are checked off).
	 * NOTE: 'array' returnFormat will always have event_id as 2nd key and will always have checkbox options as a sub-array 
	 * for each given checkbox field.
	 */
	public static function getData()
	{
		global $salt, $reserved_field_names, $lang, $user_rights, $redcap_version, $datetime_format;
		// Get function arguments
		$args = func_get_args();
		// Make sure we have a project_id
		if (!is_numeric($args[0]) && !defined("PROJECT_ID")) throw new Exception('No project_id provided!');
		// If first parameter is numerical, then assume it is $project_id and that second parameter is $returnFormat
		if (is_numeric($args[0])) {
			$project_id = $args[0];
			$returnFormat = (isset($args[1])) ? $args[1] : 'array';
			$records = (isset($args[2])) ? $args[2] : array();
			$fields = (isset($args[3])) ? $args[3] : array();
			$events = (isset($args[4])) ? $args[4] : array();
			$groups = (isset($args[5])) ? $args[5] : array();
			$combine_checkbox_values = (isset($args[6])) ? $args[6] : false;
			$outputDags = (isset($args[7])) ? $args[7] : false;
			$outputSurveyFields = (isset($args[8])) ? $args[8] : false;
			$filterLogic = (isset($args[9])) ? $args[9] : false;
			$outputAsLabels = (isset($args[10])) ? $args[10] : false;
			$outputCsvHeadersAsLabels = (isset($args[11])) ? $args[11] : false;
			$hashRecordID = (isset($args[12])) ? $args[12] : false;
			$dateShiftDates = (isset($args[13])) ? $args[13] : false;
			$dateShiftSurveyTimestamps = (isset($args[14])) ? $args[14] : false;
			$sortArray = (isset($args[15])) ? $args[15] : array();
			$removeLineBreaksInValues = (isset($args[16])) ? $args[16] : false;
			$replaceFileUploadDocId = (isset($args[17])) ? $args[17] : false;
			$returnIncludeRecordEventArray = (isset($args[18])) ? $args[18] : false;
			$orderFieldsAsSpecified = (isset($args[19])) ? $args[19] : false;
			$outputSurveyIdentifier = (isset($args[20])) ? $args[20] : $outputSurveyFields;
			$outputCheckboxLabel = (isset($args[21])) ? $args[21] : false;
                        $outputScheduleDates = (isset($args[22])) ? $args[22] : false;
                        $outputSurveyUrls = (isset($args[23])) ? $args[23] : false;
			// Instantiate object containing all project information
			$Proj = new Project($project_id);
			$longitudinal = $Proj->longitudinal;
			$table_pk = $Proj->table_pk;
		} else {
			$project_id = PROJECT_ID;
			$returnFormat = (isset($args[0])) ? $args[0] : 'array';
			$records = (isset($args[1])) ? $args[1] : array();
			$fields = (isset($args[2])) ? $args[2] : array();
			$events = (isset($args[3])) ? $args[3] : array();
			$groups = (isset($args[4])) ? $args[4] : array();
			$combine_checkbox_values = (isset($args[5])) ? $args[5] : false;
			$outputDags = (isset($args[6])) ? $args[6] : false;
			$outputSurveyFields = (isset($args[7])) ? $args[7] : false;
			$filterLogic = (isset($args[8])) ? $args[8] : false;
			$outputAsLabels = (isset($args[9])) ? $args[9] : false;
			$outputCsvHeadersAsLabels = (isset($args[10])) ? $args[10] : false;
			$hashRecordID = (isset($args[11])) ? $args[11] : false;
			$dateShiftDates = (isset($args[12])) ? $args[12] : false;
			$dateShiftSurveyTimestamps = (isset($args[13])) ? $args[13] : false;
			$sortArray = (isset($args[14])) ? $args[14] : array();
			$removeLineBreaksInValues = (isset($args[15])) ? $args[15] : false;
			$replaceFileUploadDocId = (isset($args[16])) ? $args[16] : false;
			$returnIncludeRecordEventArray = (isset($args[17])) ? $args[17] : false;
			$orderFieldsAsSpecified = (isset($args[18])) ? $args[18] : false;
			$outputSurveyIdentifier = (isset($args[19])) ? $args[19] : $outputSurveyFields;
			$outputCheckboxLabel = (isset($args[20])) ? $args[20] : false;
                        $outputScheduleDates = (isset($args[21])) ? $args[21] : false;
                        $outputSurveyUrls = (isset($args[22])) ? $args[22] : false;
			// Get existing values since Project object already exists in global scope
			global $Proj, $longitudinal, $table_pk;
		}
		
		// Get current memory limit in bytes
		$memory_limit = str_replace("M", "", ini_get('memory_limit')) * 1024 * 1024;
		
		// Set array of valid $returnFormat values
		$validReturnFormats = array('html', 'csv', 'xml', 'json', 'array');
		
		// Set array of valid MC field types (don't include "checkbox" because it gets dealt with on its own)
		$mc_field_types = array("radio", "select", "yesno", "truefalse", "sql");
		
		// If $returnFormat is not valid, set to default 'csv'
		if (!in_array($returnFormat, $validReturnFormats)) $returnFormat = 'csv';
		
		// Cannot use $outputAsLabels for 'array' output
		if ($returnFormat == 'array') $outputAsLabels = false;
		
		// Can only use $outputCsvHeadersAsLabels for 'csv' output
		if ($returnFormat != 'csv') $outputCsvHeadersAsLabels = false;
		
		// If surveys are not enabled, then set $outputSurveyFields to false
		if (!$Proj->project['surveys_enabled'] || empty($Proj->surveys)) $outputSurveyFields = false;
			
		// Use for replacing strings in labels (if needed)
		$orig = array("\"", "\r\n", "\n", "\r");
		$repl = array("'" , "  "  , "  ", ""  );
		
		// Determine if we should apply sortArray
		$applySortFields = (is_array($sortArray) && !empty($sortArray));
		
		## Set all input values
		// Get unique event names (with event_id as key)
		$unique_events = $Proj->getUniqueEventNames();
		// Create array of formatted event labels
		if ($longitudinal && $outputAsLabels) {
			$event_labels = array();
			foreach (array_keys($unique_events) as $this_event_id) {
				$event_labels[$this_event_id] = str_replace($orig, $repl, strip_tags(label_decode($Proj->eventInfo[$this_event_id]['name_ext'])));
			}
		}
		
		// If $fields is a string, convert to array
		if (!is_array($fields) && $fields != null) {
			$fields = array($fields);
		}
		// If $fields is empty, replace it with array of ALL fields.
		$removeTablePk = false;
		if (empty($fields)) {
			foreach (array_keys($Proj->metadata) as $this_field) {
				// Make sure field is not a descriptive field (because those will never have data)
				if ($Proj->metadata[$this_field]['element_type'] != 'descriptive') {
					$fields[] = $this_field;
				}
			}
			$checkFieldNameEachLoop = true;
		} else {
			// If only returning the record-event array (as the subset record list for a report),
			// then make sure the record ID is added, or else it'll break some things downstream (not ideal solution but works as quick patch).
			// Also do this for longitudinal projects because if we don't, it might not pull data for an entire event if data doesn't exist
			// for any fields here except the record ID field. NOTE: Make sure we remove the record ID field in the end though (so it doesn't get returned
			if (($Proj->longitudinal || $returnIncludeRecordEventArray) && !in_array($Proj->table_pk, $fields)) {
				$fields = array_merge(array($Proj->table_pk), $fields);
				$removeTablePk = true;
			}
			// Validate all field names and order fields according to metadata field order
			$field_order = array();
			foreach ($fields as $this_key=>$this_event_field) {
                                $this_field = LongitudinalReports::getFieldFromEventField($this_event_field);
				// Make sure field exists AND is not a descriptive field (because those will never have data)
				if (isset($Proj->metadata[$this_field]) && $Proj->metadata[$this_field]['element_type'] != 'descriptive') {
					// Put in array for sorting
					$field_order[] = $Proj->metadata[$this_field]['field_order'];
				} else {
					// Remove any invalid field names
					unset($fields[$this_key]);
				}
			}
			// Sort fields by metadata field order (unless passing a flag to prevent reordering)
			if (!$orderFieldsAsSpecified) {
				array_multisort($field_order, SORT_NUMERIC, $fields);
			}
			unset($field_order);
			// If we're querying more than 25% of the project's fields, then don't put field names in query but check via PHP each loop.
			$checkFieldNameEachLoop = ((count($fields) / count($Proj->metadata)) > 0.25);
		}
		// Create array of fields with field name as key
		$fieldsKeys = array_fill_keys($fields, true);
		
		// If $events is a string, convert to array
		if (!is_array($events) && $events != null) {
			$events = array($events);
		}
		// If $events is empty, replace it with array of ALL fields.
		if (empty($events)) {
			$events = array_keys($Proj->eventInfo);
		} else {
			// If $events has unique event name (instead of event_ids), then convert all to event_ids
			$events_temp = array();
			foreach ($events as $this_key=>$this_event) {
				// If numeric, validate event_id
				if (is_numeric($this_event)) {
					if (!isset($Proj->eventInfo[$this_event])) {
						// Remove invalid event_id
						unset($events[$this_key]);
					} else {
						// Valid event_id
						$events_temp[] = $this_event;
					}
				}
				// If unique event name is provided
				else {
					// Get array key of unique event name provided
					$event_id_key = array_search($this_event, $unique_events);
					if ($event_id_key !== false) {
						// Valid event_id
						$events_temp[] = $event_id_key;
					}
				}
			}
			// Now swap out $events_temp for $events
			$events = $events_temp;
			unset($events_temp);
		}
		
		// Get array of all DAGs
		$allDags = $Proj->getUniqueGroupNames();
		// Validate DAGs
		if (empty($allDags)) {
			// If no DAGs exist, then automatically set array as empty
			$groups = array();
			// Automatically set $outputDags as false (in case was set to true mistakenly)
			$outputDags = false;
		} else {
			// If $groups is a string, convert to array
			if (!is_array($groups) && $groups != null) {
				$groups = array($groups);
			}
			// If $groups is not empty, replace it with array of ALL data access group IDs.
			if (!empty($groups)) {
				// If $groups has unique group name (instead of group_ids), then convert all to group_ids
				$groups_temp = array();
				foreach ($groups as $this_key=>$this_group) {
                                        // If numeric, validate group_id
                                        if (is_numeric($this_group)) {
                                            if (!isset($allDags[$this_group])) {
                                                // Check to see if its really the unique group name (and not the group_id) // LS Patched: https://iwg.devguard.com/trac/redcap/changeset/7747#file0 
                                                $group_id_key = array_search($this_group, $allDags);
                                                if ($group_id_key !== false) {
                                                    // Valid group_id
                                                    $groups_temp[] = $group_id_key;
                                                } else {
                                                    // Remove invalid group_id
                                                    unset($groups[$this_key]);
                                                }
                                            } else {
                                                // Valid group_id
                                                $groups_temp[] = $this_group;
                                            }
                                        }
                                        // If unique group name is provided
					else {
						// Get array key of unique group name provided
						$group_id_key = array_search($this_group, $allDags);
						if ($group_id_key !== false) {
							// Valid group_id
							$groups_temp[] = $group_id_key;
						}
					}
				}
				// Now swap out $groups_temp for $groups
				$groups = $groups_temp;
				unset($groups_temp);
			}
		}
		
		## RECORDS
		// If $records is a string, convert to array
		if (!is_array($records) && $records != null) {
			$records = array($records);
		}
		// If $records is empty, replace it with array of ALL records.
		$recordsEmpty = false;
		$recordCount = null;
		if (empty($records)) {
			$records = self::getRecordList($project_id);
			// Set flag that $records was originally passed as empty
			$recordsEmpty = true;
			$checkRecordNameEachLoop = true;
		} else {
			// If we're querying more than 25% of the project's records, then don't put field names in query but check via PHP each loop.
			if ($recordCount == null) $recordCount = self::getRecordCount();
			$checkRecordNameEachLoop = ((count($records) / $recordCount) > 0.25);
		}		
		// Create array of fields with field name as key
		$recordsKeys = array_fill_keys($records, true);
		
		## DAG RECORDS: If pulling data for specific DAGs, get list of records in DAGs specified and replace $records with them
		$hasDagRecords = false;
		if (!empty($groups)) 
		{
			// Collect all DAG records into array
			$dag_records = array();
			$sql = "select distinct record from redcap_data where project_id = $project_id
					and field_name = '__GROUPID__' and value in (" . prep_implode($groups) . ")";
			if (!$checkRecordNameEachLoop) {
				$sql .= " and record in (" . prep_implode($records) . ")";
			}
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				// If we need to validate the record name in each loop, then check.
				if ($checkRecordNameEachLoop && !isset($recordsKeys[$row['record']])) continue;
				// Add to array
				$dag_records[] = $row['record'];
			}
			// Set flag if returned some DAG records
			$hasDagRecords = (!empty($dag_records));
			// Replace $records array
			$records = $dag_records;
			unset($dag_records);
			// If we're querying more than 25% of the project's records, then don't put field names in query but check via PHP each loop.
			if ($recordCount == null) $recordCount = self::getRecordCount();
			$checkRecordNameEachLoop = ((count($records) / $recordCount) > 0.25);
			// Create array of fields with field name as key
			$recordsKeys = array_fill_keys($records, true);
		}
		
		## APPLY FILTERING LOGIC: Get records-events where filter logic is true
		$filterResults = false;
		$filterReturnedEmptySet = false;
		if ($filterLogic != '') {
			// Get array of applicable record-events (only pass $project_id if already passed explicitly to getData)
			$record_events_filtered = self::applyFilteringLogic($filterLogic, $records, (is_numeric($args[0]) ? $project_id : null));
			$filterResults = ($record_events_filtered !== false);
			// If logic returns zero record/events, then manually set $records to ''/blank
			if ($filterResults) {
				if (empty($record_events_filtered)) {
					$records = array('');
					$checkRecordNameEachLoop = false;
					$filterReturnedEmptySet = true;
				} else {
					// Replace headers
					$records = array_keys($record_events_filtered);
					// If we're querying more than 25% of the project's records, then don't put field names in query but check via PHP each loop.
					if ($recordCount == null) $recordCount = self::getRecordCount();
					$checkRecordNameEachLoop = ((count($records) / $recordCount) > 0.25);
					// Create array of fields with field name as key
					$recordsKeys = array_fill_keys($records, true);
				}
			}
		}
		
		## SORTING IN REPORTS: If the sort fields are NOT in $fields (i.e. should be returned as data), 
		// then temporarily add them to $fields and then remove them later when performing sorting.
		if ($applySortFields) {
			$sortArrayRemoveFromData = array();
			foreach (array_keys($sortArray) as $this_field) {
				if (!in_array($this_field, $fields)) {
					$sortArrayRemoveFromData[] = $fields[] = $this_field;
				}
			}
		}
	
		## PIPING (only for exporting labels OR for displaying reports)
		$piping_receiver_fields = array();
		$do_label_piping = false;
		if ($outputAsLabels || $returnFormat == 'html') {
			// If any dropdowns, radios, or checkboxes are using piping in their option labels, then get data for those and then inject them
			$piping_transmitter_fields = $piping_record_data = array();
			foreach ($fields as $this_field) {
                                $this_field = LongitudinalReports::getFieldFromEventField($this_field);
				if (in_array($Proj->metadata[$this_field]['element_type'], array('dropdown','select','radio','checkbox'))) {
					$this_field_enum = $Proj->metadata[$this_field]['element_enum'];
					// If has at least one left and right square bracket
					if ($this_field_enum != '' && strpos($this_field_enum, '[') !== false && strpos($this_field_enum, ']') !== false) {
						// If has at least one field piped
						$these_piped_fields = array_keys(getBracketedFields($this_field_enum, true, true, true));
						if (!empty($these_piped_fields)) {
							$piping_receiver_fields[] = $this_field;
							$piping_transmitter_fields = array_merge($piping_transmitter_fields, $these_piped_fields);
						}
					}
				}
			}
			if (!empty($piping_receiver_fields)) {
				// Get data for piping fields
				$piping_record_data = self::getData('array', $records, $piping_transmitter_fields);
				// Remove unneeded variables
				unset($piping_transmitter_fields, $potential_piping_fields);
				// Set flag
				$do_label_piping = true;
			}
		}
		
		## GATHER DEFAULT VALUES
		// Get default values for all records (all fields get value '', except Form Status and checkbox fields get value 0)
		$default_values = $mc_choice_labels = array();
		$prev_form = null;
                $fieldsNoEventRef = array();
		foreach ($fields as $this_field)
		{
                        //LongitudinalReports: make array of field names without event ref for reading from redcap_data
                        $this_field = LongitudinalReports::getFieldFromEventField($this_field);
                        if (array_search($this_field, $fieldsNoEventRef) === false ) {
                            $fieldsNoEventRef[] = $this_field;
                        }
			// Get field's field type
			$field_type = $Proj->metadata[$this_field]['element_type'];
			// If exporting labels for multiple choice questions, store codes/labels in array for later use when replacing
			if ($outputAsLabels && ($field_type == 'checkbox' || in_array($field_type, $mc_field_types))) {
				if ($field_type == "yesno") {
					$mc_choice_labels[$this_field] = parseEnum("1, Yes \\n 0, No");
				} elseif ($field_type == "truefalse") {
					$mc_choice_labels[$this_field] = parseEnum("1, True \\n 0, False");
				} else {
					$enum = ($field_type == "sql") ? getSqlFieldEnum($Proj->metadata[$this_field]['element_enum']) : $Proj->metadata[$this_field]['element_enum'];
					foreach (parseEnum($enum) as $this_value=>$this_label) {
						// Decode (just in case)
						$this_label = html_entity_decode($this_label, ENT_QUOTES);
						// Replace double quotes with single quotes
						$this_label = str_replace("\"", "'", $this_label); 
						// Replace line breaks with two spaces
						$this_label = str_replace("\r\n", "  ", $this_label);
						// Add to array
						$mc_choice_labels[$this_field][$this_value] = $this_label;
					}
				}
			}
			// Loop through all designated events so that each event
			foreach (array_keys($Proj->eventInfo) as $this_event_id)
			{
				// If event_id isn't in list of event_ids provided, then skip
				if (!in_array($this_event_id, $events)) continue;
				// Get the form_name of this field
				$this_form = $Proj->metadata[$this_field]['form_name'];
                                $current_event_field = "[{$Proj->getUniqueEventNames($this_event_id)}][$this_field]";
                                
                                if (array_search($current_event_field, $fields) !== false ||
                                    ($this_field == $Proj->table_pk && $this_event_id == $Proj->firstEventId) ) {

                                        // If we're starting a new survey, then add its Timestamp field as the first field in the instrument
                                        if ($outputSurveyFields && $this_field != $table_pk && isset($Proj->forms[$this_form]['survey_id'])) {
                                                $current_event_field = "[{$Proj->getUniqueEventNames($this_event_id)}][{$this_field}_timestamp]";
                                                $default_values[$current_event_field] = '';//$default_values[$this_event_id][$this_form.'_timestamp'] = '';
                                        }
                                        // If longitudinal, is this form designated for this event
                                        $validFormEvent = (!$longitudinal || ($longitudinal && in_array($this_form, $Proj->eventsForms[$this_event_id])));
                                        // Check a checkbox or Form Status field
                                        if ($Proj->isCheckbox($this_field)) {
                                                // Loop through all choices and set each as 0
                                                foreach (array_keys(parseEnum($Proj->metadata[$this_field]['element_enum'])) as $choice) {
                                                        // Set default value as 0 (unchecked)
                                                        if (!$validFormEvent || ($outputAsLabels && $outputCheckboxLabel)) {
                                                                $default_values[$current_event_field][$choice] = '';//$default_values[$this_event_id][$this_field][$choice] = '';
                                                        } elseif ($outputAsLabels) {
                                                                $default_values[$current_event_field][$choice] = 'Unchecked';//$default_values[$this_event_id][$this_field][$choice] = 'Unchecked';
                                                        } else {
                                                                $default_values[$current_event_field][$choice] = '0';//$default_values[$this_event_id][$this_field][$choice] = '0';
                                                        }
                                                }
                                        } elseif ($this_field == $this_form . "_complete") {
                                                // Set default Form Status as 0
                                                if (!$validFormEvent) {
                                                        $default_values[$current_event_field] = '';//$default_values[$this_event_id][$this_field] = '';
                                                } elseif ($outputAsLabels) {
                                                        $default_values[$current_event_field] = 'Incomplete';//$default_values[$this_event_id][$this_field] = 'Incomplete';
                                                } else {
                                                        $default_values[$current_event_field] = '0';//$default_values[$this_event_id][$this_field] = '0';
                                                }
                                        } else {
                                                // Set as ''
                                                $default_values[$current_event_field] = '';//$default_values[$this_event_id][$this_field] = '';
                                                // If this is the Record ID field and we're exporting DAG names and/or survey fields, them add them.
                                                // If the Record ID field is not included in the report, then add DAG names and/or survey fields if not already added.
                                                if ($this_field == $table_pk || !in_array($table_pk, $fields)) {
                                                        // DAG field
                                                        if ($outputDags && !isset($default_values['redcap_data_access_group'])) {
                                                                $default_values['redcap_data_access_group'] = '';
                                                        }
                                                        if ($outputSurveyIdentifier && !isset($default_values['redcap_survey_identifier'])) {
                                                                // Survey Identifier field
                                                                $default_values['redcap_survey_identifier'] = '';
                                                                // Survey Timestamp field (first instrument only - for other instruments, it's doing this same thing above in the loop)
                                                                // if ($prev_form == null && isset($Proj->forms[$this_form]['survey_id'])) {
                                                                        // $default_values[$this_event_id][$this_form.'_timestamp'] = '';
                                                                // }
                                                        }
                                                        // Event schedule dates selected
                                                        if (is_array($outputScheduleDates) && count($outputScheduleDates) > 0) {
                                                                foreach ($outputScheduleDates as $outputDateEventId) {
                                                                        $default_values["[{$Proj->getUniqueEventNames($outputDateEventId)}][___schedule_date]"] = '';
                                                                }
                                                        }
                                                        // Survey URLs selected
                                                        if (is_array($outputSurveyUrls) && count($outputSurveyUrls) > 0) {
                                                                foreach ($outputSurveyUrls as $outputSurveyEventIdSurveyId) {
                                                                        //Event id/survey form stored as pipe-separated pair e.g. 2365|my_survey
                                                                        $eventsurvey = explode('|', $outputSurveyEventIdSurveyId);
                                                                        $eventRef = $Proj->getUniqueEventNames($eventsurvey[0]);
                                                                        $default_values["[$eventRef][$eventsurvey[1]___url]"] = '';
                                                                }
                                                        }
                                                }
                                        }
                                }
				// Set for next loop
				$prev_form = $this_form;
			}
		}
		
		## QUERY DATA TABLE
		// Set main query
		$sql = "select record, event_id, field_name, value from redcap_data
				where project_id = " . $project_id . " and record != ''";
		if (!empty($events)) {
			$sql .= " and event_id in (" . prep_implode($events) . ")";
		}
		if (!$checkFieldNameEachLoop && !empty($fields)) {
			// $sql .= " and field_name in (" . prep_implode($fields) . ")";
			$sql .= " and field_name in (" . prep_implode($fieldsNoEventRef) . ")";
		}
		if (!$checkRecordNameEachLoop && !empty($records)) {
			$sql .= " and record in (" . prep_implode($records) . ")";
		}
		// If we are to return records for specific DAG(s) but those DAGs contain no records, then cause the query to return nothing.
		if (!$hasDagRecords && !empty($groups)) {
			$sql .= " and 1 = 2";
		}
		// Order query results by record name if constant has been defined
		if (defined("EXPORT_WRITE_TO_FILE")) {
			$sql .= " order by record";
		}
		//print "<br><b>MySQL Error:</b> ".db_error()."<br><b>Query:</b> $sql<br><br>";
		// Use unbuffered query method
		$q = db_query($sql, null, MYSQLI_USE_RESULT);
		// Return database query error to super users
		if (defined('SUPER_USER') && SUPER_USER && db_error() != '') {
			print "<br><b>MySQL Error:</b> ".db_error()."<br><b>Query:</b> $sql<br><br>";
		}
		// Set flag is record ID field is a display field
		$recordIdInFields = (in_array($Proj->table_pk, $fields));
		// Remove unnecessary things for memory usage purposes
//		unset($fields, $events);
		// Set intial values
		$num_rows_returned = 0;
		$event_id = 0;
		$record = "";
		$record_data = array();
		$days_to_shift = array();
		$record_data_tmp_line = array();
		$record_line = 1;
		// If writing data to a file instead to memory, create temp file
		if (defined("EXPORT_WRITE_TO_FILE")) {
			$record_data_tmp_file = tmpfile();
		}
                
		// Loop through data one record at a time
		while ($row = db_fetch_assoc($q))
		{
			// If value is blank, then skip
			if ($row['value'] == '') continue;
			// If we need to validate the record name in each loop, then check.
			if ($checkRecordNameEachLoop && !isset($recordsKeys[$row['record']])) continue;
			// If we need to validate the field name in each loop, then check.
                        if ($checkFieldNameEachLoop && !isset($fieldsKeys["[{$Proj->getUniqueEventNames($row['event_id'])}][{$row['field_name']}]"])) continue;
//			// If filtering the results using a logic string, then skip this record-event if doesn't match valid logic
//			if ($filterResults && !isset($record_events_filtered[$row['record']][$row['event_id']])) continue;
			// Add initial default data for this record-event
			if (!isset($record_data[$row['record']])) { // [$row['event_id']])) {
				/* 
				// If we're close to running out of memory, then set contstant and call this method recursively
				// to start over and write data to file instead of to memory (to allow full data export w/o hitting memory limits).
				if (!defined("EXPORT_WRITE_TO_FILE") && memory_get_usage() >= $memory_limit*0.8) {
					// Define the constant
					define("EXPORT_WRITE_TO_FILE", true);
					// Unset variables to clear up memory
					unset($record_data, $records, $recordsKeys, $fieldsKeys, $record_events_filtered);
					// Close the unbuffered query
					db_free_result($q);
					// Call this function with constant defined to start over while writing data to file
					return call_user_func_array('Records::getData', $args);
				} elseif (defined("EXPORT_WRITE_TO_FILE") && $event_id > 0) {
					// Add data to file and clear from array
					fwrite($record_data_tmp_file, serialize(array($record=>array($event_id=>$record_data[$record][$event_id])))."\n");
					// Add this to record line
					$record_data_tmp_line[$record][$event_id] = $record_line++;
					// Clear array for next record-event
					$record_data = array();
				}
				*/
				// Add default data
//				$record_data[$row['record']][$row['event_id']] = $default_values[$row['event_id']];
				$record_data[$row['record']] = $default_values;
				// Get date shift amount for this record (if applicable)
				if ($dateShiftDates) {
					$days_to_shift[$row['record']] = self::get_shift_days($row['record'], $Proj->project['date_shift_max'], $Proj->project['__SALT__']);
				}
			}
			// Decode the value
			$row['value'] = html_entity_decode($row['value'], ENT_QUOTES);
			// Set values for this loop
			$event_id = $row['event_id'];
			$record   = $row['record'];
			// Add the value into the array (double check to make sure the event_id still exists)
                        // - but only if it is an event/field we're interested in for Longitudinal Report
			if (isset($unique_events[$event_id]))
			{
                            $current_event_field = "[{$Proj->getUniqueEventNames($event_id)}][{$row['field_name']}]";
                            if (array_search($current_event_field, $fields) !== false ||
                                    ($row['field_name'] == $Proj->table_pk && $event_id == $Proj->firstEventId) ) {
                                
				// Get field's field type
				$field_type = $Proj->metadata[$row['field_name']]['element_type'];
				if ($field_type == 'checkbox') {
					// Make sure that this checkbox option still exists
					//if (isset($default_values[$event_id][$row['field_name']][$row['value']])) {
					if (isset($default_values[$current_event_field][$row['value']])) {
						// Add checkbox field value
						if ($outputAsLabels) {
							// If using $outputCheckboxLabel API flag, then output the choice label 
							if ($outputCheckboxLabel) {
								// Get MC option label
								$this_mc_label = $mc_choice_labels[$row['field_name']][$row['value']];
								// PIPING (if applicable)
								if ($do_label_piping && in_array($row['field_name'], $piping_receiver_fields)) {
									$this_mc_label = strip_tags(Piping::replaceVariablesInLabel($this_mc_label, $record, $event_id, $piping_record_data));
								}
								// Add option label
								//$record_data[$record][$event_id][$row['field_name']][$row['value']] = $this_mc_label;
								$record_data[$record][$current_event_field][$row['value']] = $this_mc_label;
							} else {
								//$record_data[$record][$event_id][$row['field_name']][$row['value']] = 'Checked';
								$record_data[$record][$current_event_field][$row['value']] = 'Checked';
							}
						} else {
							//$record_data[$record][$event_id][$row['field_name']][$row['value']] = '1';
							$record_data[$record][$current_event_field][$row['value']] = '1';
						}
					}
				} else {
					// Non-checkbox field value
					// When outputting labels for MULTIPLE CHOICE questions (excluding checkboxes), add choice labels to answers_labels
					if ($outputAsLabels && isset($mc_choice_labels[$row['field_name']])) {
						// Get MC option label
						$this_mc_label = $mc_choice_labels[$row['field_name']][$row['value']];
						// PIPING (if applicable)
						if ($do_label_piping && in_array($row['field_name'], $piping_receiver_fields)) {
							$this_mc_label = strip_tags(Piping::replaceVariablesInLabel($this_mc_label, $record, $event_id, $piping_record_data));
						}
						// Add option label
						//$record_data[$record][$event_id][$row['field_name']] = $this_mc_label;
						$record_data[$record][$current_event_field] = $this_mc_label;
					} else {
						// Shift all date[time] fields, when applicable
						if ($dateShiftDates && $field_type == 'text' 
							&& (substr($Proj->metadata[$row['field_name']]['element_validation_type'], 0, 8) == 'datetime'
								|| in_array($Proj->metadata[$row['field_name']]['element_validation_type'], array('date', 'date_ymd', 'date_mdy', 'date_dmy')))) 
						{
							//$record_data[$record][$event_id][$row['field_name']] = Records::shift_date_format($row['value'], $days_to_shift[$record]);
							$record_data[$record][$current_event_field] = Records::shift_date_format($row['value'], $days_to_shift[$record]);
						}
						// For "File Upload" fields, replace doc_id value with [document] if flag is set
						elseif ($replaceFileUploadDocId && $field_type == 'file') {
							//$record_data[$record][$event_id][$row['field_name']] = '[document]';
							$record_data[$record][$current_event_field] = '[document]';
						}
						// Add raw value
						else {
							// Check if we should replace any line breaks with spaces or double quotes with single quotes
							if ($removeLineBreaksInValues) {
								$row['value'] = str_replace($orig, $repl, $row['value']);
							}
							// Add value
							//$record_data[$record][$event_id][$row['field_name']] = $row['value'];
							$record_data[$record][$current_event_field] = $row['value'];
						}
					}
				}
                            }
			}
			// Increment row counter
			$num_rows_returned++;
		}
		db_free_result($q);
		
		// If query returns 0 rows, then simply put default values for $record_data as placeholder for blanks and other defaults.
		// If DAGs were specified as input parameters but there are no records in those DAGs, then output NOTHING but a blank array.
		if ($num_rows_returned < 1 && !($hasDagRecords && !empty($groups))) {
			if ($recordsEmpty) {
				// Loop through ALL records and add default values for each
				foreach ($records as $this_record) {
					$record_data[$this_record] = $default_values;
				}
			} else {
				// Validate the records passed in $records and loop through them and add default values for each
				foreach (array_intersect($records, self::getRecordList($project_id)) as $this_record) {
					$record_data[$this_record] = $default_values;
				}
			}
		}
		
		/* 
		print memory_get_usage()/1024/1024;
		//print_array($record_data);
		print_array($record_data_tmp_line);
		fseek($record_data_tmp_file, 0);
		echo stream_get_contents($record_data_tmp_file);
		 */
		
		// REPORTS ONLY: If the Record ID field is included in the report, then also display the Custom Record Label
		$extra_record_labels = array();
		if ($returnFormat == 'html' && $recordIdInFields) {
			$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords($records, false, 'all');
		}
		
		// Sort by record and event name ONLY if we are NOT sorting by other fields
		if (empty($sortArray)) {
			## SORT RECORDS BY RECORD NAME (i.e., array keys) using case insensitive natural sort
			natcaseksort($record_data);
/*			## SORT EVENTS WITHIN EACH RECORD (LONGITUDINAL ONLY)
			if ($longitudinal) {
				// Create array of event_id's in order by arm_num, then by event order
				$event_order = array_keys($Proj->eventInfo);
				// Loop through each record and reorder the events (if has more than one event of data per record)
				foreach ($record_data as $this_record=>&$these_events) {
					// Set array to collect the data for this record in reordered form
					$this_record_data_reordered = array();
					// Skip if there's only one event with data
					if (count($these_events) == 1) continue;
					// Loop through all existing PROJECT events in their proper order
					foreach (array_intersect($event_order, array_keys($these_events)) as $this_event_id) {
						// Add this event's data to reordered data array
						$this_record_data_reordered[$this_event_id] = $these_events[$this_event_id];
					}
					// Replace old data with reordered data
					$record_data[$this_record] = $this_record_data_reordered;
				}
				// Remove unnecessary things for memory usage purposes
				unset($this_record_data_reordered, $these_events, $event_order);
			}*/
		}
		
		## ADD DATA ACCESS GROUP NAMES (IF APPLICABLE)
		if ($outputDags) {
			// If exporting labels, then create array of DAG labels
			if ($outputAsLabels) {
				$allDagLabels = $Proj->getGroups();
			}
			// Get all DAG values for the records
			$sql = "select distinct record, value from redcap_data 
					where project_id = $project_id and field_name = '__GROUPID__'";					
			if (!$checkRecordNameEachLoop) {
				// For performance reasons, don't use "record in ()" unless we really need to
				$sql .= " and record in (" . prep_implode($records, false) . ")";
			}
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				// Validate record name and DAG group_id value
				if (isset($allDags[$row['value']]) && isset($record_data[$row['record']])) {
					// Add unique DAG name to every event for this record
//					foreach (array_keys($record_data[$row['record']]) as $dag_event_id) {
						// Add DAG name or unique DAG name
						//$record_data[$row['record']][$dag_event_id]['redcap_data_access_group'] 
						$record_data[$row['record']]['redcap_data_access_group'] 
							= ($outputAsLabels) ? $allDagLabels[$row['value']] : $allDags[$row['value']];
//					}					
				}			
			}
			unset($allDagLabels);
		}

		## ADD DATES FROM SCHEDULE IF REQURIED
		if (isset($outputScheduleDates) && count($outputScheduleDates) > 0) {
			$sql = "select event_id, record, event_date, event_time, event_status " .
				"from redcap_events_calendar " .
				"where project_id = $project_id "; 
			if (!$checkRecordNameEachLoop) {
				// For performance reasons, don't use "record in ()" unless we really need to
				$sql .= " and r.record in (" . prep_implode($records, false) . ")";
			}
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// Process further only if record is in our array 
				if (!isset($record_data[$row['record']])) continue;

                                if (array_search($row['event_id'], $outputScheduleDates) !== false) {
                                        $record_data[$row['record']]["[{$Proj->getUniqueEventNames($row['event_id'])}][___schedule_date]"] 
                                            = trim("{$row['event_date']} {$row['event_time']}");
                                }
			}
		}

                
		## ADD SURVEY IDENTIFIER AND TIMESTAMP FIELDS FOR ALL SURVEYS
/* TODO Longitudinal Reports: include survey timestamps/urls/returncodes/survey queue links          */      
		if ($outputSurveyFields || (isset($outputSurveyUrls) && count($outputSurveyUrls) > 0)) {
			$sql = "select r.record, r.first_submit_time, r.completion_time, p.participant_identifier, s.form_name, p.event_id, p.hash, r.return_code " .
				"from redcap_surveys s, redcap_surveys_response r, redcap_surveys_participants p, redcap_events_metadata a  " .
				"where p.participant_id = r.participant_id and s.project_id = $project_id and s.survey_id = p.survey_id " .
				"and p.event_id = a.event_id "; //and r.first_submit_time is not null";					
			if (!$checkRecordNameEachLoop) {
				// For performance reasons, don't use "record in ()" unless we really need to
				$sql .= " and r.record in (" . prep_implode($records, false) . ")";
			}
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// Make sure we have this record-event in array first
				//if (!isset($record_data[$row['record']][$row['event_id']])) continue;
				if (!isset($record_data[$row['record']])) continue;
/*				// Add participant identifier
				if ($row['participant_identifier'] != "" && isset($default_values[$row['event_id']]['redcap_survey_identifier'])) {
					$record_data[$row['record']][$row['event_id']]['redcap_survey_identifier'] = html_entity_decode($row['participant_identifier'], ENT_QUOTES);
				}
				// If response exists but is not completed, note this in the export
				if ($dateShiftSurveyTimestamps && $row['completion_time'] != "") {
					// Shift the survey timestamp, if applicable
					$row['completion_time'] = Records::shift_date_format($row['completion_time'], $days_to_shift[$row['record']]);
				} elseif ($row['completion_time'] == "") {
					// Replace with text "[not completed]" if survey wasn't completed
					$row['completion_time'] = "[not completed]";
				}
				// Add to record_data array
				if (isset($record_data[$row['record']][$row['event_id']][$row['form_name'].'_timestamp'])) {
					$record_data[$row['record']][$row['event_id']][$row['form_name'].'_timestamp'] = $row['completion_time'];
				}*/
                                
                                $frm = $row['form_name'];
                                $evt = $row['event_id'];
                                if (array_search("$evt|$frm", $outputSurveyUrls) !== false) {
                                    // Found a record/survey we want to include
                                    $url = ($row['completion_time'] == "") 
                                            ? APP_PATH_WEBROOT_FULL . "surveys/?s=" . $row['hash'] 
                                            : $row['completion_time'] ;
                                    $record_data[$row['record']]["[{$Proj->getUniqueEventNames($evt)}][{$frm}___url]"] = $url;
                                }
			}
		}
		unset($days_to_shift);
		
		## HASH THE RECORD ID (replace record names with hash value)
		if ($hashRecordID) {
			foreach ($record_data as $this_record=>$eattr) {
				// Hash the record name using a system-level AND project-level salt
				$this_new_record = md5($salt . $this_record . $Proj->project['__SALT__']);
				// Add new record name
				$record_data[$this_new_record] = $record_data[$this_record];
				// Remove the old one
				unset($record_data[$this_record]);
				// If Record ID field exists in the report, then set it too at the value level
				foreach ($eattr as $this_event_id=>$attr) {
					if (isset($attr[$Proj->table_pk])) {
						//$record_data[$this_new_record][$this_event_id][$Proj->table_pk] = $this_new_record;
						$record_data[$this_new_record][$Proj->table_pk] = $this_new_record;
					}
				}
			}
			unset($eattr, $attr);
		}
		
		// Remove unnecessary things for memory usage purposes
		unset($records, $default_values, $fieldsKeys, $recordsKeys, $record_events_filtered);
		db_free_result($q);
		
//      		// If we need to remove the record ID field, then loop through all events of data and remove it
		// If we need to remove the record ID field, then loop through all records of data and remove it
		if ($removeTablePk) {
			foreach ($record_data as $this_record=>&$these_fields) { //&$these_events) {
//				foreach ($these_events as $this_event_id=>&$these_fields) {
					if (isset($these_fields[$Proj->table_pk])) {
						//unset($record_data[$this_record][$this_event_id][$Proj->table_pk]);
						unset($record_data[$this_record][$Proj->table_pk]);
					}
//				}
			}
		}
		
		## RETURN DATA IN SPECIFIED FORMAT
		// ARRAY format
		if ($returnFormat == 'array') {
			// Return as-is (already in array format)
			return $record_data;			
		}
		else
		{
			## For non-array formats, reformat data array (e.g., add unique event names, separate check options)			
			
			// HEADERS: Do initial loop through array to build headers (do JUST one loop)
			$headers = $checkbox_choice_labels = array();
/*			foreach ($record_data as $this_record=>&$field_data) { // &$event_data) {
				// Loop through events in this record
				foreach ($event_data as $this_event_id=>&$field_data) {
					// Create array of all forms
					$all_forms = array_keys($Proj->forms);
					// Loop through fields in this event
					foreach ($field_data as $this_field=>$this_value) {
						// Skip the Record ID field since it will be redundant
						//if ($this_field == $table_pk) continue;
						// If field is only a sorting field and not a real data field to return, then skip it
						if ($applySortFields && in_array($this_field, $sortArrayRemoveFromData)) continue;

                                                $this_field = LongitudinalReports::getFieldFromEventField($this_field);
                                                        
                                                // If a checkbox split into multiple fields
						if (is_array($this_value) && !$combine_checkbox_values) {
							// If exporting labels, get labels for this field
							if ($outputCsvHeadersAsLabels) {
								$this_field_enum = parseEnum($Proj->metadata[$this_field]['element_enum']);
							}
							// Loop through all checkbox choices and add as separate "fields"
							foreach ($this_value as $this_code=>$this_checked_value) {
								// Store original code before formatting
								$this_code_orig = $this_code;
								// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
								$this_code = (Project::getExtendedCheckboxCodeFormatted($this_code));
								// Add choice to header
								$headers[] = ($outputCsvHeadersAsLabels) 
									? str_replace($orig, $repl, strip_tags(label_decode($Proj->metadata[$this_field]['element_label'])))." (choice=".str_replace(array("'","\""),array("",""),$this_field_enum[$this_code_orig]).")" 
									: $this_field."___".$this_code;
							}
						// If a normal field or DAG/Survey fields
						} else {
							// Get this field's form
							$this_form = $Proj->metadata[$this_field]['form_name'];
							// If the record ID field
							if ($this_field == $table_pk) {
								$headers[] = ($outputCsvHeadersAsLabels) ? str_replace($orig, $repl, strip_tags(label_decode($Proj->metadata[$table_pk]['element_label']))) : $table_pk;
								// If longitudinal, add unique event name to line
								if ($longitudinal) {
									$headers[] = ($outputCsvHeadersAsLabels) ? 'Event Name' : 'redcap_event_name';
								}
							}
							// Check if a special field or a normal field
							elseif (!$outputCsvHeadersAsLabels) {
								// Add field to header array
								$headers[] = $this_field;
								// Add checkbox labels to array (only for $combine_checkbox_values=TRUE)
								if (is_array($this_value) && $combine_checkbox_values) {
									foreach (parseEnum($Proj->metadata[$this_field]['element_enum']) as $raw_coded_value=>$checkbox_label) {
										$checkbox_choice_labels[$raw_coded_value] = $checkbox_label;
									}
								}
							// Output labels for normal field or DAG/Survey fields
							} elseif ($this_field == 'redcap_data_access_group') {
								$headers[] = 'Data Access Group';
							} elseif ($this_field == 'redcap_survey_identifier') {
								$headers[] = 'Survey Identifier';
							} elseif (substr($this_field, -10) == '_timestamp' && in_array(substr($this_field, 0, -10), $all_forms)) {
								$headers[] = 'Survey Timestamp';							
							} else {
								$headers[] = str_replace($orig, $repl, strip_tags(label_decode($Proj->metadata[$this_field]['element_label'])));
							}
						}
					}
					unset($all_forms);
					// Stop after we have all the fields for the first event listed (that's all we need)
					break; // 2;
//				}
			} */

                        // Loop through fields in order specified in LongitudinalReport spec
                        foreach ($fields as $this_eventfield) { 
                                // If field is only a sorting field and not a real data field to return, then skip it
                                if ($applySortFields && in_array($this_eventfield, $sortArrayRemoveFromData)) continue;

                                $this_eventref = LongitudinalReports::getEventFromEventField($this_eventfield);
                                $this_field = LongitudinalReports::getFieldFromEventField($this_eventfield);

                                // If a checkbox split into multiple fields
//                                if (is_array($this_value) && !$combine_checkbox_values) {
                                if ($Proj->isCheckbox($this_field) && !$combine_checkbox_values) {
                                        // If exporting labels, get labels for this field
                                        //if ($outputCsvHeadersAsLabels) {
                                                $this_field_enum = parseEnum($Proj->metadata[$this_field]['element_enum']);
                                        //}
                                        // Loop through all checkbox choices and add as separate "fields"
                                        foreach ($this_field_enum as $this_code=>$this_checked_value) {
                                                // Store original code before formatting
                                                $this_code_orig = $this_code;
                                                // If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
                                                $this_code = (Project::getExtendedCheckboxCodeFormatted($this_code));
                                                // Add choice to header
//                                                $headers[] = ($outputCsvHeadersAsLabels) 
//                                                        ? str_replace($orig, $repl, strip_tags(label_decode($Proj->metadata[$this_field]['element_label'])))." (choice=".str_replace(array("'","\""),array("",""),$this_field_enum[$this_code_orig]).")" 
//                                                        : $this_field."___".$this_code;
                                                if ($outputCsvHeadersAsLabels) {
                                                        // Longitudinal Reports - also include event name in header labels/ref
                                                        $event_id = $Proj->getEventIdUsingUniqueEventName($this_eventref);
                                                        $event_name = REDCap::getEventNames(false, true, $event_id);
                                                        $hdr_display = str_replace($orig, $repl, strip_tags(label_decode($Proj->metadata[$this_field]['element_label']))).
                                                                " ($event_name)" .
                                                                " (choice=".str_replace(array("'","\""),array("",""),$this_field_enum[$this_code_orig]).")";

                                                        $headers[] = $hdr_display;
                                                } else {
                                                        $headers[] = "[$this_eventref][{$this_field}___{$this_code}]";
                                                }
                                        }
                                // If a normal field or DAG/Survey fields
                                } else {
                                        // Get this field's form
                                        $this_form = $Proj->metadata[$this_field]['form_name'];
                                        // If the record ID field
                                        if ($this_field == $table_pk) {
                                                $headers[] = ($outputCsvHeadersAsLabels) ? str_replace($orig, $repl, strip_tags(label_decode($Proj->metadata[$table_pk]['element_label']))) : $table_pk;
    //								// If longitudinal, add unique event name to line
    //								if ($longitudinal) {
    //									$headers[] = ($outputCsvHeadersAsLabels) ? 'Event Name' : 'redcap_event_name';
    //								}
                                        }
                                        // Check if a special field or a normal field
                                        elseif (!$outputCsvHeadersAsLabels) {
                                                // Add field to header array
                                                $headers[] = $this_eventfield; // $this_field;
                                                // Add checkbox labels to array (only for $combine_checkbox_values=TRUE)
                                                //if (is_array($this_value) && $combine_checkbox_values) {
                                                if ($combine_checkbox_values && $Proj->isCheckbox($this_field)) {
                                                        foreach (parseEnum($Proj->metadata[$this_field]['element_enum']) as $raw_coded_value=>$checkbox_label) {
                                                                $checkbox_choice_labels[$raw_coded_value] = $checkbox_label;
                                                        }
                                                }
/*                                        // Output labels for normal field or DAG/Survey fields
                                        } elseif ($this_field == 'redcap_data_access_group') {
                                                $headers[] = 'Data Access Group';
                                        } elseif ($this_field == 'redcap_survey_identifier') {
                                                $headers[] = 'Survey Identifier';
                                        } elseif (substr($this_field, -10) == '_timestamp' && in_array(substr($this_field, 0, -10), $all_forms)) {
                                                $headers[] = 'Survey Timestamp';							*/
                                        } else {
//                                                $headers[] = str_replace($orig, $repl, strip_tags(label_decode($Proj->metadata[$this_field]['element_label'])));

                                                // Longitudinal Reports - also include event name in header labels/ref
                                                $event_id = $Proj->getEventIdUsingUniqueEventName($this_eventref);
                                                $event_name = REDCap::getEventNames(false, true, $event_id);
                                                $hdr_display = str_replace($orig, $repl, strip_tags(label_decode($Proj->metadata[$this_field]['element_label']))).
                                                        " ($event_name)";

                                                $headers[] = $hdr_display;
                                        }
                                }
                        }
			
                        // Add other fields at the end (DAG, survey stuff...)
                        if ($outputDags) {
                                $headers[] = ($outputCsvHeadersAsLabels) ? 'Data Access Group' : 'redcap_data_access_group';
                        } 
                        if ($outputSurveyFields) {
                                $headers[] = 'Survey Identifier';
                                $headers[] = 'Survey Timestamp';							
                        }
                        if (isset($outputScheduleDates) && count($outputScheduleDates) > 0) {
                                foreach ($outputScheduleDates as $evt) {
                                        if ($outputCsvHeadersAsLabels) {
                                                $headers[] = "Schedule Date ({$Proj->eventInfo[$evt]['name_ext']})";
                                        } else {
                                                $headers[] = "[{$Proj->getUniqueEventNames($evt)}][___schedule_date]";
                                        }
                                }
                        }
                        if (isset($outputSurveyUrls) && count($outputSurveyUrls) > 0) {
                                foreach ($outputSurveyUrls as $evtSurv) {
                                        //Event id/survey form stored as pipe-separated pair e.g. 2365|my_survey
                                        $es = explode('|', $evtSurv);
                                        if ($outputCsvHeadersAsLabels) {
                                                $headers[] = label_decode(strip_tags($Proj->forms[$es[1]]['menu']))." URL ({$Proj->eventInfo[$evt]['name_ext']})";
                                        } else {
                                                $headers[] = "[{$Proj->getUniqueEventNames($es[0])}][$es[1]___url]";
                                        }
                                }
                        }
                        
                        
			// Place formatted data into $record_data_formatted
			$record_data_formatted = array();
			// Set line/item number for each record/event
			//$recordEventNum = 0;
			$recordNum = 0;
			
			// If no results were returned (empty array with no values), then output row with message stating that
			if (!$filterReturnedEmptySet) {
				// Loop through array and output line as CSV
				foreach ($record_data as $this_record=>&$field_data) { // &$event_data) {
					// Loop through events in this record
//					foreach ($event_data as $this_event_id=>&$field_data) {
//						// Loop through fields in this event
                                    
                                                // Loop through fields in order specified in setup
						foreach ($fields as $this_eventfield) {
                                                    
                                                        $this_eventref = LongitudinalReports::getEventFromEventField($this_eventfield);
                                                        $this_field = LongitudinalReports::getFieldFromEventField($this_eventfield);
                                                        
                                                        $this_value = $field_data[$this_eventfield];
                                                                
							// Is value an array? (i.e. a checkbox)
							$value_is_array = is_array($this_value);
							// Check field type
							if ($value_is_array && !$combine_checkbox_values) {
								// Loop through all checkbox choices and add as separate "fields"
								foreach ($this_value as $this_code=>$this_checked_value) {
									// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
									$this_code = (Project::getExtendedCheckboxCodeFormatted($this_code));
									//$record_data_formatted[$recordEventNum][$this_field."___".$this_code] = $this_checked_value;
									$record_data_formatted[$recordNum]["[$this_eventref][$this_field"."___"."$this_code]"] = $this_checked_value;
								}
							} elseif ($value_is_array && $combine_checkbox_values) {
								// Loop through all checkbox choices and create comma-delimited list of all *checked* options as value of single field
								$checked_off_options = array();
								foreach ($this_value as $this_code=>$this_checked_value) {
									// If value is 0 (unchecked), then skip it here. (Also skip if blank, which means that this form not designated for this event.)
									if ($this_checked_value == '0' || $this_checked_value == '' || $this_checked_value == 'Unchecked') continue;
									// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
									// $this_code = (Project::getExtendedCheckboxCodeFormatted($this_code));
									// Add checked off option code to array of checked off options
									$checked_off_options[] = ($outputAsLabels ? $checkbox_choice_labels[$this_code] : $this_code);
								}
								// Add checkbox as single field
								//$record_data_formatted[$recordEventNum][$this_field] = implode(",", $checked_off_options);
								$record_data_formatted[$recordNum]["[$this_eventref][$this_field]"] = implode(",", $checked_off_options);
							} else {
								// Add record name to line
								if ($this_field == $table_pk) {
									//$record_data_formatted[$recordEventNum][$table_pk] = $this_record;
									$record_data_formatted[$recordNum][$table_pk] = $this_record;
//									// If longitudinal, add unique event name to line
//									if ($longitudinal) {
//										if ($outputAsLabels) {
//											$record_data_formatted[$recordEventNum]['redcap_event_name'] = $event_labels[$this_event_id];
//										} else {
//											$record_data_formatted[$recordEventNum]['redcap_event_name'] = $unique_events[$this_event_id];
//										}
//									}
								} else {							
									// Add field and its value
									//$record_data_formatted[$recordEventNum][$this_field] = $this_value;
									$record_data_formatted[$recordNum]["[$this_eventref][$this_field]"] = $this_value;
								}
							}
						}
						
			
                                                // Add data for other fields at the end (DAG, survey stuff...)
                                                if ($outputDags) {
                                                        $record_data_formatted[$recordNum]["redcap_data_access_group"] = $field_data["redcap_data_access_group"];
                                                } 
                                                if ($outputSurveyFields) {
                                                        $record_data_formatted[$recordNum]["Survey Identifier"] = "_";
                                                        $record_data_formatted[$recordNum]["Survey Timestamp"] = "_";
                                                }
                                                if (isset($outputScheduleDates) && count($outputScheduleDates) > 0) {
                                                        foreach ($outputScheduleDates as $evt) {
                                                                $colRef = "[{$Proj->getUniqueEventNames($evt)}][___schedule_date]";
                                                                $record_data_formatted[$recordNum][$colRef] = $field_data[$colRef];
                                                        }
                                                }
                                                if (isset($outputSurveyUrls) && count($outputSurveyUrls) > 0) {
                                                        foreach ($outputSurveyUrls as $evtSurv) {
                                                                //Event id/survey form stored as pipe-separated pair e.g. 2365|my_survey
                                                                $es = explode('|', $evtSurv);
                                                                $colRef = "[{$Proj->getUniqueEventNames($es[0])}][$es[1]___url]";
                                                                //$url = ($returnFormat == 'html') 
                                                                //        ? "<a href=\"{$field_data[$colRef]}\" target=\"_blank\">{$field_data[$colRef]}</a>"
                                                                //        : $field_data[$colRef];
                                                                $record_data_formatted[$recordNum][$colRef] = $field_data[$colRef];
                                                        }
                                                }
                                                // Increment item counter
						//$recordEventNum++;
						$recordNum++;
					//}
					// Remove record from array to free up memory as we go
					unset($record_data[$this_record]);
				}
			}
			unset($record_data);
			
			// APPLY MULTI-FIELD SORTING
			if ($applySortFields) 
			{
				// Move array keys to array with them as values
				$sortFields = array_keys($sortArray);
				$sortTypes  = array_values($sortArray);	
				// Determine if any of the sort fields are numerical fields (number, integer, calc, slider)
				$sortFieldIsNumber = array();
				foreach ($sortFields as $this_sort_field) {
                                    $this_sort_field = LongitudinalReports::getFieldFromEventField($this_sort_field);
					$field_type = $Proj->metadata[$this_sort_field]['element_type'];
					$val_type = $Proj->metadata[$this_sort_field]['element_validation_type'];
					$sortFieldIsNumber[] = (($this_sort_field == $Proj->table_pk && $Proj->project['auto_inc_set']) || $val_type == 'float' || $val_type == 'int' || $field_type == 'calc' || $field_type == 'slider');
				}
				// Loop through each record/event and build separate array for each sort field
				$sortFieldValues = array();
				foreach ($record_data_formatted as &$line) {
					foreach ($sortFields as $key=>$this_sort_field) {
						// Add value to array as lower case (since we need to do case insensitive sorting)
						$sortFieldValues[$key][] = strtolower($line[$this_sort_field]);
					}
				}				
				// print_array($sortFieldValues);
				// Sort the data array
				if (count($sortFieldValues) == 1) {
					// One sort field
					array_multisort($sortFieldValues[0], ($sortTypes[0] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[0] ? SORT_NUMERIC : SORT_STRING), 
									$record_data_formatted);
				} elseif (count($sortFieldValues) == 2) {
					// Two sort fields
					array_multisort($sortFieldValues[0], ($sortTypes[0] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[0] ? SORT_NUMERIC : SORT_STRING), 
									$sortFieldValues[1], ($sortTypes[1] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[1] ? SORT_NUMERIC : SORT_STRING), 
									$record_data_formatted);
				} else {
					// Three sort fields
					array_multisort($sortFieldValues[0], ($sortTypes[0] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[0] ? SORT_NUMERIC : SORT_STRING), 
									$sortFieldValues[1], ($sortTypes[1] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[1] ? SORT_NUMERIC : SORT_STRING), 
									$sortFieldValues[2], ($sortTypes[2] == 'ASC' ? SORT_ASC : SORT_DESC), ($sortFieldIsNumber[2] ? SORT_NUMERIC : SORT_STRING), 
									$record_data_formatted);
				}
				// If any sorting fields did NOT exist in $fields originally (but were added so their data could be obtained for
				// sorting purposes only), then remove them now.
				if (!empty($sortArrayRemoveFromData)) {
					foreach ($sortArrayRemoveFromData as $this_field) {
						foreach ($record_data_formatted as &$this_item) {
							// Remove field from this record-event
							unset($this_item[$this_field]);
						}
					}
				}
				// Remove vars to save memory
				unset($sortFieldValues);
			}
			
			## HTML format (i.e., report)
			if ($returnFormat == 'html') 
			{
				// Build array of events with unique event name as key and full event name as value
				$eventsUniqueFullName = $eventsUniqueEventId = array();
				if ($longitudinal) {
					foreach ($unique_events as $this_event_id=>$this_unique_name) {
						// Arrays event name and event_id with unique event name as key
						$eventsUniqueFullName[$this_unique_name] = str_replace($orig, $repl, strip_tags(label_decode($Proj->eventInfo[$this_event_id]['name_ext'])));
						$eventsUniqueEventId[$this_unique_name] = $this_event_id;
					}
				}
				
				// Build array of DAGs with unique DAG names as key and 
				$dagUniqueFullName = array();
				foreach ($Proj->getUniqueGroupNames() as $this_group_id=>$this_unique_dag) {
					$dagUniqueFullName[$this_unique_dag] = str_replace($orig, $repl, strip_tags(label_decode($Proj->getGroups($this_group_id))));
				}
				
				// Set number of results
				$num_results_returned = count($record_data_formatted);
				
				// If we're JUST returning Records/Events array and NOT the html report, then collect all records/event_ids and return
				if ($returnIncludeRecordEventArray)
				{
					// Collect records/event_ids in array
					$includeRecordsEvents = array();
					foreach ($record_data_formatted as $key=>$item) {
						// Add record/event
						$this_event_id = ($longitudinal) ? $eventsUniqueEventId[$item['redcap_event_name']] : $Proj->firstEventId;
						$includeRecordsEvents[$item[$Proj->table_pk]][$this_event_id] = true;
						// Remove each as we go to save memory
						unset($record_data_formatted[$key]);
					}
					// Return array of the whole table, number of results returned, and total number of items queried
					return array($includeRecordsEvents, $num_results_returned);
				}
				
				// PAGING FOR REPORTS: If has more than $num_per_page results, then page it $num_per_page per page
				// (only do this for pre-defined reports though)
				$num_per_page = LR_RESULTS_PER_PAGE;
				$limit_begin  = 0;
				if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum'])) {
					$limit_begin = ($_GET['pagenum'] - 1) * $num_per_page;
				} elseif (!isset($_GET['pagenum'])) {
					$_GET['pagenum'] = 1;
				} else {
					$_GET['pagenum'] = 'ALL';
				}
				$pageNumDropdown = "";
				//if (isset($_POST['report_id']) && !is_numeric($_POST['report_id']) && $num_results_returned > $num_per_page) 
				if ($num_results_returned > $num_per_page) 
				{
					// Build drop-down list of page numbers
					$num_pages = ceil($num_results_returned/$num_per_page);	
					// Only display drop-down if we have more than one page
					if ($num_pages > 1) {
						// Initialize array of options for drop-down
						$pageNumDropdownOptions = array('ALL'=>'-- '.$lang['docs_44'].' --');
						// Loop through pages
						for ($i = 1; $i <= $num_pages; $i++) {
							$end_num   = $i * $num_per_page;
							$begin_num = $end_num - $num_per_page + 1;
							$value_num = $end_num - $num_per_page;
							if ($end_num > $num_results_returned) $end_num = $num_results_returned;
							// If Record ID field not included in report, then use "results 1 through 100" instead of "A101 through B203" using record names
							if (isset($record_data_formatted[0][$Proj->table_pk])) {
								$resultNamePrefix = $lang['data_entry_177'] . " ";
								$resultName1 = "\"".$record_data_formatted[$begin_num-1][$Proj->table_pk]."\"";
								$resultName2 = "\"".$record_data_formatted[$end_num-1][$Proj->table_pk]."\"";
							} else {
								$resultNamePrefix = $lang['report_builder_112']." ";
								$resultName1 = $begin_num;
								$resultName2 = $end_num;
							}
							$pageNumDropdownOptions[$i] = "{$resultName1} {$lang['data_entry_216']} {$resultName2}";
						}
						// Create HTML for pagenum drop-down
						$pageNumDropdown =  RCView::div(array('class'=>'chklist hide_in_print report_pagenum_div'),
												// Display page number (if performing paging)
												(!(isset($_GET['pagenum']) && is_numeric($_GET['pagenum'])) ? '' :
													RCView::span(array('style'=>'font-weight:bold;margin-right:7px;font-size:13px;'), 
														"{$lang['survey_132']} {$_GET['pagenum']} {$lang['survey_133']} $num_pages{$lang['colon']}"
													)
												) .
												$resultNamePrefix . 
												RCView::select(array('class'=>'x-form-text x-form-field','style'=>'font-size:11px;margin-left:6px;margin-right:4px;padding-right:0;padding-top:1px;height:19px;', 'onchange'=>"loadReportNewPage(this.value);"), 
															   $pageNumDropdownOptions, $_GET['pagenum'], 500) .
												$lang['survey_133'].
												RCView::span(array('style'=>'font-weight:bold;margin:0 4px;font-size:13px;'), 
													User::number_format_user($num_results_returned)
												) .
												$lang['report_builder_113']
											);
						unset($pageNumDropdownOptions);
					}
					// Filter the results down to just a single page
					if (is_numeric($_GET['pagenum'])) {
						$record_data_formatted = array_slice($record_data_formatted, $limit_begin, $num_per_page, true);
					}
				}
				
				// Set extra set of reserved field names for survey timestamps and return codes pseudo-fields
				$reserved_field_names2 = explode(',', implode("_timestamp,", array_keys($Proj->forms)) . "_timestamp"
									   . "," . implode("_return_code,", array_keys($Proj->forms)) . "_return_code");
				$reserved_field_names2 = $reserved_field_names + array_fill_keys($reserved_field_names2, 'Survey Timestamp');
				// Place all html in $html
				$html = $pageNumDropdown . "<table id='report_table' class='dt2' style='margin:0;font-family:Verdana;font-size:11px;'>";
				$mc_choices = array();
				
				// Array to store fields to which user has no form-level access
				$fields_no_access = array(); 
				// Add form fields where user has no access
				foreach ($user_rights['forms'] as $this_form=>$this_access) {
					if ($this_access == '0') {
						$fields_no_access[$this_form . "_timestamp"] = true;
					}
				}
				
				// Loop through header fields and build HTML row
				$datetime_convert = array();
				$row = "";
				foreach ($headers as $this_hdr_ef) {

                                        $this_hdr_ev = LongitudinalReports::getEventFromEventField($this_hdr_ef);
                                        $this_hdr = LongitudinalReports::getFieldFromEventField($this_hdr_ef);
					
                                        // Set original field name
					$this_hdr_orig = $this_hdr;
					// Determine if a checkbox
					$isCheckbox = false;
					$checkbox_label_append = "";
					if (!isset($Proj->metadata[$this_hdr]) && strpos($this_hdr, "___") !== false) {
						// Set $this_hdr as the true field name
						list ($this_hdr, $raw_coded_value_formatted) = explode("___", $this_hdr, 2);
						$isCheckbox = $Proj->isCheckbox($this_hdr);
						// Obtain the label for this checkbox choice
						foreach (parseEnum($Proj->metadata[$this_hdr]['element_enum']) as $raw_coded_value=>$checkbox_label) {
							if ($this_hdr_orig == Project::getExtendedCheckboxFieldname($this_hdr, $raw_coded_value)) {
								$checkbox_label_append = " (Choice = '".strip_tags(label_decode($checkbox_label))."')";
								// If user does not have form-level access to this field's form
								if ($user_rights['forms'][$Proj->metadata[$this_hdr]['form_name']] == '0') {
									$fields_no_access[$this_hdr_orig] = true;
								}
								break;
							}
						}
					}
					// If user does not have form-level access to this field's form
					if (isset($Proj->metadata[$this_hdr]) && $this_hdr != $Proj->table_pk && $user_rights['forms'][$Proj->metadata[$this_hdr]['form_name']] == '0') {
						$fields_no_access[$this_hdr] = true;
					}
					// If field is a reserved field name (redcap_event_name, redcap_data_access_group)
					if (!isset($Proj->metadata[$this_hdr_orig]) && !$isCheckbox) { 
                                                if (isset($reserved_field_names2[$this_hdr_orig])) {
                                                        $field_type = '';
                                                        $field_label_display = strip_tags(label_decode($reserved_field_names2[$this_hdr_orig]));
                                                } else if ($this_hdr_orig == "___schedule_date") {
                                                        $field_label_display = "Schedule Date";
                                                } else if (strpos($this_hdr_orig, "___url") !== false){
                                                        $frm = str_replace("___url", "", $this_hdr_orig);
                                                        $field_label_display = "{$Proj->forms[$frm]['menu']} URL";
                                                }
					} else {
                                                $field_type = $Proj->metadata[$this_hdr]['element_type'];
                                                $field_label = strip_tags(label_decode($Proj->metadata[$this_hdr]['element_label']));
                                                if (strlen($field_label) > 100) $field_label = substr($field_label, 0, 67)." ... ".substr($field_label, -30);
                                                $field_label_display = $field_label . $checkbox_label_append;
					}

                                        // Longitudinal Reports - also include event name in header labels/ref (unless lone pk field)
                                        if ($this_hdr_ev !== '') {
                                            $event_id = $Proj->getEventIdUsingUniqueEventName($this_hdr_ev);
                                            $event_name = REDCap::getEventNames(false, true, $event_id);
                                            $field_label_display .= "<div style='font-weight:normal;color:#800000;margin:3px 0;'>$event_name</div>";
                                        }

                                        // Add field to header html row
					$row .= "<th".(isset($fields_no_access[$this_hdr]) ? " class=\"form_noaccess\"" : '').">" .
							"$field_label_display<div class=\"rprthdr\">" . str_replace('][', ']<br>[', implode("_<wbr>", explode("_", $this_hdr_ef))) . "</div></th>";
					// Place only MC fields into array to reference 
					if (in_array($field_type, array('yesno', 'truefalse', 'sql', 'select', 'radio', 'advcheckbox', 'checkbox'))) {
						// Convert sql field types' query result to an enum format
						$enum = ($field_type == "sql") ? getSqlFieldEnum($Proj->metadata[$this_hdr]['element_enum']) : $Proj->metadata[$this_hdr]['element_enum'];
						// Add to array
						if ($isCheckbox) {
							// Reformat checkboxes to export format field name
							foreach (parseEnum($enum) as $raw_coded_value=>$checkbox_label) {
								$this_hdr_chkbx = $Proj->getExtendedCheckboxFieldname($this_hdr, $raw_coded_value);
								$mc_choices[$this_hdr_chkbx] = array('0'=>"Unchecked", '1'=>"Checked");
							}
						} else {
							$mc_choices[$this_hdr] = parseEnum($enum);
						}
					}
					// Put all date/time fields into array for quick converting of their value to desired date format
					if (!$isCheckbox) {
						$val_type = $Proj->metadata[$this_hdr]['element_validation_type'];
						if (substr($val_type, 0, 4) == 'date' && (substr($val_type, -4) == '_mdy' || substr($val_type, -4) == '_dmy')) {
							// Add field name as key to array with 'mdy' or 'dmy' as value
							$datetime_convert[$this_hdr] = substr($val_type, -3);
						}
                                                if ($this_hdr_orig == '___schedule_date') {
                                                    // Include event schedule dates for date formatting as per user pref
                                                    $formatPref = 'ymd';
                                                    if (substr($datetime_format, 0, 1) == "D") {
                                                        $formatPref = 'dmy';
                                                    } else if (substr($datetime_format, 0, 1) == "M") {
                                                        $formatPref = 'mdy';
                                                    }
                                                    $datetime_convert[$this_hdr_orig] = 'dmy';
                                                }
					}
				}
				$html .= "<thead><tr class=\"hdr2\">$row</tr></thead>";
				// If no data, then output row with message noting this
				if (empty($record_data_formatted)) {
					$html .= RCView::tr(array('class'=>'odd'), 
								RCView::td(array('style'=>'color:#777;', 'colspan'=>count($headers)), 
									$lang['report_builder_87']
								)
							 );
				}				
				
				// If record ID is in report for a classic project and will thus be displayed as a link, then get 
				// the user's first form based on their user rights (so we don't point to a form that they don't have access to.)
				if ($recordIdInFields && !$longitudinal) {
					foreach (array_keys($Proj->forms) as $this_form) {
						if ($user_rights['forms'][$this_form] == '0') continue;
						$first_form = $this_form;
						break;
					}
				}
				
				// DATA: Loop through each row of data (record-event) and output to html
				$j = 1;
				foreach ($record_data_formatted as $key=>&$line) {
					// Set row class
					$class = ($j%2==1) ? "odd" : "even";
					$row = "";
					// Loop through each element in row
					foreach ($line as $this_eventfieldname=>$this_value) 
					{
                                                $this_eventname = LongitudinalReports::getEventFromEventField($this_eventfieldname);
                                                $this_fieldname = LongitudinalReports::getFieldFromEventField($this_eventfieldname);
                                                
						// Check for form-level user access to this field
						if (isset($fields_no_access[$this_fieldname])) {
							// User has no rights to this field
							$row .= "<td class=\"form_noaccess\">-</td>";
						} else {
							// If redcap_event_name field
							if ($this_fieldname == 'redcap_event_name') {
								$cell = $eventsUniqueFullName[$this_value];
							}
							// If DAG field
							elseif ($this_fieldname == 'redcap_data_access_group') {
								$cell = $dagUniqueFullName[$this_value];
							}
                                                        // If survey url
                                                        elseif (strpos($this_fieldname, "___url") !== false) {
                                                                if(filter_var($this_value, FILTER_VALIDATE_URL)) {
                                                                        $hash = substr($this_value, -10);
                                                                        if (strpos($hash, '=') !== false) $hash = substr($this_value, -6);
                                                                        $cell = RCView::a(array('href'=>$this_value, 'target' => '_blank', 'style' => 'margin-right:10px;'), 
										RCView::img(array('src' => 'link.png', 'style'=>'vertical-align:middle;'))
										).
                                                                                RCView::a(array('href'=>'javascript:;', 'onclick'=>"getAccessCode('$hash');"),
                                                                                        (!gd2_enabled() 
                                                                                                ? RCView::img(array('src'=>'ticket_arrow.png', 'style'=>'vertical-align:middle;')) 
                                                                                                : RCView::img(array('src'=>'access_qr_code.gif', 'style'=>'vertical-align:middle;'))
                                                                                        )
                                                                                );
                                                                } else {
                                                                        // Show completion timestamp
									$cell = DateTimeRC::datetimeConvert(substr($this_value, 0, 16), 'ymd', DateTimeRC::get_user_format_base());
                                                                }
                                                        }
							// For a radio, select, or advcheckbox, show both num value and text
							elseif (isset($mc_choices[$this_fieldname])) { 
								// Get option label
								$cell = $mc_choices[$this_fieldname][$this_value];
								// PIPING (if applicable)
								if ($do_label_piping && in_array($this_fieldname, $piping_receiver_fields)) {
									$cell = strip_tags(Piping::replaceVariablesInLabel($cell, $line[$Proj->table_pk], 
											($longitudinal ? $Proj->getEventIdUsingUniqueEventName($line['redcap_event_name']) : $Proj->firstEventId), 
											$piping_record_data));
								}
								// Append raw coded value
								if (trim($this_value) != "") {
									$cell .= " <span class=\"ch\">($this_value)</span>";
								}
							}
							// For survey timestamp fields
							elseif (substr($this_fieldname, -10) == '_timestamp' && isset($reserved_field_names2[$this_fieldname])) { 
								// Convert datetime to user's preferred date format
								if ($this_value == "[not completed]") {
									$cell = $this_value;
								} else {
									$cell = DateTimeRC::datetimeConvert(substr($this_value, 0, 16), 'ymd', DateTimeRC::get_user_format_base());
								}
							}
							// All other fields (text, etc.)
							else 
							{
								// If a date/time field, then convert value to its designated date format (YMD, MDY, DMY)
								if (isset($datetime_convert[$this_fieldname])) {
									$cell = DateTimeRC::datetimeConvert($this_value, 'ymd', $datetime_convert[$this_fieldname]);
								}
								// Replace line breaks with HTML <br> tags for display purposes
								else {
									$cell = nl2br(htmlspecialchars($this_value, ENT_QUOTES));
								}
							}
							// If record name, then convert it to a link (unless project is archived/inactive)
							if ($Proj->project['status'] < 2 && $this_fieldname == $Proj->table_pk) {
								// Link URL
								$this_arm = ($Proj->longitudinal) ? $Proj->eventInfo[$eventsUniqueEventId[$line['redcap_event_name']]]['arm_num'] : $Proj->firstArmNum;
								if ($longitudinal) {
									$this_url = "grid.php?pid={$Proj->project_id}&id=".removeDDEending($this_value)."&arm=$this_arm";
								} else {
									$this_url = "index.php?pid={$Proj->project_id}&id=".removeDDEending($this_value)."&page=".$first_form;
								}
								// If has custom record label, then display it
								$this_custom_record_label = (isset($extra_record_labels[$this_arm][$this_value])) ? "&nbsp; ".$extra_record_labels[$this_arm][$this_value] : '';
								// Wrap record name with link HTML
								$cell = RCView::a(array('href'=>APP_PATH_WEBROOT."DataEntry/$this_url", 'class'=>'rl'), 
											removeDDEending($cell)
										) .
										$this_custom_record_label;
							}
							// Add cell to row
							$row .= "<td>$cell</td>";
						}
					}
					// Add row
					$html .= "<tr class=\"$class\">$row</tr>";
					// Remove line from array to free up memory as we go
					unset($record_data_formatted[$key]);
					$j++;
				}
				unset($row);
				// Build entire HTML table
				$html .= "</table>" . $pageNumDropdown;
				// Return array of the whole table, number of results returned, and total number of items queried
				return array($html, $num_results_returned);
			}
			
			## CSV format
			elseif ($returnFormat == 'csv') {
				// Open connection to create file in memory and write to it
				$fp = fopen('php://memory', "x+");
				// Add header row to CSV
				fputcsv($fp, $headers);
				// Loop through array and output line as CSV
				foreach ($record_data_formatted as $key=>&$line) {
					// Write this line to CSV file
					fputcsv($fp, $line);
					// Remove line from array to free up memory as we go
					unset($record_data_formatted[$key]);
				}
				// Open file for reading and output to user
				fseek($fp, 0);
				$csv_file_contents = stream_get_contents($fp);
				fclose($fp);
				// Return CSV string
				return $csv_file_contents;
			}
			
			## XML format
			elseif ($returnFormat == 'xml') {
				// Convert all data into XML string
				$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<records>\n";				
				// Loop through array and add to XML string
				foreach ($record_data_formatted as $key=>&$item) {
					// Begin item
					$xml .= "<item>";
					// Loop through all fields/values
					foreach ($item as $this_field=>$this_value) {
						// If ]]> is found inside this value, then "escape" it (cannot really escape it but can do clever replace with "]]]]><![CDATA[>")
						if (strpos($this_value, "]]>") !== false) {
							$this_value = str_replace("]]>", "]]]]><![CDATA[>", $this_value);
						}
						// Add value
						$xml .= "<$this_field><![CDATA[$this_value]]></$this_field>";
					}
					// End item
					$xml .= "</item>\n";
					// Remove line from array to free up memory as we go
					unset($record_data_formatted[$key]);
				}
				// End XML string
				$xml .= "</records>";
				// Return XML string
				return $xml;
			} 
			
			## JSON format
			elseif ($returnFormat == 'json') {
				// Convert all data into JSON string (do record by record to preserve memory better)
				$json = '';
				foreach ($record_data_formatted as $key=>&$item) {
					// Loop through each record and encode
					$json .= ",".json_encode($item);
					// Remove line from array to free up memory as we go
					unset($record_data_formatted[$key]);
				}
				return '[' . substr($json, 1) . ']';
			}
		}
	}
	
	
	// APPLY RECORD FILTERING FROM A LOGIC STRING: Get record-events where logic is true
	public static function applyFilteringLogic($logic, $records=array(), $project_id=null)
	{		
		// Skip this if no filtering will be performed
		if ($logic == '') return false;
		
		// Get or create $Proj object
		if (is_numeric($project_id)) {
			// Instantiate object containing all project information
			// This only occurs when calling getData for a project in a plugin in another project's context
			$Proj = new Project($project_id);
		} else {
			// Set global var
			global $Proj;
		}
	
		// Place record list in array
		$records_filtered = array();
		
		// Parse the label to pull out the events/fields used therein
		$fields = array_keys(getBracketedFields($logic, true, true, false));
		
		// If no fields were found in string, then return the label as-is
		if (empty($fields)) return false;
		
		// Instantiate logic parse
		$parser = new LogicParser();
		
		// Check syntax of logic string: If there is an issue in the logic, then return false and stop processing
		// if (!LogicTester::isValid($logic)) return false;
		
		// Loop through fields, and if is longitudinal with prepended event names, separate out those with non-prepended fields
		$events = array();
		$fields_classic = array();
		$fields_no_events = array();
		foreach ($fields as $this_key=>$this_field) 
		{
			// If longitudinal with a dot, parse it out and put unique event name in $events array
			if (strpos($this_field, '.') !== false) {
				// Separate event from field
				list ($this_event, $this_field) = explode(".", $this_field, 2);
				// Add field to fields_no_events array
				$fields_no_events[] = $this_field;
				// Put event in $events array
				$this_event_id = $Proj->getEventIdUsingUniqueEventName($this_event);
				if (!isset($events[$this_event_id])) $events[$this_event_id] = $this_event;
			} else {
				// Add field to fields_no_events array
				$fields_no_events[] = $fields_classic[] = $this_field;
			}
		}
		// Perform unique on $events and $fields arrays
		$fields_no_events = array_unique($fields_no_events);
		$fields_classic = array_unique($fields_classic);
		// If a longitudinal project and some fields in logic are to be evaluated on ALL events, then include all events
		$hasLongitudinalAllEventLogic = false;
		if ($Proj->longitudinal && !empty($fields_classic)) {
			$events = $Proj->getUniqueEventNames();
			// Add flag to denote that some fields need to be checked for ALL events
			$hasLongitudinalAllEventLogic = true;
		}
		// Get all data for these records, fields, events
		$eventsGetData = (empty($events)) ? array_keys($Proj->eventInfo) : array_keys($events);		

                // Longitudinal Reports- use the ordinary Records::getData as this version 
                // is modified for row=record results, not row=record-events!
                //$record_data = self::getData($Proj->project_id, 'array', $records, array_merge(array($Proj->table_pk), $fields_no_events), $eventsGetData);
		$record_data = Records::getData($Proj->project_id, 'array', $records, array_merge(array($Proj->table_pk), $fields_no_events), $eventsGetData);
		// Due to issues where a record contains only BLANK values for the fields $fields_no_events, the record will be removed. 
		// In this case, re-add that record manually as empty to allow logic parsing to work as intended.
		$blank_records = array_diff($records, array_keys($record_data));
		if (!empty($blank_records)) {
			foreach ($blank_records as $this_record) {
				foreach ($eventsGetData as $this_event_id) {
					foreach ($fields_no_events as $this_field) {
						$record_data[$this_record][$this_event_id][$this_field] = '';
					}
				}
			}
		}
                
                // LongitudinalReports - need to add missing events too...
                foreach ($record_data as $this_record_id => $this_record) {
            		$blank_events = array_diff(array_keys($events), array_keys($this_record));
                	if (!empty($blank_events)) {
                                foreach ($blank_events as $this_event_id) {
                                        foreach ($fields_no_events as $this_field) {
                                                $record_data[$this_record_id][$this_event_id][$this_field] = '';
                                        }
                                }
                        }
                }
                
                // Place all logic functions in array so we can call them quickly
		$logicFunctions = array();
		// Loop through all relevent events and build event-specific logic and anonymous logic function
		$event_ids = array_flip($events);
/*		if ($Proj->longitudinal) {
			// Longitudinal
			foreach ($events as $this_event_id=>$this_unique_event) {
				// Customize logic for this event (longitudinal only)
				if ($hasLongitudinalAllEventLogic) {
					$this_logic = LogicTester::logicPrependEventName($logic, $events[$this_event_id]);
				} else {
					$this_logic = $logic;
				}
				// Generate logic function and argument map
				try {
					list ($funcName, $argMap) = $parser->parse($this_logic, $event_ids);
				} catch(ErrorException $e) {
					return false;
				}
				// Add to array
				$logicFunctions[$this_event_id] = array('funcName'=>$funcName, 'argMap'=>$argMap); //, 'code'=>$parser->generatedCode);
			}
		} else {
			// Classic
*/
// Longitudinal Reports - row per participant so evaluate across events                
                // Generate logic function and argument map
			try {
				list ($funcName, $argMap) = $parser->parse($logic, $event_ids);
			} catch(ErrorException $e) {
				return false;
			}
			// Add to array
// incl generated code for info			$logicFunctions[$Proj->firstEventId] = array('funcName'=>$funcName, 'argMap'=>$argMap); //, 'code'=>$parser->generatedCode);
			$logicFunctions[$Proj->firstEventId] = array('funcName'=>$funcName, 'argMap'=>$argMap, 'code'=>$parser->generatedCode);
//		}		
		
		// Loop through each record-event and apply logic
		$records_logic_true = array();
		foreach ($record_data as $this_record=>&$event_data) {
/* No NOT loop through events, just records!
 * 			// Loop through events in this record
			foreach (array_keys($event_data) as $this_event_id) {		
				// Execute the logic to return boolean (return TRUE if is 1 and not 0 or FALSE)
				$logicValid = (LogicTester::applyLogic($logicFunctions[$this_event_id]['funcName'], 
								$logicFunctions[$this_event_id]['argMap'], $event_data, $Proj->firstEventId) === 1);
				// Add record-event to array if logic is valid
				if ($logicValid) $record_events_logic_true[$this_record][$this_event_id] = true;
			}
			// Remove each record as we go to conserve memory
			unset($record_data[$this_record]); */
                        $this_event_id = $Proj->firstEventId;
                        
                        // Execute the logic to return boolean (return TRUE if is 1 and not 0 or FALSE)
                        $logicValid = (LogicTester::applyLogic($logicFunctions[$this_event_id]['funcName'], 
								$logicFunctions[$this_event_id]['argMap'], $event_data, $Proj->firstEventId) === 1);
                        
                        // Add record to array if logic is valid
                        if ($logicValid) $records_logic_true[$this_record] = true;
			// Remove each record as we go to conserve memory
			unset($record_data[$this_record]);
		}
		// Return array of records-events where logic is true
		return $records_logic_true;
	}
	
	
	/**
	 * DATE SHIFTING: Get number of days to shift for a record 
	 */
	public static function get_shift_days($idnumber, $date_shift_max, $__SALT__) 
	{
		global $salt;
		if ($date_shift_max == "") {
			$date_shift_max = 0;
		}
		$dec = hexdec(substr(md5($salt . $idnumber . $__SALT__), 10, 8));
		// Set as integer between 0 and $date_shift_max
		$days_to_shift = round($dec / pow(10,strlen($dec)) * $date_shift_max);
		return $days_to_shift;
	}
	
	
	/**
	 * DATE SHIFTING: Shift a date by providing the number of days to shift
	 */
	public static function shift_date_format($date, $days_to_shift) 
	{
		if ($date == "") return $date;
		// Explode into date/time pieces (in case a datetime field)
		list ($date, $time) = explode(' ', $date, 2);
		// Separate date into components
		$mm   = substr($date, 5, 2) + 0;
		$dd   = substr($date, 8, 2) + 0;
		$yyyy = substr($date, 0, 4) + 0;
		// Shift the date
		$newdate = date("Y-m-d", mktime(0, 0, 0, $mm , $dd - $days_to_shift, $yyyy));
		// Re-add time component (if applicable)
		$newdate = trim("$newdate $time");
		// Return new date/time
		return $newdate;
	}
	
	
	// Return count of all record-event pairs in project (longitudinal only)
	public static function getCountRecordEventPairs()
	{
		global $Proj;
		// Quick and dirty way is to get CSV data output and count the rows
		$csv_data = self::getData($Proj->project_id, 'csv', array(), $Proj->table_pk);
		// Count line breaks (= num records since header is included here)
		$num_records = substr_count(trim($csv_data), "\n");
		// Return count
		return $num_records;
	}
	
}
