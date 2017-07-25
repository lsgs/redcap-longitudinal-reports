********************************************************************************
# Longitudinal Reports REDCap Plugin

Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
********************************************************************************


********************************************************************************
## Functionality

The "Longitudinal Reports" plugin lets you see row-per-participant data for 
longitudinal REDCap projects, rather than the row-per-event-per-participant
view that REDCap's standard Data Exports & Reports module gives you.

For more description and background on the problem this plugin aims to solve
please see my "Longitudinal Reports" presentation from REDCapCon 2015, included 
in this repo (Conference_Presentation.pdf) and available via the conference 
presentations page at https://starbrite.vanderbilt.edu/rocket/index.php?doc_id=11677 

Note regarding v7 repeating forms/events:
REDCap v7.0.0 introduced functionality for repeating forms or events - a big 
change to the data model as an event/field combination need no longer be unique.
As of 04-Jan-2017 the plugin will display data from the last instance of a 
repeated form or event. 
Note that REDCap core code has not yet implemented any mechanism for referencing
repeating instances within filter logic, hence logic incorporating fields from 
a repeating form or events will not be functional.

********************************************************************************
## Licence

Released under standard GPL license: no warranty provided; you are responsible 
for ensuring this code or any modifications to it meet your requirements. 

Please also leave attribution to this repository in the code and push back 
updates and enhancements.

********************************************************************************
## Demonstration

You can view the plugin in action on MCRI's server and have a go at creating/
editing/viewing reports. 
 - URL       https://redcap.mcri.edu.au/redcap_v7.1.2/index.php?pid=2011
 - Username  lrdemo
 - Password  lrDemo123

Look for the "Longitudinal Reports" bookmark.

********************************************************************************
## REDCap Version Requirements

The plugin is designed to work with REDCap v6.9.0 and later (including 7.0.0+). 
 - 6.9.0+        All good
 - 6.8.0 - 6.8.2 You may experience problems with sessions logging out on
                 plugin pages
 - 6.7.5-        You will need to replace the REDCap::saveData code with 
                 equivalent code that saves report data via the REDCap API

********************************************************************************
## Installation

The plugin functions without requiring any changes to the main REDCap codebase, 
although there are two optional changes described below: one to enable the 
longitudinal reports to be accessed via the REDCap API, another to display a 
link to the longitudinal reports page on the main REDCap Data Export page.

1. Extract the contents of the zip to a plugin directory on your server
   e.g. /var/www/redcap/plugins/longitudinal_reports

2. Create a new project that will be used to store all of the configuration
   information for longitudinal reports
    - Title: Longitudinal Reports Plugin Data Store (or similar)
    - Upload the data dictionary included in this repo
    - Note the project id (pid=? in the url)

3. Edit the settings in longitudinal_reports/config.php to suit your environment
   At minimum you will need to set appropriate values for:
    - Project id of report data store project created in step 2
    - Path to redcap_connect.php relative to longitudinal_reports/config.php
    - Path to plugin directory relative to APP_PATH_WEBROOT (which is 
      something like https://redcap.institution.edu/redcap_v7.1.2 )

4. (Optional) Use the redcap_every_page_top hook to inject a new item into the 
   Applications menu for users with export permission in longitudinal projects.

```
/**
 * All project pages in longitudinal projects (for users with Export permission)
 *  - include link to Longitudinal Reports in Applications menu
 */
if (isset($_GET['pid']) && $_GET['pid']>0 && $Proj->longitudinal && (SUPER_USER || $user_rights['data_export_tool']>=1)) {
    print RCView::div(array('style' => 'display:none;', 'id' => 'lrMenuLink', 'class' => 'hang'),
        RCView::img(array('style' => 'margin-top:2px;', 'src' => APP_PATH_IMAGES.'layout_down_arrow.gif')) .
        RCView::a(array(
                'href' => APP_PATH_WEBROOT_FULL.'plugins/longitudinal_reports/index.php?pid='.$_GET['pid']), 
                "Longitudinal Reports"
        )
    );
?>
<script type='text/javascript'>
$(document).ready(function() {
    $('#lrMenuLink').detach().appendTo('#app_panel .menubox:last').show();
});
</script>
<?php
}
```

### Optional REDCap Core Code Changes^

1. Add an information message about the plugin - and a link - on REDCap's main
   Data Export module page (may not be required if using hook as per 4, above)

   redcap_v7.1.2/DataExport/index.php line 257
   
   To include the message file included with the plugin, change
      257  // Tabs
   to
      257  include APP_PATH_DOCROOT.'../plugins/longitudinal_reports/data_export_page_message.php'; // Tabs

2. Enable Longitudinal Reports to be exported using the REDCap API

   redcap_v7.1.2/API/report/export.php line 28

   Request a longitudinal report by including a longitudinal_reports=1 
   parameter in your API report export request (POST). This code detects the 
   presence of this parameter and redirects the processing to the Longitudinal
   Reports plugin:

   Find this code block around lines 28 - 31:
 ```php
		// Export the data for this report
		$content = DataExport::doReport($post['report_id'], 'export', $format, ($post['rawOrLabel'] == 'label'), ($post['rawOrLabelHeaders'] == 'label'), 
			false, false, $removeIdentifierFields, $hashRecordID, $removeUnvalidatedTextFields, 
			$removeNotesFields, $removeDateFields, false, false, array(), array(), false, $post['exportCheckboxLabel']);
```   
   Surround the existing block with an if statement that will catch the presence of a longitudinal_reports POST 
   parameter and redirect flow to the longitudinal_reports code:
```php
		if (isset($post['longitudinal_reports']) && (bool)$post['longitudinal_reports']) {
				require_once('../../plugins/longitudinal_reports/config.php');
				$content = LongitudinalReports::doReport($post['report_id'], 'export', $format, ($post['rawOrLabel'] == 'label'), ($post['rawOrLabelHeaders'] == 'label'), 
					false, false, $removeIdentifierFields, $hashRecordID, $removeUnvalidatedTextFields, 
					$removeNotesFields, $removeDateFields, false, false, array(), array(), false, $post['exportCheckboxLabel']);
		} else {
				// Export the data for this report
				$content = DataExport::doReport($post['report_id'], 'export', $format, ($post['rawOrLabel'] == 'label'), ($post['rawOrLabelHeaders'] == 'label'), 
					false, false, $removeIdentifierFields, $hashRecordID, $removeUnvalidatedTextFields, 
					$removeNotesFields, $removeDateFields, false, false, array(), array(), false, $post['exportCheckboxLabel']);
		}
 ```
 ^ Note that changes to the main REDCap code must be re-made with each version
 upgrade. Note also that line numbers may vary between versions.

********************************************************************************
