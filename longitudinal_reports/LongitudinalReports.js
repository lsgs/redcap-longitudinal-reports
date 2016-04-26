/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 * Version date 16-Nov-2015 
 * Altered for LongitudinalReports from redcap_v6.4.3/Resources/js/DataExport.js 
 */


// Set ajax variable
var exportajax;
// Variable to track last report field type (textbox or drop-down)
var rprtft = 'text';

// On pageload
$(function(){
	// Add id to all auto suggest text boxes and enable auto suggest for them
	enableAutoSuggestFields();
	// Trigger to select export format when you click its row
	resetExportOptionRows();
	$('table#export_choices_table tr td').click(function(){
		$(this).find('input[name="export_format"]').prop('checked', true);
		resetExportOptionRows();
	});
	$('table#export_choices_table tr td').hover(function(){
		$(this).css({'background':'#d9ebf5', 'border':'1px solid #ccc'});
	},function(){
		if (!$(this).find('input[name="export_format"]').prop('checked')) {
			$(this).css({'background':'#eee', 'border':'1px solid #eee', 'border-bottom':'1px solid #ddd'});
			$('table#export_choices_table tr td:last').css({'border':'1px solid #eee'});
		}
	});
	// If viewing a report, then fetch the report
	if ($('#report_parent_div').length) {
		var pagenum = getParameterByName('pagenum');
		//if (pagenum == '') pagenum = (isNumeric(getParameterByName('report_id'))) ? 'ALL' : '1';
		if (pagenum == '') pagenum = '1';
		fetchReportAjax(getParameterByName('report_id'), pagenum);
	}
	// Add hide_in_print class to page elements so that they don't display when printing
	$('div#center_inner h3, div#sub-nav, #showPlotsStatsOptions').addClass('hide_in_print');
	// Add/Edit Report: Enable drag-n-drop of fields on table
	if ($('table#create_report_table').length) {
		// If report title is blank, then set cursor in title field
		var report_title_field = $('table#create_report_table input[name="__TITLE__"]');
		if (report_title_field.val() == '') report_title_field.focus();	
		// Add "nodrop" class on all rows except report fields rows
		$('table#create_report_table tr:not(.field_row)').addClass('nodrop');
		$('table#create_report_table tr.field_row:last').addClass('nodrop');
		// Enable drag n drop
		$('table#create_report_table').tableDnD({
			onDrop: function(table, row) {
				// Reset the "Field X" text for report field rows
				resetFieldNumLabels();
				// Highlight row
				$(row).find('td:eq(0) div, td:eq(1), select, input').effect('highlight',{},2000);
			},
			dragHandle: "dragHandle"
		});
		// Set hover action for table rows to display dragHandle icon
		setTableRowHover($('table#create_report_table tr.field_row'));
		// Set up drag-n-drop pop-up tooltip
		$('#dragndrop_tooltip_trigger').tooltip({ tipClass: 'tooltip4sm', position: 'top center', offset: [30,0], predelay: 100, delay: 0, effect: 'fade' });
		setDragHandleHover($('.dragHandle'));
		// Set triggers for onchange of field drop-down to display form name
		showFormLabel($('#create_report_table .field-dropdown option[value=""]:not(:selected)').parent());
	}
	// Report List: Enable drag n drop on report list table
	if ($('table#table-report_list').length) {
		enableReportListTable();
	}
});

// Add form fields to report
function addFormFieldsToReport(form) {
	// Loop through all form fields
	var fields = formFields[form].split(',');
	var thisfield;
	showProgress(1,0);
	setTimeout(function(){
		var k = 0;
		for (var i=0; i<fields.length; i++) {
			thisfield = fields[i];
			// Make sure not already added to report
			if ($('.field-dropdown option[value="'+thisfield+'"]:selected').length == 0) {
				// Special exception for first field since it already exists on page
				if (k == 0) {
					$('.field-dropdown-a:last').trigger('click').parents('tr:first').children().effect('highlight', { }, 2000);
				}
				// Add field
				$('.field-dropdown:last').val(thisfield).trigger('change');
				k++;
			}
		}
		// Reset drop-down
		$('#add_form_field_dropdown').val('');
		showProgress(0,100);
	},50);
}

// Show/hide the advanced logic row in Report Builder
function showAdvancedLogicRow(show_advanced, skip_confirm) {
	// If converting to advanced, get user to confirm its okay to abandon simple format
	if (show_advanced && !skip_confirm && $('.limiter-dropdown option[value=""]:not(:selected)').length > 0) {
		initDialog('convertAdvancedLogicConfirm');
		$('#convertAdvancedLogicConfirm')
			.html(langConvertToAdvLogic2+"<div style='color:#444;margin:20px 0 2px;font-weight:bold;'>"+langPreviewLogic+"</div><div style='font-family:verdana;color:#C00000;'>"+convertSimpleLogicToAdvanced()+"</div>")
			.dialog({ bgiframe: true, modal: true, width: 500, title: langConvertToAdvLogic, 
			position: { my: "left bottom", at: "left top", of: $('tr#adv_logic_row_link') }, 
			buttons: 
				[{ text: closeBtnTxt, click: function() {
					$(this).dialog('close');
				}},
				{ text: langConvert, click: function() {
					showAdvancedLogicRow(show_advanced, true); 
					$(this).dialog('close');
				}}]
			});
		return;
	}
	// If converting to back to simple, get user to confirm its okay to abandon advanced logic
	$('tr#adv_logic_row textarea[name="advanced_logic"]').val( trim($('tr#adv_logic_row textarea[name="advanced_logic"]').val()) );
	if (!show_advanced && !skip_confirm && $('tr#adv_logic_row textarea[name="advanced_logic"]').val().length > 0) {
		initDialog('convertAdvancedLogicConfirm');
		$('#convertAdvancedLogicConfirm').html(langConvertToAdvLogic3).dialog({ bgiframe: true, modal: true, width: 500, title: langConvertToAdvLogic5, 
			position: { my: "left bottom", at: "left top", of: $('tr#adv_logic_row_link2') }, 
			buttons: 
				[{ text: closeBtnTxt, click: function() {
					$(this).dialog('close');
				}},
				{ text: langConvertToAdvLogic4, click: function() {
					showAdvancedLogicRow(show_advanced, true); 
					$(this).dialog('close');
				}}]
			});
		return;
	}
	// Convert
	if (show_advanced) {
		$('.limiter_and_row').addClass('hidden');
		var logic = convertSimpleLogicToAdvanced();		
		// Add logic to textarea
		$('tr#adv_logic_row textarea[name="advanced_logic"]').val(logic);
		// Remove all original filter rows
		var i = 0;
		$('tr.limiter_and_row, tr.limiter_row').each(function(){
			if (i++ > 1) $(this).remove();
		});
		$('.limiter-dropdown:first').val('').trigger('change');
	} else {
		$('.limiter_and_row').removeClass('hidden');		
		$('tr#adv_logic_row textarea[name="advanced_logic"]').val('');
		$('.limiter_and_row').find('a').hide(); // Hide delete icon
	}
	$('.limiter_row, #adv_logic_row_link, #adv_logic_row_link2, #adv_logic_row, #oper_value_hdr, #how_to_filters_link').toggle();
	// Highlight row
	if (show_advanced) {
		highlightTableRowOb($('tr#adv_logic_row'), 2500);
		highlightTableRowOb($('tr#adv_logic_row_link2'), 2500);
	} else {
		highlightTableRowOb($('tr.limiter_row:first'), 2500);
		highlightTableRowOb($('tr#adv_logic_row_link'), 2500);
	}
}

