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

    /** @var string $serverip redis server ip address */
    protected $serverip;

    /** @var int $port redis server port */
    protected $port = 6379;

    /** @var string $prefix prefix used to identify session, defaults to value */
    protected $prefix = 'PHPREDIS_SESSION:';

    /** @var int $database the redis database that will be used, default values from 0-15 */
    protected $database = 0;

    /** @var int $acquiretimeout how long to wait for session lock */
    protected $acquiretimeout = 120;

    /**
     * Create new instance of handler.
     */
    public function __construct() {
        global $CFG;

        if (empty($CFG->session_redis_serverip)) {
            $this->serverip = '';
        } else {
            $this->serverip = $CFG->session_redis_serverip;
        }

        if (!empty($CFG->session_redis_prefix)) {
            $this->prefix = $CFG->session_redis_prefix;
        }

        if (!empty($CFG->session_redis_port)) {
            $this->port = $CFG->session_redis_port;
        }

        if (!empty($CFG->session_redis_database)) {
            $this->database = $CFG->session_redis_database;
        }

        if (!empty($CFG->session_redis_acquire_lock_timeout)) {
            $this->acquiretimeout = $CFG->session_redis_acquire_lock_timeout;
        }

        $this->savepath = 'tcp://' . $this->serverip . ':' . $this->port . '?database=' . $this->database;

        if (!empty($CFG->session_redis_prefix)) {
            $this->savepath .= '&prefix=' . $this->prefix;
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

        if (empty($this->serverip)) {
            throw new exception('sessionhandlerproblem', 'error', '', null, '$CFG->session_redis_serverip must be specified in config.php');
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
        $redis = new \Redis();
        $redis->connect($this->serverip, $this->port);
        $redis->select($this->database);
        $value = $redis->get($this->prefix . $sid);
        $redis->close();

        if ($value !== false) {
            return true;
        }

        return false;
    }

    /**
     * Kill all active sessions, the core sessions table is
     * purged afterwards.
     */
    public function kill_all_sessions() {
        global $DB;

        $redis = new \Redis();
        $redis->connect($this->serverip, $this->port);
        $redis->select($this->database);

        $rs = $DB->get_recordset('sessions', array(), 'id DESC', 'id, sid');
        foreach ($rs as $record) {
            $redis->delete($this->prefix . $sid);
        }
        $redis->close();
    }

    /**
     * Kill one session, the session record is removed afterwards.
     * @param string $sid
     */
    public function kill_session($sid) {
        $redis = new \Redis();
        $redis->connect($this->serverip, $this->port);
        $redis->select($this->database);
        $redis->delete($this->prefix . $sid);
        $redis->close();
    }
}
