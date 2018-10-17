<?php
/* 
 * Longitudinal Reports Plugin
 * Luke Stevens, Murdoch Childrens Research Institute https://www.mcri.edu.au
 */

/**
 * LongitudinalReports class
 * Altered for LongitudinalReports from redcap_v6.4.3/Classes/DataExport.php 
 */
class LongitudinalReports
{
        
	// Display tabs on page
	public static function renderTabs()
	{
		global $lang, $user_rights, $redcap_version;
		// Get current URL relative to version folder
		/*$version_folder = "redcap_v{$redcap_version}/";
		$current_url = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], $version_folder) + strlen($version_folder));
		// Get query string parameters for the current page's URL
		$params = (strpos($current_url, ".php?") === false) ? array() : explode("&", parse_url($current_url, PHP_URL_QUERY));
		// Remove query string from $current_url
		list ($current_url, $query_string) = explode('?', $current_url, 2);
		// Format query string for the url to add 'pid'
		if (!empty($params)) {
			foreach ($params as $key=>$val) {
				// Remove the pid in the query string
				if ($val == "pid=".PROJECT_ID) unset($params[$key]);
			}
			$current_url .= "?" . implode("&", $params);
		} */
		// If have report_id, then get report name
		if (isset($_GET['report_id'])) $report_name = self::getReportNames($_GET['report_id']);
		// Determine tabs to display
		$tabs = array();
		// Tab to build a new report
		if ($user_rights['reports']) {
			$tabs[LR_PATH_FROM_WEBROOT.'index.php?create=1&addedit=1'] =  RCView::img(array('src'=>'plus_blue.png', 'style'=>'vertical-align:middle;height:14px;width:14px;')) . 
																RCView::span(array('style'=>'vertical-align:middle;'), $lang['report_builder_14']);
		}
		// Tab to view list of existing reports
		$tabs[LR_PATH_FROM_WEBROOT.'index.php'] = RCView::img(array('src'=>'layout_down_arrow.gif', 'style'=>'vertical-align:middle;position:relative;top:1px;')) . 
										RCView::span(array('style'=>'vertical-align:middle;'), $lang['report_builder_47']);
/*		// Other export options (zip, pdf, etc.) if user has some export rights
		if ($user_rights['data_export_tool'] > 0) {
			$tabs['DataExport/index.php?other_export_options=1'] = RCView::img(array('src'=>'documents_arrow.png', 'style'=>'vertical-align:middle;')) . 
										RCView::span(array('style'=>'vertical-align:middle;'), $lang['data_export_tool_188']);
		}
*/		
		// Edit existing report
		if (isset($_GET['addedit']) && isset($_GET['report_id']) && is_numeric($_GET['report_id'])) {
			$tabs[LR_PATH_FROM_WEBROOT.'index.php?addedit=1&report_id='.$_GET['report_id']] =
                                RCView::img(array('src'=>'pencil.png', 'style'=>'vertical-align:middle;')) . 
                                        RCView::span(array('style'=>'vertical-align:middle;'), 
                                                $lang['report_builder_05'] . $lang['colon'] . RCView::SP .
                                                RCView::span(array('style'=>'font-weight:normal;color:#800000;'), $report_name)
                                        );
		}
/*		// View stats & charts for existing report
		elseif (isset($_GET['stats_charts']) && isset($_GET['report_id'])) {
			$tabs[$current_url] = 	RCView::img(array('src'=>'chart_bar.png', 'style'=>'vertical-align:middle;')) . 
									RCView::span(array('style'=>'vertical-align:middle;'), 
										$lang['report_builder_78'] . $lang['colon'] . RCView::SP .
										RCView::span(array('style'=>'font-weight:normal;color:#800000;'), $report_name)
									);
		}
*/		// Tab for viewing single report
		elseif (!isset($_GET['addedit']) && !isset($_GET['stats_charts']) && isset($_GET['report_id'])) {
			$tabs[LR_PATH_FROM_WEBROOT.'index.php?report_id='.$_GET['report_id']] = 
                                RCView::img(array('src'=>'layout.png', 'style'=>'vertical-align:middle;')) . 
                                        RCView::span(array('style'=>'vertical-align:middle;'), 
                                                $lang['report_builder_44'] . $lang['colon'] . RCView::SP .
                                                RCView::span(array('style'=>'font-weight:normal;color:#800000;'), $report_name)
                                        );
		}
		// Render the tabs
                // REDCap v7 csrf functions refactored as static methods of System class
                if (version_compare(REDCAP_VERSION, '7.0.0', '<')) {
                        renderTabs($tabs);
                } else {
                        RCView::renderTabs($tabs);
                }
	}
	
	
	// Display list of all usernames who have access to a given report (by report_id)
	public static function displayReportAccessUsernames($post)
	{
		global $Proj, $lang;
		// Get list of users
		$user_list = self::getReportAccessUsernames($post);
		// Get all roles in the project
		$roles = UserRights::getRoles();
		$hasRoles = !empty($roles);
		// Get all roles in the project
		$dags = $Proj->getGroups();
		$hasDags = !empty($dags);
		
		// Loop through users and create table rows
		$rows = RCView::tr(array(),
					RCView::td(array('class'=>'header', 'style'=>'width:250px;'),
						$lang['global_17']
					) .
					(!$hasRoles ? '' :
						RCView::td(array('class'=>'header'),
							$lang['global_115']
						)
					) .	
					(!$hasDags ? '' :
						RCView::td(array('class'=>'header'),
							$lang['global_78']
						)
					)
				);
		foreach ($user_list as $user=>$attr) {
			// Add user
			$rows .= RCView::tr(array(),
						RCView::td(array('class'=>'labelrc', 'style'=>'width:250px;padding:5px 10px;color:#800000;font-size:13px;font-weight:normal;'),
							$attr['name']
						) .
						(!$hasRoles ? '' :
							RCView::td(array('class'=>'data', 'style'=>'padding:5px 10px;'),
								(is_numeric($attr['role_id']) ? $roles[$attr['role_id']]['role_name'] : '')
							)
						) .			
						(!$hasDags ? '' :			
							RCView::td(array('class'=>'data', 'style'=>'padding:5px 10px;'),
								(is_numeric($attr['group_id']) ? $dags[$attr['group_id']] : '')
							)
						)
					);
		}
		// No users with access
		if (empty($user_list)) {
			$rows .= RCView::tr(array(),
						RCView::td(array('colspan'=>(1+($hasRoles ? 1 : 0)+($hasDags ? 1 : 0)), 'class'=>'data', 'style'=>'width:250px;padding:5px 10px;color:#800000;font-size:13px;'),
							$lang['report_builder_110']
						)
					);
		}
		// Output table
		$html =	RCView::div(array('style'=>'margin:0 0 15px;'),
					$lang['report_builder_109']
				) .
				RCView::table(array('class'=>'form_border', 'style'=>"width:100%;table-layout:fixed;"),
					$rows
				);
		// Return html
		return $html;
	}
	
	
	// Return array of all usernames who have access to a given report (by report_id)
	public static function getReportAccessUsernames($post)
	{
		// Get list of ALL users in project
		$all_users = User::getProjectUsernames(array(), true);
		// Get username list
		if ($post['user_access_radio'] == 'ALL') {
			// ALL USERS
			return $all_users;
		} else {
			// SELECTED USERS
			$selected_users = array();
			// User access rights
			$user_access_users = $user_access_roles = $user_access_dags = array();
			if (isset($post['user_access_users'])) {
				$user_access_users = $post['user_access_users'];
				if (!is_array($user_access_users)) $user_access_users = array($user_access_users);
			}
			if (isset($post['user_access_roles'])) {
				$user_access_roles = $post['user_access_roles'];
				if (!is_array($user_access_roles)) $user_access_roles = array($user_access_roles);
			}
			if (isset($post['user_access_dags'])) {
				$user_access_dags = $post['user_access_dags'];
				if (!is_array($user_access_dags)) $user_access_dags = array($user_access_dags);
			}
                        
                        $user_access_users = preg_replace('/\"|\[|\]/', '', $user_access_users);
			$user_sql = prep_implode($user_access_users);
			if ($user_sql == '') $user_sql = "''";

                        $user_access_roles = preg_replace('/\"|\[|\]/', '', $user_access_roles);
                        $role_sql = prep_implode($user_access_roles);
			if ($role_sql == '') $role_sql = "''";
			
                        $user_access_dags = preg_replace('/\"|\[|\]/', '', $user_access_dags);
                        $dag_sql = prep_implode($user_access_dags);
			if ($dag_sql == '') $dag_sql = "''";

                        // Query tables
			$sql = "select u.username, r.role_id, g.group_id from redcap_user_rights u
					left join redcap_user_roles r on r.role_id = u.role_id
					left join redcap_data_access_groups g on g.group_id = u.group_id
					where u.project_id = ".PROJECT_ID." and 
					(u.username in ($user_sql) or r.role_id in ($role_sql) or g.group_id in ($dag_sql)) 
					order by u.username";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) {
				// Add to array
				$selected_users[$row['username']] = array('name'=>$all_users[$row['username']], 'role_id'=>$row['role_id'], 'group_id'=>$row['group_id']);
			}
			return $selected_users;
		}
	}
	
	
	// Output html table for users to create or modify reports
	public static function outputCreateReportTable($report_id=null)
	{
		global $lang, $Proj, $surveys_enabled, $user_rights;
		// Get report_id
		$report_id = ($report_id == null ? 0 : $report_id);
		// Get report attributes
		$report = self::getReports($report_id);
		// Create array of all field validation types and their attributes
		$allValTypes = getValTypes();
		// Set counter for number of fields in report + number of limiters used
		$field_counter = $limiter_counter = 1;
		// Get all field drop-down options
		$rc_field_dropdown_options = self::getFieldDropdownOptions(); //Form::getFieldDropdownOptions();
		$rc_field_dropdown_options_orderby = self::getFieldDropdownOptions(true); //Form::getFieldDropdownOptions(true);
/*		// Get all forms as drop-down list
		$addFormFieldsDropDownOptions = array(''=>'-- '.$lang['report_builder_101'].' --');
		foreach ($Proj->forms as $key=>$attr) {
			$addFormFieldsDropDownOptions[$key] = $attr['menu'];
		}		*/
		// Get list of User Roles
		$role_dropdown_options = array();
		foreach (UserRights::getRoles() as $role_id=>$attr) {
			$role_dropdown_options[$role_id] = $attr['role_name'];
		}
		// Get list of all DAGs, events, users, and records
		$dag_dropdown_options = $Proj->getGroups();
		$user_dropdown_options = User::getProjectUsernames(array(), true);
		$event_dropdown_options = $event_dropdown_options_with_all = array();
//		if ($Proj->longitudinal) {
			foreach ($Proj->eventInfo as $this_event_id=>$attr) {
				$event_dropdown_options[$this_event_id] = $attr['name_ext'];
			}
			$event_dropdown_options_with_all = array(''=>$lang['dataqueries_136']) + $event_dropdown_options;
//		}
		$user_access_radio_custom_checked = ($report['user_access'] != 'ALL') ? 'checked' : '';
		$user_access_radio_all_checked = ($report['user_access'] == 'ALL') ? 'checked' : '';
		if ($report['user_access'] == 'ALL') {
			// If ALL is selected, then remove custom options
			$report['user_access_users'] = $report['user_access_roles'] = $report['user_access_dags'] = array();
		}
		// Add blank values onto the end of some attributes to create empty row for user to enter a new field, filter, etc.
 		$report['fields'][] = "";
		$report['limiter_fields'][] = array('field_name'=>'', 'limiter_group_operator'=>'AND', 'limiter_event_id'=>'', 
											'limiter_operator' =>'', 'limiter_value'=>'');
		// Instructions
		print   RCView::div(array('style'=>'max-width:800px;margin:5px 0 20px;'),
					$lang['report_builder_118']
				);
		// Initialize table rows
		print  "<div style='max-width:800px;'>
				 <form id='create_report_form'>
					<table id='create_report_table' class='form_border' style='width:100%;'>";
		// Report title
		print   RCView::tr(array(),
					RCView::td(array('class'=>'header nowrap', 'style'=>'text-align:center;padding-right:0;padding-left:0;color:#800000;height:50px;width:120px;font-size: 14px;'),
						$lang['report_builder_16']
					) .
					RCView::td(array('class'=>'header', 'colspan'=>3, 'style'=>'color:#800000;height:50px;padding:5px 10px;'),
						RCView::text(array('name'=>'__TITLE__', 'value'=>htmlspecialchars($report['title'], ENT_QUOTES), 'class'=>'x-form-text x-form-field', 'maxlength'=>60, 'style'=>'padding: 4px 6px 3px;font-size:16px;width:95%;'))
					)
				);
		
		## USER ACCESS
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom', 
						'style'=>'padding:0;background:#fff;border-left:0;border-right:0;height:45px;'), 
						RCView::div(array('style'=>'color:#444;position:relative;top:10px;background-color:#ddd;border:1px solid #ccc;border-bottom:1px solid #ddd;float:left;padding:5px 8px;'),
							$lang['global_117']." 1"
						)
					)
				);
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom'),
						RCView::div(array('style'=>''),
							RCView::img(array('src'=>'group_add.png', 'class'=>'imgfix')) .
							$lang['extres_35'] /*. $lang['colon'] . " " .
							RCView::span(array('style'=>'font-weight:normal;'), $lang['report_builder_133']) .
							RCView::a(array('href'=>'javascript:;', 'class'=>'help', 'title'=>$lang['global_58'], 'onclick'=>"simpleDialog('".cleanHtml($lang['report_builder_134'])."','".cleanHtml($lang['report_builder_135'])."',null,600);"), '?')*/
						)
					)
				);
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc', 'colspan'=>4, 'style'=>'padding-top:6px;padding-bottom:6px;'),
						// All users
						RCView::div(array('style'=>'float:left;'),
							RCView::radio(array('name'=>'user_access_radio', 'onchange'=>"displayUserAccessOptions()", 'class'=>'imgfix2', 'value'=>'ALL', $user_access_radio_all_checked=>$user_access_radio_all_checked))
						) .
						RCView::div(array('style'=>'float:left;margin:2px 0 0 2px;'),
							$lang['control_center_182']
						) .
						RCView::div(array('style'=>'float:left;color:#888;font-weight:normal;margin:2px 20px 0 25px;'),
							"&ndash; " . $lang['global_46'] . " &ndash;"
						) .
						// Custom user access
						RCView::div(array('style'=>'float:left;'),
							RCView::radio(array('name'=>'user_access_radio', 'onchange'=>"displayUserAccessOptions()", 'class'=>'imgfix2', 'value'=>'SELECTED', $user_access_radio_custom_checked=>$user_access_radio_custom_checked))
						) .
						RCView::div(array('style'=>'float:left;margin:2px 0 0 2px;'),
							RCView::div(array('style'=>'margin-bottom:10px;'),
								$lang['report_builder_62'] .
								RCView::span(array('id'=>'selected_users_note1', 'style'=>($report['user_access'] == 'ALL' ? 'display:none;' : '').'margin-left:10px;color:#800000;font-size:11px;font-weight:normal;'),
									$lang['report_builder_105']
								) .
								RCView::span(array('id'=>'selected_users_note2', 'style'=>($report['user_access'] != 'ALL' ? 'display:none;' : '').'margin-left:10px;color:#888;font-size:11px;font-weight:normal;'),
									$lang['report_builder_66']
								)
							) .
							RCView::div(array('id'=>'selected_users_div', 'style'=>($report['user_access'] == 'ALL' ? 'display:none;' : '')),
								// Select Users
								RCView::div(array('style'=>'margin-right:30px;float:left;font-weight:normal;vertical-align:top;'),
									$lang['extres_28'] . 
									RCView::div(array('style'=>'margin-left:3px;'),
										RCView::select(array('name'=>'user_access_users', 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:70px;'), 
											$user_dropdown_options, $report['user_access_users'], 200)
									)
								) .
								// Select User Roles
								(empty($role_dropdown_options) ? '' :
									RCView::div(array('style'=>'margin-right:30px;float:left;font-weight:normal;vertical-align:top;'),
										$lang['report_builder_61'] . 
										RCView::div(array('style'=>'margin-left:3px;'),
											RCView::select(array('name'=>'user_access_roles', 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:70px;'), 
												$role_dropdown_options, $report['user_access_roles'], 200)
										)
									)
								) .
								// Select DAGs
								(empty($dag_dropdown_options) ? '' :
									RCView::div(array('style'=>'float:left;font-weight:normal;vertical-align:top;'),
										$lang['extres_52'] . 
										RCView::div(array('style'=>'margin-left:3px;'),
											RCView::select(array('name'=>'user_access_dags', 'multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:70px;'), 
												$dag_dropdown_options, $report['user_access_dags'], 200)
										)
									)
								) .
								RCView::div(array('style'=>'clear:both;padding:10px 0 0 3px;font-size:11px;font-weight:normal;color:#888;'),
									$lang['report_builder_40']
								) .
								// Get list of users who would have access given the selections made
								RCView::div(array('style'=>'padding:5px 0 0 3px;font-size:11px;font-weight:normal;color:#222;'),
									$lang['report_builder_111'] .
									RCView::button(array('class'=>'jqbuttonsm', 'style'=>'margin-left:7px;font-family:arial;font-size:11px;', 'onclick'=>"getUserAccessList();return false;"), 
										$lang['report_builder_107']
									)
								)
							)
						)
					)
				);
		
		## FIELDS USED IN REPORT
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom', 
						'style'=>'padding:0;background:#fff;border-left:0;border-right:0;height:45px;'), 
						RCView::div(array('style'=>'color:#444;position:relative;top:10px;background-color:#ddd;border:1px solid #ccc;border-bottom:1px solid #ddd;float:left;padding:5px 8px;'),
							$lang['global_117']." 2"
						)
					)
				);
                // "Fields" section header
                $messageOnRepeating = (version_compare(REDCAP_VERSION, '7.0.0', '>=') && $Proj->hasRepeatingFormsEvents()) 
                        ? '<div style="float:right;font-size:11px;padding:5px;" class="text-warning bg-warning">Repeating forms/events: <span style="font-weight:normal;">Only the last instance will be included.</span></div>'
                        : '';
                print   RCView::tr(array(),
                                  RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom'),
                                      RCView::div(array('style'=>'float:left;'),
                                          RCView::img(array('id'=>'dragndrop_tooltip_trigger', 'title'=>$lang['report_builder_67'], 'src'=>'tags.png', 'class'=>'imgfix')) .
                                          $lang['report_builder_29']
                                      ) .
                                      // Quick Add button
                                      RCView::div(array('style'=>'float:left;margin:0 20px;'),
                                          RCView::button(array('class'=>'jqbuttonsm', 'style'=>'color:green;font-size:11px;font-family:arial;', 'onclick'=>"openQuickAddDialog(this); return false;"),
                                              RCView::img(array('src'=>'plus_small2.png', 'style'=>'vertical-align:middle;')) .
                                              RCView::span(array('style'=>'vertical-align:middle;'),
                                                  $lang['report_builder_136']
                                              )
                                          ).
                                          RCView::img(array('src' => 'progress_circle.gif', 'id' => 'imgQAProgress', 'style' => 'display:none;'))
                                      ) . $messageOnRepeating
                                  )
                              );
		// Fill rows of fields (only for existing reports)
		foreach ($report['fields'] as $this_field) // except now $this_field is like [baseline_arm_1][dob], not just dob
		{
                        $fname = self::getFieldFromEventField($this_field);
			print   RCView::tr(array('class'=>'field_row'),
						// "Field X"
						RCView::td(array('class'=>'labelrc '.($this_field != '' ? 'dragHandle' : ''), 'style'=>'width:120px;'),
							RCView::div(array('style'=>'line-height:20px;'),
								RCView::span(array('style'=>'margin-left:25px;'), $lang['graphical_view_23'] . " ") . 
								RCView::span(array('class'=>'field_num'), $field_counter++)
							)
						) .
						// Dropdown/text box
						RCView::td(array('class'=>'labelrc', 'colspan'=>2),
							RCView::div(array('class'=>'field-auto-suggest-div', 'style'=>($this_field != '' ? 'display:none;' : '')),
								self::outputFieldAutoSuggest() .
								RCView::button(array('title'=>$lang['report_builder_32'], 'class'=>'jqbuttonsm field-dropdown-a', 'onclick'=>"showReportFieldAutoSuggest($(this),true);return false;", 'style'=>'font-size:11px;'), 
									RCView::img(array('src'=>'dropdown.png', 'style'=>'vertical-align:middle;'))
								)
							) . 
							RCView::div(array('class'=>'field-dropdown-div', 'style'=>($this_field == '' ? 'display:none;' : '')),
								RCView::div(array('style'=>'float:left;'), 
									self::outputFieldDropdown($rc_field_dropdown_options, $this_field) .
									RCView::button(array('title'=>$lang['report_builder_30'], 'class'=>'jqbuttonsm field-auto-suggest-a', 'onclick'=>"showReportFieldAutoSuggest($(this),false);return false;", 'style'=>'font-size:11px;'), 
										RCView::img(array('src'=>'form-text-box.gif', 'style'=>'vertical-align:middle;'))
									)
								) .
								RCView::div(array('class'=>'fn'),
									RCView::span(array('class'=>'fna'), $lang['design_493']) .
									RCView::span(array('class'=>'fnb'), 
										($this_field == '' ? '' : $Proj->forms[$Proj->metadata[$fname]['form_name']]['menu'])
									)
								) .
								RCView::div(array('class'=>'clear'), '')
							)
						) .
						// Delete
						RCView::td(array('class'=>'labelrc', 'style'=>'text-align:center;width:25px;'),
							RCView::a(array('href'=>'javascript:;', 'onclick'=>"deleteReportField($(this));", 'style'=>($this_field == '' ? 'display:none;' : '')),
								RCView::img(array('src'=>'cross.png', 'class'=>'imgfix opacity75', 'title'=>$lang['design_170']))
							)
						)
					);
		}
		
		## ADDITIONAL FIELDS (OUTPUT DAG NAMES, OUTPUT SURVEY FIELDS, OUTPUT EVENT SCHEDULED DATES)
                print   RCView::tr(array(),
                                        RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom', 
                                                'style'=>'background:#fff;border-left:0;border-right:0;height:5px;'), '')
                                );
                print   RCView::tr(array(),
                                        RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom'),
                                                RCView::div(array('style'=>'float:left;'),
                                                        RCView::img(array('src'=>'tag_orange.png', 'class'=>'imgfix')) .
                                                        $lang['report_builder_89'] . " " .
                                                        RCView::span(array('style'=>'font-weight:normal;'), $lang['global_06'])
                                                )
                                        )
                                );
                
                if (!empty($dag_dropdown_options)) {
                        $exportDagOption = "";
                        $outputDagChecked = ($report['output_dags']) ? 'checked' : '';
                        $exportDagOption = 
			print   RCView::tr(array(),
						RCView::td(array('class'=>'labelrc', 'colspan'=>4, 'valign'=>'top', 'style'=>''),
							RCView::span(array('style'=>'margin:0 10px 0 20px;vertical-align:top;position:relative;top:4px;float:left;width:110px;'),
                                                                $lang['global_114'].":"
                                                        ) .
                                                        RCView::checkbox(array('name'=>'output_dags', $outputDagChecked=>$outputDagChecked)
                                                        ) . 
                                                        RCView::span(array('style'=>'margin:0 0 0 10px;vertical-align:top;position:relative;top:4px;font-weight:normal;'),
                                                                $lang['data_export_tool_178']
                                                        ) 
						)
					);
                }
                print   RCView::tr(array(),
                                        RCView::td(array('class'=>'labelrc', 'colspan'=>4, 'valign'=>'top', 'style'=>''),
                                                // OUTPUT SCHEDULE DATES
                                                RCView::div(array('style'=>(!$Proj->longitudinal ? 'display:none;' : '').'float:left;margin-bottom:5px;'),
                                                        RCView::span(array('style'=>'margin:0 10px 0 20px;vertical-align:top;position:relative;top:4px;float:left;width:110px;'),
                                                                "Schedule Dates:"
                                                        ) .
                                                        RCView::select(array('multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:80px;', 
                                                            'name'=>'output_schedule_dates'), $event_dropdown_options, $report['output_schedule_dates'], 200) .
                                                        RCView::span(array('style'=>'margin:0 0 0 10px;vertical-align:top;position:relative;top:4px;font-weight:normal;'),
                                                                "Include the date of the selected event(s) from the calendar for each record"
                                                        )
                                                ) 
                                        )
                                );

                if ($surveys_enabled) {
                    
                        // TODO Copy output_survey_fields to output_survey_queue_url ?
/*                        $outputSurveyFieldsChecked = ($report['output_survey_fields']) ? 'checked' : '';
                        $exportSurveyFieldsOptions = RCView::checkbox(array('name'=>'output_survey_fields', $outputSurveyFieldsChecked=>$outputSurveyFieldsChecked
                                , 'disabled' => 'disabled')) . $lang['data_export_tool_179'];
                        print   RCView::tr(array(),
                                        RCView::td(array('class'=>'labelrc', 'colspan'=>4, 'valign'=>'top', 'style'=>'font-weight:normal;padding:8px;'),
                                                $exportSurveyFieldsOptions
                                        )
                                );*/

                        $surveyurl_dropdown_options = self::getSurveyUrlDropdownOptions();
                        
                        print   RCView::tr(array(),
                                                RCView::td(array('class'=>'labelrc', 'colspan'=>4, 'valign'=>'top', 'style'=>''),
                                                        // SURVEYS
                                                        RCView::div(array('style'=>'float:left;margin-bottom:5px;'),
                                                                RCView::span(array('style'=>'margin:0 10px 0 20px;vertical-align:top;position:relative;top:4px;float:left;width:110px;'),
                                                                        "Survey URLs:"
                                                                ) .
                                                                RCView::select(array('multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:80px;', 
                                                                        'name'=>'output_survey_urls'), $surveyurl_dropdown_options, $report['output_survey_urls'], 200) .
                                                                RCView::span(array('style'=>'margin:0 0 0 10px;vertical-align:top;position:relative;top:4px;font-weight:normal;'),
                                                                        "Include URL to the selected survey(s) for each record"
                                                                )
                                                        ) 
                                                )
                                        );
                }
                
