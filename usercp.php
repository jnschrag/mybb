<?php
/**
 * MyBB 1.2
 * Copyright � 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

define("IN_MYBB", 1);

$templatelist = "usercp,usercp_home,usercp_nav,usercp_profile,error_nopermission,buddy_online,buddy_offline,usercp_changename,usercp_nav_changename";
$templatelist .= ",usercp_usergroups_memberof_usergroup,usercp_usergroups_memberof,usercp_usergroups_joinable_usergroup,usercp_usergroups_joinable,usercp_usergroups";
$templatelist .= ",usercp_nav_messenger,usercp_nav_changename,usercp_nav_profile,usercp_nav_misc,usercp_usergroups_leader_usergroup,usercp_usergroups_leader,usercp_currentavatar,usercp_reputation";


require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("usercp");

if($mybb->user['uid'] == 0 || $mybb->usergroup['canusercp'] == "no")
{
	error_no_permission();
}

if(!$mybb->user['pmfolders'])
{
	$mybb->user['pmfolders'] = "1**".$lang->folder_inbox."$%%$2**".$lang->folder_sent_items."$%%$3**".$lang->folder_drafts."$%%$4**".$lang->folder_trash;
	$db->update_query("users", array('pmfolders' => $mybb->user['pmfolders']), "uid='".$mybb->user['uid']."'");
}

$errors = '';

usercp_menu();

$plugins->run_hooks("usercp_start");

if($mybb->input['action'] == "do_editsig" && $mybb->request_method == "post")
{
	$parser_options = array(
		'allow_html' => $mybb->settings['sightml'],
		'filter_badwords' => 'yes',
		'allow_mycode' => $mybb->settings['sigmycode'],
		'allow_smilies' => $mybb->settings['sigsmilies'],
		'allow_imgcode' => $mybb->settings['sigimgcode']
	);
	$parsed_sig = $parser->parse_message($mybb->input['signature'], $parser_options);
	if((($mybb->settings['sigimgcode'] == "no" && $mybb->settings['sigsmilies'] != 'yes') &&
		substr_count($parsed_sig, "<img") > 0) ||
		(($mybb->settings['sigimgcode'] == "yes" || $mybb->settings['sigsmilies'] == 'yes') &&
		substr_count($parsed_sig, "<img") > $mybb->settings['maxsigimages'])
	)
	{
		if($mybb->settings['sigimgcode'] == "yes")
		{
			$imgsallowed = $mybb->settings['maxsigimages'];
		}
		else
		{
			$imgsallowed = 0;
		}
		$lang->too_many_sig_images2 = sprintf($lang->too_many_sig_images2, $imgsallowed);
		$error = inline_error($lang->too_many_sig_images." ".$lang->too_many_sig_images2);
		$mybb->input['preview'] = 1;
	}
	elseif($mybb->settings['siglength'] > 0)
	{
		if($mybb->settings['sigcountmycode'] == "yes")
		{
			$parsed_sig = $parser->text_parse_message($mybb->input['signature']);
		}
		else
		{
			$parsed_sig = $mybb->input['signature'];
		}
		$parsed_sig = preg_replace("#\s#", "", $parsed_sig);
		$sig_length = my_strlen($parsed_sig);
		if($sig_length > $mybb->settings['siglength'])
		{
			$lang->sig_too_long = sprintf($lang->sig_too_long, $mybb->settings['siglength']);
			if($sig_length - $mybb->settings['siglength'] > 1)
			{
				$lang->sig_too_long .= sprintf($lang->sig_remove_chars_plural, $sig_length-$mybb->settings['siglength']);
			}
			else
			{
				$lang->sig_too_long .= $lang->sig_remove_chars_singular;
			}
			$error = inline_error($lang->sig_too_long);
		}
	}
	if($error || $mybb->input['preview'])
	{
		$mybb->input['action'] = "editsig";
	}
}

// Make navigation
add_breadcrumb($lang->nav_usercp, "usercp.php");

switch($mybb->input['action'])
{
	case "profile":
	case "do_profile":
		add_breadcrumb($lang->nav_profile);
		break;
	case "options":
	case "do_options":
		add_breadcrumb($lang->nav_options);
		break;
	case "email":
	case "do_email":
		add_breadcrumb($lang->nav_email);
		break;
	case "password":
	case "do_password":
		add_breadcrumb($lang->nav_password);
		break;
	case "changename":
	case "do_changename":
		add_breadcrumb($lang->nav_changename);
		break;
	case "subscriptions":
		add_breadcrumb($lang->nav_subthreads);
		break;
	case "forumsubscriptions":
		add_breadcrumb($lang->nav_forumsubscriptions);
		break;
	case "editsig":
	case "do_editsig":
		add_breadcrumb($lang->nav_editsig);
		break;
	case "avatar":
	case "do_avatar":
		add_breadcrumb($lang->nav_avatar);
		break;
	case "notepad":
	case "do_notepad":
		add_breadcrumb($lang->nav_notepad);
		break;
	case "editlists":
	case "do_editlists":
		add_breadcrumb($lang->nav_editlists);
		break;
	case "drafts":
		add_breadcrumb($lang->nav_drafts);
		break;
	case "usergroups":
		add_breadcrumb($lang->nav_usergroups);
		break;
	case "attachments":
		add_breadcrumb($lang->nav_attachments);
		break;
}

if($mybb->input['action'] == "do_profile" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("usercp_do_profile_start");

	if($mybb->input['away'] == "yes" && $mybb->settings['allowaway'] != "no")
	{
		$awaydate = time();
		if($mybb->input['awayday'])
		{
			if(!$mybb->input['awaymonth'])
			{
				$mybb->input['awaymonth'] = my_date('n', $awaydate);
			}
			
			if(!$mybb->input['awayyear'])
			{
				$mybb->input['awayyear'] = my_date('Y', $awaydate);
			}
			
			$returntimestamp = gmmktime(0, 0, 0, $mybb->input['awaymonth'], $mybb->input['awayday'], $mybb->input['awayyear']);
			$awaytimestamp = gmmktime(0, 0, 0, my_date('n', $awaydate), my_date('j', $awaydate), my_date('Y', $awaydate));
			if ($returntimestamp < $awaytimestamp && $mybb->input['awayyear'] < my_date("Y"))
			{
				error($lang->error_usercp_return_date_past);
			}
			$returndate = intval($mybb->input['awayday'])."-".intval($mybb->input['awaymonth'])."-".intval($mybb->input['awayyear']);
		}
		else
		{
			$returndate = "";
		}
		$away = array(
			"away" => "yes",
			"date" => $awaydate,
			"returndate" => $returndate,
			"awayreason" => $mybb->input['awayreason']
		);
	}
	else
	{
		$away = array(
			"away" => "no",
			"date" => '',
			"returndate" => '',
			"awayreason" => ''
		);
	}

	$bday = array(
		"day" => $mybb->input['bday1'],
		"month" => $mybb->input['bday2'],
		"year" => $mybb->input['bday3']
	);

	// Set up user handler.
	require_once "inc/datahandlers/user.php";
	$userhandler = new UserDataHandler("update");

	$user = array(
		"uid" => $mybb->user['uid'],
		"website" => $mybb->input['website'],
		"icq" => intval($mybb->input['icq']),
		"aim" => $mybb->input['aim'],
		"yahoo" => $mybb->input['yahoo'],
		"msn" => $mybb->input['msn'],
		"birthday" => $bday,
		"away" => $away,
		"profile_fields" => $mybb->input['profile_fields']
	);

	if($mybb->usergroup['cancustomtitle'] == "yes")
	{
		if($mybb->input['usertitle'] != '')
		{
			$user['usertitle'] = $mybb->input['usertitle'];
		}
		else if($mybb->input['reverttitle'])
		{
			$user['usertitle'] = '';
		}
	}
	$userhandler->set_data($user);

	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
		$errors = inline_error($errors);
		$mybb->input['action'] = "profile";
	}
	else
	{
		$userhandler->update_user();

		$plugins->run_hooks("usercp_do_profile_end");
		redirect("usercp.php", $lang->redirect_profileupdated);
	}
}

if($mybb->input['action'] == "profile")
{
	if($errors)
	{
		$user = $mybb->input;
		$bday = array();
		$bday[0] = $mybb->input['bday1'];
		$bday[1] = $mybb->input['bday2'];
		$bday[2] = intval($mybb->input['bday3']);
	}
	else
	{
		$user = $mybb->user;
		$bday = explode("-", $user['birthday']);
	}

	$plugins->run_hooks("usercp_profile_start");

	$bdaysel = '';
	for($i = 1; $i <= 31; ++$i)
	{
		if($bday[0] == $i)
		{
			$bdaydaysel .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$bdaydaysel .= "<option value=\"$i\">$i</option>\n";
		}
	}
	$bdaymonthsel[$bday[1]] = "selected";

	if($user['website'] == "" || $user['website'] == "http://")
	{
		$user['website'] = "http://";
	}
	else
	{
		$user['website'] = htmlspecialchars_uni($user['website']);
	}

	if($user['icq'] != "0")
	{
		$user['icq'] = intval($user['icq']);
	}
	if($user['icq'] == 0)
	{
		$user['icq'] = "";
	}
	if($errors)
	{
		$user['msn'] = htmlspecialchars_uni($user['msn']);
		$user['aim'] = htmlspecialchars_uni($user['aim']);
		$user['yahoo'] = htmlspecialchars_uni($user['yahoo']);
	}
	if($mybb->settings['allowaway'] != "no")
	{
		if($errors)
		{
			if($user['away'] == "yes")
			{
				$awaycheck['yes'] = "checked=\"checked\"";
			}
			else
			{
				$awaycheck['no'] = "checked=\"checked\"";
			}
			$returndate = array();
			$returndate[0] = $mybb->input['awayday'];
			$returndate[1] = $mybb->input['awaymonth'];
			$returndate[2] = intval($mybb->input['awayyear']);
			$user['awayreason'] = htmlspecialchars_uni($mybb->input['awayreason']);
		}
		else
		{

			if($mybb->user['away'] == "yes")
			{
				$awaydate = my_date($mybb->settings['dateformat'], $mybb->user['awaydate']);
				$awaycheck['yes'] = "checked=\"checked\"";
				$awaynotice = sprintf($lang->away_notice_away, $awaydate);
			}
			else
			{
				$awaynotice = $lang->away_notice;
				$awaycheck['no'] = "checked=\"checked\"";
			}
			$returndate = explode("-", $mybb->user['returndate']);
		}
		$returndatesel = '';
		for($i = 1; $i <= 31; ++$i)
		{
			if($returndate[0] == $i)
			{
				$returndatesel .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
			}
			else
			{
				$returndatesel .= "<option value=\"$i\">$i</option>\n";
			}
		}
		$returndatemonthsel[$returndate[1]] = "selected";

		eval("\$awaysection = \"".$templates->get("usercp_profile_away")."\";");
	}
	// Custom profile fields baby!
	$altbg = "trow1";
	$requiredfields = '';
	$customfields = '';
	$query = $db->simple_select("profilefields", "*", "editable='yes'", array('order_by' => 'disporder'));
	while($profilefield = $db->fetch_array($query))
	{
		$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
		$profilefield['description'] = htmlspecialchars_uni($profilefield['description']);
		$thing = explode("\n", $profilefield['type'], "2");
		$type = $thing[0];
		$options = $thing[1];
		$field = "fid{$profilefield['fid']}";
		$select = '';
		if($errors)
		{
			$userfield = $mybb->input['profile_fields'][$field];
		}
		else
		{
			$userfield = $user[$field];
		}
		if($type == "multiselect")
		{
			if($errors)
			{
				$useropts = $userfield;
			}
			else
			{
				$useropts = explode("\n", $userfield);
			}
			if(is_array($useropts))
			{
				foreach($useropts as $key => $val)
				{
					$seloptions[$val] = $val;
				}
			}
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					$val = trim($val);
					$val = str_replace("\n", "\\n", $val);

					$sel = "";
					if($val == $seloptions[$val])
					{
						$sel = " selected=\"selected\"";
					}
					$select .= "<option value=\"$val\"$sel>$val</option>\n";
				}
				if(!$profilefield['length'])
				{
					$profilefield['length'] = 3;
				}
				$code = "<select name=\"profile_fields[$field][]\" size=\"{$profilefield['length']}\" multiple=\"multiple\">$select</select>";
			}
		}
		elseif($type == "select")
		{
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					$val = trim($val);
					$val = str_replace("\n", "\\n", $val);
					$sel = "";
					if($val == $userfield)
					{
						$sel = " selected=\"selected\"";
					}
					$select .= "<option value=\"$val\"$sel>$val</option>";
				}
				if(!$profilefield['length'])
				{
					$profilefield['length'] = 1;
				}
				$code = "<select name=\"profile_fields[$field]\" size=\"{$profilefield['length']}\">$select</select>";
			}
		}
		elseif($type == "radio")
		{
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					$checked = "";
					if($val == $userfield)
					{
						$checked = " checked=\"checked\"";
					}
					$code .= "<input type=\"radio\" class=\"radio\" name=\"profile_fields[$field]\" value=\"$val\"$checked /> <span class=\"smalltext\">$val</span><br />";
				}
			}
		}
		elseif($type == "checkbox")
		{
			if($errors)
			{
				$useropts = $userfield;
			}
			else
			{
				$useropts = explode("\n", $userfield);
			}
			if(is_array($useropts))
			{
				foreach($useropts as $key => $val)
				{
					$seloptions[$val] = $val;
				}
			}
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					$checked = "";
					if($val == $seloptions[$val])
					{
						$checked = " checked=\"checked\"";
					}
					$code .= "<input type=\"checkbox\" class=\"checkbox\" name=\"profile_fields[$field][]\" value=\"$val\"$checked /> <span class=\"smalltext\">$val</span><br />";
				}
			}
		}
		elseif($type == "textarea")
		{
			$value = htmlspecialchars_uni($userfield);
			$code = "<textarea name=\"profile_fields[$field]\" rows=\"6\" cols=\"30\" style=\"width: 95%\">$value</textarea>";
		}
		else
		{
			$value = htmlspecialchars_uni($userfield);
			$code = "<input type=\"text\" name=\"profile_fields[$field]\" class=\"textbox\" size=\"{$profilefield['length']}\" maxlength=\"{$profilefield['maxlength']}\" value=\"$value\" />";
		}
		if($profilefield['required'] == "yes")
		{
			eval("\$requiredfields .= \"".$templates->get("usercp_profile_customfield")."\";");
		}
		else
		{
			eval("\$customfields .= \"".$templates->get("usercp_profile_customfield")."\";");
		}
		$altbg = alt_trow();
		$code = "";
		$select = "";
		$val = "";
		$options = "";
		$expoptions = "";
		$useropts = "";
		$seloptions = "";
	}
	if($customfields)
	{
		eval("\$customfields = \"".$templates->get("usercp_profile_profilefields")."\";");
	}

	if($mybb->usergroup['cancustomtitle'] == "yes")
	{
		if($mybb->usergroup['usertitle'] == "")
		{
			$query = $db->simple_select("usertitles", "*", "posts <='".$mybb->user['postnum']."'", array('order_by' => 'posts', 'order_dir' => 'DESC', 'limit' => 1));
			$utitle = $db->fetch_array($query);
			$defaulttitle = $utitle['title'];
		}
		else
		{
			$defaulttitle = $mybb->usergroup['usertitle'];
		}
		if(empty($user['usertitle']))
		{
			$lang->current_custom_usertitle = '';
		}
		else
		{
			if($errors)
			{
				$newtitle = htmlspecialchars_uni($user['usertitle']);
				$user['usertitle'] = $mybb->user['usertitle'];
			}
		}
		eval("\$customtitle = \"".$templates->get("usercp_profile_customtitle")."\";");
	}
	else
	{
		$customtitle = "";
	}
	eval("\$editprofile = \"".$templates->get("usercp_profile")."\";");
	$plugins->run_hooks("usercp_profile_end");
	output_page($editprofile);
}

if($mybb->input['action'] == "do_options" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("usercp_do_options_start");

	// Set up user handler.
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler("update");

	$user = array(
		"uid" => $mybb->user['uid'],
		"style" => intval($mybb->input['style']),
		"dateformat" => intval($mybb->input['dateformat']),
		"timeformat" => intval($mybb->input['timeformat']),
		"timezone" => $db->escape_string($mybb->input['timezoneoffset']),
		"language" => $mybb->input['language']
	);

	$user['options'] = array(
		"allownotices" => $mybb->input['allownotices'],
		"hideemail" => $mybb->input['hideemail'],
		"subscriptionmethod" => $mybb->input['subscriptionmethod'],
		"invisible" => $mybb->input['invisible'],
		"dstcorrection" => $mybb->input['dstcorrection'],
		"threadmode" => $mybb->input['threadmode'],
		"showsigs" => $mybb->input['showsigs'],
		"showavatars" => $mybb->input['showavatars'],
		"showquickreply" => $mybb->input['showquickreply'],
		"remember" => $mybb->input['remember'],
		"receivepms" => $mybb->input['receivepms'],
		"pmnotice" => $mybb->input['pmnotice'],
		"daysprune" => $mybb->input['daysprune'],
		"showcodebuttons" => intval($mybb->input['showcodebuttons']),
		"pmnotify" => $mybb->input['pmnotify'],
		"showredirect" => $mybb->input['showredirect']
	);

	if($mybb->settings['usertppoptions'])
	{
		$user['options']['tpp'] = intval($mybb->input['tpp']);
	}

	if($mybb->settings['userpppoptions'])
	{
		$user['options']['ppp'] = intval($mybb->input['ppp']);
	}

	$userhandler->set_data($user);


	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
		$errors = inline_error($errors);
		$mybb->input['action'] = "options";
	}
	else
	{
		$userhandler->update_user();

		// If the cookie settings are different, re-set the cookie
		if($mybb->input['remember'] != $mybb->user['remember'])
		{
			$mybb->user['remember'] = $mybb->input['remember'];
			// Unset the old one
			my_unsetcookie("mybbuser");
			// Set the new one
			if($mybb->input['remember'] == "yes")
			{
				my_setcookie("mybbuser", $mybb->user['uid']."_".$mybb->user['loginkey'], null, true);
			}
			else
			{
				my_setcookie("mybbuser", $mybb->user['uid']."_".$mybb->user['loginkey'], -1, true);
			}
		}

		$plugins->run_hooks("usercp_do_options_end");

		redirect("usercp.php", $lang->redirect_optionsupdated);
	}
}

if($mybb->input['action'] == "options")
{
	$plugins->run_hooks("usercp_options_start");

	if($errors != '')
	{
		$user = $mybb->input;
	}
	else
	{
		$user = $mybb->user;
	}
	$languages = $lang->get_languages();
	$langoptions = '';
	foreach($languages as $lname => $language)
	{
		$sel = "";
		if($user['language'] == $lname)
		{
			$sel = " selected=\"selected\"";
		}
		$langoptions .= "<option value=\"$lname\"$sel>$language</option>\n";
	}

	// Lets work out which options the user has selected and check the boxes
	if($user['allownotices'] == "yes")
	{
		$allownoticescheck = "checked=\"checked\"";
	}
	else
	{
		$allownoticescheck = "";
	}

	if($user['invisible'] == "yes")
	{
		$invisiblecheck = "checked=\"checked\"";
	}
	else
	{
		$invisiblecheck = "";
	}

	if($user['hideemail'] == "yes")
	{
		$hideemailcheck = "checked=\"checked\"";
	}
	else
	{
		$hideemailcheck = "";
	}

	if($user['subscriptionmethod'] == 1)
	{
		$no_email_subscribe_selected = "selected=\"selected\"";
	}
	else if($user['subscriptionmethod'] == 2)
	{
		$instant_email_subscribe_selected = "selected=\"selected\"";
	}
	else
	{
		$no_subscribe_selected = "selected=\"selected\"";
	}

	if($user['showsigs'] == "yes")
	{
		$showsigscheck = "checked=\"checked\"";;
	}
	else
	{
		$showsigscheck = "";
	}

	if($user['showavatars'] == "yes")
	{
		$showavatarscheck = "checked=\"checked\"";
	}
	else
	{
		$showavatarscheck = "";
	}

	if($user['showquickreply'] == "yes")
	{
		$showquickreplycheck = "checked=\"checked\"";
	}
	else
	{
		$showquickreplycheck = "";
	}

	if($user['remember'] == "yes")
	{
		$remembercheck = "checked=\"checked\"";
	}
	else
	{
		$remembercheck = "";
	}

	if($user['receivepms'] == "yes")
	{
		$receivepmscheck = "checked=\"checked\"";
	}
	else
	{
		$receivepmscheck = "";
	}

	if($user['pmnotice'] == "yes")
	{
		$pmnoticecheck = " checked=\"checked\"";
	}
	else
	{
		$pmnoticecheck = "";
	}

	if($user['dstcorrection'] == 2)
	{
		$dst_auto_selected = "selected=\"selected\"";
	}
	else if($user['dstcorrection'] == 1)
	{
		$dst_enabled_selected = "selected=\"selected\"";
	}
	else
	{
		$dst_disabled_selected = "selected=\"selected\"";
	}

	if($user['showcodebuttons'] == 1)
	{
		$showcodebuttonscheck = "checked=\"checked\"";
	}
	else
	{
		$showcodebuttonscheck = "";
	}

	if($user['showredirect'] != "no")
	{
		$showredirectcheck = "checked=\"checked\"";
	}
	else
	{
		$showredirectcheck = "";
	}

	if($user['pmnotify'] != "no")
	{
		$pmnotifycheck = "checked=\"checked\"";
	}
	else
	{
		$pmnotifycheck = "";
	}

	if($user['threadmode'] != "threaded")
	{
		$user['threadmode'] = "linear";
	}

	$dateselect[$user['dateformat']] = "selected";
	$timeselect[$user['timeformat']] = "selected";

	if($user['dst'] == 1)
	{
		--$mybb->user['timezone'];
	}

	$tzselect = build_timezone_select("timezoneoffset", $mybb->user['timezone'], true);

	$threadview[$user['threadmode']] = 'selected="selected"';
	$daysprunesel[$user['daysprune']] = 'selected="selected"';
	$stylelist = build_theme_select("style", $user['style']);
	if($mybb->settings['usertppoptions'])
	{
		$explodedtpp = explode(",", $mybb->settings['usertppoptions']);
		$tppoptions = '';
		if(is_array($explodedtpp))
		{
			foreach($explodedtpp as $key => $val)
			{
				$val = trim($val);
				$selected = "";
				if($user['tpp'] == $val)
				{
					$selected = "selected=\"selected\"";
				}
				$tppoptions .= "<option value=\"$val\" $selected>".sprintf($lang->tpp_option, $val)."</option>\n";
			}
		}
		eval("\$tppselect = \"".$templates->get("usercp_options_tppselect")."\";");
	}
	if($mybb->settings['userpppoptions'])
	{
		$explodedppp = explode(",", $mybb->settings['userpppoptions']);
		$pppoptions = '';
		if(is_array($explodedppp))
		{
			foreach($explodedppp as $key => $val)
			{
				$val = trim($val);
				$selected = "";
				if($user['ppp'] == $val)
				{
					$selected = "selected=\"selected\"";
				}
				$pppoptions .= "<option value=\"$val\" $selected>".sprintf($lang->ppp_option, $val)."</option>\n";
			}
		}
		eval("\$pppselect = \"".$templates->get("usercp_options_pppselect")."\";");
	}
	eval("\$editprofile = \"".$templates->get("usercp_options")."\";");
	$plugins->run_hooks("usercp_options_end");
	output_page($editprofile);
}

if($mybb->input['action'] == "do_email" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$errors = array();

	$plugins->run_hooks("usercp_do_email_start");
	if(validate_password_from_uid($mybb->user['uid'], $mybb->input['password']) == false)
	{
		$errors[] = $lang->error_invalidpassword;
	}
	else
	{
		// Set up user handler.
		require_once "inc/datahandlers/user.php";
		$userhandler = new UserDataHandler("update");

		$user = array(
			"uid" => $mybb->user['uid'],
			"email" => $mybb->input['email'],
			"email2" => $mybb->input['email2']
		);

		$userhandler->set_data($user);

		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			if($mybb->user['usergroup'] != "5" && $mybb->usergroup['cancp'] != "yes")
			{
				$activationcode = random_str();
				$now = time();
				$db->delete_query("awaitingactivation", "uid='".$mybb->user['uid']."'");
				$newactivation = array(
					"uid" => $mybb->user['uid'],
					"dateline" => time(),
					"code" => $activationcode,
					"type" => "e",
					"oldgroup" => $mybb->user['usergroup'],
					"misc" => $db->escape_string($mybb->input['email'])
				);
				$db->insert_query("awaitingactivation", $newactivation);
		
				$username = $mybb->user['username'];
				$uid = $mybb->user['uid'];
				$lang->emailsubject_changeemail = sprintf($lang->emailsubject_changeemail, $mybb->settings['bbname']);
				$lang->email_changeemail = sprintf($lang->email_changeemail, $mybb->user['username'], $mybb->settings['bbname'], $mybb->user['email'], $mybb->input['email'], $mybb->settings['bburl'], $activationcode, $mybb->user['username'], $mybb->user['uid']);
				my_mail($mybb->input['email'], $lang->emailsubject_changeemail, $lang->email_changeemail);

				$plugins->run_hooks("usercp_do_email_verify");
				error($lang->redirect_changeemail_activation);
			}
			else
			{
				$userhandler->update_user();
				$plugins->run_hooks("usercp_do_email_changed");
				redirect("usercp.php", $lang->redirect_emailupdated);
			}
		}
	}
	if(count($errors) > 0)
	{
			$mybb->input['action'] = "email";
			$errors = inline_error($errors);
	}
}

if($mybb->input['action'] == "email")
{
	// Coming back to this page after one or more errors were experienced, show fields the user previously entered (with the exception of the password)
	if($errors)
	{
		$email = htmlspecialchars_uni($mybb->input['email']);
		$email2 = htmlspecialchars_uni($mybb->input['email2']);
	}
	else
	{
		$email = $email2 = '';
	}

	$plugins->run_hooks("usercp_email_start");
	eval("\$changemail = \"".$templates->get("usercp_email")."\";");
	$plugins->run_hooks("usercp_email_end");
	output_page($changemail);
}

if($mybb->input['action'] == "do_password" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$errors = array();

	$plugins->run_hooks("usercp_do_password_start");
	if(validate_password_from_uid($mybb->user['uid'], $mybb->input['oldpassword']) == false)
	{
		$errors[] = $lang->error_invalidpassword;
	}
	else
	{
		// Set up user handler.
		require_once "inc/datahandlers/user.php";
		$userhandler = new UserDataHandler("update");

		$user = array(
			"uid" => $mybb->user['uid'],
			"password" => $mybb->input['password'],
			"password2" => $mybb->input['password2']
		);

		$userhandler->set_data($user);

		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			$userhandler->update_user();
			my_setcookie("mybbuser", $mybb->user['uid']."_".$userhandler->data['loginkey']);
			$plugins->run_hooks("usercp_do_password_end");
			redirect("usercp.php", $lang->redirect_passwordupdated);
		}
	}
	if(count($errors) > 0)
	{
			$mybb->input['action'] = "password";
			$errors = inline_error($errors);
	}
}

if($mybb->input['action'] == "password")
{
	$plugins->run_hooks("usercp_password_start");
	eval("\$editpassword = \"".$templates->get("usercp_password")."\";");
	$plugins->run_hooks("usercp_password_end");
	output_page($editpassword);
}

if($mybb->input['action'] == "do_changename" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("usercp_do_changename_start");
	if($mybb->usergroup['canchangename'] != "yes")
	{
		error_no_permission();
	}

	if(validate_password_from_uid($mybb->user['uid'], $mybb->input['password']) == false)
	{
		$errors[] = $lang->error_invalidpassword;
	}
	else
	{
		// Set up user handler.
		require_once "inc/datahandlers/user.php";
		$userhandler = new UserDataHandler("update");

		$user = array(
			"uid" => $mybb->user['uid'],
			"username" => $mybb->input['username']
		);

		$userhandler->set_data($user);

		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			$userhandler->update_user();
			$plugins->run_hooks("usercp_do_changename_end");
			redirect("usercp.php", $lang->redirect_namechanged);

		}
	}
	if(count($errors) > 0)
	{
		$errors = inline_error($errors);
		$mybb->input['action'] = "changename";
	}
}

if($mybb->input['action'] == "changename")
{
	$plugins->run_hooks("usercp_changename_start");
	if($mybb->usergroup['canchangename'] != "yes")
	{
		error_no_permission();
	}
	eval("\$changename = \"".$templates->get("usercp_changename")."\";");
	$plugins->run_hooks("usercp_changename_end");
	output_page($changename);
}

if($mybb->input['action'] == "do_subscriptions")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("usercp_do_subscriptions_start");

	if(!is_array($mybb->input['check']))
	{
		error($lang->no_subscriptions_selected);
	}

	// Clean input - only accept integers thanks!
	array_walk($mybb->input['check'], 'intval');
	$tids = implode(",", $mybb->input['check']);

	// Deleting these subscriptions?
	if($mybb->input['do'] == "delete")
	{
		$db->delete_query("threadsubscriptions", "tid IN ($tids) AND uid='{$mybb->user['uid']}'");
	}
	// Changing subscription type
	else
	{
		if($mybb->input['do'] == "no_notification")
		{
			$new_notification = 0;
		}
		else if($mybb->input['do'] == "instant_notification")
		{
			$new_notification = 1;
		}

		// Update
		$update_array = array("notification" => $new_notification);
		$db->update_query("threadsubscriptions", $update_array, "tid IN ($tids) AND uid='{$mybb->user['uid']}'");
	}

	// Done, redirect
	redirect("usercp.php?action=subscriptions", $lang->redirect_subscriptions_updated);
}

if($mybb->input['action'] == "subscriptions")
{
	$plugins->run_hooks("usercp_subscriptions_start");

	// Do Multi Pages
	$query = $db->simple_select("threadsubscriptions", "COUNT(tid) AS threads", "uid='".$mybb->user['uid']."'");
	$threadcount = $db->fetch_field($query, "threads");

	if(!$mybb->settings['threadsperpage'])
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	$perpage = $mybb->settings['threadsperpage'];
	$page = intval($mybb->input['page']);
	if($page > 0)
	{
		$start = ($page-1) *$perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;
	if($upper > $threadcount)
	{
		$upper = $threadcount;
	}
	$multipage = multipage($threadcount, $perpage, $page, "usercp.php?action=subscriptions");
	$fpermissions = forum_permissions();

	// Fetch subscriptions
	$query = $db->query("
		SELECT s.*, t.*, t.username AS threadusername, u.username
		FROM ".TABLE_PREFIX."threadsubscriptions s
		LEFT JOIN ".TABLE_PREFIX."threads t ON (s.tid=t.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
		WHERE s.uid='".$mybb->user['uid']."'
		ORDER BY t.lastpost DESC
		LIMIT $start, $perpage
	");
	while($subscription = $db->fetch_array($query))
	{
		$forumpermissions = $fpermissions[$subscription['fid']];
		// Only keep if we're allowed to view them
		if($forumpermissions['canview'] != "no" || $forumpermissions['canviewthreads'] != "no")
		{
			$subscriptions[$subscription['tid']] = $subscription;
		}
		// Hmm, you don't have permission to view - unsubscribe!
		else
		{
			$del_subscriptions[] = $subscription['tid'];
		}
	}

	if(is_array($del_subscriptions))
	{
		$db->delete_query("threadsubscriptions", "tid IN (".implode(",", $del_subscriptions).") AND uid='{$mybb->user['uid']}'");
	}

	if(is_array($subscriptions))
	{
		$tids = implode(",", array_keys($subscriptions));

		// Check participation by the current user in any of these threads - for 'dot' folder icons
		if($mybb->settings['dotfolders'] != "no")
		{
			$query = $db->simple_select("posts", "tid,uid", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
			while($post = $db->fetch_array($query))
			{
				$subscriptions[$post['tid']]['doticon'] = 1;
			}
		}

		// Read threads
		if($mybb->settings['threadreadcut'] > 0)
		{
			$query = $db->simple_select("threadsread", "*", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})"); 
			while($readthread = $db->fetch_array($query))
			{
				$subscriptions[$readthread['tid']]['lastread'] = $readthread['dateline']; 
			}
		}


		// Now we can build our subscription list
		foreach($subscriptions as $thread)
		{
			$bgcolor = alt_trow();
		
			$folder = '';
			$prefix = '';

			// Sanitize
			$thread['subject'] = $parser->parse_badwords($thread['subject']);
			$thread['subject'] = htmlspecialchars_uni($thread['subject']);

			// Build our links
			$thread['threadlink'] = get_thread_link($thread['tid']);
			$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");
			
			// Fetch the thread icon if we have one
			if($thread['icon'] > 0 && $icon_cache[$thread['icon']])
			{
				$icon = $icon_cache[$thread['icon']];
				$icon = "<img src=\"{$icon['path']}\" alt=\"{$icon['name']}\" />";
			}
			else
			{
				$icon = "&nbsp;";
			}

			// Determine the folder
			$folder = '';
			$folder_label = '';
			
			if($thread['doticon'])
			{
				$folder = "dot_";
				$folder_label .= $lang->icon_dot;
			}
			
			$gotounread = '';
			$isnew = 0;
			$donenew = 0;
			$lastread = 0;

			$forumread = my_get_array_cookie("forumread", $thread['fid']);
			if($mybb->user['lastvisit'] > $forumread)
			{
				$forumread = $mybb->user['lastvisit'];
			}
			
			if($mybb->settings['threadreadcut'] > 0 && $thread['lastpost'] > $forumread)
			{
				$cutoff = time()-$mybb->settings['threadreadcut']*60*60*24;
			}
			
			if($thread['lastpost'] > $cutoff)
			{
				if($thread['lastpost'] > $cutoff)
				{
					if($thread['lastread'])
					{
							$lastread = $thread['lastread'];
					}
					else
					{
							$lastread = 1;
					}
				}
			} 
			
			if(!$lastread) 
			{
				$readcookie = $threadread = my_get_array_cookie("threadread", $thread['tid']); 
				if($readcookie > $forumread) 
				{ 
					$lastread = $readcookie; 
				}
				else
				{
					$lastread = $forumread;
				}
			}
			
			if($thread['lastpost'] > $lastread && $lastread)
			{
				$folder .= "new";
				$folder_label .= $lang->icon_new;
				$new_class = "subject_new";
				$thread['newpostlink'] = get_thread_link($thread['tid'], 0, "newpost");
				eval("\$gotounread = \"".$templates->get("forumdisplay_thread_gotounread")."\";");
				$unreadpost = 1;
			}
			else
			{
				$folder_label .= $lang->icon_no_new;
				$new_class = "";
			}

			if($thread['replies'] >= $mybb->settings['hottopic'] || $thread['views'] >= $mybb->settings['hottopicviews'])
			{
				$folder .= "hot";
				$folder_label .= $lang->icon_hot;
			}
			
			if($thread['closed'] == "yes")
			{
				$folder .= "lock";
				$folder_label .= $lang->icon_lock;
			}

			$folder .= "folder";

			// Build last post info

			$lastpostdate = my_date($mybb->settings['dateformat'], $thread['lastpost']);
			$lastposttime = my_date($mybb->settings['timeformat'], $thread['lastpost']);
			$lastposter = $thread['lastposter'];
			$lastposteruid = $thread['lastposteruid'];

			// Don't link to guest's profiles (they have no profile).
			if($lastposteruid == 0)
			{
				$lastposterlink = $lastposter;
			}
			else
			{
				$lastposterlink = build_profile_link($lastposter, $lastposteruid);
			}
			
			$thread['replies'] = my_number_format($thread['replies']);
			$thread['views'] = my_number_format($thread['views']);

			// What kind of notification type do we have here?
			switch($thread['notification'])
			{
				case "1": // Instant
					$notification_type = $lang->instant_notification;
					break;
				default: // No notification
					$notification_type = $lang->no_notification;
			}

			eval("\$threads .= \"".$templates->get("usercp_subscriptions_thread")."\";");
		}
	}
	else
	{
		eval("\$threads = \"".$templates->get("usercp_subscriptions_none")."\";");
	}
	eval("\$subscriptions = \"".$templates->get("usercp_subscriptions")."\";");
	$plugins->run_hooks("usercp_subscriptions_end");
	output_page($subscriptions);
}
if($mybb->input['action'] == "forumsubscriptions")
{
	$plugins->run_hooks("usercp_forumsubscriptions_start");
	$query = $db->query("
		SELECT *
		FROM ".TABLE_PREFIX."forumpermissions
		WHERE gid='".$mybb->user['usergroup']."'
	");
	while($permissions = $db->fetch_array($query))
	{
		$permissioncache[$permissions['gid']][$permissions['fid']] = $permissions;
	}
	$fpermissions = forum_permissions();
	$query = $db->query("
		SELECT fs.*, f.*, t.subject AS lastpostsubject
		FROM ".TABLE_PREFIX."forumsubscriptions fs
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid = fs.fid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = f.lastposttid)
		WHERE f.type='f' AND fs.uid='".$mybb->user['uid']."'
		ORDER BY f.name ASC
	");
	$forums = '';
	while($forum = $db->fetch_array($query))
	{
		$forum_url = get_forum_link($forum['fid']);
		$forumpermissions = $fpermissions[$forum['fid']];
		if($forumpermissions['canview'] != "no")
		{
			if(($forum['lastpost'] > $mybb->user['lastvisit'] || $mybbforumread[$forum['fid']] > $mybb->user['lastvisit']) && $forum['lastpost'] != 0)
			{
				$folder = "on";
			}
			else
			{
				$folder = "off";
			}
			if($forum['lastpost'] == 0 || $forum['lastposter'] == "")
			{
				$lastpost = "<div align=\"center\">$lang->never</div>";
			}
			else
			{
				$lastpost_date = my_date($mybb->settings['dateformat'], $forum['lastpost']);
				$lastpost_time = my_date($mybb->settings['timeformat'], $forum['lastpost']);
				$lastposttid = $forum['lastposttid'];
				$lastposter = $forum['lastposter'];
				$lastpost_profilelink = build_profile_link($lastposter, $forum['lastposteruid']);
				$lastpost_subject = $forum['lastpostsubject'];
				if(my_strlen($lastpost_subject) > 25)
				{
					$lastpost_subject = my_substr($lastpost_subject, 0, 25) . "...";
				}
				$lastpost_link = get_thread_link($forum['lastposttid'], 0, "lastpost");
				eval("\$lastpost = \"".$templates->get("forumbit_depth2_forum_lastpost")."\";");
			}
		}
		$posts = my_number_format($forum['posts']);
		$threads = my_number_format($forum['threads']);
		if($mybb->settings['showdescriptions'] == "no")
		{
			$forum['description'] = "";
		}
		eval("\$forums .= \"".$templates->get("usercp_forumsubscriptions_forum")."\";");
	}
	if(!$forums)
	{
		eval("\$forums = \"".$templates->get("usercp_forumsubscriptions_none")."\";");
	}
	$plugins->run_hooks("usercp_forumsubscriptions_end");
	eval("\$forumsubscriptions = \"".$templates->get("usercp_forumsubscriptions")."\";");
	output_page($forumsubscriptions);
}

if($mybb->input['action'] == "do_editsig" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("usercp_do_editsig_start");
	if($mybb->input['updateposts'] == "enable")
	{
		$update_signature = array(
			"includesig" => "yes"
		);
		$db->update_query("posts", $update_signature, "uid='".$mybb->user['uid']."'");
	}
	elseif($mybb->input['updateposts'] == "disable")
	{
		$update_signature = array(
			"includesig" => "no"
		);
		$db->update_query("posts", $update_signature, "uid='".$mybb->user['uid']."'");
	}
	$new_signature = array(
		"signature" => $db->escape_string($mybb->input['signature'])
	);
	$plugins->run_hooks("usercp_do_editsig_process");
	$db->update_query("users", $new_signature, "uid='".$mybb->user['uid']."'");
	$plugins->run_hooks("usercp_do_editsig_end");
	redirect("usercp.php?action=editsig", $lang->redirect_sigupdated);

}

if($mybb->input['action'] == "editsig")
{
	$plugins->run_hooks("usercp_editsig_start");
	if($mybb->input['preview'] && !$error)
	{
		$sig = $mybb->input['signature'];
		$template = "usercp_editsig_preview";
	}
	elseif(!$error)
	{
		$sig = $mybb->user['signature'];
		$template = "usercp_editsig_current";
	}
	else if($error)
	{
		$sig = $mybb->input['signature'];
	}

	if($sig && $template)
	{
		$sig_parser = array(
			"allow_html" => $mybb->settings['sightml'],
			"allow_mycode" => $mybb->settings['sigmycode'],
			"allow_smilies" => $mybb->settings['sigsmilies'],
			"allow_imgcode" => $mybb->settings['sigimgcode'],
			"me_username" => $mybb->user['username'],
		);

		$sigpreview = $parser->parse_message($sig, $sig_parser);
		eval("\$signature = \"".$templates->get($template)."\";");
	}
	if($mybb->settings['sigsmilies'] == "yes")
	{
		$sigsmilies = $lang->on;
		$smilieinserter = build_clickable_smilies();
	}
	else
	{
		$sigsmilies = $lang->off;
	}
	if($mybb->settings['sigmycode'] == "yes")
	{
		$sigmycode = $lang->on;
	}
	else
	{
		$sigmycode = $lang->off;
	}
	if($mybb->settings['sightml'] == "yes")
	{
		$sightml = $lang->on;
	}
	else
	{
		$sightml = $lang->off;
	}
	if($mybb->settings['sigimgcode'] == "yes")
	{
		$sigimgcode = $lang->on;
	}
	else
	{
		$sigimgcode = $lang->off;
	}
	$sig = htmlspecialchars_uni($sig);
	$lang->edit_sig_note2 = sprintf($lang->edit_sig_note2, $sigsmilies, $sigmycode, $sigimgcode, $sightml, $mybb->settings['siglength']);
	
	$codebuttons = build_mycode_inserter("signature");
	eval("\$editsig = \"".$templates->get("usercp_editsig")."\";");
	$plugins->run_hooks("usercp_endsig_end");
	output_page($editsig);
}

if($mybb->input['action'] == "do_avatar" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("usercp_do_avatar_start");
	require_once MYBB_ROOT."inc/functions_upload.php";
	
	$avatar_error = "";

	if($mybb->input['remove']) // remove avatar
	{
		$updated_avatar = array(
			"avatar" => "",
			"avatardimensions" => "",
			"avatartype" => ""
		);
		$db->update_query("users", $updated_avatar, "uid='".$mybb->user['uid']."'");
		remove_avatars($mybb->user['uid']);
	}
	elseif($mybb->input['gallery']) // Gallery avatar
	{
		if(empty($mybb->input['avatar']))
		{
			$avatar_error = $lang->error_noavatar;
		}
		
		if(empty($avatar_error))
		{
			if($mybb->input['gallery'] == "default")
			{
				$avatarpath = $db->escape_string($mybb->settings['avatardir']."/".$mybb->input['avatar']);
			}
			else
			{
				$avatarpath = $db->escape_string($mybb->settings['avatardir']."/".$mybb->input['gallery']."/".$mybb->input['avatar']);
			}

			if(file_exists($avatarpath))
			{
				$updated_avatar = array(
					"avatar" => $avatarpath,
					"avatardimensions" => "",
					"avatartype" => "gallery"
				);
				$db->update_query("users", $updated_avatar, "uid='".$mybb->user['uid']."'");
			}
			remove_avatars($mybb->user['uid']);
		}
	}
	elseif($_FILES['avatarupload']['name']) // upload avatar
	{
		if($mybb->usergroup['canuploadavatars'] == "no")
		{
			error_no_permission();
		}
		$avatar = upload_avatar();
		if($avatar['error'])
		{
			$avatar_error = $avatar['error'];
		}
		else
		{
			if($avatar['width'] > 0 && $avatar['height'] > 0)
			{
				$avatar_dimensions = $avatar['width']."|".$avatar['height'];
			}
			$updated_avatar = array(
				"avatar" => $avatar['avatar'],
				"avatardimensions" => $avatar_dimensions,
				"avatartype" => "upload"
			);
			$db->update_query("users", $updated_avatar, "uid='".$mybb->user['uid']."'");
		}
	}
	else // remote avatar
	{
		$mybb->input['avatarurl'] = preg_replace("#script:#i", "", $mybb->input['avatarurl']);
		$mybb->input['avatarurl'] = htmlspecialchars($mybb->input['avatarurl']);
		$ext = get_extension($mybb->input['avatarurl']);

		// Copy the avatar to the local server (work around remote URL access disabled for getimagesize)
		$file = fetch_remote_file($mybb->input['avatarurl']);
		if(!$file)
		{
			$avatar_error = $lang->error_invalidavatarurl;
		}
		else
		{
			$tmp_name = $mybb->settings['avataruploadpath']."/remote_".md5(uniqid(rand(), true));
			$fp = @fopen($tmp_name, "wb");
			if(!$fp)
			{
				$avatar_error = $lang->error_invalidavatarurl;
			}
			else
			{
				fwrite($fp, $file);
				fclose($fp);
				list($width, $height, $type) = @getimagesize($tmp_name);
				@unlink($tmp_name);
				if(!$type)
				{
					$avatar_error = $lang->error_invalidavatarurl;
				}
			}
		}

		if(empty($avatar_error))
		{
			if($width && $height && $mybb->settings['maxavatardims'] != "")
			{
				list($maxwidth, $maxheight) = explode("x", $mybb->settings['maxavatardims']);
				if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight))
				{
					$lang->error_avatartoobig = sprintf($lang->error_avatartoobig, $maxwidth, $maxheight);
					$avatar_error = $lang->error_avatartoobig;
				}
			}
		}
		
		if(empty($avatar_error))
		{
			if($width > 0 && $height > 0)
			{
				$avatar_dimensions = intval($width)."|".intval($height);
			}
			$updated_avatar = array(
				"avatar" => $db->escape_string($mybb->input['avatarurl']),
				"avatardimensions" => $avatar_dimensions,
				"avatartype" => "remote"
			);
			$db->update_query("users", $updated_avatar, "uid='".$mybb->user['uid']."'");
			remove_avatars($mybb->user['uid']);
		}
	}

	if(empty($avatar_error))
	{
		$plugins->run_hooks("usercp_do_avatar_end");
		redirect("usercp.php", $lang->redirect_avatarupdated);
	}
	else
	{
		$mybb->input['action'] = "avatar";
		$avatar_error = inline_error($avatar_error);
	}
}

if($mybb->input['action'] == "avatar")
{
	$plugins->run_hooks("usercp_avatar_start");
	// Get a listing of available galleries
	$gallerylist['default'] = $lang->default_gallery;
	$avatardir = @opendir($mybb->settings['avatardir']);
	while($dir = @readdir($avatardir))
	{
		if(is_dir($mybb->settings['avatardir']."/$dir") && substr($dir, 0, 1) != ".")
		{
			$gallerylist[$dir] = str_replace("_", " ", $dir);
		}
	}
	@closedir($avatardir);
	natcasesort($gallerylist);
	reset($gallerylist);
	$galleries = '';
	foreach($gallerylist as $dir => $friendlyname)
	{
		if($dir == $mybb->input['gallery'])
		{
			$activegallery = $friendlyname;
			$selected = "selected=\"selected\"";
		}
		$galleries .= "<option value=\"$dir\" $selected>$friendlyname</option>\n";
		$selected = "";
	}

	// Check to see if we're in a gallery or not
	if($activegallery)
	{
		$gallery = str_replace("..", "", $mybb->input['gallery']);
		$lang->avatars_in_gallery = sprintf($lang->avatars_in_gallery, $activegallery);
		// Get a listing of avatars in this gallery
		$avatardir = $mybb->settings['avatardir'];
		if($gallery != "default")
		{
			$avatardir .= "/$gallery";
		}
		$opendir = opendir($avatardir);
		while($avatar = @readdir($opendir))
		{
			$avatarpath = $avatardir."/".$avatar;
			if(is_file($avatarpath) && preg_match("#\.(jpg|jpeg|gif|bmp|png)$#i", $avatar))
			{
				$avatars[] = $avatar;
			}
		}
		@closedir($opendir);

		if(is_array($avatars))
		{
			natcasesort($avatars);
			reset($avatars);
			$count = 0;
			$avatarlist = "<tr>\n";
			foreach($avatars as $avatar)
			{
				$avatarpath = $avatardir."/".$avatar;
				$avatarname = preg_replace("#\.(jpg|jpeg|gif|bmp|png)$#i", "", $avatar);
				$avatarname = ucwords(str_replace("_", " ", $avatarname));
				if($mybb->user['avatar'] == $avatarpath)
				{
					$checked = "checked=\"checked\"";
				}
				if($count == 5)
				{
					$avatarlist .= "</tr>\n<tr>\n";
					$count = 0;
				}
				++$count;
				eval("\$avatarlist .= \"".$templates->get("usercp_avatar_gallery_avatar")."\";");
			}
			if($count != 0)
			{
				for($i = $count; $i <= 5; ++$i)
				{
					eval("\$avatarlist .= \"".$templates->get("usercp_avatar_gallery_blankblock")."\";");
				}
			}
		}
		else
		{
			eval("\$avatarlist = \"".$templates->get("usercp_avatar_gallery_noavatars")."\";");
		}
		eval("\$gallery = \"".$templates->get("usercp_avatar_gallery")."\";");
		$plugins->run_hooks("usercp_avatar_end");
		output_page($gallery);
	}
	// Show main avatar page
	else
	{
		if($mybb->user['avatartype'] == "upload" || stristr($mybb->user['avatar'], $mybb->settings['avataruploadpath']))
		{
			$avatarmsg = "<br /><strong>".$lang->already_uploaded_avatar."</strong>";
		}
		elseif($mybb->user['avatartype'] == "gallery" || stristr($mybb->user['avatar'], $mybb->settings['avatardir']))
		{
			$avatarmsg = "<br /><strong>".$lang->using_gallery_avatar."</strong>";
		}
		elseif($mybb->user['avatartype'] == "remote" || my_strpos(my_strtolower($mybb->user['avatar']), "http://") !== false)
		{
			$avatarmsg = "<br /><strong>".$lang->using_remote_avatar."</strong>";
			$avatarurl = htmlspecialchars_uni($mybb->user['avatar']);
		}
		$urltoavatar = htmlspecialchars_uni($mybb->user['avatar']);
		if($mybb->user['avatar'])
		{
			$avatar_dimensions = explode("|", $mybb->user['avatardimensions']);
			if($avatar_dimensions[0] && $avatar_dimensions[1])
			{
				$avatar_width_height = "width=\"{$avatar_dimensions[0]}\" height=\"{$avatar_dimensions[1]}\"";
			}
			eval("\$currentavatar = \"".$templates->get("usercp_avatar_current")."\";");
			$colspan = 1;
		}
		else
		{
			$colspan = 2;
		}
		if($mybb->settings['maxavatardims'] != "")
		{
			list($maxwidth, $maxheight) = explode("x", $mybb->settings['maxavatardims']);
			$lang->avatar_note .= "<br />".sprintf($lang->avatar_note_dimensions, $maxwidth, $maxheight);
		}
		if($mybb->settings['avatarsize'])
		{
			$maxsize = get_friendly_size($mybb->settings['avatarsize']*1024);
			$lang->avatar_note .= "<br />".sprintf($lang->avatar_note_size, $maxsize);
		}
		if($mybb->settings['avatarresizing'] == "auto")
		{
			$auto_resize = "<br /><span class=\"smalltext\">{$lang->avatar_auto_resize_note}</span>\n";
		}
		else if($mybb->settings['avatarresizing'] == "user")
		{
			$auto_resize = "<br /><span class=\"smalltext\"><input type=\"checkbox\" name=\"auto_resize\" value=\"1\" checked=\"checked\" id=\"auto_resize\" /> <label for=\"auto_resize\">{$lang->avatar_auto_resize_option}</label></span>";
		}

		eval("\$avatar = \"".$templates->get("usercp_avatar")."\";");
		$plugins->run_hooks("usercp_avatar_end");
		output_page($avatar);
	}
}
if($mybb->input['action'] == "notepad")
{
	$plugins->run_hooks("usercp_notepad_start");
	$mybb->user['notepad'] = htmlspecialchars_uni($mybb->user['notepad']);
	eval("\$notepad = \"".$templates->get("usercp_notepad")."\";");
	$plugins->run_hooks("usercp_notepad_end");
	output_page($notepad);
}
if($mybb->input['action'] == "do_notepad" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("usercp_do_notepad_start");
	$db->update_query("users", array('notepad' => $db->escape_string($mybb->input['notepad'])), "uid='".$mybb->user['uid']."'");
	$plugins->run_hooks("usercp_do_notepad_end");
	redirect("usercp.php", $lang->redirect_notepadupdated);
}
if($mybb->input['action'] == "editlists")
{
	$plugins->run_hooks("usercp_editlists_start");
	$buddyarray = explode(",", $mybb->user['buddylist']);
	$comma = '';
	$buddysql = '';
	$buddylist = '';
	if(!empty($buddyarray) && !empty($mybb->user['buddylist']))
	{
		foreach($buddyarray as $key => $buddyid)
		{
			$buddysql .= "{$comma}'{$buddyid}'";
			$comma = ",";
		}
		$query = $db->simple_select("users", "username, uid", "uid IN ({$buddysql})");
		while($buddy = $db->fetch_array($query))
		{
			$uid = $buddy['uid'];
			$username = $buddy['username'];
			eval("\$buddylist .= \"".$templates->get("usercp_editlists_user")."\";");
		}
	}
	$comma2 = '';
	$ignoresql = '';
	$ignorelist = '';
	$ignorearray = explode(",", $mybb->user['ignorelist']);
	if(!empty($ignorearray) && !empty($mybb->user['ignorelist']))
	{
		foreach($ignorearray as $key => $ignoreid)
		{
			$ignoresql .= "$comma2'$ignoreid'";
			$comma2 = ",";
		}
		$query = $db->simple_select("users", "username, uid", "uid IN ($ignoresql)");
		while($ignoreuser = $db->fetch_array($query))
		{
			$uid = $ignoreuser['uid'];
			$username = $ignoreuser['username'];
			eval("\$ignorelist .= \"".$templates->get("usercp_editlists_user")."\";");
		}
	}
	$newlist = '';
	for($i = 1; $i <= 2; ++$i)
	{
		$uid = "new$i";
		$username = '';
		eval("\$newlist .= \"".$templates->get("usercp_editlists_user")."\";");
	}
	eval("\$listpage = \"".$templates->get("usercp_editlists")."\";");
	$plugins->run_hooks("usercp_editlists_end");
	output_page($listpage);
}
if($mybb->input['action'] == "do_editlists" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("usercp_do_editlists_start");
	$comma = '';
	$users = '';
	foreach($mybb->input['listuser'] as $key => $val)
	{
		if(my_strtoupper($mybb->user['username']) != my_strtoupper($val))
		{
			$val = $db->escape_string($val);
			$users .= "$comma'$val'";
			$comma = ",";
		}
	}
	$comma2 = '';
	$newlist = '';
	$query = $db->simple_select("users", "uid", "username IN ($users)");
	while($user = $db->fetch_array($query))
	{
		$newlist .= "$comma2{$user['uid']}";
		$comma2 = ",";
	}
	if($mybb->input['list'] == "ignore")
	{
		$type = "ignorelist";
	}
	else
	{
		$type = "buddylist";
	}
	$db->update_query("users", array($type => $newlist), "uid='".$mybb->user['uid']."'");
	$redirecttemplate = "redirect_".$mybb->input['list']."updated";
	$plugins->run_hooks("usercp_do_editlists_end");
	redirect("usercp.php?action=editlists", $lang->$redirecttemplate);
}
if($mybb->input['action'] == "drafts")
{
	$plugins->run_hooks("usercp_drafts_start");
	// Show a listing of all of the current 'draft' posts or threads the user has.
	$drafts = '';
	$query = $db->query("
		SELECT p.subject, p.pid, t.tid, t.subject AS threadsubject, t.fid, f.name AS forumname, p.dateline, t.visible AS threadvisible, p.visible AS postvisible
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid)
		WHERE p.uid='".$mybb->user['uid']."' AND p.visible='-2'
		ORDER BY p.dateline DESC
	");
	while($draft = $db->fetch_array($query))
	{
		$trow = alt_trow();
		if($draft['threadvisible'] == 1) // We're looking at a draft post
		{
			$detail = $lang->thread." <a href=\"".get_thread_link($draft['tid'])."\">".htmlspecialchars_uni($draft['threadsubject'])."</a>";
			$editurl = "newreply.php?action=editdraft&amp;pid={$draft['pid']}";
			$id = $draft['pid'];
			$type = "post";
		}
		elseif($draft['threadvisible'] == -2) // We're looking at a draft thread
		{
			$detail = $lang->forum." <a href=\"".get_forum_link($draft['fid'])."\">".htmlspecialchars_uni($draft['forumname'])."</a>";
			$editurl = "newthread.php?action=editdraft&amp;tid={$draft['tid']}";
			$id = $draft['tid'];
			$type = "thread";
		}
		$draft['subject'] = htmlspecialchars_uni($draft['subject']);
		$savedate = my_date($mybb->settings['dateformat'], $draft['dateline']);
		$savetime = my_date($mybb->settings['timeformat'], $draft['dateline']);
		eval("\$drafts .= \"".$templates->get("usercp_drafts_draft")."\";");
	}
	if(!$drafts)
	{
		eval("\$drafts = \"".$templates->get("usercp_drafts_none")."\";");
	}
	else
	{
		eval("\$draftsubmit = \"".$templates->get("usercp_drafts_submit")."\";");
	}
	eval("\$draftlist = \"".$templates->get("usercp_drafts")."\";");
	$plugins->run_hooks("usercp_drafts_end");
	output_page($draftlist);

}
if($mybb->input['action'] == "do_drafts" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("usercp_do_drafts_start");
	if(!$mybb->input['deletedraft'])
	{
		error($lang->no_drafts_selected);
	}
	$pidin = array();
	$tidin = array();
	foreach($mybb->input['deletedraft'] as $id => $val)
	{
		if($val == "post")
		{
			$pidin[] = "'".intval($id)."'";
		}
		elseif($val == "thread")
		{
			$tidin[] = "'".intval($id)."'";
		}
	}
	if($tidin)
	{
		$tidin = implode(",", $tidin);
		$db->delete_query("threads", "tid IN ($tidin) AND visible='-2' AND uid='".$mybb->user['uid']."'");
		$tidinp = "OR tid IN ($tidin)";
	}
	if($pidin || $tidinp)
	{
		if($pidin)
		{
			$pidin = implode(",", $pidin);
			$pidinq = "pid IN ($pidin)";
		}
		else
		{
			$pidinq = "1=0";
		}
		$db->delete_query("posts", "($pidinq $tidinp) AND visible='-2' AND uid='".$mybb->user['uid']."'");
	}
	$plugins->run_hooks("usercp_do_drafts_end");
	redirect("usercp.php?action=drafts", $lang->selected_drafts_deleted);
}
if($mybb->input['action'] == "usergroups")
{
	$plugins->run_hooks("usercp_usergroups_start");
	$ingroups = ",".$mybb->user['usergroup'].",".$mybb->user['additionalgroups'].",".$mybb->user['displaygroup'].",";

	// Changing our display group
	if($mybb->input['displaygroup'])
	{
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!my_strpos($ingroups, ",".$mybb->input['displaygroup'].","))
		{
			error($lang->not_member_of_group);
		}
		$query = $db->simple_select("usergroups", "*", "gid='".intval($mybb->input['displaygroup'])."'");
		$dispgroup = $db->fetch_array($query);
		if($dispgroup['candisplaygroup'] != "yes")
		{
			error($lang->cannot_set_displaygroup);
		}
		$db->update_query("users", array('displaygroup' => intval($mybb->input['displaygroup'])), "uid='".$mybb->user['uid']."'");
		$plugins->run_hooks("usercp_usergroups_change_displaygroup");
		redirect("usercp.php?action=usergroups", $lang->display_group_changed);
		exit;
	}

	// Leaving a group
	if($mybb->input['leavegroup'])
	{
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!my_strpos($ingroups, ",".$mybb->input['leavegroup'].","))
		{
			error($lang->not_member_of_group);
		}
		if($mybb->user['usergroup'] == $mybb->input['leavegroup'])
		{
			error($lang->cannot_leave_primary_group);
		}
		$query = $db->simple_select("usergroups", "*", "gid='".intval($mybb->input['leavegroup'])."'");
		$usergroup = $db->fetch_array($query);
		if($usergroup['type'] != 4 && $usergroup['type'] != 3)
		{
			error($lang->cannot_leave_group);
		}
		leave_usergroup($mybb->user['uid'], $mybb->input['leavegroup']);
		$plugins->run_hooks("usercp_usergroups_leave_group");
		redirect("usercp.php?action=usergroups", $lang->left_group);
	}

	// Joining a group
	if($mybb->input['joingroup'])
	{
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		$mybb->input['joingroup'] = intval($mybb->input['joingroup']);
		$query = $db->simple_select("usergroups", "*", "gid='".intval($mybb->input['joingroup'])."'");
		$usergroup = $db->fetch_array($query);

		if(($usergroup['type'] != 4 && $usergroup['type'] != 3) || !$usergroup['gid'])
		{
			error($lang->cannot_join_group);
		}

		if(my_strpos($ingroups, ",".intval($mybb->input['joingroup']).",") || $mybb->user['usergroup'] == $mybb->input['joingroup'] || $mybb->user['displaygroup'] == $mybb->input['joingroup'])
		{
			error($lang->already_member_of_group);
		}

		$query = $db->simple_select("joinrequests", "*", "uid='".$mybb->user['uid']."' AND gid='".intval($mybb->input['joingroup'])."'");
		$joinrequest = $db->fetch_array($query);
		if($joinrequest['rid'])
		{
			error($lang->already_sent_join_request);
		}
		if($mybb->input['do'] == "joingroup" && $usergroup['type'] == 4)
		{
			$reason = $db->escape_string($reason);
			$now = time();
			$joinrequest = array(
				"uid" => $mybb->user['uid'],
				"gid" => intval($mybb->input['joingroup']),
				"reason" => $db->escape_string($mybb->input['reason']),
				"dateline" => time()
				);

			$db->insert_query("joinrequests", $joinrequest);
			$plugins->run_hooks("usercp_usergroups_join_group_request");
			redirect("usercp.php?action=usergroups", $lang->group_join_requestsent);
			exit;
		}
		elseif($usergroup['type'] == 4)
		{
			$joingroup = $mybb->input['joingroup'];
			eval("\$joinpage = \"".$templates->get("usercp_usergroups_joingroup")."\";");
			output_page($joinpage);
			exit();
		}
		else
		{
			join_usergroup($mybb->user['uid'], $mybb->input['joingroup']);
			$plugins->run_hooks("usercp_usergroups_join_group");
			redirect("usercp.php?action=usergroups", $lang->joined_group);
		}
	}
	// Show listing of various group related things

	// List of usergroup leaders
	$query = $db->query("
		SELECT g.*, u.username, u.displaygroup, u.usergroup
		FROM ".TABLE_PREFIX."groupleaders g
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=g.uid)
		ORDER BY u.username ASC
	");
	while($leader = $db->fetch_array($query))
	{
		$groupleaders[$leader['gid']][$leader['uid']] = $leader;
	}

	// List of groups this user is a leader of
	$groupsledlist = '';
	
	
	switch($db->type)
	{
		case "sqlite3":
		case "sqlite2":
			$query = $db->query("
				SELECT g.title, g.gid, g.type, COUNT(u.uid) AS users, COUNT(j.rid) AS joinrequests, l.canmanagerequests, l.canmanagemembers
				FROM ".TABLE_PREFIX."groupleaders l
				LEFT JOIN ".TABLE_PREFIX."usergroups g ON(g.gid=l.gid)
				LEFT JOIN ".TABLE_PREFIX."users u ON(((','|| u.additionalgroups|| ',' LIKE '%,'|| g.gid|| ',%') OR u.usergroup = g.gid))
				LEFT JOIN ".TABLE_PREFIX."joinrequests j ON(j.gid=g.gid)
				WHERE l.uid='".$mybb->user['uid']."'
				GROUP BY l.gid
			");
			break;
		default:
			$query = $db->query("
				SELECT g.title, g.gid, g.type, COUNT(DISTINCT u.uid) AS users, COUNT(DISTINCT j.rid) AS joinrequests, l.canmanagerequests, l.canmanagemembers
				FROM ".TABLE_PREFIX."groupleaders l
				LEFT JOIN ".TABLE_PREFIX."usergroups g ON(g.gid=l.gid)
				LEFT JOIN ".TABLE_PREFIX."users u ON(((CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%')) OR u.usergroup = g.gid))
				LEFT JOIN ".TABLE_PREFIX."joinrequests j ON(j.gid=g.gid)
				WHERE l.uid='".$mybb->user['uid']."'
				GROUP BY l.gid
			");
	}
	
	while($usergroup = $db->fetch_array($query))
	{
		$memberlistlink = $moderaterequestslink = '';
		$memberlistlink = " [<a href=\"managegroup.php?gid=".$usergroup['gid']."\">".$lang->view_members."</a>]";
		if($usergroup['type'] != 4)
		{
			$usergroup['joinrequests'] = '--';
		}
		if($usergroup['joinrequests'] > 0 && $usergroup['canmanagerequests'] == "yes")
		{
			$moderaterequestslink = " [<a href=\"managegroup.php?action=joinrequests&amp;gid={$usergroup['gid']}\">{$lang->view_requests}</a>]";
		}
		$groupleader[$usergroup['gid']] = 1;
		$trow = alt_trow();
		eval("\$groupsledlist .= \"".$templates->get("usercp_usergroups_leader_usergroup")."\";");
	}
	if($groupsledlist)
	{
		eval("\$leadinggroups = \"".$templates->get("usercp_usergroups_leader")."\";");
	}

	// Fetch the list of groups the member is in
	// Do the primary group first
	$query = $db->simple_select("usergroups", "*", "gid='".$mybb->user['usergroup']."'");
	$usergroup = $db->fetch_array($query);
	$leavelink = "<div style=\"text-align:center;\"><span class=\"smalltext\">{$lang->usergroup_leave_primary}</span></div>";
	$trow = alt_trow();
	if($usergroup['candisplaygroup'] == "yes" && $usergroup['gid'] == $mybb->user['displaygroup'])
	{
		$displaycode = "<input type=\"radio\" name=\"displaygroup\" value=\"{$usergroup['gid']}\" checked=\"checked\" />";
	}
	elseif($usergroup['candisplaygroup'] == "yes")
	{
		$displaycode = "<input type=\"radio\" name=\"displaygroup\" value=\"{$usergroup['gid']}\" />";
	}
	else
	{
		$displaycode = '';
	}

	eval("\$memberoflist = \"".$templates->get("usercp_usergroups_memberof_usergroup")."\";");
	$showmemberof = false;
	if($mybb->user['additionalgroups'])
	{
		$query = $db->simple_select("usergroups", "*", "gid IN (".$mybb->user['additionalgroups'].") AND gid !='".$mybb->user['usergroup']."'", array('order_by' => 'title'));
		while($usergroup = $db->fetch_array($query))
		{
			$showmemberof = true;
			if($groupleader[$usergroup['gid']])
			{
				$leavelink = "<div style=\"text-align:center;\"><span class=\"smalltext\">$lang->usergroup_leave_leader</span></div>";
			}
			else
			{
				$leavelink = "<div align=\"center\"><a href=\"usercp.php?action=usergroups&leavegroup=".$usergroup['gid']."&amp;my_post_key={$mybb->post_code}\">".$lang->usergroup_leave."</a></div>";
			}
			if($usergroup['description'])
			{
				$description = "<br /><span class=\"smalltext\">".$usergroup['description']."</span>";
			}
			else
			{
				$description = '';
			}
			if(!$usergroup['usertitle'])
			{
				// fetch title here
			}
			$trow = alt_trow();
			if($usergroup['candisplaygroup'] == "yes" && $usergroup['gid'] == $mybb->user['displaygroup'])
			{
				$displaycode = "<input type=\"radio\" name=\"displaygroup\" value=\"{$usergroup['gid']}\" checked=\"checked\" />";
			}
			elseif($usergroup['candisplaygroup'] == "yes")
			{
				$displaycode = "<input type=\"radio\" name=\"displaygroup\" value=\"{$usergroup['gid']}\" />";
			}
			else
			{
				$displaycode = '';
			}
			eval("\$memberoflist .= \"".$templates->get("usercp_usergroups_memberof_usergroup")."\";");
		}
	}
	eval("\$membergroups = \"".$templates->get("usercp_usergroups_memberof")."\";");

	// List of groups this user has applied for but has not been accepted in to
	$query = $db->simple_select("joinrequests", "*", "uid='".$mybb->user['uid']."'");
	while($request = $db->fetch_array($query))
	{
		$appliedjoin[$request['gid']] = $request['dateline'];
	}

	// Fetch list of groups the member can join
	$existinggroups = $mybb->user['usergroup'];
	if($mybb->user['additionalgroups'])
	{
		$existinggroups .= ",".$mybb->user['additionalgroups'];
	}
	$joinablegroups = '';
	$query = $db->simple_select("usergroups", "*", "(type='3' OR type='4') AND gid NOT IN ($existinggroups)", array('order_by' => 'title'));
	while($usergroup = $db->fetch_array($query))
	{
		$trow = alt_trow();
		if($usergroup['description'])
		{
			$description = "<br /><span class=\"smallfont\">".$usergroup['description']."</span>";
		}
		else
		{
			$description = '';
		}
		if($usergroup['type'] == 4) // Moderating join requests
		{
			$conditions = $lang->usergroup_joins_moderated;
		}
		else
		{
			$conditions = $lang->usergroup_joins_anyone;
		}
		if($appliedjoin[$usergroup['gid']])
		{
			$applydate = my_date($mybb->settings['dateformat'], $appliedjoin[$usergroup['gid']]);
			$applytime = my_date($mybb->settings['timeformat'], $appliedjoin[$usergroup['gid']]);
			$joinlink = sprintf($lang->join_group_applied, $applydate, $applytime);
		}
		else
		{
			$joinlink = "<a href=\"usercp.php?action=usergroups&amp;joingroup={$usergroup['gid']}&amp;my_post_key={$mybb->post_code}\">{$lang->join_group}</a>";
		}
		$usergroupleaders = '';
		if($groupleaders[$usergroup['gid']])
		{
			$comma = '';
			$usergroupleaders = '';
			foreach($groupleaders[$usergroup['gid']] as $leader)
			{
				$leader['username'] = format_name($leader['username'], $leader['usergroup'], $leader['displaygroup']);
				$usergroupleaders .= $comma.build_profile_link($leader['username'], $leader['uid']);
				$comma = ", ";
			}
			$usergroupleaders = $lang->usergroup_leaders." ".$usergroupleaders;
		}
		eval("\$joinablegrouplist .= \"".$templates->get("usercp_usergroups_joinable_usergroup")."\";");
	}
	if($joinablegrouplist)
	{
		eval("\$joinablegroups = \"".$templates->get("usercp_usergroups_joinable")."\";");
	}

	eval("\$groupmemberships = \"".$templates->get("usercp_usergroups")."\";");
	$plugins->run_hooks("usercp_usergroups_end");
	output_page($groupmemberships);
}
if($mybb->input['action'] == "attachments")
{
	$plugins->run_hooks("usercp_attachments_start");
	require_once MYBB_ROOT."inc/functions_upload.php";
	$attachments = '';
	$query = $db->query("
		SELECT a.*, p.subject, p.dateline, t.tid, t.subject AS threadsubject
		FROM ".TABLE_PREFIX."attachments a
		LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		WHERE a.uid='".$mybb->user['uid']."' AND a.pid!='0'
		ORDER BY p.dateline DESC
	");
	$bandwidth = $totaldownloads = 0;
	while($attachment = $db->fetch_array($query))
	{
		if($attachment['dateline'] && $attachment['tid'])
		{
			$attachment['subject'] = htmlspecialchars_uni($parser->parse_badwords($attachment['subject']));
			$attachment['postlink'] = get_post_link($attachment['pid'], $attachment['tid']);
			$attachment['threadlink'] = get_thread_link($attachment['tid']);
			$attachment['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($attachment['threadsubject']));
			$size = get_friendly_size($attachment['filesize']);
			$icon = get_attachment_icon(get_extension($attachment['filename']));
			$sizedownloads = sprintf($lang->attachment_size_downloads, $size, $attachment['downloads']);
			$attachdate = my_date($mybb->settings['dateformat'], $attachment['dateline']);
			$attachtime = my_date($mybb->settings['timeformat'], $attachment['dateline']);
			$altbg = alt_trow();
			eval("\$attachments .= \"".$templates->get("usercp_attachments_attachment")."\";");
			// Add to bandwidth total
			$bandwidth += ($attachment['filesize'] * $attachment['downloads']);
			$totaldownloads += $attachment['downloads'];
		}
		else
		{
			// This little thing delets attachments without a thread/post
			remove_attachment($attachment['pid'], $attachment['posthash'], $attachment['aid']);
		}
	}
	$query = $db->simple_select("attachments", "SUM(filesize) AS ausage, COUNT(aid) AS acount", "uid='".$mybb->user['uid']."'");
	$usage = $db->fetch_array($query);
	$totalusage = $usage['ausage'];
	$totalattachments = $usage['acount'];
	$friendlyusage = get_friendly_size($totalusage);
	$bandwidth = get_friendly_size($bandwidth);
	if($mybb->usergroup['attachquota'])
	{
		$percent = round(($totalusage/($mybb->usergroup['attachquota']*1024))*100)."%";
		$attachquota = get_friendly_size($mybb->usergroup['attachquota']*1024);
		$usagenote = sprintf($lang->attachments_usage_quota, $friendlyusage, $attachquota, $percent, $totalattachments);
	}
	else
	{
		$percent = $lang->unlimited;
		$attachquota = $lang->unlimited;
		$usagenote = sprintf($lang->attachments_usage, $friendlyusage, $totalattachments);
	}
	if(!$attachments)
	{
		eval("\$attachments = \"".$templates->get("usercp_attachments_none")."\";");
		$usagenote = '';
	}
	eval("\$manageattachments = \"".$templates->get("usercp_attachments")."\";");
	$plugins->run_hooks("usercp_attachments_end");
	output_page($manageattachments);
}
if($mybb->input['action'] == "do_attachments" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$plugins->run_hooks("usercp_do_attachments_start");
	require_once MYBB_ROOT."inc/functions_upload.php";
	if(!is_array($mybb->input['attachments']))
	{
		error($lang->no_attachments_selected);
	}
	$aids = $db->escape_string(implode(",", $mybb->input['attachments']));
	$query = $db->simple_select("attachments", "*", "aid IN ($aids) AND uid='".$mybb->user['uid']."'");
	while($attachment = $db->fetch_array($query))
	{
		remove_attachment($attachment['pid'], '', $attachment['aid']);
	}
	$plugins->run_hooks("usercp_do_attachments_end");
	redirect("usercp.php?action=attachments", $lang->attachments_deleted);
}
if(!$mybb->input['action'])
{
	// Get posts per day
	$daysreg = (time() - $mybb->user['regdate']) / (24*3600);
	$perday = $mybb->user['postnum'] / $daysreg;
	$perday = round($perday, 2);
	if($perday > $mybb->user['postnum'])
	{
		$perday = $mybb->user['postnum'];
	}

	$stats = $cache->read("stats");
	$posts = $stats['numposts'];
	if($posts == 0)
	{
		$percent = "0";
	}
	else
	{
		$percent = $mybb->user['postnum']*100/$posts;
		$percent = round($percent, 2);
	}

	$lang->posts_day = sprintf($lang->posts_day, my_number_format($perday), $percent);
	$usergroup = $groupscache[$mybb->user['usergroup']]['title'];

	$colspan = 2;
	if($mybb->user['avatar'])
	{
		$avatar_dimensions = explode("|", $mybb->user['avatardimensions']);
		if($avatar_dimensions[0] && $avatar_dimensions[1])
		{
			$avatar_width_height = "width=\"{$avatar_dimensions[0]}\" height=\"{$avatar_dimensions[1]}\"";
		}
		eval("\$avatar = \"".$templates->get("usercp_currentavatar")."\";");
		$colspan = 3;
	}
	else
	{
		$avatar = '';
	}
	$regdate = my_date($mybb->settings['dateformat'].", ".$mybb->settings['timeformat'], $mybb->user['regdate']);

	if($mybb->user['usergroup'] == 5 && $mybb->settings['regtype'] != "admin")
	{
		$usergroup .= "<br />(<a href=\"member.php?action=resendactivation\">$lang->resend_activation</a>)";
	}
	// Make reputations row
	$reputations = '';
	if($mybb->usergroup['usereputationsystem'] == 'yes' && $mybb->settings['enablereputation'] == 'yes')
	{
		$reputation_link = get_reputation($mybb->user['reputation']);
		eval("\$reputation = \"".$templates->get("usercp_reputation")."\";");
	}

	if($mybb->settings['enablewarningsystem'] != "no" && $mybb->settings['canviewownwarning'] != "no")
	{
		$warning_level = round($mybb->user['warningpoints']/$mybb->settings['maxwarningpoints']*100);
		if($warning_level > 100)
		{
			$warning_level = 100;
		}
		if($warning_level > 0)
		{
			$lang->current_warning_level = sprintf($lang->current_warning_level, $warning_level, $mybb->user['warningpoints'], $mybb->settings['maxwarningpoints']);
			// Fetch latest warnings
			$query = $db->query("
				SELECT w.*, t.title AS type_title, u.username, p.subject AS post_subject
				FROM ".TABLE_PREFIX."warnings w
				LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (t.tid=w.tid)
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.issuedby)
				LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=w.pid)
				WHERE w.uid='{$mybb->user['uid']}'
				ORDER BY w.expired ASC, w.dateline DESC
				LIMIT 5
			");
			while($warning = $db->fetch_array($query))
			{
				$post_link = "";
				if($warning['post_subject'])
				{
					$warning['post_subject'] = $parser->parse_badwords($warning['post_subject']);
					$warning['post_subject'] = htmlspecialchars_uni($warning['post_subject']);
					$post_link = "<br /><small>{$lang->warning_for_post} <a href=\"".get_post_link($warning['pid'])."\">{$warning['post_subject']}</a></small>";
				}
				$issuedby = build_profile_link($warning['username'], $warning['uid']);
				$date_issued = my_date($mybb->settings['dateformat'], $warning['dateline']).", ".my_date($mybb->settings['timeformat'], $warning['dateline']);
				if($warning['type_title'])
				{
					$warning_type = $warning['type_title'];
				}
				else
				{
					$warning_type = $warning['title'];
				}
				$warning_type = htmlspecialchars_uni($warning_type);
				if($warning['points'] > 0)
				{
					$warning['points'] = "+{$warning['points']}";
				}
				$points = sprintf($lang->warning_points, $warning['points']);
				if($warning['expired'] != 1)
				{
					$expires = my_date($mybb->settings['dateformat'], $warning['expires']).", ".my_date($mybb->settings['timeformat'], $warning['expires']);
				}
				else
				{
					if($warning['daterevoked'])
					{
						$expires = $lang->warning_revoked;
					}
					else if($warning['expires'])
					{
						$expires = $lang->already_expired;
					}
					else
					{
						$expires = $lang->never;
					}
				}
				$alt_bg = alt_trow();
				eval("\$warnings .= \"".$templates->get("usercp_warnings_warning")."\";");
			}
			eval("\$latest_warnings = \"".$templates->get("usercp_warnings")."\";");
		}
	}

	// Format username
	$username = format_name($mybb->user['username'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
	$username = build_profile_link($username, $mybb->user['uid']);

	// Format post numbers
	$mybb->user['postnum'] = my_number_format($mybb->user['postnum']);

	eval("\$usercp = \"".$templates->get("usercp")."\";");
	$plugins->run_hooks("usercp_end");
	output_page($usercp);
}
?>