// Check advanced logic for syntax errors
function check_advanced_logic() {
	$('tr#adv_logic_row textarea[name="advanced_logic"]').val( trim($('tr#adv_logic_row textarea[name="advanced_logic"]').val()) );
	var logic = $('tr#adv_logic_row textarea[name="advanced_logic"]').val();
	// Return true if logic is blank
	if (logic == '') return true;
	// Make ajax request to check the logic via PHP (use async=false)
	var isSuccess = false;
	$.ajax({
        url: app_path_webroot+'Surveys/automated_invitations_check_logic.php?pid='+pid,
        type: 'POST',
		data: { logic: logic, redcap_csrf_token: redcap_csrf_token },
        async: false,
        success:
            function(data){
				if (data == '0') {
					alert(woops);
				} else if (data == '1') {
					// Success - so do nothing
					isSuccess = true;
				} else {
					// Error msg - problems in logic to fix
					simpleDialog(data);
				}
            }
    });
	// Return success value
	return isSuccess;
}

// Convert simple report filtering logic in Report Builder to advanced format
function convertSimpleLogicToAdvanced() {
	// Capture logic in string
	var logic = '';
	var ob, valdt;
	var all_oper = new Array('CONTAINS', 'NOT_CONTAIN', 'STARTS_WITH', 'ENDS_WITH');
	// Loop through all filter rows
	var i = 0;
	$('tr.limiter_row').each(function(){
		// object
		ob = $(this);
		// Get field, operator, and value
		var varname = ob.find('.limiter-dropdown').val();
		var oper = ob.find('.limiter-operator').val();
		var inputvalob = ob.find('input.limiter-value');
		if (inputvalob.length) {
			var val = inputvalob.val();
		} else {
			var val = ob.find('select.limiter-value').val();
		}
		// If the field or operator is blank then skip it
		if (varname == '' || oper == '') return;
		// If longitudinal, then get unique event name from event_id in drop-down
		var eventname = '';
		if (longitudinal) {
			var this_event_id = ob.find('.event-dropdown').val();
			eventname = uniqueEvents[this_event_id];
			if (eventname != '') eventname = "[" + eventname + "]";
		}		
		// Check if field is a MDY or DMY date/datetime/datetime_seconds field
		if (inputvalob.length) {
			if (inputvalob.hasClass('date_mdy')) {
				val = date_mdy2ymd(val);
			} else if (inputvalob.hasClass('datetime_mdy') || inputvalob.hasClass('datetime_seconds_mdy')) {
				valdt = val.split(' ');
				val = date_mdy2ymd(valdt[0])+' '+valdt[1];
			} else if (inputvalob.hasClass('date_dmy')) {
				val = date_dmy2ymd(val);
			} else if (inputvalob.hasClass('datetime_dmy') || inputvalob.hasClass('datetime_seconds_dmy')) {
				valdt = val.split(' ');
				val = date_dmy2ymd(valdt[0])+' '+valdt[1];
			}
		}
		// Determine if this is an AND drop-down row
		logic += (ob.find('.lgoo').css('visibility') != 'hidden') ? (i == 0 ? "(" : " OR ") : (i == 0 ? "(" : ") AND (");
		// If is "contains", "not contain", "starts_with", or "ends_with"
		if (in_array(oper, all_oper)) {
			logic += oper.toLowerCase() + "(" + eventname + "[" + varname + "], \"" + val.replace(/"/g, "\\\"") + "\")";
		} 
		// If is "checked" or "unchecked"
		else if (oper == 'CHECKED' || oper == 'UNCHECKED') {
			logic += eventname + "[" + varname + "(" + val + ")] = \"" + (oper == 'CHECKED' ? "1" : "0") + "\"";
		} 
		// Normal
		else {
			var quotes = (isNumeric(val) && oper != 'E' && oper != 'NE') ? '' : '"';
			logic += eventname + "[" + varname + "] " + allLimiterOper[oper].replace(/ /g, "") + " " + quotes + val.replace(/"/g, "\\\"") + quotes;
		}
		// Increment counter
		i++;
	});
	// Add final parenthesis
	if (logic != '') logic += ")";
	// Return logic
	return logic;
}

// Enable report list table
function enableReportListTable() {
	// Add dragHandle to first cell in each row
	$("table#table-report_list tr").each(function() {
		var report_id = trim($(this.cells[0]).text());
		$(this).prop("id", "reprow_"+report_id).attr("reportid", report_id);
		if (isNumeric(report_id)) {
			// User-defined reports (draggable)
			$(this.cells[0]).addClass('dragHandle');
			// $(this.cells[3]).addClass('opacity50');
			// $(this.cells[4]).addClass('opacity50');
		} else {
			// Pre-defined reports
			$(this).addClass("nodrop").addClass("nodrag");
		}
	});
	// Restripe the report list rows
	restripeReportListRows();
	if (user_rights_reports) {
		// Enable drag n drop (but only if user has "reports" user rights)
		$('table#table-report_list').tableDnD({
			onDrop: function(table, row) {
				// Loop through table
				var ids = "";
				var this_id = $(row).prop('id');
				$("table#table-report_list tr").each(function() {
					// Gather form_names
					var row_id = $(this).attr("reportid");
					if (isNumeric(row_id)) {
						ids += row_id + ",";
					}
				});
				// Save new order via ajax
				$.post(app_path_webroot+lrPluginPath+'report_order_ajax.php?pid='+pid, { report_ids: ids }, function(data) {
					if (data == '0') {
						alert(woops);
						window.location.reload();
					} else if (data == '2') {
						window.location.reload();
					}
					// Update left-hand menu panel of Reports
					//updateReportPanel();
				});
				// Reset report order numbers in report list table
				resetReportOrderNumsInTable();
				// Restripe table rows
				restripeReportListRows();
				// Highlight row
				setTimeout(function(){
					var i = 1;
					$('tr#'+this_id+' td').each(function(){
						if (i++ != 1) $(this).effect('highlight',{},2000);
					});
				},100);
			},
			dragHandle: "dragHandle"
		});	
		// Create mouseover image for drag-n-drop action and enable button fading on row hover
		$("table#table-report_list tr:not(.nodrag)").hover(function() {
			$(this.cells[0]).css('background','#ffffff url("'+app_path_images+'updown.gif") no-repeat center');
			$(this.cells[0]).css('cursor','move');
			// $(this.cells[3]).removeClass('opacity50');
			// $(this.cells[4]).removeClass('opacity50');
		}, function() {
			$(this.cells[0]).css('background','');
			$(this.cells[0]).css('cursor','');
			// $(this.cells[3]).addClass('opacity50');
			// $(this.cells[4]).addClass('opacity50');
		});	
		// Set up drag-n-drop pop-up tooltip
		var first_hdr = $('#report_list .hDiv .hDivBox th:first');
		first_hdr.prop('title',langDragReport);
		first_hdr.tooltip({ tipClass: 'tooltip4sm', position: 'top center', offset: [25,0], predelay: 100, delay: 0, effect: 'fade' });
		$('.dragHandle').hover(function() {
			first_hdr.trigger('mouseover');
		}, function() {
			first_hdr.trigger('mouseout');
		});
	}
}