/*		if (!empty($dags) || $surveys_enabled) 
		{
			$exportDagOption = "";
			$exportSurveyFieldsOptions = "";
			if (!empty($dags)) {
				$outputDagChecked = ($report['output_dags']) ? 'checked' : '';
				$exportDagOption = RCView::checkbox(array('name'=>'output_dags', $outputDagChecked=>$outputDagChecked)) . 
								   $lang['data_export_tool_178'];
			}
			if ($surveys_enabled) {
				$outputSurveyFieldsChecked = ($report['output_survey_fields']) ? 'checked' : '';
				$exportSurveyFieldsOptions = RCView::checkbox(array('name'=>'output_survey_fields', $outputSurveyFieldsChecked=>$outputSurveyFieldsChecked
                                        , 'disabled' => 'disabled')) . 
											 $lang['data_export_tool_179'];
			}
			$exportDagSurveyFieldsOptions = $exportDagOption . RCView::br() . $exportSurveyFieldsOptions;
			print   RCView::tr(array(),
						RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom', 
							'style'=>'background:#fff;border-left:0;border-right:0;height:5px;'), '')
					);
			print   RCView::tr(array(),
						RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom'),
							RCView::div(array('style'=>'float:left;'),
								RCView::img(array('src'=>'tag_orange.png', 'class'=>'imgfix')) .
								$lang['report_builder_89'] . " " .
								RCView::span(array('style'=>'font-weight:normal;'), $lang['global_06'])
							)
						)
					);
			print   RCView::tr(array(),
						RCView::td(array('class'=>'labelrc', 'colspan'=>4, 'valign'=>'top', 'style'=>'font-weight:normal;padding:8px;'),
							$exportDagSurveyFieldsOptions
						)
					);
		}*/

		## LIMTERS
		// "Limiters" section header
                $messageOnRepeating = (version_compare(REDCAP_VERSION, '7.0.0', '>=') && $Proj->hasRepeatingFormsEvents()) 
                        ? '<div style="float:right;font-size:11px;padding:5px;" class="text-warning bg-warning">Logic for fields in repeated form/event not supported.</div>'
                        : '';

		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom', 
						'style'=>'padding:0;background:#fff;border-left:0;border-right:0;height:45px;'), 
						RCView::div(array('style'=>'color:#444;position:relative;top:10px;background-color:#ddd;border:1px solid #ccc;border-bottom:1px solid #ddd;float:left;padding:5px 8px;'),
							$lang['global_117']." 3"
						)
					)
				);
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>2, 'valign'=>'bottom', 'style'=>'border-right:0;'),
						RCView::img(array('src'=>'filter_plus.gif', 'class'=>'imgfix')) .
						$lang['report_builder_35'] . " " .
						RCView::span(array('style'=>'font-weight:normal;'), $lang['global_06']) .
                                                $messageOnRepeating
					) .
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>2, 'valign'=>'bottom', 'style'=>'border-left:0;'),
						/*// Help link
						RCView::div(array('id'=>'how_to_filters_link', 'style'=>'text-align:right;margin:2px 5px 6px 0;'.($report['advanced_logic'] != '' ? 'display:none;' : '')),
							RCView::img(array('src'=>'help.png', 'style'=>'vertical-align:middle;')) .
							RCView::a(array('href'=>'javascript:;', 'onclick'=>"simpleDialog(null,null,'filter_help',600);fitDialog($('#filter_help'));", 'style'=>'vertical-align:middle;font-weight:normal;color:#3E72A8;'),
								$lang['report_builder_119']
							)
						) .*/
						// "Operator / Value" text
						RCView::div(array('id'=>'oper_value_hdr', 'style'=>($report['advanced_logic'] != '' ? 'display:none;' : '')),
							$lang['report_builder_19']
						)
					)
				);
		// Fill rows of limiter fields (only for existing reports)
		$limiter_group_operator_options = array("OR"=>$lang['global_46'], "AND"=>$lang['global_87']);
		$limiter_field_num = 0;
		foreach ($report['limiter_fields'] as $attr) 
		{
			// If doing a new "AND" group, then display extra row (but if not, then keep hidden via CSS)
			$display_limiter_and_row = ($limiter_field_num > 0 && $attr['limiter_group_operator'] == 'AND');
			// Render "AND" row
			print   RCView::tr(array('class'=>'limiter_and_row'.($report['advanced_logic'] != '' ? ' hidden' : ''), 'style'=>($display_limiter_and_row ? '' : 'display:none;')),
						RCView::td(array('class'=>'labelrc', 'colspan'=>4, 'style'=>'padding:8px 60px;background:#ddd;'),
							RCView::select(array('lgo'=>$limiter_counter, 'class'=>'lgoc x-form-text x-form-field', 'style'=>'padding-right:0;height:22px;color:#800000;', 
								'onchange'=>"displaylimiterGroupOperRow($(this));"), $limiter_group_operator_options, $attr['limiter_group_operator'])
						)
					 );
			// Render row
			print   RCView::tr(array('class'=>'limiter_row'.($report['advanced_logic'] != '' ? ' hidden' : '')),
						// Label
						RCView::td(array('class'=>'labelrc', 'style'=>'width:120px;'),
							// AND/OR limiter operator dropdown
							RCView::span(array('style'=>'margin:0;'.(($limiter_field_num == 0 || $attr['limiter_group_operator'] == 'AND') ? 'visibility:hidden;' : '')), 
								RCView::select(array('name'=>'limiter_group_operator[]', 'lgo'=>$limiter_counter, 'class'=>'lgoo x-form-text x-form-field', 'style'=>'font-size:11px;padding: 0 0 0 2px;', 
									'onchange'=>"displaylimiterGroupOperRow($(this));"), $limiter_group_operator_options, $attr['limiter_group_operator'])
							) .
							// "Filter X"
							RCView::span(array('style'=>'margin-left:10px;'), 
								$lang['report_builder_31'] . " " . 
								RCView::span(array('class'=>'limiter_num'), $limiter_counter++)
							)
						) .
						RCView::td(array('class'=>'labelrc', 'valign'=>'top'),
							// Text box auto suggest
							RCView::div(array('class'=>'field-auto-suggest-div nowrap', 'style'=>($attr['field_name'] != '' ? 'display:none;' : '')),
								self::outputFieldAutoSuggest() .
								RCView::button(array('title'=>$lang['report_builder_32'], 'class'=>'jqbuttonsm limiter-dropdown-a', 'onclick'=>"showLimiterFieldAutoSuggest($(this),true);return false;", 'style'=>'font-size:11px;'), 
									RCView::img(array('src'=>'dropdown.png', 'style'=>'vertical-align:middle;'))
								)
							) . 
							// Drop-down list
							RCView::div(array('class'=>'limiter-dropdown-div nowrap', 'style'=>($attr['field_name'] == '' ? 'display:none;' : '')),
								self::outputLimiterDropdown($rc_field_dropdown_options, $attr['field_name']) .
								RCView::button(array('title'=>$lang['report_builder_30'], 'class'=>'jqbuttonsm field-auto-suggest-a', 'onclick'=>"showLimiterFieldAutoSuggest($(this),false);return false;", 'style'=>'font-size:11px;'), 
									RCView::img(array('src'=>'form-text-box.gif', 'style'=>'vertical-align:middle;'))
								)
							) /*.
							// Event drop-down
							(!$Proj->longitudinal ? '' : 
								RCView::div(array('style'=>'margin-top:4px;'),
									RCView::span(array('style'=>'font-weight:normal;margin:0 8px 0 3px;color:#444;'), $lang['global_107'] ) .
									self::outputEventDropdown($event_dropdown_options_with_all, $attr['limiter_event_id'])
								)
							)*/
						) .
						RCView::td(array('class'=>'labelrc nowrap', 'valign'=>'top'),
							// Operator drop-down list (>, <, =, etc.)
							self::outputLimiterOperatorDropdown($attr['field_name'], $attr['limiter_operator'], $allValTypes) .
							// Value text box OR drop-down list (if multiple choice)
							self::outputLimiterValueTextboxOrDropdown($attr['field_name'], $attr['limiter_value'])
						) .
						// Delete
						RCView::td(array('class'=>'labelrc', 'style'=>'text-align:center;width:25px;'),
							RCView::a(array('href'=>'javascript:;', 'onclick'=>"deleteLimiterField($(this));", 'style'=>($attr['field_name'] == '' ? 'display:none;' : '')),
								RCView::img(array('src'=>'cross.png', 'class'=>'imgfix opacity75', 'title'=>$lang['design_170']))
							)
						)
					);
			$limiter_field_num++;
		} 
		## ADVANCED LOGIC TEXTBOX
		print   RCView::tr(array('id'=>'adv_logic_row_link', 'style'=>($report['advanced_logic'] != '' ? 'display:none;' : '')),
					RCView::td(array('colspan'=>'4', 'class'=>'labelrc', 'style'=>'padding:10px;color:#444;font-weight:normal;'),
						RCView::img(array('src'=>'arrow_circle_double_gray.gif', 'class'=>'imgfix')) . 
						$lang['report_builder_92'] . RCView::SP . RCView::SP .
						RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;font-weight:normal;', 'onclick'=>"showAdvancedLogicRow(true,false)"), 
							$lang['report_builder_90']
						)
					)
				);
		print   RCView::tr(array('id'=>'adv_logic_row', 'style'=>($report['advanced_logic'] == '' ? 'display:none;' : '')),
					// Label
					RCView::td(array('colspan'=>'4', 'class'=>'labelrc', 'style'=>'padding:10px;'),
						// AND/OR limiter operator dropdown
						RCView::div(array('style'=>'margin:0 0 4px;'), 
							RCView::div(array('style'=>'float:left;'), 
								$lang['report_builder_93']
							) . 
							RCView::div(array('style'=>'margin:0 30px;float:right;'), 
								RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;font-size:11px;font-weight:normal;', 'onclick'=>"helpPopup('logic_functions');"),
									$lang['dataqueries_79']
								)
							) .
							RCView::div(array('style'=>'float:right;font-size:11px;color:#666;font-weight:normal;'), 
								'(e.g., [age] > 30 and [gender] = "1")'
							) .				 
							RCView::div(array('class'=>'clear'), '')
						) .
						// Logic textbox
						RCView::textarea(array('name'=>'advanced_logic', 'class'=>'x-form-field notesbox', 'style'=>'width:95%;height:46px;',
							'onblur'=>"check_advanced_logic();"), 
							$report['advanced_logic']
						)
					)
				);
		print   RCView::tr(array('id'=>'adv_logic_row_link2', 'style'=>($report['advanced_logic'] == '' ? 'display:none;' : '')),
					RCView::td(array('colspan'=>'4', 'class'=>'labelrc', 'style'=>'padding:10px;color:#444;font-weight:normal;'),
						RCView::img(array('src'=>'arrow_circle_double_gray.gif', 'class'=>'imgfix')) . 
						$lang['report_builder_92'] . RCView::SP . RCView::SP .
						RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;font-weight:normal;', 'onclick'=>"showAdvancedLogicRow(false)"), 
							$lang['report_builder_91']
						)
					)
				);
		
		## ADDITIONAL FILTERS (only if has events and/or DAGs)
		if (/*$Proj->longitudinal ||*/ !empty($dag_dropdown_options))
		{
			print   RCView::tr(array(),
						RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom', 
							'style'=>'background:#fff;border-left:0;border-right:0;height:5px;'), '')
					);
			// "Additional filters" section header
			print   RCView::tr(array(),
						RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom'),
							RCView::div(array('style'=>'float:left;'),
								RCView::img(array('src'=>'filter.gif', 'class'=>'imgfix')) .
								$lang['report_builder_36'] . " " .
								RCView::span(array('style'=>'font-weight:normal;'), $lang['global_06'])
							) .
							RCView::div(array('style'=>'float:right;margin:0 20px 0 0;font-size:11px;color:#555;font-weight:normal;'),
								$lang['report_builder_106']
							) .
							RCView::div(array('class'=>'clear'), '')
						)
					);
			print   RCView::tr(array(),
						RCView::td(array('class'=>'labelrc', 'colspan'=>4, 'valign'=>'top', 'style'=>''),
/*							// FILTER EVENTS
							RCView::div(array('style'=>(!$Proj->longitudinal ? 'display:none;' : '').'float:left;margin-bottom:5px;'),
								RCView::span(array('style'=>'margin:0 10px 0 20px;vertical-align:top;position:relative;top:4px;float:left;width:110px;'),
									$lang['report_builder_38']
								) .
								RCView::select(array('multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:80px;', 
								'name'=>'filter_events'), $event_dropdown_options, $report['limiter_events'], 200)
							) .*/
							// FILTER DAGS
							RCView::div(array('style'=>(empty($dag_dropdown_options) ? 'display:none;' : '').'float:left;margin-bottom:5px;'),
								RCView::span(array('style'=>'margin:0 10px 0 '.($Proj->longitudinal ? '50px;' : '20px;').'vertical-align:top;position:relative;top:4px;float:left;width:110px;'),
									$lang['report_builder_39']
								) .
								RCView::select(array('multiple'=>'', 'class'=>'x-form-text x-form-field', 'style'=>'font-size:11px;padding-right:15px;height:80px;', 
								'name'=>'filter_dags'), $dag_dropdown_options, $report['filter_dags'], 200)
							) .
							RCView::div(array('style'=>'clear:both;padding:10px 0 0 3px;font-size:11px;font-weight:normal;color:#888;'),
								$lang['report_builder_40']
							)
						)
					);
		}
		
		## SORTING FIELDS USED IN REPORT
		// "Sorting" section header
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom', 
						'style'=>'padding:0;background:#fff;border-left:0;border-right:0;height:45px;'), 
						RCView::div(array('style'=>'color:#444;position:relative;top:10px;background-color:#ddd;border:1px solid #ccc;border-bottom:1px solid #ddd;float:left;padding:5px 8px;'),
							$lang['global_117']." 4"
						)
					)
				);
		print   RCView::tr(array(),
					RCView::td(array('class'=>'labelrc create_rprt_hdr', 'colspan'=>4, 'valign'=>'bottom'),
						RCView::img(array('src'=>'sort_ascend.png', 'class'=>'imgfix')) .
						$lang['report_builder_20'] . " " .
						RCView::span(array('style'=>'font-weight:normal;'), $lang['global_06'])
					)
				);
		// SORT FIELD 1
		print   RCView::tr(array('class'=>'sort_row'),
					RCView::td(array('class'=>'labelrc', 'style'=>'width:120px;'),
						$lang['report_builder_25']
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top'),
						RCView::div(array('class'=>'field-auto-suggest-div nowrap', 'style'=>($report['orderby_field1'] != '' ? 'display:none;' : '')),
							self::outputFieldAutoSuggest() .
							RCView::button(array('title'=>$lang['report_builder_32'], 'class'=>'jqbuttonsm sort-dropdown-a', 'onclick'=>"showSortFieldAutoSuggest($(this),true);return false;", 'style'=>'font-size:11px;'), 
								RCView::img(array('src'=>'dropdown.png', 'style'=>'vertical-align:middle;'))
							)
						) . 
						RCView::div(array('class'=>'sort-dropdown-div nowrap', 'style'=>($report['orderby_field1'] == '' ? 'display:none;' : '')),
							self::outputSortingDropdown($rc_field_dropdown_options_orderby, $report['orderby_field1']) .
							RCView::button(array('title'=>$lang['report_builder_30'], 'class'=>'jqbuttonsm field-auto-suggest-a', 'onclick'=>"showSortFieldAutoSuggest($(this),false);return false;", 'style'=>'font-size:11px;'), 
								RCView::img(array('src'=>'form-text-box.gif', 'style'=>'vertical-align:middle;'))
							)
						)
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top', 'colspan'=>2),
						self::outputSortAscDescDropdown($report['orderby_sort1'])
					)
				);	
		// SORT FIELD 2
		print   RCView::tr(array('class'=>'sort_row'),
					RCView::td(array('class'=>'labelrc', 'style'=>'width:120px;'),
						$lang['report_builder_26']
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top'),
						RCView::div(array('class'=>'field-auto-suggest-div nowrap', 'style'=>($report['orderby_field2'] != '' ? 'display:none;' : '')),
							self::outputFieldAutoSuggest() .
							RCView::button(array('title'=>$lang['report_builder_32'], 'class'=>'jqbuttonsm sort-dropdown-a', 'onclick'=>"showSortFieldAutoSuggest($(this),true);return false;", 'style'=>'font-size:11px;'), 
								RCView::img(array('src'=>'dropdown.png', 'style'=>'vertical-align:middle;'))
							)
						) . 
						RCView::div(array('class'=>'sort-dropdown-div nowrap', 'style'=>($report['orderby_field2'] == '' ? 'display:none;' : '')),
							self::outputSortingDropdown($rc_field_dropdown_options_orderby, $report['orderby_field2']) .
							RCView::button(array('title'=>$lang['report_builder_30'], 'class'=>'jqbuttonsm field-auto-suggest-a', 'onclick'=>"showSortFieldAutoSuggest($(this),false);return false;", 'style'=>'font-size:11px;'), 
								RCView::img(array('src'=>'form-text-box.gif', 'style'=>'vertical-align:middle;'))
							)
						)
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top', 'colspan'=>2),
						self::outputSortAscDescDropdown($report['orderby_sort2'])
					)
				);		
		// SORT FIELD 3
		print   RCView::tr(array('class'=>'sort_row'),
					RCView::td(array('class'=>'labelrc', 'style'=>'width:120px;'),
						$lang['report_builder_26']
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top'),
						RCView::div(array('class'=>'field-auto-suggest-div nowrap', 'style'=>($report['orderby_field3'] != '' ? 'display:none;' : '')),
							self::outputFieldAutoSuggest() .
							RCView::button(array('title'=>$lang['report_builder_32'], 'class'=>'jqbuttonsm sort-dropdown-a', 'onclick'=>"showSortFieldAutoSuggest($(this),true);return false;", 'style'=>'font-size:11px;'), 
								RCView::img(array('src'=>'dropdown.png', 'style'=>'vertical-align:middle;'))
							)
						) . 
						RCView::div(array('class'=>'sort-dropdown-div nowrap', 'style'=>($report['orderby_field3'] == '' ? 'display:none;' : '')),
							self::outputSortingDropdown($rc_field_dropdown_options_orderby, $report['orderby_field3']) .
							RCView::button(array('title'=>$lang['report_builder_30'], 'class'=>'jqbuttonsm field-auto-suggest-a', 'onclick'=>"showSortFieldAutoSuggest($(this),false);return false;", 'style'=>'font-size:11px;'), 
								RCView::img(array('src'=>'form-text-box.gif', 'style'=>'vertical-align:middle;'))
							)
						)
					) .
					RCView::td(array('class'=>'labelrc', 'valign'=>'top', 'colspan'=>2),
						self::outputSortAscDescDropdown($report['orderby_sort3'])
					)
				);			
				
		// Set table html
		print     "</table>
					</form>" .
					RCView::div(array('style'=>'text-align:center;margin:30px 0 50px;'),
						RCView::button(array('class'=>'jqbuttonmed', 'style'=>'font-weight:bold;font-size:14px;', 'onclick'=>"saveReport($report_id);"),
							$lang['report_builder_27']
						) .
						RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;margin-left:20px;font-size:13px;', 'onclick'=>'history.go(-1)'),
							$lang['global_53']
						)
					) .
				"</div>";
	}
	
	
	// Output the limiter value text box OR drop-down list (if multiple choice)
	public static function outputLimiterValueTextboxOrDropdown($field, $limiter_value="")
	{
		global $Proj;
                        
                $field = self::getFieldFromEventField($field);
		
                // For last field ("add new limiter"), disable the element
		$disabled = ($field == "") ? "disabled" : "";
		if ($field != '' && ($Proj->isMultipleChoice($field) || $Proj->metadata[$field]['element_type'] == 'sql')) {
			// Build enum options
			$enum = $Proj->metadata[$field]['element_enum'];
			$options = ($Proj->metadata[$field]['element_type'] == 'sql') ? parseEnum(getSqlFieldEnum($enum)) : parseEnum($enum);
			// Make sure it has a blank option at the beginning (EXCEPT checkboxes)
			if ($Proj->metadata[$field]['element_type'] != 'checkbox') {
				$options = array(''=>'') + $options;
			}
			// Multiple choice drop-down
			return RCView::select(array('name'=>'limiter_value[]', $disabled=>$disabled, 'class'=>'x-form-text x-form-field limiter-value', 'style'=>'max-width:150px;padding-right:0;height:22px;'), $options, $limiter_value, 200);
		} 
		// Text field
		else {
			// If field has validation, then add its validation as onblur
			$val_type = ($Proj->metadata[$field]['element_type'] == 'text') ? $Proj->metadata[$field]['element_validation_type'] : '';
			$onblur = "";
			if ($val_type != '') {
				// Convert legacy validation types
				if ($val_type == 'int') $val_type = 'integer';
				elseif ($val_type == 'float') $val_type = 'number';
				// Add onblur
				$onblur = "redcap_validate(this,'{$Proj->metadata[$field]['element_validation_min']}','{$Proj->metadata[$field]['element_validation_max']}','hard','$val_type',1)";
			}
			// If an MDY or DMY date/time field, then convert value
			if ($limiter_value != '') {
				if (substr($val_type, 0, 4) == 'date' && (substr($val_type, -4) == '_mdy' || substr($val_type, -4) == '_dmy')) {
					// Convert to MDY or DMY format
					$limiter_value = DateTimeRC::datetimeConvert($limiter_value, 'ymd', substr($val_type, -3));
				}
			}
			// Adjust text box size for date/time fields
			if (strpos($val_type, 'datetime_seconds') === 0) {
				$style = 'width:120px;';
			} elseif (strpos($val_type, 'datetime') === 0) {
				$style = 'width:103px;';
			} elseif (strpos($val_type, 'date') === 0) {
				$style = 'width:70px;';
			} else {
				$style = 'width:150px;';
			}
			// Build date/time format text for date/time fields
			$dformat = MetaData::getDateFormatDisplay($val_type);
			$dformat_span = ($dformat == '') ? '' : RCView::span(array('class'=>'df', 'style'=>'padding-left:4px;'), $dformat);
			// Return text field
			return 	RCView::text(array('name'=>'limiter_value[]', $disabled=>$disabled, 'onblur'=>$onblur, 'class'=>$val_type.' x-form-text x-form-field limiter-value', 
									  'maxlength'=>255, 'style'=>$style, 'value'=>htmlspecialchars($limiter_value, ENT_QUOTES))) .
					$dformat_span;
		}
	}
	
	
	// Output html of field drop-down displaying all project fields
	public static function outputFieldDropdown($options=array(), $selectedField="")
	{
		// Output the html
		return RCView::select(array('class'=>'x-form-text x-form-field field-dropdown', 'style'=>'width:260px;max-width:260px;padding-right:0;height:22px;', 
					'name'=>'field[]', 'onchange'=>($selectedField == "" ? "rprtft='dropdown';addNewReportRow($(this));" : "")), $options, $selectedField, 200);
	}
	
	
	// Output html of event drop-down displaying all project fields
	public static function outputEventDropdown($options=array(), $selectedField="")
	{
		// Output the html
		return RCView::select(array('class'=>'x-form-text x-form-field event-dropdown', 'style'=>'width:240px;max-width:240px;padding-right:0;height:22px;', 
					'name'=>'limiter_event[]'), $options, $selectedField, 200);
	}
	
	
	// Output html of limiter drop-down displaying all project fields
	public static function outputLimiterDropdown($options=array(), $selectedField="")
	{
		// Output the html
		return RCView::select(array('class'=>'x-form-text x-form-field limiter-dropdown', 'style'=>'width:260px;max-width:260px;padding-right:0;height:22px;', 
					'name'=>'limiter[]', 'onchange'=>($selectedField == "" ? "rprtft='dropdown';addNewLimiterRow($(this));" : "")."fetchLimiterOperVal($(this));"), 
					$options, $selectedField, 200);
	}
	
	
	// Output html of sorting drop-down displaying all project fields
	public static function outputSortingDropdown($options=array(), $selectedField="")
	{
		// Output the html
		return RCView::select(array('class'=>'x-form-text x-form-field sort-dropdown', 'style'=>'width:260px;max-width:260px;padding-right:0;height:22px;', 
					'name'=>'sort[]'), $options, $selectedField, 200);
	}
	
	
	// Output array of ALL possible limiter operators
	public static function getLimiterOperators()
	{
		global $lang;
		// List of ALL possible options
		return array('E'=>'=', 'NE'=>'not =', 'LT'=>'< ', 'LTE'=>'< =', 'GT'=>'>', 'GTE'=>'> =',
					 'CONTAINS'=>$lang['report_builder_34'], 'NOT_CONTAIN'=>$lang['report_builder_88'], 'STARTS_WITH'=>$lang['report_builder_79'], 
					 'ENDS_WITH'=>$lang['report_builder_86'], 'CHECKED'=>$lang['report_builder_64'], 'UNCHECKED'=>$lang['report_builder_65']);
	}
	
	
	// Output html of limiter operator drop-down displaying all valid operators
	public static function outputLimiterOperatorDropdown($field, $selectedField="", $allValTypes)
	{
		global $lang, $Proj;
                
                $field = self::getFieldFromEventField($field);
                
		// Set options based upon field type
		$field_type = $Proj->metadata[$field]['element_type'];
		$val_type = $Proj->metadata[$field]['element_validation_type'];
		if ($val_type == 'int') $val_type = 'integer';
		elseif ($val_type == 'float') $val_type = 'number';
		$data_type = $allValTypes[$val_type]['data_type'];
		if ($Proj->isCheckbox($field)) {
			// Checkbox
			$options_this_field = array('CHECKED', 'UNCHECKED');
		} elseif ($Proj->isMultipleChoice($field) || $field_type == 'sql') {
			// MC fields (excluding checkboxes)
			$options_this_field = array('E', 'NE');
		} elseif ( in_array($field_type, array('slider', 'calc')) 
				|| in_array($data_type, array('integer', 'number', 'date', 'datetime', 'datetime_seconds'))) {
			// Date/times and numbers/integers (including sliders, calcs)
			$options_this_field = array('E', 'NE', 'LT', 'LTE', 'GT', 'GTE');
		} else {
			// Free-form text
			$options_this_field = array('E', 'NE', 'CONTAINS', 'NOT_CONTAIN', 'STARTS_WITH', 'ENDS_WITH');		
		}
		// List of ALL possible options
		$all_options = self::getLimiterOperators();
		// Loop through all options to build field-specific drop-down list
		$options = array();
		foreach ($all_options as $key=>$val) {
			if (in_array($key, $options_this_field)) $options[$key] = $val;
		}
		// For last field ("add new limiter"), disable the element
		$disabled = ($field == "") ? "disabled" : "";
		// Output the html
		return RCView::select(array('class'=>'x-form-text x-form-field limiter-operator', $disabled=>$disabled, 
					'name'=>'limiter_operator[]'), $options, $selectedField, 200);
	}
	
	
	// Output html of sorting drop-down displaying option as ascending or descending
	public static function outputSortAscDescDropdown($selectedField="ASC")
	{
		global $lang;
		// Set options
		$options = array('ASC'=>$lang['report_builder_22'], 'DESC'=>$lang['report_builder_23']);
		// Output the html
		return RCView::select(array('class'=>'x-form-text x-form-field sort-ascdesc', 'style'=>'padding-right:0;height:22px;', 
					'name'=>'sortascdesc[]', 'onchange'=>""), $options, $selectedField, 200);
	}
	
	
	// Output html of text field with auto-suggest feature
	public static function outputFieldAutoSuggest()
	{
		global $lang;
		// Output the html
		return RCView::text(array('class'=>'x-form-text x-form-field field-auto-suggest', 'style'=>'width:252px;color:#bbb;',
					'onfocus'=>'asfocus(this)', 'onblur'=>'asblur(this)', 'value'=>$lang['report_builder_30']));
	}
	
	
	// Get auto suggest JavaScript string for all project fields
	public static function getAutoSuggestJsString()
	{
		global $Proj;
/*		// Build an array of listing all REDCap fields' variable name + field label
		$rc_fields = array();
		foreach ($Proj->metadata as $this_field=>$attr1) {
			// Skip descriptive fields
			if ($attr1['element_type'] == 'descriptive') continue;
			// Add to fields array
			$rc_fields[] = "'$this_field \"" . cleanHtml(strip_tags($attr1['element_label'])) . "\"'";
		}
		return "[ " . implode(", ", $rc_fields) . " ]";
*/                
                // Get array of events - forms - fields
                $eventFieldList = self::longitudinalEventsFormsFields();

                $rc_fields = array();
                $rc_fields[] = "'[".$Proj->table_pk."] ".self::cleanString($Proj->table_pk_label)."'";
                
/*		foreach ($eventFormFields as $event=>$eventAttrs) {
                    foreach ($eventAttrs['forms'] as $form=>$formAttrs) {
                        foreach ($formAttrs['fields'] as $field=>$fieldAttrs) {
                            $rc_fields[] = "'\[".$eventAttrs['unique_event_name']."\]\[".$fieldAttrs['field_name']."\] \"".$fieldAttrs['element_label']."\" (".$formAttrs['title'].")'";
                        }
                    }
                }*/
                foreach ($eventFieldList as $fieldRef => $fieldAttrs) {
                    
                    // Skip if pk field (added separately)
                    if ($fieldAttrs['field_name'] == $Proj->table_pk) continue;

                    $label = self::cleanString($fieldAttrs['field_label']);
                    
                    if (strlen($label) > 65) {
                        $label = str_replace("...'", "...", trim(substr($label, 0, 47)) . "..." . trim(substr($label, -15)));
                    }

                    $rc_fields[] = "'$fieldRef $label ({$fieldAttrs['form_title']}'";
                    unset($eventFieldList[$fieldRef]);
                }
                
		return "[ " . implode(", ", $rc_fields) . " ]";
	}
	
	
	// Checks for errors in the report order of all reports (in case their numbering gets off)
	public static function checkReportOrder()
	{
/*		// Do a quick compare of the field_order by using Arithmetic Series (not 100% reliable, but highly reliable and quick)
		// and make sure it begins with 1 and ends with field order equal to the total field count.
		$sql = "select sum(report_order) as actual, round(count(1)*(count(1)+1)/2) as ideal, 
				min(report_order) as min, max(report_order) as max, count(1) as report_count 
				from redcap_reports where project_id = " . PROJECT_ID;
		$q = db_query($sql);
		$row = db_fetch_assoc($q);
		db_free_result($q);
		if ( ($row['actual'] != $row['ideal']) || ($row['min'] != '1') || ($row['max'] != $row['report_count']) )
		{*/
			return self::fixReportOrder();
//		}
	}
	
	
	// Fixes the report order of all reports (if somehow their numbering gets off)
	public static function fixReportOrder()
	{
                global $Proj;
                
		// Counters
		$counter = 1;
                $importRows = array();
                
/*		$errors = 0;
		// Get list of reports to display as table
		$report_ids = array();
		$sql = "select report_id from redcap_reports where project_id = ".PROJECT_ID." 
				order by report_order, report_id";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$report_ids[] = $row['report_id'];
		}
		// Set up all actions as a transaction to ensure everything is done here
		db_query("SET AUTOCOMMIT=0");
		db_query("BEGIN");
		// Set all report_orders to null
		$sql = "update redcap_reports set report_order = null where project_id = ".PROJECT_ID;
		db_query($sql);
		// Reset field_order of all fields, beginning with "1"
		foreach ($report_ids as $report_id)
		{
			$sql = "update redcap_reports set report_order = ".$counter++." 
					where project_id = " . PROJECT_ID . " and report_id = $report_id";
			if (!db_query($sql)) $errors++;
		}
		// If errors, do not commit
		$commit = ($errors > 0) ? "ROLLBACK" : "COMMIT";
		db_query($commit);
		// Set back to initial value
		db_query("SET AUTOCOMMIT=1");
*/
                $allProjectRpts = REDCap::getData(
                   LR_REPORT_DATA_PROJECT_ID, 
                    'array',  // return_format
                    null,     // records 
                    'report_order',     // fields 
                    null,     // events 
                    null,     // groups 
	            false,    // combine_checkbox_values 
                    false,    // exportDataAccessGroups 
                    false,    // exportSurveyFields 
                    '[project_id] = '.$Proj->project_id, // filterLogic 
                    false,    // exportAsLabels 
                    false     // exportCsvHeadersAsLabels 
                );
                
                foreach ($allProjectRpts as $rId => $reportEventRecord) {
                    foreach ($reportEventRecord as $eventId => $rptSpec) {
        		// Reset report_order of all reports, beginning with "1"
                        $rpt = array();
                        $rpt['report_id'] = $rptSpec['report_id'];
                        $rpt['report_order'] = $counter;
			$counter++;
                        
                        $importRows[] = $rpt;
                    }
                }

                $success = self::save($importRows);

                REDCap::logEvent("Longitudinal reports returned to default sort order");
                // Return
		return ($success);
	
	}
	
	
	// Return all reports (unless one is specified explicitly) as an array of their attributes
	public static function getReports(	$report_id=null)//, 
										// The parameters below are ONLY used for $report_id == 'SELECTED'
