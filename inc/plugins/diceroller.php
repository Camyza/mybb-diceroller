<?php
/**
 * Dice Roller MyCode
 * Copyright 2017 Shinka, All Rights Reserved
 *
 * License: http://www.mybb.com/about/license
 *
 */

 global $seed, $alias;
 $seed = 1;     // seed used for RNG -- fallback to 1 if cannot fetch post ID

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("postbit", "diceroller_setup");
$plugins->add_hook("postbit_prev", "diceroller_setup");
$plugins->add_hook("parse_message_end", "diceroller_parse");

function diceroller_info() {
	return array(
		"name"			=> "Dice Roller MyCode",
		"description"	=> "Rolls dice in a variety of exciting ways.",
		"website"		=> "https://community.mybb.com/mods.php?action=view&pid=955",
		"author"		=> "Shinka",
		"authorsite"	=> "",
		"version"		=> "1.0",
		"guid" 			=> "",
		"compatibility" => "18*"
	);
}

function diceroller_install() {
    global $db, $mybb;

    // Add templates
	$diceroller = '<div align="center">Rolling $dalias {$dice}: $rolls $doffset $dsum $results $resources</div>';
    $diceroller_roll = '{$roll} {$plus}';
    $diceroller_offset = '{$offset}';
    $diceroller_sum = '= {$sum}';
    $diceroller_result = '<br />{$result}';
    $diceroller_alias = '<strong>{$alias}</strong>';
    $diceroller_resource = '<br />{$resource}';

	$diceroller_array = array(
	    'title' => 'diceroller',
	    'template' => $db->escape_string($diceroller),
	    'sid' => '-1',
	    'version' => '',
	    'dateline' => time()
	);

	$diceroller_roll_array = array(
	    'title' => 'diceroller_roll',
	    'template' => $db->escape_string($diceroller_roll),
	    'sid' => '-1',
	    'version' => '',
	    'dateline' => time()
	);

	$diceroller_offset_array = array(
	    'title' => 'diceroller_offset',
	    'template' => $db->escape_string($diceroller_offset),
	    'sid' => '-1',
	    'version' => '',
	    'dateline' => time()
	);

	$diceroller_sum_array = array(
	    'title' => 'diceroller_sum',
	    'template' => $db->escape_string($diceroller_sum),
	    'sid' => '-1',
	    'version' => '',
	    'dateline' => time()
	);

	$diceroller_result_array = array(
	    'title' => 'diceroller_result',
	    'template' => $db->escape_string($diceroller_result),
	    'sid' => '-1',
	    'version' => '',
	    'dateline' => time()
	);

	$diceroller_alias_array = array(
	    'title' => 'diceroller_alias',
	    'template' => $db->escape_string($diceroller_alias),
	    'sid' => '-1',
	    'version' => '',
	    'dateline' => time()
	);

	$diceroller_resource_array = array(
	    'title' => 'diceroller_resource',
	    'template' => $db->escape_string($diceroller_resource),
	    'sid' => '-1',
	    'version' => '',
	    'dateline' => time()
	);

	$db->insert_query('templates', $diceroller_array);
	$db->insert_query('templates', $diceroller_roll_array);
	$db->insert_query('templates', $diceroller_offset_array);
	$db->insert_query('templates', $diceroller_sum_array);
	$db->insert_query('templates', $diceroller_result_array);
	$db->insert_query('templates', $diceroller_alias_array);
	$db->insert_query('templates', $diceroller_resource_array);

    // Add settings group
    $setting_group = array(
        'name' => 'dicerollergroup',
        'title' => 'Dice Roller MyCode Settings',
        'description' => 'Roll dice in a variety of exciting ways!',
        'disporder' => 5,
        'isdefault' => 0
    );

    $gid = $db->insert_query("settinggroups", $setting_group);

    // Add settings
    $setting_array = array(
        'enable_sum' => array(
            'title' => 'Display sum of NdS rolls?',
            'description' => 'When rolling multiple dice with NdS, should the sum of the results be displayed?',
            'optionscode' => 'yesno',
            'value' => 1,
            'disporder' => 1
        ),
        'aliases' => array(
            'title' => 'Roll Aliases',
            'description' => 'A list of shorthand names and the rolls they
            represent, using any of the supported syntax formats. Separate aliases
            with line breaks, e.g.
            <br />strength=1d6+5
            <br />endurance=1-10
            <br />agility=25,25,50',
            'optionscode' => 'textarea',
            'value' => '',
            'disporder' => 2
        ),
        'result_messages' => array(
            'title' => 'Result Messages',
            'description' => 'Display custom messages based on the result of a roll.
            Output is based on sum of all rolls and offset.
            Separate messages with commas and rolls with line breaks, e.g.
            <br />1d20=Crit Fail, Fail, Success, Crit Success
            <br />strength=Fumbled!, Missed!, Glancing Blow!, Direct Hit!, Perfect Strike!
            <br />agility=You faceplanted!?, You tripped..., You have legs, Run like the wind!!!',
            'optionscode' => 'textarea',
            'value' => '',
            'disporder' => 3
        ),
        'result_ranges' => array(
            'title' => 'Result Ranges',
            'description' => 'Ranges in format <i>low</i>-<i>high</i> that
            indicate when to display each custom message.
            Separate messages with commas and rolls with line breaks, e.g.
            <br />1d20=1-1,2-10,11-19,20-20 (rolling 1 will display "Crit Fail",
            rolling between 2-10 will display "Fail", etc.)
            <br />strength=1-5,6-10,11-15,16-20
            <br />agility=1-25,26-50,51-75,76-100',
            'optionscode' => 'textarea',
            'value' => '',
            'disporder' => 4
        ),
        'resources' => array(
            'title' => 'Resources',
            'description' => 'Display resource item based on the result of a roll.
            Output is based on each roll, not the sum, so 3d20 will output three
            resource items. Separate items with commas and rolls with line breaks, e.g.
            <br />1d20=Common Card,Uncommon Card,Rare Card,Super Rare Card!!!!',
            'optionscode' => 'textarea',
            'value' => '',
            'disporder' => 5
        ),
        'remove_dice' => array(
            'title' => 'Remove Dice From Pool',
            'description' => 'Remove value from dice pool so it cannot be landed
            on again. List rolls or aliases you want to turn this setting on for,
            separated by line breaks, e.g.
            <br />carddraw
            <br />1d20',
            'optionscode' => 'textarea',
            'value' => '',
            'disporder' => 6
        )
    );

    foreach($setting_array as $name => $setting)
    {
        $setting['name'] = $name;
        $setting['gid'] = $gid;

        $db->insert_query('settings', $setting);
    }

    rebuild_settings();
}

