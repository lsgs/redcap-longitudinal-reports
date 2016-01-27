<?php
/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 * Version date 16-Nov-2015 
 */

require_once dirname(__FILE__) . '/config.php';

$selectAllStyle = 'float:right;display:inline-block;color:#777;margin:5px 0;font-weight:normal;font-size:12px;';
$selectAllStyle .= (LR_SHOW_QUICK_ADD_ALL_PROJECT_FIELDS) ? '': 'visibility:hidden;';

// Validate checked_fields, if is not empty
$checked_fields = explode(",", $_POST['checked_fields']);

foreach ($checked_fields as $key=>$this_field) {
        $this_field = LongitudinalReports::getFieldFromEventField($this_field); // remvoe square brackets and event ref
	if (!isset($Proj->metadata[$this_field])) {
		unset($checked_fields[$key]);
	}	
}

// Set fields as keys in array
$checked_fields = array_flip(array_unique($checked_fields));

// Loop through all fields and build HTML table (exclude Descriptive fields)   
$t = "";                 
 
$rc_field_dropdown_options = LongitudinalReports::getFieldDropdownOptions(); //Form::getFieldDropdownOptions();  

$select = RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:11px;margin:0 3px;text-decoration:underline;font-weight:normal;', 'onclick'=>"reportQuickAddForm('all',true);"), $lang['data_export_tool_52']);
$deselect = RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:11px;margin:0 3px;text-decoration:underline;font-weight:normal;', 'onclick'=>"reportQuickAddForm('all',false);"), $lang['data_export_tool_53']);

$t .=  RCView::tr(array(),
    RCView::td(array('class'=>'header', 'valign'=>'bottom', 'style'=>'color:#800000;font-size:14px;'),
        RCView::div(array('style'=>'font-weight:bold;font-style:italic;'),
                "Events / Fields" ).
        RCView::div(array('style'=>$selectAllStyle)," ($select/$deselect)")
    )
);
      
//$rc_field_dropdown_options = $indexCompleted;

$accordion = "";

foreach ($rc_field_dropdown_options as $eventFieldGroup=>$eventFields) {

    if ($eventFieldGroup !== '') {

        // Only show Select All / Deselect All if count of fields in group is less than threshold
        $selectAllInGroupStyle = 'color:#777;margin:5px 0;font-weight:normal;font-size:11px;';
        if (count($eventFields) < 2 || count($eventFields) > LR_SHOW_QUICK_ADD_ALL_GROUP_THRESHOLD ) {
            $selectAllInGroupStyle .= 'visibility:hidden;';
        }
    
        $eventFieldRef = preg_replace("/[^a-z0-9_]/", "", strtolower($eventFieldGroup));
        $select = RCView::a(array('href'=>'javascript:;', 'style'=>'display:inline-block;text-decoration:underline;font-weight:normal;padding:0 3px;', 'onclick'=>"reportQuickAddForm('$eventFieldRef',true);"), $lang['data_export_tool_52']);
        $deselect = RCView::a(array('href'=>'javascript:;', 'style'=>'display:inline-block;text-decoration:underline;font-weight:normal;padding:0 3px;', 'onclick'=>"reportQuickAddForm('$eventFieldRef',false);"), $lang['data_export_tool_53']);

        $accordion .= RCView::h3(array('style' => 'font-size:12px; font-weight:bold; font-style:italic; padding:5px;'),
                RCView::div(array(),
                        RCView::escape(strip_tags(label_decode($eventFieldGroup))) .
                        RCView::span(array('style'=>$selectAllInGroupStyle)," ($select/$deselect)")
                )
            );
    
        $panel = "";
        foreach($eventFields as $fieldRef=>$label)
        {    
            // Add the "checked" attribute if field already exists in report
            $checked = (isset($checked_fields[$fieldRef])) ? "checked" : "";  
            // Add field row
            $panel .= 	RCView::div(array('class'=>'data nowrap', 'style' => 'float:left;width:100%;padding: 4px 0 4px 8px;'),
                                    RCView::span(array('style'=>'width:30px;text-align:center;'),
                                            RCView::checkbox(array('class'=>"frm-all frm-".$eventFieldRef, 'name'=>$fieldRef, 'onclick'=>"qa($(this))", $checked=>$checked))
                                    ) .    
                                    RCView::span(array('style'=>''),
                                            RCView::escape(strip_tags(label_decode($label)))
                                    )
                            );
        }
        $accordion .= RCView::div(array('style' => 'padding:1em;'), $panel);
    }
}

$t .= RCView::tr(array(), 
        RCView::td(array(),
                RCView::div(array('id' => 'quickAddAccordion'), $accordion)
            )
        );

// Response
$dialog_title = 	RCView::img(array('src'=>'plus2.png', 'style'=>'vertical-align:middle')) .
					RCView::span(array('style'=>'color:green;vertical-align:middle'), $lang['report_builder_136']);
$dialog_content = 	RCView::div(array('style'=>'font-size:13px;margin-bottom:15px;'),
						$lang['report_builder_137']
					) .
					RCView::div(array('style'=>''),
						// Table
						RCView::table(array('cellspacing'=>'0', 'class'=>'form_border', 'style'=>'table-layout:fixed;width:100%;'),
							$t
						)
					); 
                    
// Output JSON response
print json_encode(array('title'=>$dialog_title, 'content'=>$dialog_content));