//										$selectedInstruments=array(), $selectedEvents=array())
	{
            global $Proj, $lang, $double_data_entry, $user_rights;
		
            // Get REDCap validation types
            $valTypes = getValTypes();
		
            // Array to place report attributes
            $reports = array();
            // If report_id is 0 (report doesn't exist), then return field defaults from tables
            if ($report_id === 0 ) { // || $report_id == 'ALL' || $report_id == 'SELECTED') {
                // Add to reports array
                $reports[$report_id] = getTableColumns('redcap_reports');
                // Pre-fill empty slots for limiters and fields
                $reports[$report_id]['fields'] = array();
                $reports[$report_id]['limiter_fields'] = array();
                $reports[$report_id]['filter_dags'] = array();
                //$reports[$report_id]['limiter_events'] = array();
                $reports[$report_id]['limiter_logic'] = "";
                $reports[$report_id]['user_access_users'] = array();
                $reports[$report_id]['user_access_roles'] = array();
                $reports[$report_id]['user_access_dags'] = array();
                $reports[$report_id]['output_dags'] = 0;
                $reports[$report_id]['output_survey_fields'] = 0;
                $reports[$report_id]['output_schedule_dates'] = array();
                $reports[$report_id]['output_survey_urls'] = array();

                // For "new" (to-be created) reports, set Record ID field as first field and first sorting field in report
                $reports[$report_id]['fields'] = array($Proj->table_pk);
                $reports[$report_id]['orderby_field1'] = $Proj->table_pk;
                $reports[$report_id]['orderby_sort1'] = 'ASC';

                // DDE: If user is DDE person 1 or 2, then limit to ONLY their records
                if ($double_data_entry && is_array($user_rights) && $user_rights['double_data'] != 0) {
                        if ($reports[$report_id]['limiter_logic'] == '') {
                                $reports[$report_id]['limiter_logic'] = "ends_with([{$Proj->table_pk}], \"--{$user_rights['double_data']}\")";
                        } else {
                                $reports[$report_id]['limiter_logic'] = "({$reports[$report_id]['limiter_logic']}) and ends_with([{$Proj->table_pk}], \"--{$user_rights['double_data']}\")";
                        }
                }
                // Return array
                return $reports[$report_id];
            }
            
            $lrProjectData = array();
            $filterExpr = '[project_id] = '.$Proj->project_id;
            
            if (is_numeric($report_id)) {
                $filterExpr .= " and [report_id] = $report_id";
            }
            
            $lrProjectData = REDCap::getData(
                    LR_REPORT_DATA_PROJECT_ID, 
                    'array',  // return_format
                    null,     // records 
                    null,     // fields 
                    null,     // events 
                    null,     // groups 
	            false,    // combine_checkbox_values 
                    false,    // exportDataAccessGroups 
                    false,    // exportSurveyFields 
                    $filterExpr, // filterLogic 
                    false,    // exportAsLabels 
                    false     // exportCsvHeadersAsLabels 
                );
            
            // If no reports, then return empty array
            if (empty($lrProjectData)) return array();

            foreach ($lrProjectData as $eventId => $reportRecords) {
                foreach ($reportRecords as $rptRec) {
                    $rId = $rptRec['report_id'];
                    $reports[$rId]['report_id'] = $rId;
                    $reports[$rId]['project_id'] = $rptRec['project_id'];
                    $reports[$rId]['title'] = $rptRec['title'];
                    $reports[$rId]['report_order'] = $rptRec['report_order'];
                    $reports[$rId]['user_access'] = $rptRec['user_access'];
                    $reports[$rId]['user_access_dags'] = json_decode($rptRec['user_access_dags'], true);
                    $reports[$rId]['user_access_roles'] = json_decode($rptRec['user_access_roles'], true);
                    $reports[$rId]['user_access_users'] = json_decode($rptRec['user_access_users'], true);
                    $reports[$rId]['fields'] = json_decode($rptRec['fields'], true);
                    $reports[$rId]['output_dags'] = ($rptRec['output_dags'] === "on") ? "1" : "0";
                    $reports[$rId]['output_survey_fields'] = ($rptRec['output_survey_fields'] === "on") ? "1" : "0";
                    $reports[$rId]['output_schedule_dates'] = json_decode($rptRec['output_schedule_dates'], true);
                    $reports[$rId]['output_survey_urls'] = json_decode($rptRec['output_survey_urls'], true);
                    $reports[$rId]['limiter_fields'] = json_decode($rptRec['limiter_fields'], true);
                    $reports[$rId]['filter_dags'] = json_decode($rptRec['filter_dags'], true);
                    $reports[$rId]['advanced_logic'] = $rptRec['advanced_logic'];
                    $reports[$rId]['orderby_field1'] = $rptRec['orderby_field1'];
                    $reports[$rId]['orderby_sort1'] = $rptRec['orderby_sort1'];
                    $reports[$rId]['orderby_field2'] = $rptRec['orderby_field2'];
                    $reports[$rId]['orderby_sort2'] = $rptRec['orderby_sort2'];
                    $reports[$rId]['orderby_field3'] = $rptRec['orderby_field3'];
                    $reports[$rId]['orderby_sort3'] = $rptRec['orderby_sort3'];
                    
                    $reports[$rId]['limiter_logic'] = ""; // Will build below
                }
            }

            // Loop through all reports and build the filter logic into a single string
            foreach ($reports as $this_report_id=>$rattr) {
                // Advanced logic
                if ($rattr['advanced_logic'] != '') {
                    $reports[$this_report_id]['limiter_logic'] = $rattr['advanced_logic'];
                } 
                // Simple logic
                elseif (!empty($rattr['limiter_fields'])) {
                    foreach ($rattr['limiter_fields'] as $i=>$attr) {
                        // Translate the limiter item into logic
                        $reports[$this_report_id]['limiter_logic'] .= ($attr['limiter_group_operator'] == 'AND' ? ($i == 0 ? "(" : ") AND (") : " OR ") . 
                                                                                                                      self::translateLimiterItem($attr);
                    }
                    // Finish with ending parenthesis
                    $reports[$this_report_id]['limiter_logic'] .= ")";
                }

                // DDE: If user is DDE person 1 or 2, then limit to ONLY their records by appending ends_with() onto limiter_logic
                if ($double_data_entry && is_array($user_rights) && $user_rights['double_data'] != 0) {
                        if ($reports[$this_report_id]['limiter_logic'] == '') {
                                $reports[$this_report_id]['limiter_logic'] = "ends_with([{$Proj->table_pk}], \"--{$user_rights['double_data']}\")";
                        } else {
                                $reports[$this_report_id]['limiter_logic'] = "({$reports[$this_report_id]['limiter_logic']}) and ends_with([{$Proj->table_pk}], \"--{$user_rights['double_data']}\")";
                        }
                }

                // Double check to make sure that it truly has SELECTED user access
                if ($rattr['user_access'] == 'SELECTED' && empty($rattr['user_access_users']) && empty($rattr['user_access_roles']) && empty($rattr['user_access_dags'])) {
                        $reports[$this_report_id]['user_access'] = 'ALL';
                }

                // Make sure that Order By fields are NOT checkboxes (because that doesn't make sense)
                if ($Proj->isCheckbox($reports[$this_report_id]['orderby_field3'])) {
                        $reports[$this_report_id]['orderby_field3'] = $reports[$this_report_id]['orderby_sort3'] = '';
                }
                if ($Proj->isCheckbox($reports[$this_report_id]['orderby_field2'])) {
                        $reports[$this_report_id]['orderby_field2'] = $reports[$this_report_id]['orderby_field3'];
                        $reports[$this_report_id]['orderby_sort2'] = $reports[$this_report_id]['orderby_sort3'];
                        $reports[$this_report_id]['orderby_field3'] = $reports[$this_report_id]['orderby_sort3'] = '';
                }
                if ($Proj->isCheckbox($reports[$this_report_id]['orderby_field1'])) {
                        $reports[$this_report_id]['orderby_field1'] = $reports[$this_report_id]['orderby_field2'];
                        $reports[$this_report_id]['orderby_sort1'] = $reports[$this_report_id]['orderby_sort2'];
                        $reports[$this_report_id]['orderby_field2'] = $reports[$this_report_id]['orderby_field3'];
                        $reports[$this_report_id]['orderby_sort2'] = $reports[$this_report_id]['orderby_sort3'];
                        $reports[$this_report_id]['orderby_field3'] = $reports[$this_report_id]['orderby_sort3'] = '';
                }
            }
            // Return array of report(s) attributes
            if ($report_id == null) {
                return $reports;
            } else {
                return $reports[$report_id];
            }
	}
	
	
	// Translate a single limiter item's attributes into its appropriate logic 
	public static function translateLimiterItem($attr) 
	{
//		global $Proj;
		// If longitudinal, then get unique event name to prepend to field in logic
//		$event_name = ($Proj->longitudinal && is_numeric($attr['limiter_event_id'])) ? "[".$Proj->getUniqueEventNames($attr['limiter_event_id'])."]" : "";

                $event_name = self::getEventFromEventField($attr['field_name']);
                $field_name = self::getFieldFromEventField($attr['field_name']);

                if ($event_name !== '') { 
                    //i.e. not filtering on record id with no specified event
                    $event_part = "[$event_name]" ; 
                }

                $item = "";

		if (in_array($attr['limiter_operator'], array('CONTAINS', 'NOT_CONTAIN', 'STARTS_WITH', 'ENDS_WITH'))) {
			$item = strtolower($attr['limiter_operator'])."({$event_part}[{$field_name}], \"" . str_replace('"', "\\\"", $attr['limiter_value']) . "\")";
		}
		// If is "checked" or "unchecked"
		elseif ($attr['limiter_operator'] == 'CHECKED' || $attr['limiter_operator'] == 'UNCHECKED') {
			$checkVal = ($attr['limiter_operator'] == 'CHECKED') ? "1" : "0";
			$item = "{$event_part}[{$field_name}({$attr['limiter_value']})] = \"$checkVal\"";
		}
		// All mathematical operators
		else {
			// If value is numerical and using >, >=, <, or <=, then don't surround in double quotes
			$quotes = (is_numeric($attr['limiter_value']) && $attr['limiter_operator'] != 'E' && $attr['limiter_operator'] != 'NE') ? '' : '"';
			$item = "{$event_part}[{$field_name}] " . self::translateLimiterOperator($attr['limiter_operator']) . 
						" $quotes" . str_replace('"', "\\\"", $attr['limiter_value']) . $quotes;
		}	
                return $item;
	}
	
	
	// Translate backend limiter operator (LT, GTE, E) into mathematical operator (<, >=, =)
	public static function translateLimiterOperator($backend_value) 
	{
		$all_options = array('E'=>'=', 'NE'=>'!=', 'LT'=>'<', 'LTE'=>'<=', 'GT'=>'>', 'GTE'=>'>=');
		return (isset($all_options[$backend_value]) ? $all_options[$backend_value] : 'E');
	}
	
	
	// Delete a report
	public static function deleteReport($report_id)
	{
		global $userid;
                // Can't delete a record via API/saveData so assign it to the Longitudinal Report Config project instead
            
                //Get all reports, change the project id of the one deleted and fix the order for the rest
                $reports = self::getReports();
                
                if (empty($reports)) { return false; }
                
                $i = 1;
                $import = array();
                
                foreach ($reports as $thisRpt) {
                    $rpt = array();
                    $rpt['report_id'] = $thisRpt['report_id'];

                    if ($thisRpt['report_id'] == $report_id) {
                        $rpt['project_id'] = LR_REPORT_DATA_PROJECT_ID;

                        $now = new DateTime();
                        $rpt['update_by'] = $userid;
                        $rpt['update_at'] = $now->format('Y-m-d H:i:s');
                    } else {
                        $rpt['report_order'] = $i++;
                    }

                    $import[] = $rpt;
                }
                
                $success = self::save($import);
                return $success;
	}
	
	
	// Copy the report and return the new report_id
	public static function copyReport($report_id)
	{
		global $userid;

                $success = false;
                $report = self::getReports($report_id);
                
                if (!empty($report)) {
                        // Adding new report - get eventid, next report id and next project report_order
                        list($eventId, $new_report_id, $new_report_order) = self::getNewReportIdAndOrder($_GET['pid']);
                    
                        // Give report copy the new id and order
                        $report['report_id'] = $new_report_id;
                        $report['report_order'] = $new_report_order;

                        unset($report['limiter_logic']); // which is derived in getReports from what is stored (limiter_fields)
                        $report['user_access_dags'] = json_encode($report['user_access_dags']);
                        $report['user_access_roles'] = json_encode($report['user_access_roles']);
                        $report['user_access_users'] = json_encode($report['user_access_users']);
                        $report['fields'] = json_encode($report['fields']);
                        $report['output_schedule_dates'] = json_encode($report['output_schedule_dates']);
                        $report['output_survey_urls'] = json_encode($report['output_survey_urls']);
                        $report['limiter_fields'] = json_encode($report['limiter_fields']);
                        $report['filter_dags'] = json_encode($report['filter_dags']);

                        $now = new DateTime();
                        $report['update_by'] = $userid;
                        $report['update_at'] = $now->format('Y-m-d H:i:s');
                        $report['report_complete'] = '2';
                        
                        $report['title'] = $report['title'] . " - Copy " . $now->format('Y-m-d H:i:s');

                        $import[] = $report;
                        
                        $success = self::save($import);
                }

		// Return report_id of new report, else FALSE if errors occurred
		return ($success) ? $new_report_id : false;
	}
	
	
	// Get report names. Returns array with report_id as key and title as value
	public static function getReportNames($report_id=null, $applyUserAccess=false, $fixOrdering=true)
	{
		global $lang, $Proj, $user_rights, $userid;
		
                $filterExpr = '[project_id] = '.$Proj->project_id;
            
                if (is_numeric($report_id)) {
                    $filterExpr .= " and [report_id] = $report_id";
                    $fixOrdering = false;
                }

                $allProjectRpts = REDCap::getData(
                   LR_REPORT_DATA_PROJECT_ID, 
                    'array',  // return_format
                    null,     // records 
                    array('report_order','title','user_access','user_access_dags','user_access_roles','user_access_users'),     // fields 
                    null,     // events 
                    null,     // groups 
	            false,    // combine_checkbox_values 
                    false,    // exportDataAccessGroups 
                    false,    // exportSurveyFields 
                    $filterExpr, // filterLogic 
                    false,    // exportAsLabels 
                    false     // exportCsvHeadersAsLabels 
                );
                
		// Add reports to array
		$reports = array();
                $reportsInDefaultOrder = array();
                $orderToId = array();
		$reportsOutOfOrder = false;
		foreach ($allProjectRpts as $rId => $reportEventRecord) {
                    foreach ($reportEventRecord as $eventId => $rptSpec) {
			// Add to array if non-blank
                        if ($rptSpec['title'] !== '' && $rptSpec['report_order'] !== '') {
                            $reportsInDefaultOrder[$rId] = $rptSpec['title'];
                            $orderToId[intval($rptSpec['report_order'])] = $rId;
                        }
                    }
                }

                // Sort and check report order
                if (count($orderToId) > 0) {
                    ksort($orderToId);
                    $counter = 1;
                    foreach ($orderToId as $order => $rId) {
                        $reports[$rId] = $reportsInDefaultOrder[$rId];
                        if ($counter++ != $order && !$reportsOutOfOrder) {
                            $reportsOutOfOrder = true;
                        }
                    }

                    // If report order is off, fix it 
                    if ($fixOrdering && $reportsOutOfOrder && self::fixReportOrder()) {
                            // Since they're fixed, call this method recursively so that it outputs the fixed report order
                            return self::getReportNames($report_id, $applyUserAccess, false);
                    }

                    if ($applyUserAccess) {
                        // Remove reports if user access does not include current user
                        $dropRpts = array();
                        foreach ($allProjectRpts as $rId => $reportEventRecord) {
                            foreach ($reportEventRecord as $eventId => $rptSpec) {
                                if ($rptSpec['user_access'] === 'SELECTED') {
                                    $user_list = self::getReportAccessUsernames($rptSpec);
                                    if (!SUPER_USER && !array_key_exists($userid, $user_list)) {
                                        $dropRpts[] = $rId;
                                    }
                                }
                            }
                        }

                        if (count($dropRpts)) {
                            foreach ($dropRpts as $dropRpt) {
                                unset($reports[$dropRpt]);
                            }
                        }
                    }
                }
                
		// Return reports array
		if (is_numeric($report_id)) return $reports[$report_id];
		else return $reports;
	}
	
	
	// Get html table listing all reports
	public static function renderReportList()
	{
		global $Proj, $lang, $longitudinal, $user_rights, $enable_plotting;
		// Determine if user has API export rights
		$hasAPIrights = ($user_rights['api_export']);

                // Get list of reports to display as table (only apply user access filter if don't have Add/Edit Reports rights)
		$report_names = self::getReportNames(null, !$user_rights['reports']);
		// Add pre-defined reports
		$predefined_reports = array(); /*'ALL'=>RCView::b($lang['report_builder_80'])." ".$lang['report_builder_84'], 
									'SELECTED'=>RCView::b($lang['report_builder_81'] . ($longitudinal ? " ".$lang['report_builder_82'].RCView::br() : " ")) . $lang['report_builder_83']); */
		// Loop through each report to render as a row
		$rows = array();
		$row_num = $item_num = 0; // loop counter
		foreach (($predefined_reports+$report_names) as $report_id=>$report_name) 
		{
			// Determine if a pre-defined rule
			$isPredefined = !is_numeric($report_id);
			// First column
			$rows[$item_num][] = RCView::span(array('style'=>'display:none;'), $report_id);
			// Report order number
			$rows[$item_num][] = !$isPredefined ? ($row_num+1) : RCView::span(array('style'=>'color:#C00000;'), $report_id == 'ALL' ? 'A' : 'B');
			// Report title
			$rows[$item_num][] = RCView::div(array('class'=>'wrap', 'style'=>($isPredefined ? 'font-size:13px;padding:10px 0;' : 'font-size:12px;')),
									($isPredefined 
										? $report_name
										: RCView::escape($report_name)
									)
								);
			// View/export options
			$rows[$item_num][] =  // If the "Selected instruments/events" pre-defined rule, then give other button to open multi-select boxes
								/*($report_id != 'SELECTED' ? '' :
									RCView::span(array('style'=>'display:block;'),
										RCView::button(array('class'=>'jqbuttonmed', 'style'=>'margin:0;font-size:11px;padding:1px 4px 0px !important;', 'onclick'=>"
											$(this).parent().hide();
											$('.rprt_selected_hidden').css('display','block');
										"),
											RCView::img(array('src'=>'select.png', 'style'=>'vertical-align:middle;')) .
											RCView::span(array('style'=>'vertical-align:middle;'),
												$lang['data_export_tool_174']
											)
										)
									) .
									RCView::div(array('class'=>'rprt_selected_hidden wrap', 'style'=>'margin-top:5px;'),
										($longitudinal ? $lang['data_export_tool_175'] : $lang['data_export_tool_176'])
									) .
									RCView::div(array('class'=>'nowrap rprt_selected_hidden', 'style'=>'margin:8px 0;'),
										// Instrument drop-down
										RCView::div(array('style'=>'float:left;width:'.($longitudinal ? '140px;' : '250px;')),
											RCView::div(array('style'=>'font-weight:bold;'),
												$lang['global_110']
											) .
											$instrument_dropdown
										) .
										(!$longitudinal ? '' :
											RCView::div(array('style'=>'float:left;margin:15px 8px 0 2px;color:#888;'),
												$lang['global_87']
											) .
											// Event drop-down
											RCView::div(array('style'=>'float:left;width:140px;'),
												RCView::div(array('style'=>'font-weight:bold;'),
													$lang['global_45']
												) .
												$event_dropdown
											)
										) .
										RCView::div(array('class'=>'clear'), '')
									)
								) .*/
								RCView::span(array('class'=>'rprt_btns' . ($report_id == 'SELECTED' ? ' rprt_selected_hidden' : '')),
									// View Report
									RCView::button(array('class'=>'jqbuttonmed', 'style'=>'margin:0;font-size:11px;padding:1px 4px 0px !important;', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT.LR_PATH_FROM_WEBROOT."index.php?pid=".PROJECT_ID."&report_id=$report_id'+getSelectedInstrumentList();"),
										RCView::img(array('src'=>'layout.png', 'style'=>'vertical-align:middle;')) .
										RCView::span(array('style'=>'vertical-align:middle;'),
											$lang['report_builder_44']
										)
									) .
									// Data Export
									($user_rights['data_export_tool'] == '0' ? '' :
										RCView::button(array('class'=>'data_export_btn jqbuttonmed', 'onclick'=>"showExportFormatDialog('$report_id');", 'style'=>'margin:0 0 0 5px;font-size:11px;padding:1px 4px 0px !important;'),
											RCView::img(array('src'=>'go-down.png', 'style'=>'vertical-align:middle;')) .
											RCView::span(array('style'=>'vertical-align:middle;'),
												$lang['report_builder_48']
											)
										)
									) /*.
									// View Stats & Charts
									(!$user_rights['graphical'] || !$enable_plotting ? '' :
										RCView::button(array('class'=>'data_export_btn jqbuttonmed', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT."DataExport/index.php?pid=".PROJECT_ID."&report_id=$report_id&stats_charts=1'+getSelectedInstrumentList();", 'style'=>'margin:0 0 0 5px;font-size:11px;padding:1px 4px 0px !important;'),
											RCView::img(array('src'=>'chart_bar.png', 'style'=>'vertical-align:middle;')) .
											RCView::span(array('style'=>'vertical-align:middle;'),
												$lang['report_builder_78']
											)
										)
									)*/
								) ;/*.
								// For selected instrument pre-defined rule only, note on how to use multi-select
								($report_id != 'SELECTED' ? '' :
									RCView::div(array('class'=>'wrap rprt_selected_hidden', 'style'=>'padding:10px 0 5px 3px;line-height:11px;font-size:11px;font-weight:normal;color:#888;'),
										$lang['report_builder_40']
									)
								);*/
			// Management options (if user has add/edit reports privileges)
			if ($user_rights['reports']) {
				$rows[$item_num][] = ($isPredefined ? '' :
										//Edit
										RCView::button(array('class'=>'jqbuttonsm', 'style'=>'margin-right:2px;font-family:arial;', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT.LR_PATH_FROM_WEBROOT."index.php?pid=".PROJECT_ID."&report_id=$report_id&addedit=1';"),
											RCView::img(array('src'=>'pencil_small.png', 'style'=>'vertical-align:middle;')) .
											RCView::span(array('style'=>'vertical-align:middle;'),
												$lang['global_27']
											)
										) .
										// Copy
										RCView::button(array('id'=>'repcopyid_'.$report_id, 'class'=>'jqbuttonsm', 'style'=>'margin-right:2px;font-family:arial;', 'onclick'=>"copyReport($report_id,true);"),
											RCView::img(array('src'=>'copy_small.gif', 'style'=>'vertical-align:middle;position:relative;top:-1px;')) .
											RCView::span(array('style'=>'vertical-align:middle;'),
												$lang['report_builder_46']
											)
										) .
										// Delete
										RCView::button(array('id'=>'repdelid_'.$report_id, 'class'=>'jqbuttonsm', 'style'=>'font-family:arial;', 'onclick'=>"deleteReport($report_id,true);"),
											RCView::img(array('src'=>'cross_small2.png', 'style'=>'vertical-align:middle;')) .
											RCView::span(array('style'=>'vertical-align:middle;'),
												$lang['global_19']
											)
										)
									);
			}
			// If user has API export rights, then display report_id
			if ($hasAPIrights) {
				$rows[$item_num][] = (!is_numeric($report_id)) ? '' : RCView::div(array('style'=>'font-size:11px;color:#777;'), $report_id);
			}
			// Increment row counter
			if (!$isPredefined) $row_num++;
			$item_num++;			
		}
		// Add last row as "add new report" button
		if ($user_rights['reports']) {
			$rows[$item_num] = array('', '', 
									RCView::button(array('class'=>'jqbuttonmed', 'style'=>'margin:8px 0;', 'onclick'=>"window.location.href = app_path_webroot+'".LR_PATH_FROM_WEBROOT."index.php?create=1&addedit=1&pid='+pid;"), 
										'+ ' . $lang['report_builder_14']
									), '', '', '');
		}
		// Set table headers and attributes
		$viewExportOptionsWidthReduce = $viewAPIWidthAdd = 0;
		$viewExportOptionsWidthReduce += ($user_rights['data_export_tool'] > 0) ? 0 : 80;
		//$viewExportOptionsWidthReduce += ($user_rights['graphical'] && $enable_plotting > 0) ? 0 : 80;
		$col_widths_headers = array();
		$col_widths_headers[] = array(18, "", "center");
		$col_widths_headers[] = array(18, "", "center");
		$col_widths_headers[] = array(350, $lang['report_builder_42']);
		$col_widths_headers[] = array(220-$viewExportOptionsWidthReduce, $lang['report_builder_43']);
		if ($user_rights['reports']) {
			$viewReportMgmtWidthReduce = 0;
			$col_widths_headers[] = array(170, $lang['report_builder_45']);
		} else {	
			$viewReportMgmtWidthReduce = 182;
		}
		// If user has API export rights, then display report_id
		if ($hasAPIrights) {
			$viewAPIWidthAdd = 95;
			$col_widths_headers[] = array(83, 
										RCView::div(array('class'=>'nowrap', 'style'=>'text-align:center;font-size:10px;color:#777;'), 
											RCView::span(array('style'=>'vertical-align:middle;'), $lang['report_builder_125']) .
											RCView::a(array('href'=>'javascript:;', 'style'=>'margin-left:5px;', 'onclick'=>"simpleDialog(null,null,'api_report_dialog');"), 
												RCView::img(array('src'=>'help.png', 'style'=>'vertical-align:middle;'))
											) .
											RCView::br() .
											$lang['define_events_66']
										), 
										"center");
		}
		// Set table title
		$table_title = RCView::div(array('style'=>'color:#333;font-size:13px;padding:5px;'), $lang['report_builder_47']);
		// Hidden help dialog for API report export
		$hidden_dialog_api_report = RCView::div(array('id'=>'api_report_dialog', 'class'=>'simpleDialog', 'title'=>$lang['report_builder_126']),
										$lang['report_builder_127'] . " " . 
										RCView::a(array('href'=>APP_PATH_WEBROOT_PARENT.'api/help/', 'style'=>'text-decoration:underline;'), 
											$lang['control_center_445']
										) . 
										$lang['period']
									);
		// Render the table
		return $hidden_dialog_api_report .
			   renderGrid("report_list", $table_title, 837+$viewAPIWidthAdd-$viewExportOptionsWidthReduce-$viewReportMgmtWidthReduce, 'auto', $col_widths_headers, $rows, true, false, false);
	}
	
	
	// Render the dialog for user to choose export options
	public static function renderExportOptionDialog()
	{
		global $lang, $Proj, $surveys_enabled, $user_rights, $date_shift_max, $table_pk_label;
		
		// Options to remove DAG field and survey-related fields
		$dags = $Proj->getUniqueGroupNames();
		$exportDagOption = "";
		$exportSurveyFieldsOptions = "";
		if (!empty($dags) && $user_rights['group_id'] == "") {
			$exportDagOption = RCView::checkbox(array('name'=>'export_groups','checked'=>'checked','class'=>'imgfix2')) . 
							   $lang['data_export_tool_138'];
		}
		if ($surveys_enabled) {
			$exportSurveyFieldsOptions = RCView::checkbox(array('name'=>'export_survey_fields','checked'=>'checked','class'=>'imgfix2')) . 
										 $lang['data_export_tool_139'];
		}
		$exportDagSurveyFieldsOptions = $exportDagOption . ($exportDagOption == '' ? '' : RCView::br()) . $exportSurveyFieldsOptions;
		
		
		// De-Identification Options box
		if ($user_rights['data_export_tool'] == '2') {
			// FULL DE-ID: User has limited rights, so check off everything and disable options
			$deid_msg = "<font color=red>{$lang['data_export_tool_87']}</font>";
			$deid_disable2 = "onclick=\"this.checked=false;\"";
			$deid_identifier_disable = $deid_disable = "checked onclick=\"this.checked=true;\"";
			$deid_disable_date2 =  "onclick=\"
									var thisfld = this.getAttribute('id');
									var thisfldId = thisfld;
									if (thisfld == 'deid-dates-remove'){
										var thatfld = document.getElementById('deid-dates-shift');
										thisfld = document.getElementById('deid-dates-remove');
									} else {
										var thatfld = document.getElementById('deid-dates-remove');
										thisfld = document.getElementById('deid-dates-shift');
									};
									if (thisfld.checked==true) {
										thatfld.checked=false;
										thisfld.checked=true;
										if (thisfldId == 'deid-dates-remove'){
											$('#deid-surveytimestamps-shift').prop('disabled',true).prop('checked',false);
										} else {
											$('#deid-surveytimestamps-shift').prop('disabled',false);
										}				
									} else {
										thisfld.checked=false;
										thatfld.checked=true;				
										if (thisfldId == 'deid-dates-remove'){
											$('#deid-surveytimestamps-shift').prop('disabled',false);
										} else {
											$('#deid-surveytimestamps-shift').prop('disabled',true).prop('checked',false);
										}
									}\"";
			$deid_disable_date = "checked $deid_disable_date2";
			$deid_deselect = "";
			// Determine if id field is an Identifier. If so, auto-check it
			$deid_hashid = ($Proj->table_pk_phi) ? $deid_disable : $deid_disable2;
		} else {
			// User has full export rights OR remove identifier fields rights
			$deid_identifier_disable = ($user_rights['data_export_tool'] == '3') ? "checked onclick=\"this.checked=true;\"" : "";
			$deid_msg = ($user_rights['data_export_tool'] == '3') ? "<font color=red>{$lang['data_export_tool_185']}</font>" : "";
			$deid_disable = $deid_disable2 = $deid_hashid = "";
			$deid_disable_date = "onclick=\"$('#deid-surveytimestamps-shift').prop('disabled', !this.checked);\"";
			$deid_disable_date2 =  "onclick=\"
									var shiftfld = document.getElementById('deid-dates-shift');
									if (this.checked == true) {
										shiftfld.checked = false;
										shiftfld.disabled = true;
										$('#deid-surveytimestamps-shift').prop('disabled',true).prop('checked',false);
									} else {
										shiftfld.disabled = false;
										$('#deid-surveytimestamps-shift').prop('disabled',false);
									}\"";
			$deid_deselect =   "<a href='javascript:;' style='margin-top:12px;display:block;font-size:8pt;text-decoration:underline;' onclick=\"
									".($user_rights['data_export_tool'] == '3' ? "" : "document.getElementById('deid-remove-identifiers').checked = false;")."
									document.getElementById('deid-hashid').checked = false;
									document.getElementById('deid-remove-text').checked = false;
									document.getElementById('deid-remove-notes').checked = false;
									document.getElementById('deid-dates-remove').checked = false;
									document.getElementById('deid-dates-shift').checked = false;
									document.getElementById('deid-dates-shift').disabled = false;
								\">{$lang['data_export_tool_88']}</a>";
		}
		$date_shift_dialog_content =   "<b>{$lang['date_shift_02']}</b><br>
										{$lang['date_shift_03']} $date_shift_max {$lang['date_shift_04']}<br><br>
										{$lang['date_shift_05']} $date_shift_max {$lang['date_shift_06']} 
										$table_pk_label {$lang['date_shift_07']}<br><br>
										<b>{$lang['date_shift_08']}</b><br>{$lang['date_shift_09']}";
		$deid_option_box = "{$lang['data_export_tool_91']} $deid_msg
							<div style='font-size:11px;'>
								<div style='margin-top:10px;font-weight:bold;'>{$lang['data_export_tool_92']}</div>
								<div style='margin-left:2.3em;text-indent:-2.3em;line-height: 11px;'>
									<input type='checkbox' $deid_identifier_disable id='deid-remove-identifiers' name='deid-remove-identifiers' class='imgfix2'> 
									{$lang['data_export_tool_182']} 
									<span style='margin-left:3px;color:#777;font-size:10px;'>{$lang['data_export_tool_94']}</span>
								</div>
								<div style='margin-left:2.3em;text-indent:-2.3em;line-height: 11px;'>
									<input type='checkbox' $deid_hashid id='deid-hashid' name='deid-hashid' class='imgfix2'> {$lang['data_export_tool_173']}
									<span style='margin-left:3px;color:#777;font-size:10px;'>{$lang['data_export_tool_96']}</span>
								</div>
								
								<div style='margin-top:12px;font-weight:bold;'>{$lang['data_export_tool_97']}</div>
								<div style='margin-left:2.3em;text-indent:-2.3em;line-height: 11px;'>
									<input type='checkbox' $deid_disable id='deid-remove-text' name='deid-remove-text' class='imgfix2'> 
									{$lang['data_export_tool_98']} 
									<span style='margin-left:3px;color:#777;font-size:10px;'>{$lang['data_export_tool_99']}</span>
								</div>
								<div style='margin-left:2.3em;text-indent:-2.3em;line-height: 11px;'>
									<input type='checkbox' $deid_disable id='deid-remove-notes' name='deid-remove-notes' class='imgfix2'> 
									{$lang['data_export_tool_100']}
								</div>
								
								<div style='margin-top:12px;font-weight:bold;'>{$lang['data_export_tool_129']}</div>
								<div style='margin-left:2.3em;text-indent:-2.3em;line-height: 11px;'>
									<input type='checkbox' $deid_disable_date2 id='deid-dates-remove' name='deid-dates-remove' class='imgfix2'> 
									{$lang['data_export_tool_128']}
								</div>
								<div style='padding:6px 0 1px;color:#777;line-height: 11px;'>
									&mdash; {$lang['global_46']} &mdash; 
								</div>
								<div style='margin-left:2.3em;text-indent:-2.3em;line-height: 12px;'>
									<input type='checkbox' $deid_disable_date id='deid-dates-shift' name='deid-dates-shift' class='imgfix2'> 
									{$lang['data_export_tool_103']} $date_shift_max {$lang['data_export_tool_104']}<br>
									<span style='color:#777;font-size:10px;'>{$lang['data_export_tool_105']}</span>								
									<a href='javascript:;' style='margin-left:20px;font-size:8pt;text-decoration:underline;' onclick=\"
										simpleDialog('".cleanHtml($date_shift_dialog_content)."','".cleanHtml($lang['date_shift_01'])."');
									\">{$lang['data_export_tool_106']}</a>
								</div>
								".(($surveys_enabled && !empty($Proj->surveys)) ?
									"<div style='margin-left:4em;text-indent:-2em;padding-top:2px;line-height: 12px;'>
										<input type='checkbox' id='deid-surveytimestamps-shift' name='deid-surveytimestamps-shift' class='imgfix2' ".($user_rights['data_export_tool'] == 1 ? "disabled" : "")."> 
										{$lang['data_export_tool_143']} $date_shift_max {$lang['data_export_tool_104']}<br>
										<span style='color:#777;font-size:10px;'>{$lang['data_export_tool_105']}</span>
									</div>"
									: ""
								)."
								$deid_deselect
							</div>";
		
		// Return the html
		return 	RCView::div(array('class'=>'simpleDialog', 'id'=>'exportFormatDialog'),
					RCView::div(array(),
						$lang['report_builder_59']
					) .
					RCView::form(array('id'=>'exportFormatForm'),
						RCView::table(array('cellspacing'=>0, 'style'=>'width:100%;table-layout:fixed;'),
							// CSV Raw
							RCView::tr(array(),
								RCView::td(array('valign'=>'top', 'style'=>'width:360px;padding-right:20px;'),
									// Step 1: Choose export format
									RCView::fieldset(array('style'=>'margin:15px 0;border:1px solid #bbb;background-color:#eee;'),
										RCView::legend(array('style'=>'padding:0 3px;margin-left:15px;color:#800000;font-weight:bold;font-size:15px;'),
											$lang['report_builder_114']
										) .
										RCView::table(array('id'=>'export_choices_table', 'cellspacing'=>0, 'style'=>'margin-top:6px;width:100%;table-layout:fixed;'),
											// CSV Raw
											RCView::tr(array(),
												RCView::td(array('style'=>'padding:1px 15px 5px;cursor:pointer;cursor:hand;'),
													RCView::radio(array('name'=>'export_format', 'value'=>'csvraw', 'style'=>'vertical-align:middle;margin-right:22px;')) .
													RCView::img(array('src'=>'excelicon.gif', 'style'=>'vertical-align:middle;')) .
													RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;font-size:13px;margin-left:10px;'),
														$lang['data_export_tool_172'] .
														" " . $lang['report_builder_49']
													)
												)
											) .
											// CSV Labels
											RCView::tr(array(),
												RCView::td(array('style'=>'padding:5px 15px;cursor:pointer;cursor:hand;'),
													RCView::radio(array('name'=>'export_format', 'value'=>'csvlabels', 'style'=>'vertical-align:middle;margin-right:22px;')) .
													RCView::img(array('src'=>'excelicon.gif', 'style'=>'vertical-align:middle;')) .
													RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;font-size:13px;margin-left:10px;'),
														$lang['data_export_tool_172'] .
														" " . $lang['report_builder_50']
													)
												)
											) /*.
											// SPSS
											RCView::tr(array(),
												RCView::td(array('style'=>'padding:8px 15px;cursor:pointer;cursor:hand;'),
													RCView::radio(array('name'=>'export_format', 'value'=>'spss', 'style'=>'vertical-align:middle;margin-right:26px;')) .
													RCView::img(array('src'=>'spsslogo_small.png', 'style'=>'vertical-align:middle;')) .
													RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;font-size:13px;margin-left:14px;'),
														$lang['data_export_tool_07']
													)
												)
											) .
											// SAS
											RCView::tr(array(),
												RCView::td(array('style'=>'padding:8px 15px;cursor:pointer;cursor:hand;'),
													RCView::radio(array('name'=>'export_format', 'value'=>'sas', 'style'=>'vertical-align:middle;margin-right:26px;')) .
													RCView::img(array('src'=>'saslogo_small.png', 'style'=>'vertical-align:middle;')) .
													RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;font-size:13px;margin-left:4px;'),
														$lang['data_export_tool_11']
													)
												)
											) .
											// R
											RCView::tr(array(),
												RCView::td(array('style'=>'padding:9px 15px;cursor:pointer;cursor:hand;'),
													RCView::radio(array('name'=>'export_format', 'value'=>'r', 'style'=>'vertical-align:middle;margin-right:24px;')) .
													RCView::img(array('src'=>'rlogo_small.png', 'style'=>'vertical-align:middle;')) .
													RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;font-size:13px;margin-left:18px;'),
														$lang['data_export_tool_09']
													)
												)
											) .
											// Stata
											RCView::tr(array(),
												RCView::td(array('style'=>'padding:9px 15px;cursor:pointer;cursor:hand;'),
													RCView::radio(array('name'=>'export_format', 'value'=>'stata', 'style'=>'vertical-align:middle;margin-right:24px;')) .
													RCView::img(array('src'=>'statalogo_small.png', 'style'=>'vertical-align:middle;')) .
													RCView::span(array('style'=>'vertical-align:middle;font-weight:bold;font-size:13px;margin-left:8px;'),
														$lang['data_export_tool_187']
													)
												)
											)*/
										)
									) .
									// Step 2: Archive files in File Repository? FOR NOW, ALWAYS DO THIS!!!!!!!!
									RCView::fieldset(array('style'=>'display:none;margin:15px 0;padding-left:8px;border:1px solid #bbb;background-color:#eee;'),
										RCView::legend(array('style'=>'color:#800000;font-weight:bold;font-size:13px;'),
											$lang['report_builder_54']
										) .
										RCView::div(array('style'=>'padding:5px 8px 8px 2px;'),
											RCView::div(array('style'=>'cursor:pointer;cursor:hand;', 'onclick'=>"$(this).find('input:first').prop('checked', true);"),
												RCView::radio(array('name'=>'export_options_archive', 'class'=>'imgfix2', 'value'=>'1', 'checked'=>'checked')) .
												$lang['report_builder_57']
											) .						
											RCView::div(array('style'=>'cursor:pointer;cursor:hand;', 'onclick'=>"$(this).find('input:first').prop('checked', true);"),
												RCView::radio(array('name'=>'export_options_archive', 'class'=>'imgfix2', 'value'=>'0')) .
												$lang['report_builder_58']
											)
										)
									)
								) .
								RCView::td(array('valign'=>'top'),
									// De-ID Options
									RCView::fieldset(array('style'=>'margin:15px 0;padding-left:8px;border:1px solid #ddd;background-color:#f9f9f9;'),
										RCView::legend(array('style'=>'margin-left:5px;color:#800000;font-weight:bold;font-size:13px;'),
											$lang['data_export_tool_89'] .
											RCView::span(array('style'=>'font-weight:normal;margin-left:5px;'), $lang['survey_251'])
										) .
										RCView::div(array('style'=>'padding:5px 8px 8px 2px;'),
											$deid_option_box
										)
									) .
									// Export DAGs and/or Survey Fields
									($exportDagSurveyFieldsOptions == '' ? '' :
										RCView::fieldset(array('id'=>'export_dialog_dags_survey_fields_options', 'style'=>'margin:15px 0 0;padding-left:8px;border:1px solid #ddd;background-color:#f9f9f9;'),
											RCView::legend(array('style'=>'margin-left:5px;color:#800000;font-weight:bold;font-size:13px;'),
												$lang['data_export_tool_140']
											) .
											RCView::div(array('style'=>'padding:5px 8px 8px 2px;'),
												$exportDagSurveyFieldsOptions
											)
										)
									)
								)
							)
						)
					)
				 );
	}
	
	
	// Output a specific report in a specified output format (html, csvlabels, csvraw, spss, sas, r, stata)
	public static function doReport($report_id='0', $outputType='report', $outputFormat='html', $apiExportLabels=false, $apiExportHeadersAsLabels=false, 
									$outputDags=false, $outputSurveyFields=false, $removeIdentifierFields=false, $hashRecordID=false, 
									$removeUnvalidatedTextFields=false, $removeNotesFields=false, 
									$removeDateFields=false, $dateShiftDates=false, $dateShiftSurveyTimestamps=false,
									$selectedInstruments=array(), $selectedEvents=array(), $returnIncludeRecordEventArray=false,
									$outputCheckboxLabel=false)
	{
		global $Proj, $user_rights, $isAjax, $app_title, $lang, $redcap_version;
		
		// Check report_id
		if (!is_numeric($report_id) && $report_id != 'ALL' && $report_id != 'SELECTED') {
			exit($isAjax ? '0' : 'ERROR');
		}
		
		// Increase memory limit in case needed for intensive processing
		if (str_replace("M", "", ini_get('memory_limit')) < 1024) ini_set('memory_limit', '1024M');
		
		// TESTING
		// if (isDev()) ini_set('memory_limit', '12M');
		
		// Determine if this is API report export
		$isAPI = (PAGE == 'api/index.php' || PAGE == 'API/index.php');
		
		// Set flag to ALWAYS archive exported files in File Repository
		$archiveFiles = true;
		
		// Get report attributes
		$report = self::getReports($report_id, $selectedInstruments, $selectedEvents);
		if (empty($report)) {
			if ($isAPI) {
				exit(RestUtility::sendResponse(400, 'The value of the parameter "report_id" is not valid'));
			} else {
				exit($isAjax ? '0' : 'ERROR');
			}
		}
		
		// Check user rights: Does user have access to this report? (exclude super users in this check)
		if ((defined('SUPER_USER') && !SUPER_USER) || !defined('SUPER_USER')) {
			// If user has Add/Edit Report rights then let them view this report, OR if they have explicit rights to this report
			if (self::getReportNames($report_id, !$user_rights['reports']) == null) {
				// User does NOT have access to this report AND also does not have Add/Edit Report rights
				if ($isAPI) {
					exit(RestUtility::sendResponse(403, "User \"".USERID."\" does not have access to this report."));
				} else {
					exit($isAjax ? '0' : 'ERROR');
				}
			}
		}
		
		// Determine if a report or an export
		$outputType = ($outputType == 'report') ? 'report' : 'export';
		if ($outputType != 'report') $returnIncludeRecordEventArray = false;
		// Determine whether to output a stats syntax file
		$stats_packages = array('r', 'spss', 'stata', 'sas');
//		$outputSyntaxFile = (in_array($outputFormat, $stats_packages));
		
		// If CSV, determine whether to output a stats syntax file
		$outputAsLabels = ($outputFormat == 'csvlabels');
		$outputHeadersAsLabels = ($outputFormat == 'csvlabels');
		
		// List of fields to export
		$fields = $report['fields'];
		// If removing any fields due to DE-IDENTIFICATION, loop through them and remove them
		if ($removeIdentifierFields || $removeUnvalidatedTextFields || $removeNotesFields || $removeDateFields) {
			foreach ($fields as $key=>$this_event_field) {
                                $this_field = self::getFieldFromEventField($this_event_field);
				// Skip record ID field
				if ($this_field == $Proj->table_pk) continue;
				// Get field type and validation type
				$this_field_type = $Proj->metadata[$this_field]['element_type'];
				$this_val_type = $Proj->metadata[$this_field]['element_validation_type'];
				$this_phi = $Proj->metadata[$this_field]['field_phi'];
				// Check if needs to be removed
				if (   ($this_phi && $removeIdentifierFields)
					|| ($this_field_type == 'text' && $this_val_type == '' && $removeUnvalidatedTextFields)
					|| ($this_field_type == 'textarea' && $removeNotesFields)
					|| ($this_field_type == 'text' && $removeDateFields && substr($this_val_type, 0, 4) == 'date')) 
				{
					// Remove the field from $fields
					unset($fields[$key]);
				}
			}
		}		
		
		// List of events to export
		$events = $report['limiter_events'];
		
		// Limit to user's DAG (if user is in a DAG), and if not in a DAG, then limit to the DAG filter
		$userInDAG = (isset($user_rights['group_id']) && is_numeric($user_rights['group_id']));
		$dags = ($userInDAG) ? $user_rights['group_id'] : $report['filter_dags'];
		// Set options to include DAG names and/or survey fields (exclude ALL and SELECTED pre-defined reports)
		if (is_numeric($report_id)) {
			$outputDags = ($report['output_dags'] == '1');
			$outputSurveyFields = ($report['output_survey_fields'] == '1');
		} 
		// For pre-defined reports, if viewing as report, then ALWAYS default to displaying the DAGs and survey fields
		elseif (!is_numeric($report_id) && $outputType == 'report') {
			$outputDags = $outputSurveyFields = true;
		}
		// If user is in a DAG, then do not output the DAG name field
		if ($userInDAG) $outputDags = false;
		// If we're removing identifier fields, then also remove Survey Identifier (if outputting survey fields)
		$outputSurveyIdentifier = ($outputSurveyFields && !$removeIdentifierFields);
		
                $outputScheduleDates = $report['output_schedule_dates']; 
                $outputSurveyUrls = $report['output_survey_urls'];
		
                // File names for archived file
		$today_hm = date("Y-m-d_Hi");
		$projTitleShort = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 20);
		if ($outputFormat == 'r' || $outputFormat == 'csvraw') {
			// CSV with header row
			$csv_filename = $projTitleShort . "_DATA_" .$today_hm. ".csv";
		} elseif ($outputFormat == 'csvlabels') {
			// CSV labels
			$csv_filename = $projTitleShort ."_DATA_LABELS_" .$today_hm. ".csv";
		} else {
			// CSV without header row
			$csv_filename = $projTitleShort . "_DATA_NOHDRS_" .$today_hm. ".csv";
		}
		
		// Build sort array of sort fields and their attribute (ASC, DESC)
		$sortArray = array();
		if ($report['orderby_field1'] != '') $sortArray[$report['orderby_field1']] = $report['orderby_sort1'];
		if ($report['orderby_field2'] != '') $sortArray[$report['orderby_field2']] = $report['orderby_sort2'];
		if ($report['orderby_field3'] != '') $sortArray[$report['orderby_field3']] = $report['orderby_sort3'];
		// If the only sort field is record ID field, then remove it (because it will sort by record ID and event on its own)
		if (count($sortArray) == 1 && isset($sortArray[$Proj->table_pk]) && $sortArray[$Proj->table_pk] == 'ASC') {
			unset($sortArray[$Proj->table_pk]);			
		}
		
		## BUILD AND STORE CSV FILE
		// Set output format (CSV or HTML or API format)
		if ($isAPI) {
			// For API report export, return in desired format
			$returnDataFormat = $outputFormat;
			$outputAsLabels = $apiExportLabels;
			$outputHeadersAsLabels = $apiExportHeadersAsLabels;
		} elseif ($outputType == 'report') {
			// For webpage report, return html
			$returnDataFormat = 'html';
		} else {
			$returnDataFormat = 'csv';
		}
		
		// Check syntax of logic string: If there is an issue in the logic, then return false and stop processing
		if ($outputType == 'report' && $report['limiter_logic'] != '' && !LogicTester::isValid($report['limiter_logic'])) {
			return array(RCView::div(array('class'=>'red'), 
						RCView::img(array('src'=>'exclamation.png', 'class'=>'imgfix')) .
						RCView::b($lang['global_01'].$lang['colon']) . " " . $lang['report_builder_132']), 0);
		}
		
		// Retrieve CSV data file
		$data_content = LongitudinalRecords::getData(PROJECT_ID, $returnDataFormat, array(), $fields, $events, $dags, false, $outputDags, $outputSurveyFields, 
										 $report['limiter_logic'], $outputAsLabels, $outputHeadersAsLabels, $hashRecordID, $dateShiftDates, 
										 $dateShiftSurveyTimestamps, $sortArray, ($outputType != 'report'), true, $returnIncludeRecordEventArray, 
										 true, $outputSurveyIdentifier, $outputCheckboxLabel, $outputScheduleDates, $outputSurveyUrls);
		// Replace any MS Word chacters in the data (from 6.12.0 string passed by ref)
                if (version_compare($redcap_version, '6.12.0', '<')) {
                        $data_content = replaceMSchars($data_content);
                } else {
                        //replaceMSchars(is_array($data_content) ? $data_content[0] : $data_content); //"Only variables can be passed by reference"
                        if(is_array($data_content)) {
                            replaceMSchars($data_content[0]);
                        } else {
                            replaceMSchars($data_content);
                        }
                }
                
		## Logging (for exports only)
		if ($outputType != 'report' || $isAPI) {
			// Set data_values as JSON-encoded
			$data_values = array('report_id'=>$report_id, 
								 'export_format'=>(substr($outputFormat, 0, 3) == 'csv' ? 'CSV' : strtoupper($outputFormat)), 
								 'rawOrLabel'=>($outputAsLabels ? 'label' : 'raw'));
			if ($outputDags) $data_values['export_data_access_group'] = 'Yes';
			if ($outputSurveyFields) $data_values['export_survey_fields'] = 'Yes';
			if ($dateShiftDates) $data_values['date_shifted'] = 'Yes';
			if (isset($user_rights['data_export_tool']) && $user_rights['data_export_tool'] == '2') $data_values['deidentified'] = 'Yes';
			if (isset($user_rights['data_export_tool']) && $user_rights['data_export_tool'] == '3') $data_values['removed_identifiers'] = 'Yes';
			$data_values['fields'] = (empty($fields)) ? array_keys($Proj->metadata) : $fields;
			// Log it
                        if (REDCap::versionCompare(REDCAP_VERSION, '7.0.0') >= 0) {
                                Logging::logEvent("","redcap_data","longitudinal_report","",json_encode($data_values),"Longitudinal Report" . ($isAPI ? " (API)" : ""));
                        } else {
        			log_event("","redcap_data","longitudinal_report","",json_encode($data_values),"Longitudinal Report" . ($isAPI ? " (API)" : ""));
                        }
		}
		
		// IF OUTPUTTING A REPORT, RETURN THE CONTENT HERE
		if ($outputType == 'report' || $isAPI) {
			return $data_content;
		}
		
		// For SAS, SPSS, and Stata, remove the CSV file's header row
		if ((in_array($outputFormat, array('spss', 'stata', 'sas')))) {
			// Remove header row
			list ($headers, $data_content) = explode("\n", $data_content, 2);
		}
		// Store the data file
		$data_edoc_id = self::storeExportFile($csv_filename, $data_content, $archiveFiles, $dateShiftDates);
		if ($data_edoc_id === false) return false;
        
