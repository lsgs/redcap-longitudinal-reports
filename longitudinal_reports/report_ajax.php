<?php
/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 * Version date 16-Nov-2015 
 */

require_once dirname(__FILE__) . '/config.php';



// Get html report table
list ($report_table, $num_results_returned) = LongitudinalReports::doReport($_POST['report_id'], 'report', 'html', false, false, false, false, 
												false, false, false, false, false, false, false,
												(isset($_GET['instruments']) ? explode(',', $_GET['instruments']) : array()),
												(isset($_GET['events']) ? explode(',', $_GET['events']) : array()));
// Display report and title and other text
print  	"<div id='report_div' style='margin:10px 0 20px;'>" .
			RCView::div(array('style'=>''),
				RCView::div(array('class'=>'hide_in_print', 'style'=>'float:left;width:350px;'), 
					RCView::div(array('style'=>'font-weight:bold;'), 
						$lang['custom_reports_02'] . 
						RCView::span(array('style'=>'margin-left:5px;color:#800000;font-size:15px;'), 
							User::number_format_user($num_results_returned)
						)
					) .
					RCView::div(array('style'=>''), 
						$lang['custom_reports_03'] . 
						RCView::span(array('style'=>'margin-left:5px;'), User::number_format_user(LongitudinalRecords::getRecordCount())) . // Records::getCountRecordEventPairs())) .
						(!$longitudinal ? "" :
							RCView::div(array('style'=>'margin-top:3px;color:#888;font-size:11px;font-family:tahoma,arial;'), 
								$lang['custom_reports_09']
							)
						)
					)
				) .
				RCView::div(array('class'=>'hide_in_print', 'style'=>'float:left;'),
				/*	// Stats & Charts button
					(!$user_rights['graphical'] || !$enable_plotting ? '' : 
						RCView::button(array('class'=>'report_btn jqbuttonmed', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT."DataExport/index.php?pid=".PROJECT_ID."&report_id={$_POST['report_id']}&stats_charts=1'+getInstrumentsListFromURL();", 'style'=>'font-size:11px;padding:1px 4px 0px !important;'),
							RCView::img(array('src'=>'chart_bar.png', 'style'=>'vertical-align:middle;')) .
							RCView::span(array('style'=>'vertical-align:middle;'),
								$lang['report_builder_78']
							)
						)
					) .
					RCView::SP .*/
					// Export Data button
					($user_rights['data_export_tool'] == '0' ? '' :
						RCView::button(array('class'=>'report_btn jqbuttonmed', 'onclick'=>"showExportFormatDialog('{$_POST['report_id']}');", 'style'=>'font-size:11px;padding:1px 4px 0px !important;'),
							RCView::img(array('src'=>'go-down.png', 'style'=>'vertical-align:middle;')) .
							RCView::span(array('style'=>'vertical-align:middle;'),
                                $lang['report_builder_48'] ?? $lang['custom_reports_12']
							)
						)
					) .
					RCView::SP .
					// Print link
					RCView::button(array('class'=>'report_btn jqbuttonmed', 'onclick'=>"window.print();", 'style'=>'font-size:11px;padding:1px 4px 0px !important;'),
						RCView::img(array('src'=>'printer.png', 'style'=>'vertical-align:middle;')) .
						RCView::span(array('style'=>'vertical-align:middle;'),
							$lang['custom_reports_13']
						)
					) . 
					RCView::SP .
					($_POST['report_id'] == 'ALL' || $_POST['report_id'] == 'SELECTED' || !$user_rights['reports'] ? '' :
						// Edit report link
						RCView::button(array('class'=>'report_btn jqbuttonmed', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT.LR_PATH_FROM_WEBROOT."index.php?pid=".PROJECT_ID."&report_id={$_POST['report_id']}&addedit=1';", 'style'=>'font-size:11px;padding:1px 4px 0px !important;'),
							RCView::img(array('src'=>'pencil_small.png', 'style'=>'vertical-align:middle;')) .
							RCView::span(array('style'=>'vertical-align:middle;'),
								$lang['custom_reports_14']
							)
						)
					)
				) .
				RCView::div(array('class'=>'clear'), '')
			) .
			// Report title
			RCView::div(array('id'=>'this_report_title', 'style'=>'margin:40px 0 8px;padding:5px 3px;color:#800000;font-size:18px;font-weight:bold;'), 
				// Title
				LongitudinalReports::getReportNames($_POST['report_id'])
			) .
			// Report table
			$report_table .
		"</div>";