// Restripe the rows of the report list table
function restripeReportListRows() {
	// Loop through the pre-defined ones fist
	var i = 1;
	$("table#table-report_list tr").each(function() {
		// Restripe table
		$(this).removeClass('erow');
		//
		if (isNumeric($(this).attr('reportid'))) {
			if (i++ % 2 == 0) $(this).addClass('erow');
		} else {
			$(this).find('td').css('background','#f0f0f0');
		}
	});
}

// Copy a report
function copyReport(report_id, confirmCopy) {
	if (confirmCopy == null) confirmCopy = true;
	// Get report title from table
	var row_id = $('#repcopyid_'+report_id).parents('tr:first').attr('id');
	var report_title = trim($('#repcopyid_'+report_id).parents('tr:first').find('td:eq(2)').text());
	if (confirmCopy) {
		// Prompt user to confirm copy
		simpleDialog(langCopyReportConfirm
			+ '<br>"<span style="color:#C00000;font-size:14px;">'+report_title+'</span>"'+langQuestionMark,
			langCopyReport,null,350,null,closeBtnTxt,"copyReport("+report_id+",false);",langCopy);
	} else {
		// Copy via ajax
		$.post(app_path_webroot+lrPluginPath+'report_copy_ajax.php?pid='+pid, { report_id: report_id }, function(data) {
			if (data == '0') {
				alert(woops);
				return;
			}
			// Parse JSON
			var json_data = jQuery.parseJSON(data);
			// Replace current report list on page
			$('#report_list_parent_div').html(json_data.html);
			// Re-enable table
			enableReportListTable();
			initWidgets();
			// Highlight new row then remove row from table
			var i = 1;
			$('tr#reprow_'+json_data.new_report_id+' td').each(function(){
				if (i++ != 1) $(this).effect('highlight',{},2000);
			});
			// Update left-hand menu panel of Reports
			updateReportPanel();
		});
	}
}

// Delete a report
function deleteReport(report_id, confirmDelete) {
	if (confirmDelete == null) confirmDelete = true;
	// Get report title from table
	var row_id = $('#repdelid_'+report_id).parents('tr:first').attr('id');
	var report_title = trim($('#repdelid_'+report_id).parents('tr:first').find('td:eq(2)').text());
	if (confirmDelete) {
		// Prompt user to confirm deletion
		simpleDialog(langDeleteReportConfirm
			+ '<br>"<span style="color:#C00000;font-size:14px;">'+report_title+'</span>"'+langQuestionMark,
			langDeleteReport,null,350,null,closeBtnTxt,"deleteReport("+report_id+",false);",langDelete);
	} else {
		// Delete via ajax
		$.post(app_path_webroot+lrPluginPath+'report_delete_ajax.php?pid='+pid, { report_id: report_id }, function(data) {
			if (data == '0') {
				alert(woops);
				return;
			}
			// Highlight deleted row then remove row from table
			var i = 1;
			$('tr#'+row_id+' td').each(function(){
				if (i++ != 1) $(this).effect('highlight',{},700);
			});
			setTimeout(function(){
				$('tr#'+row_id).hide('fade',function(){
					$('tr#'+row_id).remove();
					resetReportOrderNumsInTable();
					restripeReportListRows();
				});
			},300);
			// Update left-hand menu panel of Reports
			updateReportPanel();
		});
	}
}


// Reset report order numbers in report list table
function resetReportOrderNumsInTable() {
	var i = 1;
	$("table#table-report_list tr:not(.nodrag)").each(function(){
		$(this).find('td:eq(1) div').html(i++);
	});
}

// Set hover action for table rows to display dragHandle icon
function setTableRowHover(rowob) {
	rowob.hover(function() {
		$(this).find('td.dragHandle').css({'cursor':'move', 'background':'#F0F0F0 url("'+app_path_images+'updown.gif") no-repeat 5px center'});
	}, function() {
		$(this).find('td.dragHandle').css({'cursor':'', 'background':'#F0F0F0 url("'+app_path_images+'label-bg.gif") repeat-x scroll 0 0'});
	});
}

// Set hover action for table drag n drop dragHandle
function setDragHandleHover(ob) {
	ob.hover(function() {
		$('#dragndrop_tooltip_trigger').trigger('mouseover');
	}, function() {
		$('#dragndrop_tooltip_trigger').trigger('mouseout');
	});
}

// Display or hide the limiter group operator row (AND/OR dropdown)
function displaylimiterGroupOperRow(ob) {
	// Determine if this is the clone drop-down in the preceding row
	var isClone = ob.hasClass('lgoc');
	var thisrow = ob.parents('tr:first');
	var thisval = ob.val();
	// If change AND to OR
	if (isClone) {
		// Hide the current row
		thisrow.hide();
		// Make visible the original in-row drop-down and set its value as same as this one
		thisrow.next().find('.lgoo').css('visibility','visible').val(thisval);
	}
	// If change OR to AND
	else {
		// Show the prev row
		thisrow.prev().show();
		// Hide the original in-row drop-down and set the clone's value as same as this one
		thisrow.find('.lgoo').css('visibility','hidden');
		thisrow.prev().find('.lgoc').val(thisval);
	}	
}

// Display or hide user access custom options
function displayUserAccessOptions() {
	if ($('#create_report_table input[name="user_access_radio"]:checked').val() == 'SELECTED') {
		// Open custom user access options
		$('#selected_users_div').show('blind','fast');
	} else {
		// Hide options
		$('#selected_users_div').hide('blind','fast');
	}
	$('#selected_users_note1, #selected_users_note2').toggle();
}