/* Stats syntax not implemented for Longitudinal Reports		
		## BUILD AND STORE SYNTAX FILE (if applicable)
		// If exporting to a stats package, then also generate the associate syntax file for that package
		$syntax_edoc_id = null;
		if ($outputSyntaxFile) {
			// Generate syntax file	
			$syntax_file_contents = self::getStatsPackageSyntax($outputFormat, $fields, $csv_filename, $outputDags, $outputSurveyFields, $removeIdentifierFields);
			// Set the filename of the syntax file
			if ($outputFormat == 'spss') {
				$stats_package_filename = $projTitleShort ."_" . strtoupper($outputFormat) . "_$today_hm.sps";
			} elseif ($outputFormat == 'stata') {
				$stats_package_filename = $projTitleShort ."_" . strtoupper($outputFormat) . "_$today_hm.do";
			} else {
				$stats_package_filename = $projTitleShort ."_" . strtoupper($outputFormat) . "_$today_hm.$outputFormat";
			}
			// Store the syntax file
			$syntax_edoc_id = self::storeExportFile($stats_package_filename, $syntax_file_contents, $archiveFiles, $dateShiftDates);
			if ($syntax_edoc_id === false) return false;
		}	
*/$syntax_edoc_id=null;		
		// Return the edoc_id's of the CSV data file
		return array($data_edoc_id, $syntax_edoc_id);
	}
	
