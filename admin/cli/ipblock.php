<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Enable or disable the IP blocker.
 *
 * @package    core
 * @subpackage cli
 * @author     Grigory Baleevskiy <grigory@catalyst-au.net>
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir.'/clilib.php');

// Now get cli options.
list($options, $unrecognized) = cli_get_params(array(
    'enable' => false,
    'disable' => false,
    'message' => false,
    'theme' => false,
    'reset' => false,
    'help' => false,
    ), array('h' => 'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "IP Blocker settings.
Current status will be displayed if no option is specified.

Options:
--enable                    Enable the IP blocker.
--disable                   Disable the IP blocker.
--theme                     Encapsulate the error message around the current theme.
--message=STRING            The message that is shown to blocked users.
--reset                     Resets IP blocker message defaults that could be configured here.
-h, --help                  Print out this help.
";
    echo $help;
    die;
}

cli_heading(get_string('ipblocker', 'admin')." ($CFG->wwwroot)");

if ($options['message']) {
    set_config('ipblockermessage', $options['message']);
}

if ($options['theme']) {
    set_config('enableipblockertheme', 1);
    echo get_string('ipblockerclitheme', 'admin')."\n";
} else {
    set_config('enableipblockertheme', 0);
}

if ($options['reset']) {
    echo get_string('ipblockerclireset', 'admin')."\n";
    set_config('ipblockermessage', get_string('ipblocked', 'admin'));
    set_config('enableipblockertheme', 0);
    set_config('enableipblocker', 0);
    exit(0);
} else if ($options['enable']) {
    echo get_string('ipblockerclienable', 'admin')."\n";
    set_config('enableipblocker', 1);
    exit(0);
} else if ($options['disable']) {
    echo get_string('ipblockerclidisable', 'admin')."\n";
    set_config('enableipblocker', 0);
    exit(0);
}

if (!empty($CFG->enableipblocker)) {
    echo get_string('clistatusenabled', 'admin')."\n";
} else {
    echo get_string('clistatusdisabled', 'admin')."\n";
}