// Fetch report via ajax
function fetchReportAjax(report_id,pagenum) {
	// Initialize divs
	$('#report_load_progress').show();
	$('#report_load_progress2').hide();
	$('#report_parent_div').html('');
	$('.FixedHeader_Cloned , #FixedTableHdrsEnable').remove();
	if (pagenum == null) pagenum = '';
	// Ajax call
	exportajax = $.post(app_path_webroot+lrPluginPath+'report_ajax.php?pid='+pid+getInstrumentsListFromURL()+'&pagenum='+pagenum, { report_id: report_id }, function(data) {
		if (data == '0' || data == '') {
			$('#report_load_progress').hide();
			simpleDialog(langReportFailed,langError);
			return;
		}
		// Hide/show progress divs
		$('#report_load_progress').hide();
		$('#report_load_progress2').show();
		// Load report into div on page
		setTimeout(function(){
			// Hide "please wait" div
			$('#report_load_progress2, #report_load_progress_pagenum_text').hide();
			// Add report tabel to page
			document.getElementById('report_parent_div').innerHTML = data;
			// Buttonize the report buttons
			$('.report_btn').button();
			// Change width of pagenum div (if exists on page)
			if ($('.report_pagenum_div').length) {
				var table_width = $('#report_table').width();
				if (table_width > $('.report_pagenum_div:first').width()) {
					// Make pagenum div the same width as table
					$('.report_pagenum_div').width(table_width);
					$('.report_pagenum_div:eq(0)').css('border-bottom','0');
					$('.report_pagenum_div:eq(1)').css('border-top','0');
				}
			}
			// Enable fixed table headers for event grid
			enableFixedTableHdrs('report_table',true,false);
		},10);
	})
	.fail(function(xhr, textStatus, errorThrown) {
		$('#report_load_progress').hide();
		if (xhr.statusText == 'Internal Server Error') simpleDialog(langReportFailed,langError);
	});
	// Set progress div to appear if report takes more than 0.5s to load
	setTimeout(function(){ 
		if (exportajax.readyState == 1) {
			$('#report_load_progress').show();
		}
	},500);
}

// Make sure user has chosen export format and whether to archive files in File Repository
function exportFormatDialogSaveValidate() {
	return ($('#exportFormatDialog input[name="export_format"]:checked').length && $('#exportFormatDialog input[name="export_options_archive"]:checked').length);
}

// Reset style of data export option table rows
function resetExportOptionRows() {
	// Set bg color and border for all rows first
	$('table#export_choices_table tr td').css({'background':'#eee', 'border':'1px solid #eee', 'border-bottom':'1px solid #ddd'});
	$('table#export_choices_table tr td:last').css({'border':'1px solid #eee'});
	// Set for selected row
	$('table#export_choices_table tr td input[name="export_format"]:checked').parents('td:first').css({'background':'#d9ebf5', 'border':'1px solid #ccc'});
}

// Display "Working" export div as progress indicator
function showProgressExport(show,ms) {
	// Set default time for fade-in/fade-out
	if (ms == null) ms = 500;
	if (!$("#working_export").length) {
		$('body').append('<div id="working_export"><div style="margin:10px 20px 10px 10px;"><img src="'+app_path_images+'progress_circle.gif">&nbsp; '+langIconSaveProgress+'</div>'
			+ '<div style="margin:15px 10px 5px 0;font-weight:normal;font-size:12px;">'+langIconSaveProgress2+'</div>'
			+ '<div style="margin:10px 5px 2px;text-align:right;"><button id="export_cancel_btn" class="jqbuttonmed" style="font-size:11px;" onclick="cancelExportAjax()">'+langCancel+'</button></div></div>');
		$('#export_cancel_btn').button();
	}
	if (!$("#fade").length) $('body').append('<div id="fade"></div>');
	if (show) {
		$('#working_export').center().fadeIn(ms);
		$('#fade').dialog({ bgiframe: false, modal: true, width: 0, height: 0, position: ['right','bottom'],
			open: function(){
				// Because the dialog will show, even though small, we need to hide it
				$('#fade').parent().hide();
			}
		});
	} else {
		setTimeout(function(){	
			$("#working_export").fadeOut(ms);
			$("#fade").dialog('destroy');
		},ms);
	}
}

// Cancel the export data ajax request
function cancelExportAjax() {
	if (exportajax.readyState == 1) {
		exportajax.abort();
	}
	showProgressExport(0,0);
}

// Open data export dialog (to choose export format)
function showExportFormatDialog(report_id) {
	// Hide the "export DAGs and survey fields" box unless a pre-defined report
	if (isNumeric(report_id)) {
		$('#exportFormatForm #export_dialog_dags_survey_fields_options').hide();
	} else {
		$('#exportFormatForm #export_dialog_dags_survey_fields_options').show();
	}
	// Get report name
	var report_name = 'report';
	if ($('table#table-report_list').length) {
		report_name = trim($('table#table-report_list tr#reprow_'+report_id+' td:eq(2)').text());
	} else if ($('#this_report_title').length) {
		report_name = trim($('#this_report_title').text());
	}
	
	// Set title text
	var title = "<img src='"+app_path_images+"go-down.png' style='vertical-align:middle;'> "
			  + "<span style='vertical-align:middle;font-size:15px;'>"+langExporting+" \""+report_name+"\"</span>";
	// Show dialog
	$('#exportFormatDialog').dialog({ title: title, bgiframe: true, modal: true, width: 870, open: function(){ fitDialog(this) }, buttons: 
		[{ text: closeBtnTxt, click: function() {
			$(this).dialog('close');
		}},
		{text: exportBtnTxt, click: function() {
			// Make sure necessary options are selected
			if (!exportFormatDialogSaveValidate()) {
				simpleDialog(langSaveValidate,langError);
				return;
			}
			// Set params
			var params = $('form#exportFormatForm').serializeObject();
			params.report_id = report_id;
			// Start clock so we can display progress for set amount of time
			var start_time = new Date().getTime();
			var min_wait_time = 1000;
			// Close dialog
			$('#exportFormatDialog').dialog('close');
			// Get all the form values and submit via ajax
			exportajax = $.post(app_path_webroot+lrPluginPath+'data_export_ajax.php?pid='+pid+getSelectedInstrumentList()+getInstrumentsListFromURL(), params, function(data) {
				if (data == '0' || data == '') {
					showProgressExport(0,0);
					simpleDialog(langExportFailed,langError);
					return;
				}
				// End clock
				var total_time = new Date().getTime() - start_time;
				// If total_time is less than min_wait_time, then wait till it gets to min_wait_time
				var wait_time = (total_time < min_wait_time) ? (min_wait_time-total_time) : 0;
				// Set wait time, if any
				setTimeout(function(){
					// Close other dialogs
					showProgressExport(0,0);
					try {
						// Parse JSON
						var json_data = jQuery.parseJSON(data);
						// Display success dialog
						simpleDialog(json_data.content, json_data.title, null, 700);
					} catch (e) {
						simpleDialog(langExportFailed,langError);
					}
				}, wait_time);
			})
			.fail(function(xhr, textStatus, errorThrown) {
				showProgressExport(0,0);
				if (xhr.statusText == 'Internal Server Error') simpleDialog(langExportFailed,langError);
			});
			// Set progress bar if still running after a moment
			setTimeout(function(){
				showProgressExport(1,300);
			},100);
		}}]
	});
	$('#exportFormatDialog').dialog("widget").find(".ui-dialog-buttonpane button").eq(1).css({'font-weight':'bold', 'color':'#333'});
}