/* Stats syntax not implemented for Longitudinal Reports		
	// Build and return the stats package syntax file
	public static function getStatsPackageSyntax($stats_package, $fields, $data_file_name, $exportDags=false, $exportSurveyFields=false, $do_remove_identifiers=false)
	{
		global $Proj, $user_rights;
	
		// If DAGs exist, get unique group name and label IF user specified
		$dagLabels = $Proj->getGroups();
		$exportDags = ($exportDags && !empty($dagLabels) && (!isset($user_rights['group_id']) || (isset($user_rights['group_id']) && $user_rights['group_id'] == "")));
		if ($exportDags) {
			$dagUniqueNames = $Proj->getUniqueGroupNames();
			// Create enum for DAGs with unique name as coded value
			$dagEnumArray = array();
			foreach (array_combine($dagUniqueNames, $dagLabels) as $group_id=>$group_label) {
				$dagEnumArray[] = "$group_id, " . label_decode($group_label);
			}
			$dagEnum = implode(" \\n ", $dagEnumArray);;
		}
		
		# Initializing the syntax file strings
		$spss_string = "FILE HANDLE data1 NAME='data_place_holder_name' LRECL=90000.\n";
		$spss_string .= "DATA LIST FREE" . "\n\t";
		$spss_string .= "FILE = data1\n\t/";
		$sas_string = "DATA " . $Proj->project_name . ";\nINPUT ";
		$sas_format_string = "data redcap;\n\tset redcap;\n";
		$stata_string = "clear\n\n";
		$R_string = "#Clear existing data and graphics\nrm(list=ls())\n";
		$R_string .= "graphics.off()\n";
		$R_string .= "#Load Hmisc library\nlibrary(Hmisc)\n";
		$R_label_string = "#Setting Labels\n";
		$R_units_string = "\n#Setting Units\n" ;
		$R_factors_string = "\n\n#Setting Factors(will create new variable for factors)";
		$R_levels_string = "";
		$value_labels_spss = "VALUE LABELS ";
		
		// Collect fields into meta_array
		$meta_array = array();
	
		// Loop through fields
		foreach ($fields as $field)
		{
			// Set field attributes
			$row = $Proj->metadata[$field];
			
			// Skip any descriptive fields (because they cannot have data and should be excluded)
			if ($Proj->metadata[$field]['element_type'] == 'descriptive') continue;
			
			// Create object for each field we loop through
			$ob = new stdClass();
			foreach ($row as $col=>$val) {
				$col = strtoupper($col);
				$ob->$col = $val;
			}
			
			// Set values for this loop
			$this_form = $Proj->metadata[$ob->FIELD_NAME]['form_name'];		
			
			// If surveys exist, as timestamp and identifier fields
			if ($exportSurveyFields && $prev_form != $this_form && $ob->FIELD_NAME != $Proj->table_pk && isset($Proj->forms[$this_form]['survey_id']))
			{
				// Alter $meta_array
				$ob2 = new stdClass();
				$ob2->ELEMENT_TYPE = 'text';
				$ob2->FIELD_NAME = $this_form.'_timestamp';
				$ob2->ELEMENT_LABEL = 'Survey Timestamp';
				$ob2->ELEMENT_ENUM = '';
				$ob2->FIELD_UNITS = '';
				$ob2->ELEMENT_VALIDATION_TYPE = '';
				$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
			}
			
			
			if ($ob->ELEMENT_TYPE != 'checkbox') {			
				// For non-checkboxes, add to $meta_array
				$meta_array[$ob->FIELD_NAME] = (Object)$ob;
			} else {
				// For checkboxes, loop through each choice to add to $meta_array
				$orig_fieldname = $ob->FIELD_NAME;
				$orig_fieldlabel = $ob->ELEMENT_LABEL;
				$orig_elementenum = $ob->ELEMENT_ENUM;
				foreach (parseEnum($orig_elementenum) as $this_value=>$this_label) {
					unset($ob);
					// $ob = $meta_set->FetchObject();
					$ob = new stdClass();
					// If coded value is not numeric, then format to work correct in variable name (no spaces, caps, etc)
					$this_value = (Project::getExtendedCheckboxCodeFormatted($this_value));
					// Convert each checkbox choice to a advcheckbox field (because advcheckbox has equivalent processing we need)
					// Append triple underscore + coded value
					$ob->FIELD_NAME = $orig_fieldname . '___' . $this_value;
					$ob->ELEMENT_ENUM = "0, Unchecked \\n 1, Checked";
					$ob->ELEMENT_TYPE = "advcheckbox";
					$ob->ELEMENT_LABEL = "$orig_fieldlabel (choice=".str_replace(array("'","\""),array("",""),$this_label).")";
					$meta_array[$ob->FIELD_NAME] = (Object)$ob;
				}
			}
			
			
			if ($ob->FIELD_NAME == $Proj->table_pk)
			{
				// If project has multiple Events (i.e. Longitudinal), add new column for Event name
				if ($Proj->longitudinal) 
				{
					// Put unique event names and labels into array to convert to enum format
					$evtEnumArray = array();
					$evtLabels = array();
					foreach ($Proj->eventInfo as $event_id=>$attr) {
						$evtLabels[$event_id] = label_decode($attr['name_ext']);
					}
					foreach ($evtLabels as $event_id=>$event_label) {
						$evtEnumArray[] = $Proj->getUniqueEventNames($event_id) . ", $event_label";
					}
					$evtEnum = implode(" \\n ", $evtEnumArray);
					// Alter $meta_array
					$ob2 = new stdClass();
					$ob2->ELEMENT_TYPE = 'select';
					$ob2->FIELD_NAME = 'redcap_event_name';
					$ob2->ELEMENT_LABEL = 'Event Name';
					$ob2->ELEMENT_ENUM = $evtEnum;
					$ob2->FIELD_UNITS = '';
					$ob2->ELEMENT_VALIDATION_TYPE = '';
					$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
					// Add pseudo-field to array
					$field_names_prepend[] = $ob2->FIELD_NAME;
				}
				// If project has DAGs, add new column for group name
				if ($exportDags) 
				{
					// Alter $meta_array
					$ob2 = new stdClass();
					$ob2->ELEMENT_TYPE = 'select';
					$ob2->FIELD_NAME = 'redcap_data_access_group';
					$ob2->ELEMENT_LABEL = 'Data Access Group';
					$ob2->ELEMENT_ENUM = $dagEnum;
					$ob2->FIELD_UNITS = '';
					$ob2->ELEMENT_VALIDATION_TYPE = '';
					$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
					// Add pseudo-field to array
					$field_names_prepend[] = $ob2->FIELD_NAME;
				}
				
				// Add survey identifier (unless we've set it to remove all identifiers - treat survey identifier same as field identifier)
				if ($exportSurveyFields && !$do_remove_identifiers) {
					// Alter $meta_array
					$ob2 = new stdClass();
					$ob2->ELEMENT_TYPE = 'text';
					$ob2->FIELD_NAME = 'redcap_survey_identifier';
					$ob2->ELEMENT_LABEL = 'Survey Identifier';
					$ob2->ELEMENT_ENUM = '';
					$ob2->FIELD_UNITS = '';
					$ob2->ELEMENT_VALIDATION_TYPE = '';
					$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
					// Add pseudo-field to array
					$field_names_prepend[] = $ob2->FIELD_NAME;
				}		
			
				// If surveys exist, as timestamp and identifier fields
				if ($exportSurveyFields && $prev_form != $this_form && isset($Proj->forms[$this_form]['survey_id']))
				{
					// Alter $meta_array
					$ob2 = new stdClass();
					$ob2->ELEMENT_TYPE = 'text';
					$ob2->FIELD_NAME = $this_form.'_timestamp';
					$ob2->ELEMENT_LABEL = 'Survey Timestamp';
					$ob2->ELEMENT_ENUM = '';
					$ob2->FIELD_UNITS = '';
					$ob2->ELEMENT_VALIDATION_TYPE = '';
					$meta_array[$ob2->FIELD_NAME] = (Object)$ob2;
				}
			}
			
			// Set values for next loop
			$prev_form = $this_form;
			$prev_field = $ob->FIELD_NAME;
		}
		
	
		// Now reset field_names array
		$field_names = array_keys($meta_array);
		
		
		// $spss_data_type_array = "";
		$spss_format_dates   = "";
		$spss_variable_label = "VARIABLE LABEL ";
		$spss_variable_level = array();
		$sas_label_section = "\ndata redcap;\n\tset redcap;\n";
		$sas_value_label = "proc format;\n";
		$sas_input = "input\n";
		$sas_informat = "";
		$sas_format = "";
		$stata_insheet = "insheet ";
		$stata_var_label = "";
		$stata_inf_label = "";
		$stata_value_label = "";
		$stata_date_format = "";
		
		$first_label = true;
		$large_name_counter = 0;
		$large_name = false;
		
		// Obtain all validation types to get the data format of each field (so we can export each truly as a data type rather than
		// being tied to their validation name).
		$valTypes = getValTypes();
		
		// Use arrays for string replacement
		$orig = array("'", "\"", "\r\n", "\r", "\n", "&lt;", "<=");
		$repl = array("", "", " ", " ", " ", "<", "< =");
		$repl_sas_choices = array("''", "", " ", " ", " ", "<", "< =");
		
		//print_array($meta_array);print_array($field_names);exit;
		
		
		// Loop through all metadata fields
		for ($x = 0; $x <= count($field_names) + 1; $x++) 
		{
					
			if (($x % 5)== 0 && $x != 0) {
				$spss_string .=  "\n\t";
			}
			$large_name = false;
			
			// Set field object for this loop
			$ob = $meta_array[$field_names[$x]];
			
			// Remove any . or - in the field name (as a result of checkbox raw values containing . or -)
			// $ob->FIELD_NAME = str_replace(array("-", "."), array("_", "_"), (string)$ob->FIELD_NAME);
			
			// Convert "sql" field types to "select" field types so that their Select Choices come out correctly in the syntax files.
			if ($ob->ELEMENT_TYPE == "sql")
			{
				// Change to select
				$ob->ELEMENT_TYPE = "select";
				// Now populate it's choices by running the query
				$ob->ELEMENT_ENUM = getSqlFieldEnum($ob->ELEMENT_ENUM);
			}
			elseif ($ob->ELEMENT_TYPE == "yesno")
			{
				$ob->ELEMENT_ENUM = YN_ENUM;
			} 
			elseif ($ob->ELEMENT_TYPE == "truefalse")
			{
				$ob->ELEMENT_ENUM = TF_ENUM;
			}	
			
			//Remove any offending characters from label (do slightly different for SAS)
			$ob->ELEMENT_LABEL = str_replace($orig, ($stats_package == 'sas' ? $repl_sas_choices : $repl), label_decode(html_entity_decode($ob->ELEMENT_LABEL, ENT_QUOTES)));
			
			if ($field_names[$x] != "") {
				if (strlen($field_names[$x]) >= 31) {
					$short_name = substr($field_names[$x],0,20) . "_v_" . $large_name_counter;
					$sas_label_section .= "\tlabel " . $short_name ."='" . $ob->ELEMENT_LABEL . "';\n";
					$stata_var_label .= "label variable " . $short_name . ' "' . $ob->ELEMENT_LABEL . '"' . "\n";
					$stata_insheet .= $short_name . " ";
					$large_name_counter++;
					$large_name = true;
				}
				if (!$large_name) {
					$sas_label_section .= "\tlabel " . $field_names[$x] ."='" . $ob->ELEMENT_LABEL . "';\n";
					$stata_var_label .= "label variable " . $field_names[$x] . ' "' . $ob->ELEMENT_LABEL . '"' . "\n";
					$stata_insheet .= $field_names[$x] . " ";
				}
				$spss_variable_label .= $field_names[$x] . " '" . $ob->ELEMENT_LABEL . "'\n\t/" ;
				$R_label_string .= "\nlabel(data$" . $field_names[$x] . ")=" . '"' . $ob->ELEMENT_LABEL . '"';
				if (($ob->FIELD_UNITS != Null) || ($ob->FIELD_UNITS != "")) {
					$R_units_string .= "\nunits(data$" . $field_names[$x] . ")=" . '"' .  $ob->FIELD_UNITS . '"';
				}
			}

			# Checking for single element enum (i.e. if it is coded with a number or letter)
			$single_element_enum = true;
			if (substr_count(((string)$ob->ELEMENT_ENUM),",") > 0) {
				$single_element_enum = false;
			}
			
			# Select value labels are created
			if (($ob->ELEMENT_TYPE == "yesno" || $ob->ELEMENT_TYPE == "truefalse" || $ob->ELEMENT_TYPE == "select" 
				|| $ob->ELEMENT_TYPE == "advcheckbox" || $ob->ELEMENT_TYPE == "radio") && !preg_match("/\+\+SQL\+\+/",(string)$ob->ELEMENT_ENUM)) {
				
				// Replace illegal characters from the Choice Labels (do slightly different for SAS)
				$ob->ELEMENT_ENUM = str_replace($orig, ($stats_package == 'sas' ? $repl_sas_choices : $repl), label_decode($ob->ELEMENT_ENUM));
				
				//Place $ in front of SAS value if using non-numeric coded values for dropdowns/radios
				$sas_val_enum_num = ""; //default
				$numericChoices = true;
				foreach (array_keys(parseEnum($ob->ELEMENT_ENUM)) as $key) {
					if (!is_numeric($key)) {
						// If at least one key is not numeric, then stop looping because we have all we need.
						$sas_val_enum_num = "$";
						$numericChoices = false;
						break;
					}
				}
				
				if ($first_label) {
					if (!$single_element_enum) {
						$value_labels_spss .=  "\n" . (string)$ob->FIELD_NAME . " ";
					}
					$R_factors_string .= "\ndata$" . (string)$ob->FIELD_NAME . ".factor = factor(data$" . (string)$ob->FIELD_NAME . ",levels=c(";
					$R_levels_string .=  "\nlevels(data$" . (string)$ob->FIELD_NAME . ".factor)=c(";
					$first_label = false;
					if (!$large_name && !$single_element_enum) {
						$sas_value_label .= "\tvalue $sas_val_enum_num" . (string)$ob->FIELD_NAME . "_ ";
						$sas_format_string .= "\n\tformat " . (string)$ob->FIELD_NAME . " " . (string)$ob->FIELD_NAME . "_.;\n";
						if ($numericChoices) {
							$stata_inf_label .= "\nlabel values " . (string)$ob->FIELD_NAME . " " . (string)$ob->FIELD_NAME . "_\n";
							$stata_value_label = "label define " . (string)$ob->FIELD_NAME . "_ ";
						}
					} else if ($large_name && !$single_element_enum) {
						$sas_value_label .= "\tvalue $sas_val_enum_num" . $short_name . "_ ";
						$sas_format_string .= "\n\tformat " . $short_name . " " . $short_name . "_.;\n";
						if ($numericChoices) {
							$stata_value_label .= "label define " . $short_name . "_ ";
							$stata_inf_label .= "\nlabel values " . $short_name . " " . $short_name . "_\n";
						}
					}
				} else if(!$first_label) {
					if (!$single_element_enum) {
						$value_labels_spss .= "\n/" . (string)$ob->FIELD_NAME . " ";
						if (!$large_name) {
							$sas_value_label .= "\n\tvalue $sas_val_enum_num" . (string)$ob->FIELD_NAME . "_ ";
							$sas_format_string .= "\tformat " . (string)$ob->FIELD_NAME . " " . (string)$ob->FIELD_NAME . "_.;\n";
							if ($numericChoices) {
								$stata_value_label .= "\nlabel define " . (string)$ob->FIELD_NAME . "_ ";
								$stata_inf_label .= "label values " . (string)$ob->FIELD_NAME . " " . (string)$ob->FIELD_NAME . "_\n";
							}
						}
					}
					$R_factors_string .= "data$" . (string)$ob->FIELD_NAME . ".factor = factor(data$" . (string)$ob->FIELD_NAME . ",levels=c(";
					$R_levels_string .=  "levels(data$" . (string)$ob->FIELD_NAME . ".factor)=c(";
					if ($large_name && !$single_element_enum) {
						$sas_value_label .= "\n\tvalue $sas_val_enum_num" . $short_name . "_ ";
						$sas_format_string .= "\tformat " . $short_name . " " . $short_name . "_.;\n";
						if ($numericChoices) {
							$stata_value_label .= "\nlabel define " . $short_name . "_ "; //LS inserted this line 24-Feb-2012
							$stata_inf_label .= "label values " . $short_name . " " . $short_name . "_\n";
						}
					}
				}

				$first_new_line_explode_array = explode("\\n",(string)$ob->ELEMENT_ENUM);
				
				// Loop through multiple choice options
				$select_is_text = false;
				$select_determining_array = array();
				for ($counter = 0;$counter < count($first_new_line_explode_array);$counter++) {
					if (!$single_element_enum) {
			
						// SAS: Add line break after 2 multiple choice options
						if (($counter % 2) == 0 && $counter != 0) {
							$sas_value_label   .= "\n\t\t";
							$value_labels_spss .= "\n\t";
						}	
			
						$second_comma_explode = explode(",",$first_new_line_explode_array[$counter],2);
						$value_labels_spss .= "'" . trim($second_comma_explode[0]) . "' ";
						$value_labels_spss .= "'" . trim($second_comma_explode[1]) . "' ";
						if (!is_numeric(trim($second_comma_explode[0])) && is_numeric(substr(trim($second_comma_explode[0]), 0, 1))) {
							// if enum raw value is not a number BUT begins with a number, add quotes around it for SAS only (parsing issue)
							$sas_value_label .= "'" . trim($second_comma_explode[0]) . "'=";
						} else {
							$sas_value_label .= trim($second_comma_explode[0]) . "=";
						}
						$sas_value_label .= "'" . trim($second_comma_explode[1]) . "' ";
						if ($numericChoices) {
							$stata_value_label .= trim($second_comma_explode[0]) . " ";
							$stata_value_label .= "\"" . trim($second_comma_explode[1]) . "\" ";
						}
						$select_determining_array[] = $second_comma_explode[0];
						$R_factors_string .= '"' . trim($second_comma_explode[0]) . '",'; 
						$R_levels_string .= '"' . trim($second_comma_explode[1]) . '",';
					} else {
						$select_determining_array[] = $second_comma_explode[0];
						$R_factors_string .= '"' . trim($first_new_line_explode_array[$counter]) . '",'; 
						$R_levels_string .= '"' . trim($first_new_line_explode_array[$counter]) . '",';
					}
				}
				$R_factors_string = rtrim($R_factors_string,",");
				$R_factors_string .= "))\n";   //pharris 09/28/05
				$R_levels_string = rtrim($R_levels_string,",");
				$R_levels_string .=  ")\n";
				if (!$single_element_enum) {
					$sas_value_label = rtrim($sas_value_label," ");
					$sas_value_label .= ";";
				}
				if (!$single_element_enum) {
					foreach ($select_determining_array as $value) {
						if (preg_match("/([A-Za-z])/",$value)) {
							$select_is_text = true;
						}
					}
				} else {
					foreach ($first_new_line_explode_array as $value) {
						if (preg_match("/([A-Za-z])/",$value)) {
							$select_is_text = true;
						}
					}
				}
			
			
			} else if (preg_match("/\+\+SQL\+\+/",(string)$ob->ELEMENT_ENUM)) {
			
				$select_is_text = true;
				
			}
			
			################################################################################
			################################################################################    
			  
			# If the ELEMENT_VALIDATION_TYPE is a float the data is define as a Number
			if ($ob->ELEMENT_VALIDATION_TYPE == "float" || $ob->ELEMENT_TYPE == "calc" 
				// Also check if the data type of the validation type is "number"
				|| $valTypes[$ob->ELEMENT_VALIDATION_TYPE]['data_type'] == 'number') 
			{
				$spss_string  .= $ob->FIELD_NAME . " (F8.2) ";
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " best32. ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " best12. ;\n";
					$sas_input .= "\t\t" . $ob->FIELD_NAME . "\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " .  $short_name . " best32. ;\n";
					$sas_format .= "\tformat " .  $short_name . " best12. ;\n";
					$sas_input .= "\t\t" .  $short_name . "\n";
				}
				// $spss_data_type_array[$x] = "NUMBER";
				$spss_variable_level[] = $ob->FIELD_NAME . " (SCALE)";
				
			} elseif ($ob->ELEMENT_TYPE == "slider" || $ob->ELEMENT_VALIDATION_TYPE == "int") {
				$spss_string  .= $ob->FIELD_NAME . " (F8) ";
				if(!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " best32. ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " best12. ;\n";
					$sas_input .= "\t\t" . $ob->FIELD_NAME . "\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " .  $short_name . " best32. ;\n";
					$sas_format .= "\tformat " .  $short_name . " best12. ;\n";
					$sas_input .= "\t\t" .  $short_name . "\n";
				}
				// $spss_data_type_array[$x] = "NUMBER";
				$spss_variable_level[] = $ob->FIELD_NAME . " (SCALE)";
			  
			# If the ELEMENT_VALIDATION_TYPE is a DATE a treat the data as a date 
			} elseif ($ob->ELEMENT_VALIDATION_TYPE == "date" || $ob->ELEMENT_VALIDATION_TYPE == "date_ymd" 
				|| $ob->ELEMENT_VALIDATION_TYPE == "date_mdy" || $ob->ELEMENT_VALIDATION_TYPE == "date_dmy") {
				$spss_string  .= $ob->FIELD_NAME . " (SDATE10) ";
				$spss_format_dates .= "FORMATS " . $ob->FIELD_NAME . "(ADATE10).\n";
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " yymmdd10. ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " yymmdd10. ;\n";
					$sas_input .= "\t\t" . $ob->FIELD_NAME . "\n";
					$stata_date_format .= "\ntostring " . $ob->FIELD_NAME . ", replace";
					$stata_date_format .= "\ngen _date_ = date(" .  $ob->FIELD_NAME . ",\"YMD\")\n";
					$stata_date_format .= "drop " . $ob->FIELD_NAME . "\n";
					$stata_date_format .= "rename _date_ " . $ob->FIELD_NAME . "\n";
					$stata_date_format .= "format " . $ob->FIELD_NAME . " %dM_d,_CY\n"; 
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " . $short_name . " yymmdd10. ;\n";
					$sas_format .= "\tformat " . $short_name . " yymmdd10. ;\n";
					$sas_input .= "\t\t" . $short_name . "\n";
					$stata_date_format .= "\ntostring " . $short_name . ", replace";
					$stata_date_format .= "\ngen _date_ = date(" .   $short_name . ",\"YMD\")\n";
					$stata_date_format .= "drop " .  $short_name . "\n";
					$stata_date_format .= "rename _date_ " .  $short_name . "\n";
					$stata_date_format .= "format " . $short_name . " %dM_d,_CY\n"; 
				}
				
			# If the ELEMENT_VALIDATION_TYPE is TIME (military)
			} elseif ($ob->ELEMENT_VALIDATION_TYPE == "time") {
			
				$spss_string .= $ob->FIELD_NAME . " (TIME5) ";	
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " time5. ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " time5. ;\n";
					$sas_input .= "\t\t" . $ob->FIELD_NAME . "\n"; 
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " . $short_name . " time5. ;\n";
					$sas_format .= "\tformat " . $short_name . " time5. ;\n";
					$sas_input .= "\t\t" . $short_name . "\n";
				}
				
			# If the ELEMENT_VALIDATION_TYPE is DATETIME or DATETIME_SECONDS
			// } elseif (substr($ob->ELEMENT_VALIDATION_TYPE, 0, 8) == "datetime") {
			
				
			
			# If the object type is select then the variable $select_is_text is checked to
			# see if it is a TEXT or a NUMBER and treated accordanly.
			} elseif($ob->ELEMENT_TYPE == "yesno" || $ob->ELEMENT_TYPE == "truefalse" || $ob->ELEMENT_TYPE == "select" 
				|| $ob->ELEMENT_TYPE == "advcheckbox" || $ob->ELEMENT_TYPE == "radio") {
				if ($select_is_text) {
					$temp_trim = rtrim("varchar(500)",")");
					# Divides the string to get the number of caracters
					$temp_explode_number = explode("(",$temp_trim);
					$spss_string  .= $ob->FIELD_NAME . " (A" . $temp_explode_number[1] . ") ";
					if (!$large_name) {
						$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " \$". $temp_explode_number[1] .". ;\n";
						$sas_format .= "\tformat " . $ob->FIELD_NAME . " \$". $temp_explode_number[1] .". ;\n";
						$sas_input .= "\t\t" . $ob->FIELD_NAME . " \$\n";
					} elseif($large_name) {
						$sas_informat .= "\tinformat " . $short_name . " \$". $temp_explode_number[1] .". ;\n";
						$sas_format .= "\tformat " . $short_name . " \$". $temp_explode_number[1] .". ;\n";
						$sas_input .= "\t\t" . $short_name . " \$\n";
					}
					// $spss_data_type_array[$x] = "TEXT";
				} else {
					$spss_string .= $ob->FIELD_NAME . " (F3) ";
					if (!$large_name) {
						$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " best32. ;\n";
						$sas_format .= "\tformat " . $ob->FIELD_NAME . " best12. ;\n";
						$sas_input .= "\t\t" . $ob->FIELD_NAME . "\n";
					} elseif ($large_name) {
						$sas_informat .= "\tinformat " . $short_name . " best32. ;\n";
						$sas_format .= "\tformat " . $short_name . " best12. ;\n";
						$sas_input .= "\t\t" . $short_name . "\n";
					}
					// $spss_data_type_array[$x] = "NUMBER";
				}
				

			# If the object type is text a treat the data like a text and look for the length
			# that is specified in the database
			} elseif ($ob->ELEMENT_TYPE == "text" || $ob->ELEMENT_TYPE == "calc" || $ob->ELEMENT_TYPE == "file") {
				$spss_string .= $ob->FIELD_NAME . " (A500) ";		
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " \$500. ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " \$500. ;\n";
					$sas_input .= "\t\t" . $ob->FIELD_NAME . " \$\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " . $short_name . " \$500. ;\n";
					$sas_format .= "\tformat " . $short_name . " \$500. ;\n";
					$sas_input .= "\t\t" . $short_name . " \$\n";
				}
				
				
			# If the object type is textarea a treat the data like a text and specify a large
			# string size.
			} elseif ($ob->ELEMENT_TYPE == "textarea") {
				$spss_string .= $ob->FIELD_NAME . " (A30000) ";
				if (!$large_name) {
					$sas_informat .= "\tinformat " . $ob->FIELD_NAME . " \$5000. ;\n";
					$sas_format .= "\tformat " . $ob->FIELD_NAME . " \$5000. ;\n";
					$sas_input .= "\t\t" . $ob->FIELD_NAME . " \$\n";
				} elseif ($large_name) {
					$sas_informat .= "\tinformat " . $short_name . " \$5000. ;\n";
					$sas_format .= "\tformat " . $short_name . " \$5000. ;\n";
					$sas_input .= "\t\t" . $short_name . " \$\n";
				}
				// $spss_data_type_array[$x] = "TEXT";
			}

		}
		
		//Finish up syntax files
		$spss_string = rtrim($spss_string);
		$spss_string .= ".\n";
		$spss_string .= "\nVARIABLE LEVEL " . implode("\n\t/", $spss_variable_level) . ".\n";
		$spss_string .= "\n" . substr_replace($spss_variable_label,".",-3) . "\n\n";
		$spss_string .= rtrim($value_labels_spss) ;
		$spss_string .= ".\n\n$spss_format_dates\nSET LOCALE=en_us.\nEXECUTE.\n";
		
		$spss_string = str_replace("data_place_holder_name",$data_file_name,$spss_string);	
		
		$sas_read_string .= "%macro removeOldFile(bye); %if %sysfunc(exist(&bye.)) %then %do; proc delete data=&bye.; run; "
						 .  "%end; %mend removeOldFile; %removeOldFile(work.redcap); data REDCAP; "; // Suggested change by Ray Balise
		//$sas_read_string .= "proc delete data=REDCAP;\nrun;\n\ndata REDCAP;"; // Added to prevent deleting all temp files
		//$sas_read_string .= "proc delete data=_ALL_;\nrun;\n\ndata REDCAP;";
		$sas_read_string .= "%let _EFIERR_ = 0; ";
		$sas_read_string .= "infile '" . $data_file_name . "'";
		$sas_read_string .= " delimiter = ',' MISSOVER DSD lrecl=32767 firstobs=1 ; ";
		$sas_read_string .= "\n" . $sas_informat ;
		$sas_read_string .= "\n" . $sas_format;
		$sas_read_string .= "\n" . $sas_input;
		$sas_read_string .= ";\n";
		$sas_read_string .= "if _ERROR_ then call symput('_EFIERR_',\"1\");\n";
		$sas_read_string .= "run;\n\nproc contents;run;\n\n";
		$sas_read_string .= $sas_label_section . "\trun;\n";
		$sas_value_label .= "\n\trun;\n";
		$sas_format_string .= "\trun;\n";
		$sas_read_string .= "\n" . $sas_value_label;
		$sas_read_string .= "\n" . $sas_format_string;
		$sas_read_string .= "\nproc contents data=redcap;";
		$sas_read_string .= "\nproc print data=redcap;";
		$sas_read_string .= "\nrun;\nquit;";
		
		$stata_order = "order " . substr($stata_insheet, 8);
		$stata_insheet .= "using " . "\"" . $data_file_name . "\", nonames";

		$stata_string .= $stata_insheet . "\n\n";
		$stata_string .= "label data " . "\"" . $data_file_name  . "\"" . "\n\n";
		$stata_string .= $stata_value_label . "\n";
		$stata_string .= $stata_inf_label. "\n\n";
		$stata_string .= $stata_date_format . "\n";
		$stata_string .= $stata_var_label . "\n";
		$stata_string .= $stata_order . "\n";
		$stata_string .= "set more off\ndescribe\n";

		$R_string .= "#Read Data\ndata=read.csv('" . $data_file_name . "')\n";
		$R_string .= $R_label_string;
		$R_string .= $R_units_string;
		$R_string .= $R_factors_string;
		$R_string .= $R_levels_string;		
		
		// Return syntax based on package
		if ($stats_package == 'stata') {
			return strip_tags($stata_string);
		} elseif ($stats_package == 'r') {	
			return strip_tags($R_string);
		} elseif ($stats_package == 'sas') {	
			return strip_tags($sas_read_string);
		} elseif ($stats_package == 'spss') {	
			return strip_tags($spss_string);
		} else {
			return '';
		}
	}
*/	
	
	// Get download icon's HTML for a specific export type (e.g, spss, csvraw)
	public static function getDownloadIcon($exportFormat, $dateShifted=false)
	{
		switch ($exportFormat) {
			case 'spss':
				$icon = 'download_spss.gif';
				break;
			case 'r':
				$icon = 'download_r.gif';
				break;
			case 'sas':
				$icon = 'download_sas.gif';
				break;
			case 'stata':
				$icon = 'download_stata.gif';
				break;
			case 'csvlabels':
				$icon = ($dateShifted) ? 'download_csvexcel_labels_ds.gif' : 'download_csvexcel_labels.gif';
				break;
			case 'csvraw':
				$icon = ($dateShifted) ? 'download_csvexcel_raw_ds.gif' : 'download_csvexcel_raw.gif';
				break;
			default:
				$icon = ($dateShifted) ? 'download_csvdata_ds.gif' : 'download_csvdata.gif';
		}
		// Return image html
		return RCView::img(array('src'=>$icon));
	}
	

	// Store the export file after getting the docs_id from redcap_docs
	public static function storeExportFile($original_filename, $file_content, $archiveFile=false, $dateShiftDates=false)
	{
		global $edoc_storage_option;
		
		## Create the stored name of the file as it wll be stored in the file system
		$stored_name = date('YmdHis') . "_pid" . PROJECT_ID . "_" . generateRandomHash(6) . getFileExt($original_filename, true);
		$file_extension = getFileExt($original_filename);
		$mime_type = (strtolower($file_extension) == 'csv') ? 'application/csv' : 'application/octet-stream';
		
		// If file is UTF-8 encoded, then add BOM
		// Do NOT use addBOMtoUTF8() on Stata syntax file (.do) because BOM causes issues in syntax file
		if (strtolower($file_extension) != 'do') {
			$file_content = addBOMtoUTF8($file_content);
		}
		
		// If Gzip enabled, then gzip the file and append filename with .gz extension
		list ($file_content, $stored_name, $gzipped) = gzip_encode_file($file_content, $stored_name);
		
		// Get file size in bytes
		$docs_size = strlen($file_content);
		
		// Add file to file system
		if ($edoc_storage_option == '0') {
			// Store locally
			$fp = fopen(EDOC_PATH . $stored_name, 'w');
			if ($fp !== false && fwrite($fp, $file_content) !== false) {
				// Close connection
				fclose($fp);
			} else {
				// Send error response
				return false;
			}	
		// Add file to S3
		} elseif ($edoc_storage_option == '2') {	
			global $amazon_s3_key, $amazon_s3_secret, $amazon_s3_bucket;
			$s3 = new S3($amazon_s3_key, $amazon_s3_secret, SSL);
			if (!$s3->putObject($file_content, $amazon_s3_bucket, $stored_name, S3::ACL_PUBLIC_READ_WRITE)) {
				// Send error response
				return false;
			}	
		} else {
			// Store using WebDAV
			require_once (APP_PATH_LIBRARIES . "WebdavClient.php");
			require (APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php');
			$wdc = new WebdavClient();
			$wdc->set_server($webdav_hostname);
			$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
			$wdc->set_user($webdav_username);
			$wdc->set_pass($webdav_password);
			$wdc->set_protocol(1); // use HTTP/1.1
			$wdc->set_debug(false); // enable debugging?
			if (!$wdc->open()) {
				// Send error response
				return false;
			}
			if (substr($webdav_path,-1) != '/') {
				$webdav_path .= '/';
			}
			$http_status = $wdc->put($webdav_path . $stored_name, $file_content);
			$wdc->close();
		}
		## Add file info to edocs_metadata table
		// If not archiving file in File Repository, then set to be deleted in 1 hour
		$delete_time = ($archiveFile ? "" : NOW);		
		// Add to table
		$sql = "insert into redcap_edocs_metadata (stored_name, mime_type, doc_name, doc_size, file_extension, project_id, 
				stored_date, delete_date, gzipped) values ('" . prep($stored_name) . "', '$mime_type', '" . prep($original_filename) . "', 
				'" . prep($docs_size) . "', '" . prep($file_extension) . "', " . PROJECT_ID . ", '" . NOW . "', " . checkNull($delete_time) . ", $gzipped)";
		if (!db_query($sql)) {
			// Send error response
			return false;
		}
		// Get edoc_id
		$edoc_id = db_insert_id();
		## Add to doc_to_edoc table
		// Set flag if data is date shifted
		$dateShiftFlag = ($dateShiftDates ? "DATE_SHIFT" : "");
		// Set "comment" in docs table
		if (strtolower($file_extension) == 'csv') {
			$docs_comment = "Data export file created by " . USERID . " on " . date("Y-m-d-H-i-s");
		} else {
			if ($file_extension == 'sps') {
				$stats_package_name = 'Spss';
			} elseif ($file_extension == 'do') {
				$stats_package_name = 'Stata';
			} else {
				$stats_package_name = camelCase($file_extension);
			}
			$docs_comment = "$stats_package_name syntax file created by " . USERID . " on " . date("Y-m-d-H-i-s");
		}
		// Archive in redcap_docs table
		$sql = "INSERT INTO redcap_docs (project_id, docs_name, docs_file, docs_date, docs_size, docs_comment, docs_type, 
				docs_rights, export_file, temp) VALUES (" . PROJECT_ID . ", '" . prep($original_filename) . "', NULL, '" . TODAY . "', 
				'$docs_size', '" . prep($docs_comment). "', '$mime_type', " . checkNull($dateShiftFlag) . ", 1, 
				" . checkNull($archiveFile ? "0" : "1") . ")";
		if (db_query($sql)) {
			$docs_id = db_insert_id();
			// Add to redcap_docs_to_edocs also
			$sql = "insert into redcap_docs_to_edocs (docs_id, doc_id) values ($docs_id, $edoc_id)";
			db_query($sql);
		} else {
			// Could not store in table, so remove from edocs_metadata also
			db_query("delete from redcap_edocs_metadata where doc_id = $edoc_id");
			return false;
		}
		// Return successful response of docs_id from redcap_docs table
		return $docs_id;
	}
	

	// Return array list of all fields in current project that should be removed due to De-Identified data export rights
	public static function deidFieldsToRemove($fields=array(), $removeOnlyIdentifiers=false, 
											  $removeDateFields=true, $removeRecordIdIfIdentifier=true)
	{
		global $Proj;
		// Put all fields to remove in an array
		$fieldsToRemove = array();
		// If $fields is empty, assume ALL fields
		if (empty($fields) || !is_array($fields)) {
			$fields = array_keys($Proj->metadata);
		}
		// Loop through fields
		foreach ($fields as $field) {
			// Get field type and validation type
			$this_field_type = $Proj->metadata[$field]['element_type'];
			$this_val_type = $Proj->metadata[$field]['element_validation_type'];
			$this_phi = $Proj->metadata[$field]['field_phi'];
			// Skip record ID field (if flag is set to FALSE)
			if (!$removeRecordIdIfIdentifier && $field == $Proj->table_pk) continue;
			// Check if needs to be removed
			if (// Identifier field
				($this_phi == '1')
				// Unvalidated text field (freeform text)
				|| 	(!$removeOnlyIdentifiers && $this_field_type == 'text' && $this_val_type == '')
				// Notes field
				|| 	(!$removeOnlyIdentifiers && $this_field_type == 'textarea')
				// Date/time field (if flag is set to TRUE)
				|| 	(!$removeOnlyIdentifiers && $removeDateFields && $this_field_type == 'text' && substr($this_val_type, 0, 4) == 'date')) 
			{
				// Remove the field from $fields
				$fieldsToRemove[] = $field;
			}
		}
		// Return array of fields to remove
		return $fieldsToRemove;
	}
	

	// Return docs_id of associated data file (either raw or label) for a specified stats package.
	// Pass an array of rows from redcap_docs + the stats package in all caps (SPSS, R, SAS, STATA)
	public static function getDataFileDocId($stats_package, $export_files_info, $get_labels_file=false)
	{
		global $app_name;
		if ($get_labels_file) {
			// Get the labels data file
			$search_phrase = "_DATA_LABELS_20";
			$search_phrase_legacy_prefix = "DATA_LABELS_".strtoupper($app_name)."_";
		} elseif ($stats_package == 'R') {
			// Get the raw data file with headers
			$search_phrase = "_DATA_20";
			$search_phrase_legacy_prefix = "DATA_WH".strtoupper($app_name)."_";
		} else {
			// Get the raw data file WITHOUT headers
			$search_phrase = "_DATA_NOHDRS_20";
			$search_phrase_legacy_prefix = "DATA_".strtoupper($app_name)."_";
		}
		// Loop through the array of files
		foreach ($export_files_info as $this_file) {
			// Ignore other stats syntax files
			if ($this_file['docs_type'] != 'DATA') continue;
			// If did not find correct data file, keep looping till we get it
			if (strpos($this_file['docs_name'], $search_phrase_legacy_prefix) === 0 
				|| strpos($this_file['docs_name'], $search_phrase) !== false) {
				// Found it, so return the docs_id
				return $this_file['docs_id'];
			}
		}
		// Could not find it for some reason
		return '';
	}
	
