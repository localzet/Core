<?php
/**
 * @package     WebCore Server
 * @link        https://localzet.gitbook.io
 * 
 * @author      localzet <creator@localzet.ru>
 * 
 * @copyright   Copyright (c) 2018-2020 Zorin Projects 
 * @copyright   Copyright (c) 2020-2022 NONA Team
 * 
 * @license     https://www.localzet.ru/license GNU GPLv3 License
 */
namespace localzet\Core\Protocols\Http\Session;

use localzet\Core\Protocols\Http\Session;

/**
 * Class FileSessionHandler
 * @package localzet\Core\Protocols\Http\Session
 */
class FileSessionHandler implements SessionHandlerInterface
{
    /**
     * Session save path.
     *
     * @var string
     */
    protected static $_sessionSavePath = null;

    /**
     * Session file prefix.
     *
     * @var string
     */
    protected static $_sessionFilePrefix = 'session_';

    /**
     * Init.
     */
    public static function init() {
        $save_path = @\session_save_path();
        if (!$save_path || \strpos($save_path, 'tcp://') === 0) {
            $save_path = \sys_get_temp_dir();
        }
        static::sessionSavePath($save_path);
    }

    /**
     * FileSessionHandler constructor.
     * @param array $config
     */
    public function __construct($config = array()) {
        if (isset($config['save_path'])) {
            static::sessionSavePath($config['save_path']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open($save_path, $name)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($session_id)
    {
        $session_file = static::sessionFile($session_id);
        \clearstatcache();
        if (\is_file($session_file)) {
            if (\time() - \filemtime($session_file) > Session::$lifetime) {
                \unlink($session_file);
                return '';
            }
            $data = \file_get_contents($session_file);
            return $data ? $data : '';
        }
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function write($session_id, $session_data)
    {
        $temp_file = static::$_sessionSavePath.uniqid(mt_rand(), true);
        if (!\file_put_contents($temp_file, $session_data)) {
            return false;
        }
        return \rename($temp_file, static::sessionFile($session_id));
    }

    /**
     * Update sesstion modify time.
     * 
     * @see https://www.php.net/manual/en/class.sessionupdatetimestamphandlerinterface.php
     * @see https://www.php.net/manual/zh/function.touch.php
     * 
     * @param string $id Session id.
     * @param string $data Session Data.
     * 
     * @return bool
     */
    public function updateTimestamp($id, $data = "")
    {
        $session_file = static::sessionFile($id);
        if (!file_exists($session_file)) {
            return false;
        }
        // set file modify time to current time
        $set_modify_time = \touch($session_file);
        // clear file stat cache
        \clearstatcache();
        return $set_modify_time;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($session_id)
    {
        $session_file = static::sessionFile($session_id);
        if (\is_file($session_file)) {
            \unlink($session_file);
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxlifetime) {
        $time_now = \time();
        foreach (\glob(static::$_sessionSavePath . static::$_sessionFilePrefix . '*') as $file) {
            if(\is_file($file) && $time_now - \filemtime($file) > $maxlifetime) {
                \unlink($file);
            }
        }
    }

    /**
     * Get session file path.
     *
     * @param string $session_id
     * @return string
     */
    protected static function sessionFile($session_id) {
        return static::$_sessionSavePath.static::$_sessionFilePrefix.$session_id;
    }

    /**
     * Get or set session file path.
     *
     * @param string $path
     * @return string
     */
    public static function sessionSavePath($path) {
        if ($path) {
            if ($path[\strlen($path)-1] !== DIRECTORY_SEPARATOR) {
                $path .= DIRECTORY_SEPARATOR;
            }
            static::$_sessionSavePath = $path;
            if (!\is_dir($path)) {
                \mkdir($path, 0777, true);
            }
        }
        return $path;
    }
}

FileSessionHandler::init();