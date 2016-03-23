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
 * Redis based session handler.
 *
 * @package    core
 * @copyright  2016 Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\session;

defined('MOODLE_INTERNAL') || die();

/**
 * Redis based session handler.
 *
 * @package    core
 * @copyright  2016 Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class redis extends handler {
    /** @var string $savepath sav_path string */
    protected $savepath;

    /** @var array $servers list of servers parsed from save_path */
    protected $servers;

    /** @var int $acquiretimeout how long to wait for session lock */
    protected $acquiretimeout = 120;

    /**
     * Create new instance of handler.
     */
    public function __construct() {
        global $CFG;

        if (!empty($CFG->session_redis_acquire_lock_timeout)) {
            $this->acquiretimeout = $CFG->session_redis_acquire_lock_timeout;
        }

        if (empty($CFG->session_redis_save_path)) {
            $this->savepath = '';
        } else {
            $this->savepath = $CFG->session_redis_save_path;
        }

        if (empty($this->savepath)) {
            $this->servers = array();
        } else {
            $this->servers = $this->connection_string_to_redis_servers($this->savepath);
        }

    }

    /**
     * Start the session.
     * @return bool success
     */
    public function start() {
        $default = ini_get('max_execution_time');
        set_time_limit($this->acquiretimeout);

        $result = parent::start();

        set_time_limit($default);

        return $result;
    }

    /**
     * Init session handler.
     */
    public function init() {
        if (!extension_loaded('Redis')) {
            throw new exception('sessionhandlerproblem', 'error', '', null, 'redis extension is not loaded');
        }

        if (empty($this->savepath)) {
            throw new exception('sessionhandlerproblem', 'error', '', null,
                '$CFG->session_redis_save_path must be specified in config.php');
        }

        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', $this->savepath);
    }

    /**
     * Check the backend contains data for this session id.
     *
     * Note: this is intended to be called from manager::session_exists() only.
     *
     * @param string $sid
     * @return bool true if session found.
     */
    public function session_exists($sid) {
        if (!$this->servers) {
            return false;
        }

        foreach ($this->servers as $server) {
            $redis = new \Redis();
            $redis->connect($server['host'], $server['port']);
            $redis->select($server['database']);
            $value = $redis->get($server['prefix'] . $sid);
            $redis->close();

            if ($value !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kill all active sessions, the core sessions table is
     * purged afterwards.
     */
    public function kill_all_sessions() {
        global $DB;
        if (!$this->servers) {
            return false;
        }

        $serverlist = array();
        foreach ($this->servers as $server) {
            $redis = new \Redis();
            $redis->connect($server['host'], $server['port']);
            $redis->select($server['database']);
            $serverlist[] = array($redis, $server['prefix']);
        }

        $rs = $DB->get_recordset('sessions', array(), 'id DESC', 'id, sid');
        foreach ($rs as $record) {
            foreach ($serverlist as $arr) {
                list($server, $prefix) = $arr;
                $server->delete($prefix . $sid);
            }
        }

        foreach ($serverlist as $arr) {
            list($server, $prefix) = $arr;
            $server->close();
        }
    }

    /**
     * Kill one session, the session record is removed afterwards.
     * @param string $sid
     */
    public function kill_session($sid) {
        if (!$this->servers) {
            return false;
        }

        // Go through the list of all servers because
        // we do not know where the session handler put the
        // data.

        foreach ($this->servers as $server) {
            $redis = new \Redis();
            $redis->connect($server['host'], $server['port']);
            $redis->select($server['database']);
            $redis->delete($server['prefix'] . $sid);
            $redis->close();
        }
    }

    /**
     * Convert a connection string to an array of servers
     *
     * EG: Converts: "tcp://host1:123?database=0, tcp://host2?database=0&prefix=example" to
     *
     *  array(
     *      (
     *          [scheme]   => 'tcp',
     *          [host]     => 'host1',
     *          [port]     => 123,
     *          [database] => 0,
     *          [prefix]   => 'PHPREDIS_SESSION:'
     *      ),
     *      (
     *          [scheme]   => 'tcp',
     *          [host]     => 'host2',
     *          [port]     => 6379,
     *          [database] => 0,
     *          [prefix]   => 'example'
     *      )
     *  )
     *
     * @copyright  2016 Nicholas Hoobin <nicholashoobin@catalyst-au.net>
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @author     Nicholas Hoobin
     *
     * @param string $str save_path value containing memcached connection string
     * @return array
     */
    private function connection_string_to_redis_servers($str) {
        $servers     = array();
        $connections = explode(',', $str);

        foreach ($connections as $con) {
            $con = trim($con);
            $con = parse_url($con);

            // Seriously wrong url, parse_url failed.
            if ($con === false) {
                continue;
            }

            // Parsing the query string.
            if (isset($con['query'])) {
                $query = $con['query'];
                $parts = explode('&', $query);

                foreach ($parts as $part) {
                    list($key, $value) = explode('=', $part);
                    $con[$key] = $value;
                }
            }

            // Setting the default port.
            if (!isset($con['port'])) {
                $con['port'] = 6379;
            }

            // Setting the default prefix.
            if (!isset($con['prefix'])) {
                $con['prefix'] = 'PHPREDIS_SESSION:';
            }

            // Setting the default database.
            if (!isset($con['database'])) {
                $con['database'] = 0;
            }

            // If there has not been a scheme set in the string then
            // the return object will not have a 'host', but 'path'.
            if (isset($con['path'])) {
                $con['host'] = $con['path'];
                unset($con['path']);
            }

            $servers[] = $con;
        }

        return $servers;
    }
}
