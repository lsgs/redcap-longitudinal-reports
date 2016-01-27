<?php
/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 * 
 * Version date 16-Nov-2015 
 * 
 * Installation
 *  - See README.txt
 *  - Set the vaue of the constants below to suit your environment
 *  - Optionally edit the text of the $lang elements below
 */

// Setting these three appropriately is essential...
define('LR_REPORT_DATA_PROJECT_ID', ????);                           // Project id of project where report data is stored
define('LR_REDCAP_CONNECT', __DIR__.'/../../redcap_connect.php');    // Path of redcap_connect.php 
define('LR_PATH_FROM_WEBROOT', '../plugins/longitudinal_reports/');  // Path of lr plugin's directory ***relative to APP_PATH_WEBROOT*** and include end slash

// Settings from here can be left unchanged...
define('LR_PLUGIN_TITLE', 'Longitudinal Reports');                // Title of plugin, displayed on web page
define('LR_RESULTS_PER_PAGE', 1000);                              // Max number of results on a report page before it starts paging the results
define('LR_SHOW_QUICK_ADD_ALL_PROJECT_FIELDS', false);            // Show "select all" option in Quick Add dialog for ALL project fields (v. slow)
define('LR_SHOW_QUICK_ADD_ALL_GROUP_THRESHOLD', 25);              // Hide "select all" option in Quick Add dialog when more than this many fields in event/form group

            require_once(LR_REDCAP_CONNECT);
            require_once('LongitudinalReports.php');
            require_once('LongitudinalRecords.php');


            if( !ini_get('date.timezone') ) {
                date_default_timezone_set('UTC');
                error_log('Default timezone not set in php.ini. Using UTC.');
            }

// Override standard Data Export tool text
$lang['report_builder_15'] = "You may create a new longitudinal report by selecting the event/fields below that you want to include in the report. You may add as many fields to your report as you wish. You will also need to provide a name for your report. When you are finished selecting the fields you wish to include in the report, click the Save Report button at the bottom. The new report will then be added to your list of longitudinal reports above.";
$lang['report_builder_29'] = "Event / field combinations to include in report";
$lang['report_builder_47'] = "My Longitudinal Reports";
$lang['report_builder_117'] = "<p>The Longitudinal Reports module is a <strong>$institution plugin</strong> that extends REDCap's standard Data Export & Reports functionality for longitudinal projects. "
                            . "Build reports by selecting event/field combinations and specifying filter criteria, then get your results listed in row-per-participant format rather than as row-per-participant-per-event.</p>"
                            . "<p>This module is intended to assist with project management tasks: it is <strong>*not*</strong> intended to replace the Data Export module for these projects – you will still need to use that for stats package syntax files, for example.</p>"
                            . "<p>Please report any problems to <a href=\"mailto:$project_contact_email\">$project_contact_name</a>.</p>";
$lang['report_builder_118'] = "This Longitudinal Reports plugin works in a similar manner to REDCap's standard Report Builder.<br>"
                            . "Select the event/field combinations you want to see listed for each record, and specify filter criteria.<br>"
                            . "Please report any problems to <a href=\"mailto:$project_contact_email\">$project_contact_name</a>.";
$lang['report_builder_14'] = "New";
$lang['graphical_view_23'] = "Column";
$lang['graphical_view_59'] = "Select your export settings, which includes the export format and whether or not to perform de-identification on the data set.";
$lang['data_export_tool_179'] = "Survey field(s) TBC";
$lang['custom_reports_03'] = "Number of records in project:"; //"Total number of records queried:";
$lang['custom_reports_09'] = ""; //"('records' = total available data across all designated events)";