function diceroller_uninstall() {
	global $db;

    // Delete template
	$db->delete_query("templates", "title IN ('diceroller', 'diceroller_roll',
        'diceroller_offset', 'diceroller_sum', 'diceroller_result', 'diceroller_alias',
        'diceroller_resource')");

    // Delete settings and settings group
    $db->delete_query('settings', "name IN ('enable_sum', 'aliases',
        'result_messages', 'result_ranges, remove_dice')");
    $db->delete_query('settinggroups', "name = 'dicerollergroup'");

    // Don't forget this
    rebuild_settings();
}

function diceroller_is_installed() {
	global $db;

	$query = $db->simple_select("settinggroups", "COUNT(*)", "name = 'dicerollergroup'");
 	return $db->fetch_field($query, "COUNT(*)");
}

function diceroller_setup($post) {
	global $seed;

    // Fetch post ID to use as RNG seed.
	$seed = $post['pid'];
}

function diceroller_parse(&$message) {
	global $mybb, $seed;

    // Seed RNG.
	if ($seed) srand($seed);

    // Regex patterns for different roll types
	$patterns = array(
		// [roll=low-high] | e.g. [roll=1-6]
		'/\[roll=([\"\']?)(\d+)-(\d+)(([\+\-]\d+)?)\1\]/',
		// [roll=(N)dS(+/-F)] | e.g. [roll=d6] | [roll=1d6] | [roll=1d6+10] | [roll=1d6-10]
		'/\[roll=([\"\']?)(\d*)d(\d+)([\+\-]\d+)?\1\]/',
		// [roll=weighed list] | e.g. [roll=2,2,1]
		'/\[roll=([\"\']?)(\d+(,\d+)*)\1\]/',
        // [roll=alias] | e.g. [roll=strength]
		'/\[roll=([\"\']?)(\w+)\1\]/',
	);

    // Compute rolls and replace MyCode for each pattern
	$message = preg_replace_callback($patterns[0], 'parse_lowhigh_callback', $message);
	$message = preg_replace_callback($patterns[1], 'parse_nds_callback', $message);
	$message = preg_replace_callback($patterns[2], 'parse_weighted_callback', $message);
	$message = preg_replace_callback($patterns[3], 'parse_alias_callback', $message);

	return $message;
}