// Show form name for report field: Add onchange trigger to display the form name next to the field drop-down
function showFormLabel(ob) {
	ob.each(function(){
		$(this).change(function(){
			var this_field = $(this).val();
			var this_row = $(this).parents('tr:first');
			var this_span = this_row.find('.fnb');
			if (this_field == '') {
				this_span.html('');
				this_row.find('.fna').css('visibility','hidden');
			} else {
				// this_span.html( formLabels[fieldForms[$(this).val()]] );
                                // LS Longitudinal Reports: .val now [baseline][dob] not simply dob
                                var selVal = $(this).val();
                                var fn = selVal.replace(/(\[\w+\]\[)|\]/g,'');

				this_span.html( formLabels[fieldForms[fn]] );
				this_row.find('.fna').css('visibility','visible');
			}
		});
	});
}

// Add new report field row when creating/modifying report
function addNewReportRow(ob) {
	if (ob.val() == '') return false;
	// Make sure the field hasn't already been added to the report. If so, then return false;
	if ($('.field-dropdown option[value="'+ob.val()+'"]:selected').length > 1) {
		// Give it an id number temporarily so we can reference it
		obId = "flddd-"+Math.floor(Math.random()*10000000000000000);
		ob.attr('id', obId);
		simpleDialog(langChooseOtherfield,null,null,null,"$('#"+obId+"').val('').removeAttr('id').effect('highlight',{},2000);");
		return false;
	}
	// Get row object and reset some CSS (in case highlight effect is still going on)
	var rowob = ob.parents('tr:first');
	rowob.find('.field-dropdown, .field-auto-suggest').css('background','#fff');
	rowob.find('td').css('background','#F0F0F0 url("'+app_path_images+'label-bg.gif") repeat-x scroll 0 0');
	rowob.removeClass('nodrop');
	// Set trigger for onchange of field drop-down to display form name
	showFormLabel(rowob.find('.field-dropdown'));
	// Get row
	var row = rowob.clone();
	$('#create_report_table tr.field_row:last').after(row);
	// In new row, make sure the drop-down value gets reset
	var newrow = $('#create_report_table tr.field_row:last');
	newrow.find('.field-dropdown, .field-auto-suggest').val('');
	newrow.find('.field-auto-suggest').trigger('blur').removeAttr('id');
	// In new row, increment the row/field number
	var fieldnum_span = newrow.find('.field_num');
	var fieldnum = (fieldnum_span.text()*1)+1;
	fieldnum_span.html(fieldnum);
	// For IE8-9, it will sometimes append one extra row to the bottom of the table when adding a new row.
	// Not sure why this happens, but if it does, then remove the extra row.
	if (IEv <= 9 && fieldnum < $('#create_report_table tr.field_row').length) {
		$('#create_report_table tr.field_row:last').remove();
		return false;
	}
	// In new row, make sure auto-suggest field shows with drop-down hidden
	if (rprtft == 'text') {
		newrow.find('.field-dropdown-div').hide();
		newrow.find('.field-auto-suggest-div').show();
	} else {
		newrow.find('.field-dropdown-div').show();
		newrow.find('.field-auto-suggest-div').hide();
	}
	rowob.find('a').show(); // Show delete icon
	// Remove the onchange event from the original row so that changing it doesn't trigger new rows to appear
	rowob.find('.field-dropdown').removeAttr('onchange');
	// Trigger the form label display
	rowob.find('.field-dropdown').trigger('change');
	// Set hover action for table row to display dragHandle icon
	setTableRowHover(rowob);
	// Enable dragHandle for row
	rowob.find('td:first').addClass('dragHandle');
	setDragHandleHover(rowob.find('td.dragHandle'));
	// Enable table drag n drop for new row
	$('table#create_report_table').tableDnDUpdate();
	// Highlight new row
	highlightTableRowOb(newrow, 2000);
	// Add auto suggest trigger to new row
	enableAutoSuggestFields();
	// Put cursor in the new row's auto suggest text box
	newrow.find('.field-auto-suggest').focus();
}

// Reset the "Field X" text for report field rows
function resetFieldNumLabels() {
	var k = 1;
	$('.field_num').each(function(){
		$(this).html(k++);
	});
}

// Delete report field row
function deleteReportField(ob) {
	var row = ob.parents('tr:first');
	// Remove it
	highlightTableRowOb(row, 700);
	setTimeout(function(){
		row.hide('fade',function(){
			// Remove the rows and run other things
			row.remove();
			// Reset the "Field X" text for report field rows
			resetFieldNumLabels()
		});
	},200);
}

// Delete filter field row
function deleteLimiterField(ob) {
	var row = ob.parents('tr:first');	
	var prevrow = row.prev();
	// Remove them
	highlightTableRowOb(prevrow, 700);
	highlightTableRowOb(row, 700);
	setTimeout(function(){
		prevrow.hide('fade');
		row.hide('fade',function(){
			// Remove the rows and run other things
			prevrow.remove();
			row.remove();
			// Reset the "Filter X" text
			var k = 1;
			$('.limiter_num').each(function(){
				$(this).html(k++);
			});
			// Make sure the limiter group row is not displayed for the first limiter field
			if ($('.lgoc:first').val() == 'OR') {
				// Change to AND
				$('.lgoc:first').val('AND');
				$('.lgoo:first').val('AND').css('visibility','hidden');
			}
			$('.lgoc:first').parents('tr:first').hide();
		});
	},200);
}

