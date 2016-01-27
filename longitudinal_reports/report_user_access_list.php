<?php
/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 * Version date 16-Nov-2015 
 */

require_once dirname(__FILE__) . '/config.php';

// Display list of usernames who would have access
$content = LongitudinalReports::displayReportAccessUsernames($_POST);
// Output JSON
print json_encode(array('content'=>$content, 'title'=>$lang['report_builder_108']));