/**
 * Rolls dice for high-low format and evaluates templates.
 */
function parse_lowhigh_callback($matches) {
	global $mybb, $templates, $seed, $alias;

    $dosum = $mybb->settings['enable_sum'];

    // Extract information from matches
	$low = $matches[2];
	$high = $matches[3];
	$offset = $matches[4];
	$dice = $low . '-' . $high . $offset;

    $key = $alias ? $alias : $dice;
    $resourcelist = explode(',', get_setting_value('resources', $key));

	$roll = rand(intval($low), intval($high));

    // Calculate sum
	if ($offset) {
        if ($dosum) {
    		$sum = $roll + $offset;
        }
	}

    // Get resource items
    $resource = $resourcelist[$roll-1];
    eval('$resources .= "' . $templates->get('diceroller_resource') . '";');

    // Get result messages
    $key = $alias ? $alias : $dice;
    $number = $sum ? $sum : $roll;
    $resultlist = get_result_message($key, $number);
    foreach ($resultlist as $result) {
        eval('$results .= "' . $templates->get('diceroller_result') . '";');
    }

    if ($sum != null) eval('$dsum  = "' . $templates->get('diceroller_sum') . '";');
    eval('$doffset  = "' . $templates->get('diceroller_offset') . '";');
    eval('$rolls .= "' . $templates->get('diceroller_roll') . '";');
	eval('$diceroller = "' . $templates->get('diceroller') . '";');
	return $diceroller;
}

/**
 * Rolls dice for NdS format and evaluates templates.
 */
function parse_nds_callback($matches) {
	global $mybb, $templates, $seed, $alias;

    $dosum = $mybb->settings['enable_sum'];

    // Extract information from matches
	$number = $matches[2];
	$sides = $matches[3];
	$offset = $matches[4];
	$dice = $number . 'd' . $sides . $offset;

    $key = $alias ? $alias : $dice;
    $resourcelist = explode(',', get_setting_value('resources', $key));
    $remove_dice = explode("\n", $mybb->settings['remove_dice']);

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

        if ($i < $number-1) {
            $plus = '+';
        } else {
            $plus = '';
        }

        // Get resource items
        $resource = $resourcelist[$roll-1];
        eval('$resources .= "' . $templates->get('diceroller_resource') . '";');
        eval('$rolls .= "' . $templates->get('diceroller_roll') . '";');
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
        eval('$results .= "' . $templates->get('diceroller_result') . '";');
    }

    if ($sum != null) {
        eval('$dsum  = "' . $templates->get('diceroller_sum') . '";');
    }
    eval('$doffset  = "' . $templates->get('diceroller_offset') . '";');
	eval('$diceroller  = "' . $templates->get('diceroller') . '";');
	return $diceroller;
}

/**
 * Rolls dice for weighted format and evaluates templates.
 */
function parse_weighted_callback($matches) {
	global $templates, $seed, $alias;

    $dosum = $mybb->settings['enable_sum'];

    // Fetch information from matches and convert to array
	$dice = $matches[2];
	$weights = explode(',', $dice);

    $key = $alias ? $alias : $dice;
    $resourcelist = explode(',', get_setting_value('resources', $key));

    // Roll weighted die
	$roll = weighted_rng($weights);

    // Get result messages
    $key = $alias ? $alias : $dice;
    $resultlist = get_result_message($key, $roll);
    foreach ($resultlist as $result) {
        eval('$results .= "' . $templates->get('diceroller_result') . '";');
    }

    // Get resource items
    $resource = $resourcelist[$roll-1];
    eval('$resources .= "' . $templates->get('diceroller_resource') . '";');

    eval('$rolls .= "' . $templates->get('diceroller_roll') . '";');
	eval('$diceroller  = "' . $templates->get('diceroller') . '";');
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

    $alias = $matches[2];
    $dice = get_setting_value('aliases', $alias);
    if (!$dice) {
        $alias = null;
        return "Alias does not exist.";
    }

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
    } else if (preg_match($alias_patterns[0], $dice)) {
        $dice = preg_replace_callback($alias_patterns[0], 'parse_lowhigh_callback', $dice);
    } else if (preg_match($alias_patterns[2], $dice)) {
        $dice = preg_replace_callback($alias_patterns[2], 'parse_weighted_callback', $dice);
    }

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
    $result_messages = explode(',', get_setting_value('result_messages', $key));
    $result_ranges = get_setting_value('result_ranges', $key);
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

?>