// Add new report limiter row when creating/modifying report
function addNewLimiterRow(ob) {
	if (ob.val() == '') return false;
	// Get row and preceding limiter group row
	var rowob = ob.parents('tr:first');
	// Get row object and reset some CSS (in case highlight effect is still going on)
	rowob.find('.limiter-dropdown, .field-auto-suggest').css('background','#fff');
	rowob.find('td').css('background','#F0F0F0 url("'+app_path_images+'label-bg.gif") repeat-x scroll 0 0');
	var limit_group = rowob.find('.lgoo').val();
	var row = rowob.clone();
	var limiter_group_row = rowob.prev().clone();
	$('#create_report_table tr.limiter_row:last').after(row).after(limiter_group_row);
	// In new row, make sure the drop-down value gets reset
	var newrow = $('#create_report_table tr.limiter_row:last');
	newrow.find('.limiter-dropdown, .field-auto-suggest, .limiter-operator, .limiter-value').val('');
	newrow.find('.field-auto-suggest').trigger('blur').removeAttr('id');
	// Set AND/OR grouping options correctly
	var new_limit_group_row = newrow.prev();
	new_limit_group_row.find('.lgoc').val(limit_group);
	newrow.find('.lgoo').val(limit_group).css('visibility',(limit_group == 'AND' ? 'hidden' : 'visible'));
	if (limit_group == 'AND') { 
		new_limit_group_row.show();
	} else {
		new_limit_group_row.hide();
	}
	// In new row, increment the row/field number
	var fieldnum_span = newrow.find('.limiter_num');
	var fieldnum = (fieldnum_span.text()*1)+1;
	fieldnum_span.html(fieldnum);
	// In new row, make sure auto-suggest field shows with drop-down hidden
	if (rprtft == 'text') {
		newrow.find('.limiter-dropdown-div').hide();
		newrow.find('.field-auto-suggest-div').show();
	} else {
		newrow.find('.limiter-dropdown-div').show();
		newrow.find('.field-auto-suggest-div').hide();
	}
	rowob.find('a').show(); // Show delete icon
	// Remove the onchange event from the original row so that changing it doesn't trigger new rows to appear
	rowob.find('.limiter-dropdown').attr('onchange','fetchLimiterOperVal($(this));');
	// Highlight new row
	highlightTableRowOb(newrow, 2000);
	// Add auto suggest trigger to new row
	enableAutoSuggestFields();
}

// Show or hide auto suggest report field text box
function showReportFieldAutoSuggest(ob, hideAutoSuggest) {
	if (hideAutoSuggest == null) hideAutoSuggest = true;
	var row = ob.parents('tr:first');
	if (hideAutoSuggest) {
		row.find('.field-dropdown-div').show();
		row.find('.field-auto-suggest-div').hide();
		// If auto-suggest value matches drop-down value, then copy it
		var auto_suggest_val = row.find('.field-auto-suggest').val();
		if (row.find('.field-dropdown option[value="'+auto_suggest_val+'"]')) {
			row.find('.field-dropdown').val(auto_suggest_val);
			row.find('.field-dropdown').effect('highlight',{},1000);
		}
	} else {
		row.find('.field-dropdown-div').hide();
		row.find('.field-auto-suggest-div').show();
		// Copy drop-down value into auto suggest text box
		var dropdown_val = row.find('.field-dropdown').val();
		if (dropdown_val != '') {
			row.find('.field-auto-suggest').val(dropdown_val).css('color','#000');
		} else {
			row.find('.field-auto-suggest').val('').css('color','#bbb').trigger('blur');
		}
		row.find('.field-auto-suggest').effect('highlight',{},1000);
	}	
}

// Show or hide auto suggest limiter field text box
function showLimiterFieldAutoSuggest(ob, hideAutoSuggest) {
	if (hideAutoSuggest == null) hideAutoSuggest = true;
	var row = ob.parents('tr:first');
	if (hideAutoSuggest) {
		row.find('.limiter-dropdown-div').show();
		row.find('.field-auto-suggest-div').hide();
		// If auto-suggest value matches drop-down value, then copy it
		var auto_suggest_val = row.find('.field-auto-suggest').val();
		if (row.find('.limiter-dropdown option[value="'+auto_suggest_val+'"]')) {
			row.find('.limiter-dropdown').val(auto_suggest_val).effect('highlight',{},1000);
		}
	} else {
		row.find('.limiter-dropdown-div').hide();
		row.find('.field-auto-suggest-div').show();
		// Copy drop-down value into auto suggest text box
		var dropdown_val = row.find('.limiter-dropdown').val();
		if (dropdown_val != '') {
			row.find('.field-auto-suggest').val(dropdown_val).css('color','#000');
		} else {
			row.find('.field-auto-suggest').val('').css('color','#bbb').trigger('blur');
		}
		row.find('.field-auto-suggest').effect('highlight',{},1000);
	}	
}

// Show or hide auto suggest sort field text box
function showSortFieldAutoSuggest(ob, hideAutoSuggest) {
	if (hideAutoSuggest == null) hideAutoSuggest = true;
	var row = ob.parents('tr:first');
	if (hideAutoSuggest) {
		row.find('.sort-dropdown-div').show();
		row.find('.field-auto-suggest-div').hide();
		// If auto-suggest value matches drop-down value, then copy it
		var auto_suggest_val = row.find('.field-auto-suggest').val();
		if (row.find('.sort-dropdown option[value="'+auto_suggest_val+'"]')) {
			row.find('.sort-dropdown').val(auto_suggest_val).effect('highlight',{},1000);
		}
	} else {
		row.find('.sort-dropdown-div').hide();
		row.find('.field-auto-suggest-div').show();
		// Copy drop-down value into auto suggest text box
		var dropdown_val = row.find('.sort-dropdown').val();
		if (dropdown_val != '') {
			row.find('.field-auto-suggest').val(dropdown_val).css('color','#000');
		} else {
			row.find('.field-auto-suggest').val('').css('color','#bbb').trigger('blur');
		}
		row.find('.field-auto-suggest').effect('highlight',{},1000);
	}	
}

// Add id to all auto suggest text boxes and enable auto suggest for them
function enableAutoSuggestFields() {
	$('table#create_report_table .field-auto-suggest').each(function(){
		var ob = $(this);
		var obId = ob.attr('id');
		if (obId == null) {
			obId = "autosug-"+Math.floor(Math.random()*10000000000000000);
			ob.attr('id', obId);
			// Enable auto suggest
			$('#'+obId).autocomplete({ delay: 0, source: autoSuggestFieldList, minLength: 1,
				select: function( event, ui ) {
					// Get just the variable name
					var thisvar = ui.item.value;
					thisvar = thisvar.substring(0, thisvar.indexOf(' '));
					$(this).val(thisvar);
					var thisrow = $('#'+obId).parents('tr:first');
					// Now display the drop-down in place of the auto suggest text box and trigger the change event for the drop-down
					if (thisrow.find('.field-dropdown').length) {
						rprtft = "text";
						showReportFieldAutoSuggest(thisrow.find('.field-dropdown-a'),true);
						addNewReportRow(thisrow.find('.field-dropdown'));
					} else if (thisrow.find('.limiter-dropdown').length) {
						rprtft = "text";
						showLimiterFieldAutoSuggest(thisrow.find('.limiter-dropdown-a'),true);
						addNewLimiterRow(thisrow.find('.limiter-dropdown'));
						thisrow.find('.limiter-dropdown').trigger('change');
					} else {
						showSortFieldAutoSuggest(thisrow.find('.sort-dropdown-a'),true);
						thisrow.find('.sort-dropdown').trigger('change');
					}
					return false;
				}
			});
		}
	});	
}

