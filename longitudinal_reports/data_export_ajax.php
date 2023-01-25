<?php
/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 * Version date 16-Nov-2015 
 */

require_once dirname(__FILE__) . '/config.php';

// Does user have De-ID rights?
$deidRights = ($user_rights['data_export_tool'] == '2');
// Determine whether to output a stats syntax file
$stats_packages = array('r', 'spss', 'stata', 'sas');
$outputFormat = (!in_array($_POST['export_format'], $stats_packages) && $_POST['export_format'] != 'csvlabels') ? 'csvraw' : $_POST['export_format'];
// Archive the export files in File Repository?
$archiveFiles = true; //($_POST['export_options_archive'] == '1');
// Export DAG names?
$outputDags = ($user_rights['group_id'] == '' && isset($_POST['export_groups']) && $_POST['export_groups'] == 'on');
// Export survey fields
$outputSurveyFields = (isset($_POST['export_survey_fields']) && $_POST['export_survey_fields'] == 'on');
// De-Identification settings
$hashRecordID = (($Proj->table_pk_phi && $deidRights) || (isset($_POST['deid-hashid']) && $_POST['deid-hashid'] == 'on'));
$removeIdentifierFields = ($user_rights['data_export_tool'] == '3' || $deidRights || (isset($_POST['deid-remove-identifiers']) && $_POST['deid-remove-identifiers'] == 'on'));
$removeUnvalidatedTextFields = ($deidRights || (isset($_POST['deid-remove-text']) && $_POST['deid-remove-text'] == 'on'));
$removeNotesFields = ($deidRights || (isset($_POST['deid-remove-notes']) && $_POST['deid-remove-notes'] == 'on'));
$removeDateFields = (isset($_POST['deid-dates-remove']) && $_POST['deid-dates-remove'] == 'on');
$dateShiftDates = (!$removeDateFields && isset($_POST['deid-dates-shift']) && $_POST['deid-dates-shift'] == 'on');
$dateShiftSurveyTimestamps = (!$removeDateFields && isset($_POST['deid-surveytimestamps-shift']) && $_POST['deid-surveytimestamps-shift'] == 'on');
// For de-id rights, make sure dates are either removed or date shifted
if ($deidRights && !$dateShiftDates && !$removeDateFields) $removeDateFields = true;

// Export the data for this report
$saveSuccess = LongitudinalReports::doReport($_POST['report_id'], 'export', $outputFormat, false, $archiveFiles, $outputDags, $outputSurveyFields, 
									 $removeIdentifierFields, $hashRecordID, $removeUnvalidatedTextFields, $removeNotesFields, 
									 $removeDateFields, $dateShiftDates, $dateShiftSurveyTimestamps,
									 (isset($_GET['instruments']) ? explode(',', $_GET['instruments']) : array()),
									 (isset($_GET['events']) ? explode(',', $_GET['events']) : array()));
if ($saveSuccess === false) exit('0');
// Parse response to get the doc_id's of the files
$data_edoc_id   = $saveSuccess[0];
$syntax_edoc_id = $saveSuccess[1];


// Set language based on export file type
switch ($outputFormat) 
{
	case "spss": 	
		$docs_header = $lang['data_export_tool_07'];
		$docs_logo = "spsslogo_small.png";
		$instr = $lang['data_export_tool_08'].'<br>
				<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$("#spss_detail").toggle("fade");\'>'.$lang['data_export_tool_08b'].'</a>
				<div style="display:none;border-top:1px solid #aaa;margin-top:5px;padding-top:3px;" id="spss_detail">'.
					$lang['data_export_tool_08c'].' C:\folder\otherfolder<br><br>'.
					$lang['data_export_tool_08d'].'
					<br><font color=green>FILE HANDLE data1 NAME=\'DATA.CSV\' LRECL=10000.</font><br><br>'.
					$lang['data_export_tool_08e'].'<br>
					<font color=green>FILE HANDLE data1 NAME=\'<font color=red>C:\folder\otherfolder\</font>DATA.CSV\' LRECL=10000.</font><br><br>'.
					$lang['data_export_tool_08f'].'
				</div>';
		break;
	case "sas": 	
		$docs_header = $lang['data_export_tool_11'];
		$docs_logo = "saslogo_small.png";
		$instr = $lang['data_export_tool_130'].'<br>
				<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$("#sas_detail").toggle("fade");\'>'.$lang['data_export_tool_08b'].'</a>
				<div style="display:none;border-top:1px solid #aaa;margin-top:5px;padding-top:3px;" id="sas_detail">
					<b>'.$lang['data_export_tool_131'].'</b><br>'.
					$lang['data_export_tool_132'].' <font color="green">/folder/subfolder/</font> (e.g., /Users/administrator/documents/)<br><br>'.
					$lang['data_export_tool_133'].'
					<br>... <font color=green>infile \'DATA.CSV\' delimiter = \',\' MISSOVER DSD lrecl=32767 firstobs=1 ;</font><br><br>'.
					$lang['data_export_tool_08e'].'<br>
					... <font color=green>infile \'<font color=red>/folder/subfolder/</font>DATA.CSV\' delimiter = \',\' MISSOVER DSD lrecl=32767 firstobs=1 ;</font><br><br>'.
					$lang['data_export_tool_134'].'
				</div>';
		break;
	case "stata": 	
		$docs_header = $lang['data_export_tool_187'];
		$docs_logo = "statalogo_small.png";	
		$instr = $lang['data_export_tool_14'];
		break;
	case "r": 		
		$docs_header = $lang['data_export_tool_09'];
		$docs_logo = "rlogo_small.png";
		$instr = $lang['data_export_tool_10'];
		break;
	default:
		$docs_header = $lang['data_export_tool_172'] . " " 
					 . ($outputFormat == 'csvraw' ? $lang['report_builder_49'] : $lang['report_builder_50']);
		$docs_logo = "excelicon.gif";
		$instr = "{$lang['data_export_tool_118']}<br><br><i>{$lang['global_02']}{$lang['colon']} {$lang['data_export_tool_17']}</i>";
}
		
