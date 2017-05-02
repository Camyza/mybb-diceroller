<?php
/**
 * Dice Roller MyCode
 * Copyright 2017 Shinka, All Rights Reserved
 *
 * License: http://www.mybb.com/about/license
 *
 */

 global $seed, $alias, $patterns, $code_matches;
 $seed = 1;         // seed used for RNG -- fallback to 1 if cannot fetch post ID
 $patterns = array(
     // [roll=low-high] | e.g. [roll=1-6]
     '/\[roll=([\"\']?)(\d+)-(\d+)(([\+\-]\d+)?)\1\]/',
     // [roll=(N)dS(+/-F)] | e.g. [roll=d6] | [roll=1d6] | [roll=1d6+10] | [roll=1d6-10]
     '/\[roll=([\"\']?)(\d*)d(\d+)([\+\-]\d+)?\1\]/',
     // [roll=weighed list] | e.g. [roll=2,2,1]
     '/\[roll=([\"\']?)(\d+(,\d+)*)\1\]/',
     // [roll=alias] | e.g. [roll=strength]
     '/\[roll=([\"\']?)(\w+)\1\]/',
     '/(\[quote=([\"\']|&quot;|).*?\2(.*?)\])(.*\[roll=[\dd\-\,]+\].*)(\[\/quote\])/',
     // [code|php][roll=*][/code|php]
     '/\[(code|php)\](.*?\[roll=[\dd\-\,]+\].*?)\[\/\1\](\r\n?|\n?)/'
 );

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Require PluginLibrary.
if (!defined("PLUGINLIBRARY")) {
    define("PLUGINLIBRARY", MYBB_ROOT."inc/plugins/pluginlibrary.php");
}

$plugins->add_hook("admin_config_plugins_begin", "diceroller_edit");
// Parse [roll=*] MyCode at postbit so PID is accessible
$plugins->add_hook("postbit", "diceroller_setup");
$plugins->add_hook("parse_quote_message", "diceroller_quote");
$plugins->add_hook("parse_message_start", "diceroller_code");

function diceroller_info() {
    global $mybb, $plugins_cache;

    $info = array(
		"name"			=> "Dice Roller MyCode",
		"description"	=> "Rolls dice in a variety of exciting ways.",
		"website"		=> "https://community.mybb.com/mods.php?action=view&pid=955",
		"author"		=> "Shinka",
		"authorsite"	=> "https://github.com/kalynrobinson/diceroller",
		"version"		=> "1.0.2",
		"guid" 			=> "",
		"compatibility" => "18*"
	);

    // Display edit and undo buttons on plugin description.
    if (diceroller_is_installed() && $plugins_cache['active']['diceroller']) {
        global $PL;
        $PL or require_once PLUGINLIBRARY;

        $editurl = $PL->url_append("index.php?module=config-plugins",
                                   array("diceroller" => "edit",
                                         "my_post_key" => $mybb->post_code));
        $undourl = $PL->url_append("index.php",
                                   array("module" => "config-plugins",
                                         "diceroller" => "undo",
                                         "my_post_key" => $mybb->post_code));
        $editurl = "index.php?module=config-plugins&amp;diceroller=edit&amp;my_post_key=".$mybb->post_code;
        $undourl = "index.php?module=config-plugins&amp;diceroller=undo&amp;my_post_key=".$mybb->post_code;
        $info["description"] .= "<br /><a href=\"{$editurl}\">Make edits to class_parser.php</a>";
        $info["description"] .= " | <a href=\"{$undourl}\">Undo edits to class_parser.php</a>";
    }

	return $info;
}

