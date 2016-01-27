********************************************************************************
Longitudinal Reports REDCap Plugin

Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au

Version date 16-Nov-2015
********************************************************************************


********************************************************************************
Functionality

The "Longitudinal Reports" plugin lets you see row-per-participant data for 
longitudinal REDCap projects, rather than the row-per-event-per-participant
view that REDCap's standard Data Exports & Reports module gives you.

For more description and background on the problem this plugin aims to solve
please see my "Longitudinal Reports" presentation from REDCapCon 2015, available
via the REDCap wiki conference page https://iwg.devguard.com/trac/redcap/wiki/REDCap

********************************************************************************
Demonstration

You can view the plugin in action on MCRI's server and have a go at creating/
editing/viewing reports. 
    URL       https://redcap.mcri.edu.au/redcap_v6.9.4/index.php?pid=2011
    Username  lrdemo
    Password  lrDemo123
Look for the "Longitudinal Reports" bookmark.

********************************************************************************
REDCap Version Requirements

The plugin is designed to work with REDCap v6.9.0 and later. 
 - 6.9.0+        All good
 - 6.8.0 - 6.8.2 You may experience problems with sessions logging out on
                 plugin pages
 - 6.7.5-        You will need to replace the REDCap::saveData code with 
                 equivalent code that saves report data via the REDCap API

********************************************************************************
Installation

The plugin functions without requiring any changes to the main REDCap codebase, 
although there are two optional changes described below: one to enable the 
longitudinal reports to be accessed via the REDCap API, another to display a 
link to the longitudinal reports page on the main REDCap Data Export page.

1. Extract the contents of the zip to a plugin directory on your server
   e.g. redcap/plugins/longitudinal_reports

2. Create a new project that will be used to store all of the configuration
   information for longitudinal reports
        i. Title: Longitudinal Reports Plugin Data Store (or similar)
       ii. Upload the data dictionary 
      iii. Note the project id (pid=? in the url)

3. Edit the settings in longitudinal_reports/config.php to suit your environment
   At minimum you will need to set appropriate vales for:
        * Project id of report data store project
        * Path to redcap_connect.php
        * Path to plugin directory relative to APP_PATH_WEBROOT (which is 
          something like https://redcap.institution.edu/redcap_v6.5.4 )

4. Create a project bookmark to the main plugin page:
        * Link Label: Longitudinal Reports (or whatever you like)
        * Link URL:   https://<your server>/plugins/longitudinal_reports/index.php
        * Link Type:  Simple Link
        * Append project ID: Yes

Optional REDCap Code Changes*

1. Add an information message about the plugin - and a link - on REDCap's main
   Data Export module page

   redcap_v6.9.4/DataExport/index.php line 258
   
   To include the message file included with the plugin, change
      258  // Tabs
   to
      258  include APP_PATH_DOCROOT.'../plugins/longitudinal_reports/data_export_page_message.php'; // Tabs


2. Enable Longitudinal Reports to be exported using the REDCap API

   redcap_v6.9.4/API/report/export.php line 30

   Request a longitudinal report by including a longitudinal_reports = 1 
   parameter in your API report export request (POST). This code detects the 
   presence of this parameter and redirects the processing to the Longitudinal
   Reports plugin:

   Change
      29  // Export the data for this report
      30  $content = DataExport::doReport($post['report_id'], 'export', $format, ($post['rawOrLabel'] == 'label'), ($post['rawOrLabelHeaders'] == 'label'), 
      31          false, false, $removeIdentifierFields, $hashRecordID, $removeUnvalidatedTextFields, 
      32          $removeNotesFields, $removeDateFields, false, false, array(), array(), false, $post['exportCheckboxLabel']);

   to
      29  if (isset($post['longitudinal_reports']) && (bool)$post['longitudinal_reports']) {
      30      require_once('../../plugins/longitudinal_reports/config.php');
      31      $content = LongitudinalReports::doReport($post['report_id'], 'export', $format, ($post['rawOrLabel'] == 'label'), ($post['rawOrLabelHeaders'] == 'label'), 
      32              false, false, $removeIdentifierFields, $hashRecordID, $removeUnvalidatedTextFields, 
      33              $removeNotesFields, $removeDateFields, false, false, array(), array(), false, $post['exportCheckboxLabel']);
      34  } else {
      35  // Export the data for this report
      36  $content = DataExport::doReport($post['report_id'], 'export', $format, ($post['rawOrLabel'] == 'label'), ($post['rawOrLabelHeaders'] == 'label'), 
      37          false, false, $removeIdentifierFields, $hashRecordID, $removeUnvalidatedTextFields, 
      38          $removeNotesFields, $removeDateFields, false, false, array(), array(), false, $post['exportCheckboxLabel']);
      39  }

* Note that changes to the main REDCap code must be re-made with each version 
  upgrade. Note also that line numbers may vary between versions.
********************************************************************************