// SEND-IT LINKS: If Send-It is not enabled for Data Export and File Repository, then hide the link to utilize Send-It
$senditLinks = "";
if ($sendit_enabled == '1' || $sendit_enabled == '3') 
{
	$senditLinks = 	RCView::div(array('style'=>''),
						RCView::img(array('src'=>'mail_small.png', 'style'=>'vertical-align:middle;')) .
						RCView::a(array('href'=>'javascript:;', 'style'=>'vertical-align:middle;line-height:10px;color:#666;font-size:10px;text-decoration:underline;', 
							'onclick'=>"displaySendItExportFile($data_edoc_id);"), $lang['docs_53']
						)
					) .
					RCView::div(array('id'=>"sendit_$data_edoc_id", 'style'=>'display:none;padding:4px 0 4px 6px;'),
						// Syntax file
						($syntax_edoc_id == null ? '' :
							RCView::div(array(),
								" &bull; " .
								RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:10px;', 'onclick'=>"popupSendIt($syntax_edoc_id,2);"), 
									$lang['docs_55']
								)
							)
						) .
						// Data file
						RCView::div(array(),
							" &bull; " .
							RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:10px;', 'onclick'=>"popupSendIt($data_edoc_id,2);"), 
								($syntax_edoc_id != null ? $lang['docs_54'] : ($outputFormat == 'csvraw' ? $lang['data_export_tool_119'] : $lang['data_export_tool_120']))
							)
						)
					);
}
/*
// Display Pathway Mapper icon for SPSS or SAS only		
$pathway_mapper = "";					
if ($outputFormat == "spss") {
	$pathway_mapper =  "<div style='padding-bottom:5px;'>
							<a href='".APP_PATH_WEBROOT."DataExport/spss_pathway_mapper.php?pid=$project_id'
							><img src='".APP_PATH_IMAGES."download_pathway_mapper.gif'></a> &nbsp; 
						</div>";
} else if ($outputFormat == "sas") {
	$pathway_mapper =  "<div style='padding-bottom:5px;'>
							<a href='".APP_PATH_WEBROOT."DataExport/sas_pathway_mapper.php?pid=$project_id'
							><img src='".APP_PATH_IMAGES."download_pathway_mapper.gif'></a> &nbsp; 
						</div>";
}
*/