function diceroller_install() {
    // Require PluginLibrary on install.
    if (!file_exists(PLUGINLIBRARY)) {
        flash_message("The selected plugin could not be installed because
            <a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a>
            is missing.", "error");
        admin_redirect("index.php?module=config-plugins");
    }
}

function diceroller_uninstall() {
    global $PL;
    $PL or require_once PLUGINLIBRARY;

    // Delete settings and templates from database.
    $PL->settings_delete("diceroller");
    $PL->templates_delete("diceroller");
}

function diceroller_is_installed() {
    global $settings;

    // Check if diceroller settings exist.
    return isset($settings['diceroller_enable_sum']);
}

function diceroller_activate() {
    global $PL, $mybb;
    $PL or require_once PLUGINLIBRARY;

    $PL->settings("diceroller",
                  "Dice Roller MyCode",
                  "Roll dice in a variety of exciting ways!",
                  array(
                      "enable_sum" => array(
                          "title" => "Display sum of NdS rolls?",
                          "description" => "When rolling multiple dice with NdS, should the sum of the results be displayed?",
                          "optionscode" => "yesno",
                          "value" => 1
                      ),
                      "remove_dice" => array(
                          "title" => "Remove Dice from Pool",
                          "description" => "Remove value from dice pool so it cannot be landed
                              on again. List rolls or aliases you want to turn this setting on for,
                              separated by line breaks, e.g.
                              <br />carddraw
                              <br />1d20",
                          "optionscode" => "textarea",
                          "value" => "carddraw\n1d20"
                      ),
                      "aliases" => array(
                          "title" => "Roll Aliases",
                          "description" => "A list of shorthand names and the rolls they
                              represent, using any of the supported syntax formats. Separate aliases
                              with line breaks, e.g.
                              <br />strength=1d6+5
                              <br />endurance=1-10
                              <br />agility=25,25,50",
                          "optionscode" => "textarea",
                          "value" => "strength=1d6+5\nendurance=1-4\nagility=25,25,50\ncarddraw=1-4"
                      ),
                      "result_messages" => array(
                          "title" => "Result Messages",
                          "description" => "Display custom messages based on the result of a roll.
                              Output is based on sum of all rolls and offset.
                              Separate messages with commas and rolls with line breaks, e.g.
                              <br />1d20=Crit Fail, Fail, Success, Crit Success
                              <br />strength=Fumbled!, Missed!, Glancing Blow!, Direct Hit!, Perfect Strike!
                              <br />agility=You faceplanted!?, You tripped..., You have legs, Run like the wind!!!
                              <br />endurance=You're ded, Just a flesh wound, Alright!, Amazing!!!",
                          "optionscode" => "textarea",
                          "value" => "1d20=Crit Fail, Fail, Success, Crit Success\nstrength=Fumbled!, Missed!, Glancing Blow!, Direct Hit!, Perfect Strike!\nagility=You faceplanted!?, You tripped..., You have legs, Run like the wind!!!\nendurance=You're ded, Just a flesh wound, Alright!, Amazing!!!\ncarddraw=Awful luck!,Unlucky...,Lucky,Super lucky!!!!"
                      ),
                      "result_ranges" => array(
                          "title" => "Result Ranges",
                          "description" => "Ranges in format <i>low</i>-<i>high</i> that
                              indicate when to display each custom message.
                              Separate messages with commas and rolls with line breaks, e.g.
                              <br />1d20=1-1,2-10,11-19,20-20 (rolling 1 will display \"Crit Fail\",
                              rolling between 2-10 will display \"Fail\", etc.)
                              <br />strength=1-5,6-10,11-15,16-20
                              <br />agility=1-25,26-50,51-75,76-100",
                          "optionscode" => "textarea",
                          "value" => "1d20=1-1,2-10,11-19,20-20\nstrength=6-6,7-7,8-9,10-10,11-11\nagility=1-1,2-2,3-3\nendurance=1-1,2-2,3-3,4-4\ncarddraw=1-1,2-2,3-3,4-4"
                      ),
                      "resources" => array(
                          "title" => "Resources",
                          "description" => "Display resource item based on the result of a roll.
                              Output is based on each roll, not the sum, so 3d20 will output three
                              resource items. Separate items with commas and rolls with line breaks, e.g.
                              <br />1d20=Common Card,Uncommon Card,Rare Card,Super Rare Card!!!!",
                          "optionscode" => "textarea",
                          "value" => "carddraw=Common Card,Uncommon Card,Rare Card,Super Rare Card!!!!"
                      )
                  )
    );

    $PL->templates("diceroller",
               "Diceroller MyCode",
               array(
                   "" => '<div align="center">Rolling <strong>{$alias}</strong> {$dice}: $rolls {$offset} $sum $results $resources</div>',
                   "roll" => '{$roll} {$plus}',
                   "sum" => '= {$sum}',
                   "result" => '<br />{$result}',
                   "resource" => '<br />{$resource}',
                   )
    );
}

function diceroller_deactivate() {
    global $PL;
    $PL or require_once PLUGINLIBRARY;

    $PL->cache_delete("diceroller");
}

function diceroller_edit() {
    global $mybb;

    // Only perform edits if we were given the correct post key.
    if ($mybb->input['my_post_key'] != $mybb->post_code) {
        return;
    }

    global $PL;
    $PL or require_once PLUGINLIBRARY;

    if ($mybb->input['diceroller'] == 'edit') {
        $result = $PL->edit_core("diceroller", "inc/class_parser.php",
                                 array('search' => '$replace = "<blockquote',
                                       'before' => "global \$plugins;\n\$message = \$plugins->run_hooks(\"parse_quote_message\", \$message);"),
                                 true
        );
    } elseif ($mybb->input['diceroller'] == 'undo') {
        $result = $PL->edit_core("diceroller", "inc/class_parser.php",
                                 array(),
                                 true
        );
    } else {
        return;
    }

    if ($result === true) {
        // redirect with success
        flash_message("The file inc/class_parser.php was modified successfully.", "success");
        admin_redirect("index.php?module=config-plugins");
    } else {
        // redirect with failure (could offer the result string for download instead)
        flash_message("The file inc/class_parser.php could not be edited. Are the CHMOD settings correct?", "error");
        admin_redirect("index.php?module=config-plugins");
    }
}

function diceroller_code($message) {
    global $code_matches;

    preg_match_all("#\[(code|php)\](.*?)\[/\\1\](\r\n?|\n?)#si", $message, $code_matches, PREG_SET_ORDER);
    $message = preg_replace("#\[(code|php)\](.*?)\[/\\1\](\r\n?|\n?)#si", "{diceroller-code}", $message);

    return $message;
}

/**
 * Roll quoted dice so that their seed matches the PID of the quoted post.
 */
function diceroller_quote($message) {
    global $patterns;

    // Remove newlines for regex matching.
    $message = str_replace("\n", '<br />', $message);

    $message = preg_replace_callback($patterns[4], 'diceroller_quote_callback', $message);

    return $message;
}

/**
 * Recursively drill down into the quote tags and then roll dice as
 * it climbs back up.
 */
function diceroller_quote_callback($matches) {
    global $patterns;

    // Recursively drill down to innermost quote.
    $inner = $matches[4];
    $inner = preg_replace_callback($patterns[4], 'diceroller_quote_callback', $inner);

    // Extract PID and use to seed.
    $pid_pattern = '/pid=(?:&quot;|\"|\')?([0-9]+)[\"\']?(?:&quot;|\"|\')/';
    preg_match($pid_pattern, $matches[3], $pid_matches);
    if ((int)$pid_matches[1]) {
        $pid = $pid_matches[1];
        srand($pid);
    }

    // Replace [roll=*] tags.
    $inner = preg_replace_callback($patterns[1], 'parse_nds_callback', $inner);
    $inner = preg_replace_callback($patterns[0], 'parse_lowhigh_callback', $inner);
    $inner = preg_replace_callback($patterns[2], 'parse_weighted_callback', $inner);
    $inner = preg_replace_callback($patterns[3], 'parse_alias_callback', $inner);

    // Rebuild quote tags.
    return $matches[1] . $inner . $matches[5];
}

/**
 * Seed RNG using #post's PID and then parse [roll=*] tags.
 */
function diceroller_setup($post) {
	global $seed;

    // Fetch post ID to use as RNG seed.
	$seed = $post['pid'];

    // Parse [roll=*] MyCode
    $post['message'] = diceroller_parse($post['message']);

    return $post;
}

/**
 * Parse [roll=*] tags.
 */
function diceroller_parse(&$message) {
	global $mybb, $seed, $patterns, $templates, $code_matches;

    // Seed RNG
	srand($seed);


    // Strip newlines.
    // $message = str_replace("\n", "<br />", $message);

    // Compute rolls and replace MyCode for each pattern
	$message = preg_replace_callback($patterns[0], 'parse_lowhigh_callback', $message);
	$message = preg_replace_callback($patterns[1], 'parse_nds_callback', $message);
	$message = preg_replace_callback($patterns[2], 'parse_weighted_callback', $message);
	$message = preg_replace_callback($patterns[3], 'parse_alias_callback', $message);

    // Glue code tags back together.
    if(count($code_matches) > 0) {
        foreach($code_matches as $text) {
            if (my_strtolower($text[1]) == 'code') {
                // Fix up HTML inside the code tags so it is clean
                $text[2] = parse_html($text[2]);

                $code = mycode_parse_code($text[2]);
            } elseif (my_strtolower($text[1]) == 'php') {
                $code = mycode_parse_php($text[2]);
            }
            $message = preg_replace('/{diceroller-code}/', $code, $message, 1);
        }
    }

    $code_matches = null;

	return $message;
}

/**
 * Rolls dice for high-low format and evaluates templates.
 */
function parse_lowhigh_callback($matches) {
	global $mybb, $templates, $alias;

    $dosum = $mybb->settings['diceroller_enable_sum'];

    // Extract information from matches
	$low = $matches[2];
	$high = $matches[3];
	$offset = $matches[4];
	$dice = $low . '-' . $high . $offset;

    // Explode resources setting for given key
    $key = $alias ? $alias : $dice;
    $resourcesetting = get_setting_value('diceroller_resources', $key);
    if ($resourcesetting) $resourcelist = explode(',', $resourcesetting);

	$roll = rand(intval($low), intval($high));

    // Calculate sum
	if ($offset) {
        if ($dosum) {
    		$sum = $roll + $offset;
        }
	}

    // Get resource items
    if ($resourcelist) {
        $resource = $resourcelist[$roll-1];
        eval('$resources .= "' . str_replace("\n", " ", $templates->get('diceroller_resource')) . '";');
    }

    // Get result messages
    $key = $alias ? $alias : $dice;
    $number = $sum ? $sum : $roll;
    $resultlist = get_result_message($key, $number);
    foreach ($resultlist as $result) {
        eval('$results .= "' . str_replace("\n", " ", $templates->get('diceroller_result')) . '";');
    }

    // Evaluate templates
    if ($sum != null) eval('$sum  = "' . str_replace("\n", " ", $templates->get('diceroller_sum')) . '";');
    eval('$rolls .= "' . str_replace("\n", " ", $templates->get('diceroller_roll')) . '";');
	eval('$diceroller = "' . str_replace("\n", " ", $templates->get('diceroller')) . '";');
	return $diceroller;
}

/**
 * Rolls dice for NdS format and evaluates templates.
 */
function parse_nds_callback($matches) {
	global $mybb, $templates, $alias;

    $dosum = $mybb->settings['diceroller_enable_sum'];

    // Extract information from matches
	$number = $matches[2];
	$sides = $matches[3];
	$offset = $matches[4];
	$dice = $number . 'd' . $sides . $offset;

    // Explode resource setting
    $key = $alias ? $alias : $dice;
    $resourcesetting = get_setting_value('diceroller_resources', $key);
    if ($resourcesetting) $resourcelist = explode(',', $resourcesetting);
    $remove_dice = explode("\n", $mybb->settings['diceroller_remove_dice']);

    // Default number to 1
	if (!$number) $number = 1;

    // Roll dice $number times
	$rolled = array();
	for ($i = 0; $i < $number; $i++) {
        $roll = rand(1, intval($sides));
        if ($remove_dice and $number <= $sides) {
            while (in_array($roll, $rolled)) {
                $roll = rand(1, intval($sides));
            }
        }

		$rolled[] = $roll;

        // Display plus sign if there are additional rolls
        if ($i < $number-1) {
            $plus = '+';
        } else {
            $plus = '';
        }

        // Get resource items
        if ($resourcelist) {
            $resource = $resourcelist[$roll-1];
            eval('$resources .= "' . str_replace("\n", " ", $templates->get('diceroller_resource')) . '";');
        }

        eval('$rolls .= "' . str_replace("\n", " ", $templates->get('diceroller_roll')) . '";');
	}

    // Calculate sum
    if ($dosum) {
    	if ($number > 1 or $offset) {
    		$sum = array_sum($rolled);

        	if ($offset) {
        		$sum += $offset;
        	}
    	}
    }

    // Get result messages
    $num = $sum ? $sum : $rolled[0];
    $resultlist = get_result_message($key, $num);
    foreach ($resultlist as $result) {
        eval('$results .= "' . str_replace("\n", " ", $templates->get('diceroller_result')) . '";');
    }

    // Evaluate templates
    if ($sum != null) {
        eval('$sum  = "' . str_replace("\n", " ", $templates->get('diceroller_sum')) . '";');
    }
	eval('$diceroller  = "' . str_replace("\n", " ", $templates->get('diceroller')) . '";');
	return $diceroller;
}

/**
 * Rolls dice for weighted format and evaluates templates.
 */
function parse_weighted_callback($matches) {
	global $templates, $alias;

    $dosum = $mybb->settings['diceroller_enable_sum'];

    // Fetch information from matches and convert to array
	$dice = $matches[2];
	$weights = explode(',', $dice);

    // Explode resource setting for given key
    $key = $alias ? $alias : $dice;
    $resourcesetting = get_setting_value('diceroller_resources', $key);
    if ($resourcesetting) $resourcelist = explode(',', $resourcesetting);

    // Roll weighted die
	$roll = weighted_rng($weights);

    // Get result messages
    $key = $alias ? $alias : $dice;
    $resultlist = get_result_message($key, $roll);
    foreach ($resultlist as $result) {
        eval('$results .= "' . str_replace("\n", " ", $templates->get('diceroller_result')) . '";');
    }

    // Get resource item
    if ($resourcelist) {
        $resource = $resourcelist[$roll-1];
        eval('$resources .= "' . str_replace("\n", " ", $templates->get('diceroller_resource')) . '";');
    }

    // Evaluate templates
    eval('$rolls .= "' . str_replace("\n", " ", $templates->get('diceroller_roll')) . '";');
	eval('$diceroller  = "' . str_replace("\n", " ", $templates->get('diceroller')) . '";');
	return $diceroller;
}

/**
 * Chooses random number from list of weights.
 */
function weighted_rng($weights) {
  	$rand = rand(1, (int) array_sum($weights));

	foreach ($weights as $key => $value) {
		$rand -= $value;
		if ($rand <= 0) {
		  return $key + 1;
		}
	}
}

/**
 * Rolls dice for alias format and evaluates templates.
 */
function parse_alias_callback($matches) {
	global $mybb, $alias, $templates;

    // Extract information from matches and settings
    $alias = $matches[2];
    $dice = get_setting_value('diceroller_aliases', $alias);
    if (!$dice) {
        $alias = null;
        return "Alias does not exist.";
    }

    // Because the global $patterns won't work right here and I'm too lazy to
    // figure out why
    $alias_patterns = array(
		// alias=low-high | e.g. alias=1-6
        '/([\"\']?)(\d+)-(\d+)(([\+\-]\d+)?)\1/',
		// alias=(N)dS(+/-F) | e.g. alias=d6 | alias=1d6 | alias=1d6+10 | alias=1d6-10
        '/([\"\']?)(\d*)d(\d+)([\+\-]\d+)?\1/',
		// alias=weighed list | e.g. alias==2,2,1
        '/([\"\']?)(\d+(,\d+)*)\1/'
    );

    if (preg_match($alias_patterns[1], $dice)) {
        $dice = preg_replace_callback($alias_patterns[1], 'parse_nds_callback', $dice);
    } elseif (preg_match($alias_patterns[0], $dice)) {
        $dice = preg_replace_callback($alias_patterns[0], 'parse_lowhigh_callback', $dice);
    } elseif (preg_match($alias_patterns[2], $dice)) {
        $dice = preg_replace_callback($alias_patterns[2], 'parse_weighted_callback', $dice);
    }

    // Reset global $alias
    $alias = null;
    return $dice;
}

/**
 * Fetches value of setting $name and explodes into array delimited by '='.
 *
 * @param  string  $name                Case-sensitive ame of the setting.
 * @param  boolean $inner_explode       Whether the right hand side should also be exploded.
 * @return array                        Array of items in the setting.
 */
function explode_settings($name) {
    global $mybb;

    $settings = $mybb->settings[$name];
    $settings = explode("\n", $settings);
    foreach ($settings as $key => $value) {
        $explosion = explode('=', $value);
        $settings[$explosion[0]] = $explosion[1];
        unset($settings[$key]);
    }

    return $settings;
}

function explode_ranges($ranges) {
    $ranges = str_replace(' ', '', trim($ranges));
    $ranges = explode(",", $ranges);
    foreach ($ranges as $key => $value) {
        unset($ranges[$key]);
        $explosion = explode('-', $value);
        $ranges[] = array($explosion[0], $explosion[1]);
    }

    return $ranges;
}

/**
 * Determines if $number is between $min and $max
 *
 * @param  integer  $number     The number to test
 * @param  integer  $min        The minimum value in the range
 * @param  integer  $max        The maximum value in the range
 * @return boolean              Whether the number was in the range
 */
function in_range($number, $min, $max) {
    if (is_numeric($number) && is_numeric($min) && is_numeric($max)) {
        return ($number >= $min && $number <= $max);
    }

    return FALSE;
}

/**
 * Checks if $value is a key in setting $name
 */
function get_setting_value($name, $value) {
    $setting = explode_settings($name);

    return isset($setting[$value])
        ? $setting[$value]
        : null;
}

function get_result_message($key, $number) {
    $result_messages = explode(',', get_setting_value('diceroller_result_messages', $key));
    $result_ranges = get_setting_value('diceroller_result_ranges', $key);
    $results = array();
    if ($result_ranges) {
        $result_ranges = explode_ranges($result_ranges);
        $count = 0;
        foreach ($result_ranges as $range) {
            if (in_range($number, $range[0], $range[1])) {
                $results[] = $result_messages[$count];
            }
            $count++;
        }
    }

    return $results;
}

function parse_html($message) {
    $message = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $message); // fix & but allow unicode
    $message = str_replace("<","&lt;",$message);
    $message = str_replace(">","&gt;",$message);
    return $message;
}

function mycode_parse_code($code, $text_only=false) {
    global $lang, $templates;

    if ($text_only == true) {
        return "\n{$lang->code}\n--\n{$code}\n--\n";
    }

    // Clean the string before parsing.
    $code = preg_replace('#^(\t*)(\n|\r|\0|\x0B| )*#', '\\1', $code);
    $code = rtrim($code);
    $original = preg_replace('#^\t*#', '', $code);

    if (empty($original)) {
        return;
    }

    $code = str_replace('$', '&#36;', $code);
    $code = preg_replace('#\$([0-9])#', '\\\$\\1', $code);
    $code = str_replace('\\', '&#92;', $code);
    $code = str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', $code);
    $code = str_replace("  ", '&nbsp;&nbsp;', $code);
    $code = str_replace("\n", '<br />', $code);

    eval("\$mycode_code = \"".$templates->get("mycode_code", 1, 0)."\";");
    return $mycode_code;
}

/**
* Parses PHP code MyCode.
*
* @param string $str The message to be parsed
* @param boolean $bare_return Whether or not it should return it as pre-wrapped in a div or not.
* @param boolean $text_only Are we formatting as text?
* @return string The parsed message.
*/
function mycode_parse_php($str, $bare_return = false, $text_only = false)
{
    global $lang, $templates;

    if ($text_only == true) {
        return "\n{$lang->php_code}\n--\n$str\n--\n";
    }

    // Clean the string before parsing except tab spaces.
    $str = preg_replace('#^(\t*)(\n|\r|\0|\x0B| )*#', '\\1', $str);
    $str = rtrim($str);

    $original = preg_replace('#^\t*#', '', $str);

    if (empty($original)) {
        return;
    }

    // See if open and close tags are provided.
    $added_open_tag = false;
    if (!preg_match("#^\s*<\?#si", $str)) {
        $added_open_tag = true;
        $str = "<?php \n".$str;
    }

    $added_end_tag = false;
    if (!preg_match("#\?>\s*$#si", $str)) {
        $added_end_tag = true;
        $str = $str." \n?>";
    }

    $code = @highlight_string($str, true);

    // Do the actual replacing.
    $code = preg_replace('#<code>\s*<span style="color: \#000000">\s*#i', "<code>", $code);
    $code = preg_replace("#</span>\s*</code>#", "</code>", $code);
    $code = preg_replace("#</span>(\r\n?|\n?)</code>#", "</span></code>", $code);
    $code = str_replace("\\", '&#092;', $code);
    $code = str_replace('$', '&#36;', $code);
    $code = preg_replace("#&amp;\#([0-9]+);#si", "&#$1;", $code);

    if ($added_open_tag) {
        $code = preg_replace("#<code><span style=\"color: \#([A-Z0-9]{6})\">&lt;\?php( |&nbsp;)(<br />?)#", "<code><span style=\"color: #$1\">", $code);
    }

    if ($added_end_tag) {
        $code = str_replace("?&gt;</span></code>", "</span></code>", $code);
        // Wait a minute. It fails highlighting? Stupid highlighter.
        $code = str_replace("?&gt;</code>", "</code>", $code);
    }

    $code = preg_replace("#<span style=\"color: \#([A-Z0-9]{6})\"></span>#", "", $code);
    $code = str_replace("<code>", "<div dir=\"ltr\"><code>", $code);
    $code = str_replace("</code>", "</code></div>", $code);
    $code = preg_replace("# *$#", "", $code);

    if ($bare_return) {
        return $code;
    }

    // Send back the code all nice and pretty
    eval("\$mycode_php = \"".$templates->get("mycode_php", 1, 0)."\";");
    return $mycode_php;
}

?>