/*
	// Return html to render the left-hand menu panel for Reports
	public static function outputReportPanel()
	{
		$reportsMenuList = self::getReportNames(null, !SUPER_USER);
		if (!empty($reportsMenuList)) {
			$reportsList .= "<div class='menubox'>";
			// Loop through each report
			$i = 1;
			foreach ($reportsMenuList as $this_report_id=>$this_report_name) {
				$reportsList .= "<div class='hang'>
									<span class='reportnum'>".$i++.")</span> 
									<a href='" . APP_PATH_WEBROOT . "DataExport/index.php?pid=".PROJECT_ID."&report_id=$this_report_id'>".RCView::escape($this_report_name)."</a>
								 </div>";
			}
			$reportsList .= "</div>";
		}
		return $reportsList;
	}
*/	

	// Hidden dialog for help with filters and AND/OR logic
	public static function renderFilterHelpDialog()
	{
		global $lang;
		return 	RCView::div(array('class'=>'simpleDialog', 'title'=>$lang['report_builder_119'], 'id'=>'filter_help'),
					$lang['report_builder_120'] . RCView::br() . RCView::br() .
					$lang['report_builder_122'] . RCView::br() . RCView::br() .
					$lang['report_builder_121'] . RCView::br() . RCView::br() .
					$lang['report_builder_123'] . RCView::br() . RCView::br() .
					$lang['report_builder_124']
				);
		
	}

        // LS added functions for Longitudinal Reports
        
        // Longitudinal field spec
        private static function longitudinalEventsFormsFields() {
            global $Proj;
            
            $mapping = array();
            
            foreach ($Proj->eventInfo as $eventId => $armEventProperties) {
                foreach ($Proj->eventsForms[$eventId] as $formSeq => $formName) {
                    foreach ($Proj->forms[$formName]['fields'] as $fieldName => $fieldLabel) {
                        // Skip descriptive fields
			if ($Proj->metadata[$fieldName]['element_type'] !== 'descriptive') {
                            $fieldRef = "[".$Proj->getUniqueEventNames($eventId)."][".$fieldName."]";
                            $fieldAttrs = array(
                                'arm_id' => $armEventProperties['arm_id'],
                                'arm_name' => $armEventProperties['arm_name'],
                                'event_id' => $eventId,
                                'unique_event_name' => $Proj->getUniqueEventNames($eventId),
                                'event_name' => $armEventProperties['name_ext'],
                                'form_name' => $formName,
                                'form_title' => cleanHtml(strip_tags($Proj->forms[$formName]['menu'])),
                                'field_name' => $fieldName,
                                'field_label' => cleanHtml(strip_tags($fieldLabel), true)
                            );
                            $mapping[$fieldRef] = $fieldAttrs;
                        }
                    }
                }
            }
/*            foreach ($Proj->eventInfo as $eventId => $armEventProperties) {
                $mapping[$eventId]['event_name'] = $armEventProperties['name_ext'];
                $mapping[$eventId]['unique_event_name'] = $Proj->getUniqueEventNames($eventId);
                
                foreach ($Proj->eventsForms[$eventId] as $formSeq => $formName) {
                    $mapping[$eventId]['forms'][$formName]['form_name'] = $formName;
                    $mapping[$eventId]['forms'][$formName]['title'] = $Proj->forms[$formName]['menu'];

                    foreach ($Proj->forms[$formName]['fields'] as $fieldName => $fieldLabel) {
                        
                        // Skip descriptive fields
			if ($Proj->metadata[$fieldName]['element_type'] !== 'descriptive') {
                            $mapping[$eventId]['forms'][$formName]['fields'][$fieldName]['field_name'] = $fieldName;
                            $mapping[$eventId]['forms'][$formName]['fields'][$fieldName]['element_label'] = cleanHtml(strip_tags($fieldLabel));
                        }
                    }
                }
            }*/
            return $mapping;
        }

	// Get all options for drop-down displaying all project fields
	public static function getFieldDropdownOptions($removeCheckboxFields=false)
	{
		global $Proj, $lang;
                
		// Build an array of drop-down options listing all REDCap fields
		$rc_fields = array(''=>'-- '.$lang['random_02'].' --');
		$rc_fields["Record ID Field"][$Proj->table_pk] = $Proj->table_pk.' "'.self::cleanString($Proj->table_pk_label).'"';
		
                $eventFieldList = self::longitudinalEventsFormsFields();
                
                foreach ($eventFieldList as $fieldRef => $fieldAttrs) {
                    $event = $fieldAttrs['event_name'];
                    $form = $fieldAttrs['form_title'];
                    $fieldName = $fieldAttrs['field_name'];
                    $label = self::cleanString($fieldAttrs['field_label']);
                    
                    if (strlen($label) > 65) {
                        $label = trim(substr($label, 0, 47)) . "... " . trim(substr($label, -15));
                    }
                    
                    // Skip if pk field (added separately)
                    if ($fieldName == $Proj->table_pk) continue;
                    
                    // Skip checkbox fields if flag is set
                    if ($removeCheckboxFields && $Proj->metadata[$fieldName]['element_type'] == 'checkbox') continue;
		
                    // Add to fields/forms array. Get form of field.
                    $rc_fields["$event - $form"][$fieldRef] = "$fieldRef \"$label\"";
		}
		// Return all options
		return $rc_fields;
	}
        
        public static function getEventFromEventField($eventField) {
            return preg_replace('/\[|(\]\[\w+\])|^\w+$/', '', $eventField);  // e.g. replace [baseline_arm_1][dob] with baseline_arm_1
        }
        
        public static function getFieldFromEventField($eventField) {
            return preg_replace('/(\[\w+\]\[)|\]/', '', $eventField);  // e.g. replace [baseline_arm_1][dob] with dob
        }

        /**
         * LongitudinalReports::save
         * @param type $saveReportsArray an array of report config parameters rptid => p1 = v1, p2 = v2
         * @return boolean
         */
        public static function save($saveReportsArray) {
                $success = true;
    
                $saveArray = array();
                
                // Add 'event' level so array is in format expected by REDCap::saveData
                // From v7.0.0 needs to be non-zero due to Records.php line 3688 if ($this_event_id == 'repeat_instances') {
                $i=1;
                foreach ($saveReportsArray as $rptParams) {
                    $saveArray[$rptParams['report_id']][$i++] = $rptParams;
                }
                
                $result = REDCap::saveData(
                        LR_REPORT_DATA_PROJECT_ID, // * [@param int $project_id - (optional) Manually supplied project_id for this project.]
                        'array',                   // * @param string $dataFormat - Default 'array'. Format of the data provided (array, csv, json, xml).
                        $saveArray,                // * @param string/array $data - The data being imported (in the specified format).
                        'overwrite'                // * @param string $overwriteBehavior - "normal" or "overwrite" - Determines if blank values overwrite existing non-blank values.
                        );

                if (isset($result['errors']) && count($result['errors']) > 0) {
                    $success = false;
                    REDCap::logEvent('Longitudinal Reports save failed', print_r($result['errors'], true).PHP_EOL.' '.print_r($saveArray, true));
                }

                return $success;
        }

        public static function getNewReportIdAndOrder($project_id) {

            // When adding new report - get eventid, next report id and next project report_order
            $allRpts = REDCap::getData(
                            LR_REPORT_DATA_PROJECT_ID, 
                            'array',  // return_format
                            null,     // records 
                            array('project_id', 'report_order'));// fields 

            $report_id = 0;
            $report_order = 0;
            $eventId = 0;

            foreach ($allRpts as $rId => $reportEventRecord) {
                $report_id++;
                foreach ($reportEventRecord as $eventId => $rptSpec) {
                    $pId = $rptSpec['project_id'];
                    $order = $rptSpec['report_order'];

                    if ($pId == $project_id) {
                        $report_order = ($order > $report_order) ? $order : $report_order;
                    }
                }
            }
            
            $report_id++;
            $report_order++;
            
            return array($eventId, $report_id, $report_order);
        }
        
        public static function getSurveyUrlDropdownOptions() {
            global $Proj;
            $surveyTitles = array();
            foreach ($Proj->eventsForms as $eventId => $eventForms) {
                $eventName = $Proj->eventInfo[$eventId]['name_ext'];
                foreach ($eventForms as $formName) {
                    $title = $Proj->forms[$formName]['menu'];
                    if (isset($Proj->forms[$formName]['survey_id'])) {
                        $surveyTitles["$eventId|$formName"] = "$title ($eventName)";
                   }
                }
            }
            return $surveyTitles;
        }
        
        /**
         * Strip all tags, convert line break to space and trim string
         * @param string The string to clean
         * @return string The cleaned string
         */
        public static function cleanString($str) {
            return trim(str_replace(array("\n", "\r"), ' ', strip_tags2($str)));
        }
        
        public static function checkReportStoreProjectConfig() {
            $storePid = LR_REPORT_DATA_PROJECT_ID;

            $sql = "select app_title, repeatforms from redcap_projects where project_id = $storePid ";
            $q = db_query($sql);

            // store project exists?
            if ($q->num_rows === 0) { return "Report store project id ($storePid) does not exist. Fix the setting in config.php."; }

            // not set to be longitudinal?
            $row = db_fetch_assoc($q);
            if ($row['repeatforms'] !== '0') { 
                return "Report store project ({$row['app_title']}) <strong>must not</strong> be longitudinal."; 
            }
            
            // contains expected fields
            $expectedFields = array(
                'report_id','project_id','deleted','title','report_order',
                'user_access','user_access_dags','user_access_roles','user_access_users',
                'fields','output_dags','output_survey_fields','output_schedule_dates','output_survey_urls',
                'limiter_fields','advanced_logic','filter_dags',
                'orderby_field1','orderby_sort1','orderby_field2','orderby_sort2','orderby_field3','orderby_sort3',
                'update_by','update_at'
            );
            
            $notFound = array();
            $storeFields = REDCap::getDataDictionary($storePid, 'array');
            foreach ($expectedFields as $f) {
                if (!array_key_exists($f, $storeFields)) {
                    $notFound[] = $f;
                }
            }
            if (count($notFound) > 0) { 
                return "Report store project ({$row['app_title']}) is missing the following expected fields: ".
                        implode(', ', $notFound); 
            }
            
            return true;
        }
}