// Onfocus action for Auto Suggest text box
function asfocus(ob) {
	ob = $(ob);
	if (ob.val() == langTypeVarName) { 
		ob.val('').css('color','#000'); 
	}
}

// Onblur action for Auto Suggest text box
function asblur(ob) {
	ob = $(ob);
	ob.val( trim(ob.val()) );
	var val_entered = ob.val();
	var valueBlank = (val_entered == '' || val_entered == langTypeVarName);
	var id = ob.attr('id');
	if (val_entered == '') { 
		ob.val(langTypeVarName).css('color','#bbb');
		// Set corresponding drop-down with blank value (for consistency)
		ob.parents('td:first').find('select').val('');
	} else if (!valueBlank) {
		setTimeout(function(){
			// If entered value does not match anything in the drop-down, then give error msg
			var isAutoSuggestVisible = $('#'+id).is(":visible");
			if (isAutoSuggestVisible && !ob.parents('td:first').find('select option[value="'+val_entered+'"]').length) {
				setTimeout(function(){
					simpleDialog(null,null,'VarEnteredNoExist_dialog',null,"$('#"+id+"').focus();");
				},10);
			} else if (isAutoSuggestVisible) {
				var thisrow = ob.parents('tr:first');
				if (thisrow.find('.field-dropdown').length) {
					rprtft = 'text';
					showReportFieldAutoSuggest(thisrow.find('.field-dropdown-a'),true);
					addNewReportRow(thisrow.find('.field-dropdown'));
				} else if (thisrow.find('.limiter-dropdown').length) {
					rprtft = 'text';
					showLimiterFieldAutoSuggest(thisrow.find('.limiter-dropdown-a'),true);
					addNewLimiterRow(thisrow.find('.limiter-dropdown'));
					thisrow.find('.limiter-dropdown').trigger('change');
				} else {
					showSortFieldAutoSuggest(thisrow.find('.sort-dropdown-a'),true);
					thisrow.find('.sort-dropdown').trigger('change');
				}
			}
		},500);
	}
}

// Fetch limiter's operator/value pair via ajax
function fetchLimiterOperVal(ob) {
	$.post(app_path_webroot+lrPluginPath+'report_filter_ajax.php?pid='+pid, { field_name: ob.val() }, function(data) {
		if (data == '0') {
			alert(woops);
			return;
		}
		// Find the table cell where limiter-operator is located and place return HTML there
		var td = ob.parents('tr:first').find('.limiter-operator').parents('td:first');
		td.html(data).effect('highlight',{},2000);
		td.find('.limiter-value').effect('highlight',{},2000);
		td.find('.limiter-operator').effect('highlight',{},2000).focus();
		// Enable date/time picker in case the field just loaded is a date/time field
		initDatePickers();
	});
}

// Obtain list of usernames who would have access to a report based on the User Access selections on the page
function getUserAccessList() {
	// Save the report via ajax
	$.post(app_path_webroot+lrPluginPath+'report_user_access_list.php?pid='+pid, $('form#create_report_form').serializeObject(), function(data) {
		if (data == '0') {
			alert(woops);
			return;
		}
		// Parse JSON
		var json_data = jQuery.parseJSON(data);
		simpleDialog(json_data.content, json_data.title, null, 600);
	});
}

// Save the new/existing report
function saveReport(report_id) {
	// Validate the report fields
	if (!validateCreateReport()) return false;
	// Validate the advanced filtering logic (if used)
	if (!check_advanced_logic()) return false;
	// Start clock so we can display progress for set amount of time
	var start_time = new Date().getTime();
	var min_wait_time = 1000;
	// Save the report via ajax
	$.post(app_path_webroot+lrPluginPath+'report_edit_ajax.php?pid='+pid+'&report_id='+report_id, $('form#create_report_form').serializeObject(), function(data) {
		if (data == '0') {
			alert(woops);
			return;
		}
	/*	// Update left-hand menu panel of Reports
		updateReportPanel(); */
		// Parse JSON
		var json_data = jQuery.parseJSON(data);
		// Build buttons for dialog
		var btns =	[{ text: 'Continue editing report', click: function() {
						if (json_data.newreport) {
							// Reload page with new report_id
							showProgress(1);
							window.location.href = app_path_webroot+lrPluginPath+'index.php?pid='+pid+'&report_id='+json_data.report_id+'&addedit=1';
						} else {
							$(this).dialog('close').dialog('destroy');
						}
					}},
					{text: 'Return to My Reports & Exports', click: function() {
						window.location.href = app_path_webroot+lrPluginPath+'index.php?pid='+pid;
					}},
					{text: 'View report', click: function() {
						window.location.href = app_path_webroot+lrPluginPath+'index.php?pid='+pid+'&report_id='+json_data.report_id;
					}}];
		// End clock
		var total_time = new Date().getTime() - start_time;
		// If total_time is less than min_wait_time, then wait till it gets to min_wait_time
		var wait_time = (total_time < min_wait_time) ? (min_wait_time-total_time) : 0;
		// Set wait time, if any
		setTimeout(function(){
			showProgress(0,0);
			// Display success dialog
			initDialog('report_saved_success_dialog');
			$('#report_saved_success_dialog').html(json_data.content).dialog({ bgiframe: true, modal: true, width: 600, 
				title: json_data.title, buttons: btns, close: function(){
					if (json_data.newreport) {
						// Reload page with new report_id
						showProgress(1);
						window.location.href = app_path_webroot+lrPluginPath+'index.php?pid='+pid+'&report_id='+json_data.report_id+'&addedit=1';
					} else {
						$(this).dialog('destroy');
					}
				} });
			$('#report_saved_success_dialog').dialog("widget").find(".ui-dialog-buttonpane button").eq(2).css({'font-weight':'bold', 'color':'#333'});
		}, wait_time);
	});
	// Set progress bar if still running after a moment
	setTimeout(function(){
		showProgress(1,300);
	},100);
}