## NOTICES FOR CITATIONS (GRANT AND/OR SHARED LIBRARY) AND DATE-SHIFT NOTICE
//Do not display grant statement unless $grant_cite has been set for this project.
$citationText = "";
if ($grant_cite != "") {
	$citationText .= "{$lang['data_export_tool_77']} $site_org_type {$lang['data_export_tool_78']} <b>($grant_cite)</b> 
					   {$lang['data_export_tool_79']}
					   <div style='padding:8px 0 0;'>{$lang['data_export_tool_80']}";
} else {
	$citationText .= "<div>" . $lang['data_export_tool_81'];
}
$citationText .= " " . $lang['data_export_tool_82'] . " <a href='http://projectredcap.org/cite.php' target='_blank' style='text-decoration:underline;'>{$lang['data_export_tool_83']}</a>){$lang['period']}</div>";
// If instruments have been downloaded from the Shared Library, provide citatation
if ($Proj->formsFromLibrary()) {
	$citationText .= "<div style='padding:8px 0 0;'>
						{$lang['data_export_tool_144']}
						<a href='javascript:;' style='text-decoration:underline;' onclick=\"simpleDialog(null,null,'rsl_cite',550);\">{$lang['data_export_tool_145']}</a>
					  </div>";
}
if ($citationText != '') {
	$citationText = RCView::fieldset(array('style'=>'margin-top:10px;padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;color:#800000;'), 
						RCView::legend(array('style'=>'font-weight:bold;'),
							$lang['data_export_tool_147']
						) .
						RCView::div(array('style'=>'padding:5px 8px 8px 2px;'),
							$citationText
						)
					);
}
// If dates were date-shifted, give note of that.
$dateShiftText = "";
if ($dateShiftDates) {
	$dateShiftText = RCView::fieldset(array('class'=>'red', 'style'=>'margin-top:10px;padding:0 0 0 8px;max-width:1000px;'), 
						RCView::legend(array('style'=>'font-weight:bold;'),
							$lang['global_03']
						) .
						RCView::div(array('style'=>'padding:5px 8px 8px 2px;'),
							"{$lang['data_export_tool_85']} $date_shift_max {$lang['data_export_tool_86']}"
						)
					);
}

// RESPONSE
$downloadPath = (REDCap::versionCompare(REDCAP_VERSION, '13.0.0') >= 0) 
    ? "index.php?pid=$project_id&route=FileRepositoryController:download&id=$data_edoc_id"
    : "FileRepository/file_download.php?pid=$project_id&id=$data_edoc_id";
$dialog_title = 	RCView::img(array('src'=>'tick.png', 'style'=>'vertical-align:middle')) .
					RCView::span(array('style'=>'color:green;vertical-align:middle;font-size:15px;'), $lang['data_export_tool_05']);
$dialog_content = 	RCView::div(array('style'=>'margin-bottom:20px;'),
						//$lang['data_export_tool_183'] .
						//$citationText .
						$dateShiftText
					) .
					RCView::div(array('style'=>'background-color:#F0F0F0;border:1px solid #888;padding:10px 5px;margin-bottom:10px;'),
						RCView::table(array('style'=>'border-collapse:collapse;width:100%;table-layout:fixed;'),
							RCView::tr(array(),
								RCView::td(array('rowspan'=>'3', 'valign'=>'top', 'style'=>'padding-left:10px;width:70px;'),
									RCView::img(array('src'=>$docs_logo, 'title'=>$docs_header))
								) .
								RCView::td(array('rowspan'=>'3', 'valign'=>'top', 'style'=>'line-height:14px;border-right:1px solid #ccc;font-family:Verdana;font-size:11px;padding-right:20px;'),
									RCView::div(array('style'=>'font-size:14px;font-weight:bold;margin-bottom:10px;'), $docs_header) . 
									$instr
								) .
								RCView::td(array('valign'=>'top', 'class'=>'nowrap', 'style'=>'color:#666;font-size:11px;padding:0 5px 0 10px;width:125px;'),
									$lang['data_export_tool_184']
								)
							) .
							// Download icons
                            RCView::tr(array(),
								RCView::td(array('valign'=>'top', 'class'=>'nowrap', 'style'=>'padding:10px 0 0 20px;'),
/*									// Syntax file download icon
									($syntax_edoc_id == null ? '' :
										RCView::a(array('href'=>APP_PATH_WEBROOT."FileRepository/file_download.php?pid=$project_id&id=$syntax_edoc_id"), 
											trim(DataExport::getDownloadIcon($outputFormat))
										)
									) .*/
									RCView::SP . RCView::SP . RCView::SP .
									// Data CSV file download icon
									RCView::a(array('href'=>APP_PATH_WEBROOT.$downloadPath .
										// For R and Stata, add "exporttype" flag to remove BOM from UTF-8 encoded files because the BOM can cause data import issues into R and Stata
										($outputFormat == 'r' ? '&exporttype=R' : ($outputFormat == 'stata' ? '&exporttype=STATA' : ''))), 
										trim(LongitudinalReports::getDownloadIcon(($syntax_edoc_id == null ? $outputFormat : ''), $dateShiftDates))
									) .
									// Pathway mapper file (for SAS and SPSS only)
									$pathway_mapper
								)
							) .
							// Send-It links
							RCView::tr(array(),
								RCView::td(array('valign'=>'bottom', 'style'=>'padding-left:20px;'), $senditLinks)
							)
						)
					);
print json_encode(array('title'=>$dialog_title, 'content'=>$dialog_content));