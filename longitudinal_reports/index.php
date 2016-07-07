<?php
/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 * Version date 16-Nov-2015 
 * LS Altered for LongitudinalReport from redcap_v6.4.3/DataExport/index.php
 */

/* 
 * TODO 
 *  - 
 */
 
// Set all parameters and include necessary files
require_once dirname(__FILE__) . '/config.php';


if (!is_numeric($project_id)) redirectHome();

// Check project and user rights for view / export
if (!$Proj->longitudinal || ($user_rights['data_export_tool'] == "0" && $user_rights['reports'] == "0")) {
        $msg = (!$Proj->longitudinal) ? "Not a longitudinal project": "You do not have permission to access this page.";
        include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
        print RCView::div(array('style'=>'max-width:750px;margin-bottom:10px;'),
			RCView::div(array('style'=>'color: #800000;font-size: 16px;font-weight: bold;float:left;'), 
				"Longitudinal Reports" 
			) . RCView::div(array('class'=>'clear'), '') );
        displayMsg($msg, "errorMsg","center","red","exclamation_frame.png", 600);
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php'; 
        exit();
}

// Make sure the user's rights have not expired for the project
if ($user_rights['expiration'] != "" && $user_rights['expiration'] < TODAY) {
        exitWithMessage('Your user account has expired for this project.');
	exit;
}

// Check configuration of report store project
$check = LongitudinalReports::checkReportStoreProjectConfig();
if ($check !== true) {
        exitWithMessage('There is a problem with the configuration of the report store project:<br>'.$check);
	exit;
}


// Place all HTML here
$html = "";

## CREATE NEW REPORT
if (isset($_GET['addedit']))
{
	// Hidden dialog for help with filters and AND/OR logic
	$html .= LongitudinalReports::renderFilterHelpDialog();	
	// Hidden dialog for error popup when field name entered is not valid
	$html .= RCView::div(array('id'=>'VarEnteredNoExist_dialog', 'class'=>'simpleDialog'), $lang['report_builder_72']);
	// Add the actual "create report" table's HTML at the very bottom since we're doing a direct print. So output the buffer and disable buffering.
	ob_end_flush();
}
/*## OTHER EXPORT OPTIONS
elseif (isset($_GET['other_export_options']) && $user_rights['data_export_tool'] > 0)
{
	$html .= // Instructions
			RCView::p(array('style'=>'max-width:700px;margin:5px 0 10px;'),
				$lang['report_builder_116']
			) .
			// Get html for displaying additional export options
			LongitudinalReports::outputOtherExportOptions();
}*/
## VIEW LIST OF ALL REPORTS
elseif (!isset($_GET['report_id']))
{
	$html .= 	// Instructions
				RCView::p(array('style'=>'max-width:810px;margin:5px 0 15px;'),
					$lang['report_builder_117']
				) .
				// Report list table
				RCView::div(array('id'=>'report_list_parent_div'), 
					LongitudinalReports::renderReportList()
				 );
}
/*## VIEW STATS & CHARTS
elseif (isset($_GET['stats_charts']) && isset($_GET['report_id']) 
	&& (is_numeric($_GET['report_id']) || in_array($_GET['report_id'], array('ALL', 'SELECTED'))))
{
	// Get html for all the fields to display for report
	$html .= LongitudinalReports::outputStatsCharts(	$_GET['report_id'],
											(isset($_GET['instruments']) ? explode(',', $_GET['instruments']) : array()),
											(isset($_GET['events']) ? explode(',', $_GET['events']) : array()));
}*/
## VIEW REPORT
elseif (isset($_GET['report_id']) && (is_numeric($_GET['report_id']) || in_array($_GET['report_id'], array('ALL', 'SELECTED'))))
{
	// Get report name
	$report_name = LongitudinalReports::getReportNames($_GET['report_id'], !$user_rights['reports']);
	// If report name is NULL, then user doesn't have Report Builder rights AND doesn't have access to this report
	if ($report_name === null) {
		$html .= RCView::div(array('class'=>'red'),
					$lang['global_01'] . $lang['colon'] . " " . $lang['data_export_tool_180']
				);
	} else {
		// Display progress while report loads via ajax
		$html .= RCView::div(array('id'=>'report_load_progress', 'style'=>'display:none;margin:5px 0 25px 20px;color:#777;font-size:18px;'),
					RCView::img(array('src'=>'progress_circle.gif', 'class'=>'imgfix2')) .
					$lang['report_builder_60'] . " \"" .
					RCView::span(array('style'=>'color:#800000;font-size:18px;'),
						$report_name
					) .
					"\"" .
					RCView::span(array('id'=>'report_load_progress_pagenum_text', 'style'=>'display:none;margin-left:10px;color:#777;font-size:14px;'),
						"({$lang['global_14']} " . 
						RCView::span(array('id'=>'report_load_progress_pagenum'), '1')  . 
						")"
					)
				 ) .
				 RCView::div(array('id'=>'report_load_progress2', 'style'=>'display:none;margin:5px 0 0 20px;color:#999;font-size:18px;'),
					RCView::img(array('src'=>'hourglass.png', 'class'=>'imgfix2')) .
					$lang['report_builder_115']
				 );
		// Div where report will go
		$html .= RCView::div(array('id'=>'report_parent_div', 'style'=>''), '');
	}
}


// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
print	RCView::div(array('style'=>'max-width:750px;margin-bottom:10px;'),
			RCView::div(array('style'=>'color: #800000;font-size: 16px;font-weight: bold;float:left;'), 
				"Longitudinal Reports" /* $lang['app_23'] */
			) .
			/*RCView::div(array('style'=>'float:right;padding:0 5px 5px 0;'), 
				// VIDEO link
				RCView::img(array('src'=>'video_small.png', 'class'=>'imgfix')) .
				RCView::a(array('href'=>'javascript:;', 'style'=>'font-weight:normal;text-decoration:underline;', 'onclick'=>"popupvid('exports_reports01.mp4','".cleanHtml($lang['report_builder_131'])."');"), 
					"{$lang['global_80']} {$lang['report_builder_131']}"
				)
			) .*/
			RCView::div(array('class'=>'clear'), '')
		);
// JavaScript files
callJSfile('jquery_tablednd.js');
callJSfile('../../'.LR_PATH_FROM_WEBROOT.'LongitudinalReports.js');
// Hidden dialog to choose export format
$html .= LongitudinalReports::renderExportOptionDialog();
/*// Hidden dialog for Shared Library manuscript citation
if ($Proj->formsFromLibrary()) {
	$html .= "<!-- Hidden citation for Shared Library manuscript -->
			<div class='simpleDialog' style='font-size:13px;' id='rsl_cite' title='".cleanHtml($lang['data_export_tool_146'])."'>
				Jihad S. Obeid, Catherine A. McGraw, Brenda L. Minor, Jos&eacute; G. Conde, Robert Pawluk, Michael Lin, Janey Wang, Sean R. Banks, Sheree A. Hemphill, Rob Taylor, Paul A. Harris, 
				<b>Procurement of shared data instruments for Research Electronic Data Capture (REDCap)</b>, Journal of Biomedical Informatics, Available online 10 November 2012, ISSN 1532-0464, 10.1016/j.jbi.2012.10.006.
				(<a target='_blank' style='text-decoration:underline;' href='http://www.sciencedirect.com/science/article/pii/S1532046412001608'>http://www.sciencedirect.com/science/article/pii/S1532046412001608</a>)
			</div>";
}*/
?>
<style type="text/css">
.ui-autocomplete {
	max-height: 200px;
	overflow-y: auto;
	/* prevent horizontal scrollbar */
	overflow-x: hidden;
}
/* IE 6 doesn't support max-height, so use height instead */
* html .ui-autocomplete {
	height: 200px;
}
.report_pagenum_div { margin:0;padding:8px 15px 7px;max-width:100%;width:700px; }
table#export_choices_table tr td {
	border: 1px solid #eee;
}
table#report_table tr td, div.FixedHeader_Cloned table.dt2 tr td {
	border:1px solid #ccc;
	border-right:1px solid #aaa;
	border-left:1px solid #aaa;
}
table#report_table tr td span.ch { color:#777; font-size: 8pt; font-family:Verdana,Arial; font-weight:normal; }
table#report_table tr td a.rl, div.FixedHeader_Cloned table.dt2 tr td a.rl { font-size:8pt;font-family:Verdana;text-decoration:underline; }
table#report_table tr th, div.FixedHeader_Cloned table.dt2 tr th { padding: 5px; line-height: 11px; }
th.form_noaccess { background:#eee;color:#777; }
td.form_noaccess { background:#ddd;color:#777;text-align:center; }
.shadow {
	-moz-box-shadow: 3px 3px 3px #ddd;
	-webkit-box-shadow: 3px 3px 3px #ddd;
	box-shadow: 3px 3px 3px #ddd;
}
.export_box {
	border-bottom-left-radius:10px 10px;
	border-bottom-right-radius:10px 10px;
	border-top-left-radius:10px 10px;
	border-top-right-radius:10px 10px;
}
.export_hdr {
	font-weight: bold;
	font-size: 16px;
	border-bottom: 1px solid #eee;
	margin: 2px 0 8px;
}
.rprt_selected_hidden { display: none; }
.create_rprt_hdr { background:url("<?php print APP_PATH_IMAGES ?>bg_gray.png") 0% 0 repeat-x;color:#800000;height:40px;font-size:14px; }
.field-dropdown-div .fn { font-family:arial;color:#555;font-size:11px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;padding:7px 0 0 30px;width:270px; }
.field-dropdown-div .fna { font-weight:normal;margin-right:3px; }
.crl { white-space: normal; };
</style>
<script type="text/javascript">
var lrPluginPath = '<?php print LR_PATH_FROM_WEBROOT ?>'; // relative to app_path_webroot, use like app_path_webroot+lrPluginPath+'report_order_ajax.php?pid='+pid'
// Set variable if user has "reports" user rights
var user_rights_reports = <?php print $user_rights['reports'] ?>;
<?php if (isset($_GET['addedit'])) { ?>
	// List of field variables/labels for auto suggest
	var autoSuggestFieldList = <?php print LongitudinalReports::getAutoSuggestJsString() ?>;
	// List of all possible filter operators
	var allLimiterOper = new Object();
	<?php
	foreach (LongitudinalReports::getLimiterOperators() as $key=>$val) {
		// Change "not =" to "<>"
		if ($val == "not =") $val = "<>";
		print "allLimiterOper['$key'] = '".cleanHtml($val)."';\n";
	}
	?>
	// List of unique events
	var uniqueEvents = new Object();
	uniqueEvents[''] = '';
	<?php
	foreach ($Proj->getUniqueEventNames() as $key=>$val) {
		print "uniqueEvents['$key'] = '$val';\n";
	}
	?>
	// List of forms with comma-delimited list of fields in each form
	var formFields = new Object();
	<?php
	foreach ($Proj->forms as $key=>$val) {
		print "formFields['$key']='".implode(',', array_keys($val['fields']))."';\n";
	}
	?>
	// List of fields with their respective form name
	var fieldForms = new Object();
	<?php
	foreach ($Proj->metadata as $this_field=>$attr) {
		print "fieldForms['$this_field']='{$attr['form_name']}';";
	}
	print "\nvar formLabels = new Object();\n";
	foreach ($Proj->forms as $key=>$attr) {
		print "formLabels['$key']='".cleanHtml($attr['menu'])."';";
	}
} 
?>

// Language variables
var langQuestionMark = '<?php print cleanHtml($lang['questionmark']) ?>';
var closeBtnTxt = '<?php print cleanHtml($lang['global_53']) ?>';
var exportBtnTxt = '<?php print cleanHtml($lang['report_builder_48']) ?>';
var langSaveValidate = '<?php print cleanHtml($lang['report_builder_52']) ?>';
var langIconSaveProgress = '<?php print cleanHtml($lang['report_builder_55']) ?>';
var langIconSaveProgress2 = '<?php print cleanHtml($lang['report_builder_56']) ?>';
var langCancel = '<?php print cleanHtml($lang['global_53']) ?>';
var langNoTitle = '<?php print cleanHtml($lang['report_builder_68']) ?>';
var langNoUserAccessSelected = '<?php print cleanHtml($lang['report_builder_69']) ?>';
var langNoFieldsSelected = '<?php print cleanHtml($lang['report_builder_70']) ?>';
var langLimitersIncomplete = '<?php print cleanHtml($lang['report_builder_71']) ?>';
var langTypeVarName = '<?php print cleanHtml($lang['report_builder_30']) ?>';
var langDragReport = '<?php print cleanHtml($lang['report_builder_75']) ?>';
var langDelete = '<?php print cleanHtml($lang['global_19']) ?>';
var langDeleteReport = '<?php print cleanHtml($lang['report_builder_11']) ?>';
var langDeleteReportConfirm = '<?php print cleanHtml($lang['report_builder_76']) ?>';
var langCopy = '<?php print cleanHtml($lang['report_builder_46']) ?>';
var langCopyReport = '<?php print cleanHtml($lang['report_builder_08']) ?>';
var langCopyReportConfirm = '<?php print cleanHtml($lang['report_builder_77']) ?>';
var langExporting = '<?php print cleanHtml($lang['report_builder_51']) ?>';
var langConvertToAdvLogic = '<?php print cleanHtml($lang['report_builder_94']) ?>';
var langConvertToAdvLogic2 = '<?php print cleanHtml($lang['report_builder_95']) ?>';
var langConvertToAdvLogic3 = '<?php print cleanHtml($lang['report_builder_97']) ?>';
var langConvertToAdvLogic4 = '<?php print cleanHtml($lang['report_builder_98']) ?>';
var langConvertToAdvLogic5 = '<?php print cleanHtml($lang['report_builder_99']) ?>';
var langConvert = '<?php print cleanHtml($lang['report_builder_96']) ?>';
var langPreviewLogic = '<?php print cleanHtml($lang['report_builder_100']) ?>';
var langChooseOtherfield = '<?php print cleanHtml($lang['report_builder_103']) ?>';
var langError = '<?php print cleanHtml($lang['global_01']) ?>';
var langReportFailed = '<?php print cleanHtml($lang['report_builder_128']) ?>';
var langExportFailed = '<?php print cleanHtml($lang['report_builder_129']) ?>';

// Add CSRF token as javascript variable and add to every form on page
// init_functions.php createCsrfToken() does not work on pages with defined('PLUGIN')
// CSRF token is required for call to advanced logic checking in 
// Surveys/automated_invitations_check_logic.php from LongitudinalReports.js function saveReport()
var redcap_csrf_token = '<?php echo getCsrfToken() ?>';
$(function(){ appendCsrfTokenToForm(); });

</script>
<?php
// Tabs
LongitudinalReports::renderTabs();
// Output content
print $html;
// If displaying the "add/edit report" table, do direct Print to page because $html might get very big
if (isset($_GET['addedit'])) {
	LongitudinalReports::outputCreateReportTable($_GET['report_id']);
}
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';


// Classes for backward-compatibility with versions < 6.13.0
if (version_compare($redcap_version, '6.13.0', '<')) {
?>
<style type='text/css'>
.labelrc {
	font-family: "Helvetica Neue",Helvetica,Arial,Helvetica,sans-serif;
	font-size: 12px;
        background: #F0F0F0 url(../images/label-bg.gif) repeat-x scroll 0 0;
	padding: 2px; border: 1px solid #CCCCCC; font-weight: bold; padding-left: 5px; padding-right: 5px; 
}
.labelrc a:link, .labelrc a:visited, .labelrc a:active, .labelrc a:hover {
	text-decoration: underline;
	font-family: "Helvetica Neue",Helvetica,Arial,Helvetica,sans-serif;
	font-size: 12px;
}
</style>
<?php
}

function exitWithMessage($msg) {
    include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
    print RCView::div(array('style'=>'max-width:750px;margin-bottom:10px;'),
                    RCView::div(array('style'=>'color: #800000;font-size: 16px;font-weight: bold;float:left;'), 
                            LR_PLUGIN_TITLE
                    ) . RCView::div(array('class'=>'clear'), '') );
    displayMsg($msg, "errorMsg","center","red","exclamation_frame.png", 600);
    include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php'; 
    exit();
}