// Validate report attributes when adding/editing report
function validateCreateReport() {
	// Make sure there is a title
	var title_ob = $('#create_report_table input[name="__TITLE__"]');
	title_ob.val( trim(title_ob.val()) );
	if (title_ob.val() == '') {
		simpleDialog(langNoTitle,null,null,null,"$('#create_report_table input[name=__TITLE__]').focus();");
		return false;
	}
	// If doing custom user access, make sure something is selected
	if ($('#create_report_table input[name="user_access_radio"]:checked').val() != 'ALL'
		&& ($('#create_report_table select[name="user_access_users"] option:selected').length 
			+ $('#create_report_table select[name="user_access_dags"] option:selected').length 
			+ $('#create_report_table select[name="user_access_roles"] option:selected').length) == 0) {
		simpleDialog(langNoUserAccessSelected);
		return false;
	}
	// Make sure that at least one field is selected to view in report
	if ($('.field-dropdown option:selected[value!=""]').length == 0) {
		simpleDialog(langNoFieldsSelected);
		return false;
	}
	// Filters: Make sure that each has an operator selected (value is allowed to be blank for text fields)
	var limiter_error_count = 0;
	$('.limiter-dropdown option:selected[value!=""]').each(function(){
		if ($(this).parents('tr:first').find('.limiter-operator').val() == '') {
			limiter_error_count++;
		}
	});
	if (limiter_error_count > 0) {
		simpleDialog(limiter_error_count+" "+langLimitersIncomplete);
		return false;
	}
	// If we made it this far, then all is well
	return true;
}

// For the pre-defined report "Selected instruments/events", obtain the instruments/events selected
// in the multi-selects on the My Reports page, and make them comma-delimited to append to a URL.
function getSelectedInstrumentList() {
	var instruments = $('select#export_selected_instruments option:selected').map(function(){ return this.value }).get().join(",");
	var instrumentsParam = (instruments == '') ? '' : '&instruments='+instruments;
	var events = '';
	if ($('select#export_selected_events').length) {
		var events = $('select#export_selected_events option:selected').map(function(){ return this.value }).get().join(",");
	}
	var eventsParam = (events == '') ? '' : '&events='+events;
	return instrumentsParam+eventsParam;

}
	
// For the pre-defined report "Selected instruments/events", obtain the instruments/events in the query string of the URL
// and return them in order to append to another URL's query string.
function getInstrumentsListFromURL() {
	var instruments = getParameterByName('instruments');
	var instrumentsParam = (instruments == '') ? '' : '&instruments='+instruments;
	var events = getParameterByName('events');
	var eventsParam = (events == '') ? '' : '&events='+events;
	return instrumentsParam+eventsParam;
}

// Update left-hand menu panel of Reports
function updateReportPanel() {
        // For Longitudinal Reports, reload page
        window.location.reload();
}
/*	$.post(app_path_webroot+lrPluginPath+'render_report_panel_ajax.php?pid='+pid, { }, function(data){
		$('#report_panel').remove();
		if (data != '') {
			// Update the left-hand menu
			$('#help_panel').before(data);			
			// Add fade mouseover for "Edit instruments" and "Edit reports" links on project menu
			$("#menuLnkEditReports").hover(function() {
				$(this).removeClass('opacity50');
				if (isIE) $(this).find("img").removeClass('opacity50');
			}, function() {
				$(this).addClass('opacity50');
				if (isIE) $(this).find("img").addClass('opacity50');
			});
		}
	});
}
*/
// Load new report page (via changing page drop-down list)
function loadReportNewPage(pagenum) {
	var report_id = getParameterByName('report_id');
	modifyURL(app_path_webroot+lrPluginPath+'index.php?pid='+pid+'&report_id='+report_id+(report_id=='SELECTED' ? '&instruments='+getParameterByName('instruments') : '')+'&pagenum='+pagenum);
	if (isNumeric(pagenum)) {
		$('#report_load_progress_pagenum_text').show();
		$('#report_load_progress_pagenum').html(pagenum);
	}
	$('#report_parent_div').html('');
	setTimeout(function(){ 
		fetchReportAjax(report_id, pagenum); 
	}, 50);
}
// Display "Quick Add" dialog
function openQuickAddDialog(btn) {

        var progressCircle = $('#imgQAProgress');
        $(btn).hide();
        progressCircle.show();
        
        // Collect all fields already selected to include in the report
        var flds = new Array();
        var i = 0;
        var val;
        $('select.field-dropdown').each(function(){
                val = $(this).val();

                if (val !== '') {
                        flds[i++] = val;
                }
        });

//        console.log(flds);                              

        // Ajax call
        $.post(app_path_webroot+lrPluginPath+'report_quick_add_field_ajax.php?pid='+pid,{ checked_fields: flds.join(',') }, function(data){
                try {
                        // Parse JSON
                        var json_data = jQuery.parseJSON(data);
                } catch (e) {
                        simpleDialog(woops,langError);
                        return;
                }
                
                // Display success dialog
                simpleDialog(null, json_data.title, 'quickAddField_dialog', 750);
                //console.log(json_data.content);                       
                $('#quickAddField_dialog').html(json_data.content);
                
                $('#quickAddAccordion').accordion({ 
                    active: false,
                    icons: false, 
                    clearStyle: true, 
                    autoHeight: false,
                    collapsible: true,
                    heightStyle: 'content'
                });
                
                fitDialog($('#quickAddField_dialog'));

                // Add text inside dialog's button pane
                var bptext = '<div style="float:right;margin:10px 100px 0 0;color:#444;font-weight:bold;font-size:12px;">'/*+langTotFldsSelected+' '*/
                                   + '<span id="quickAddField_count" style="color:#800000;font-size:12px;">'+$('#quickAddField_dialog input[type="checkbox"]:checked').length+'</span> selected</div>';
                $('#quickAddField_dialog').dialog("widget").find(".ui-dialog-buttonpane button").eq(0).after(bptext);

                // Set the top of the quick add dialog to be near the top of screen
                $('#quickAddField_dialog').parent().css('top', '10px');

        }).always(function () {
            $(btn).show();
            progressCircle.hide();
        });
}

// Add or delete field via "Quick Add" dialog
function qa(ob) {
        var fld = ob.attr('name');
        var isChecked = ob.prop('checked');
        if (isChecked) {
                // Add row
                $('.field-dropdown-a:last').trigger('click');
                $('.field-dropdown:last').val(fld).trigger('change');
        } else {
                // Remove row
                var dd = $('.field-dropdown option[value="'+fld+'"]:selected');
                dd.parents('tr:first').find('td:last').find('img').trigger('click');
        }
        // Set total count
        $('#quickAddField_count').html( $('#quickAddField_dialog input[type="checkbox"]:checked').length );
}

// Select all fields for a form in "Quick Add" dialog
function reportQuickAddForm(eventForm,select) {
        $('#quickAddField_dialog .frm-'+eventForm).each(function(){
                var ob = $(this);
                var isChecked = ob.prop('checked');
                if ((!isChecked && select) || (isChecked && !select)) {
                        ob.prop('checked', !isChecked);
                        qa(ob);
                }
        });
}
