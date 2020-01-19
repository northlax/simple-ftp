<?php

namespace SimpleFtp;

class SimpleFtp
{
    protected $connection = false;
    protected $login = false;
    protected $defaultPort = 21; //default port connect
    protected $defaultTimeout = 90; //default timeout connect

    /**
     * SimpleFtp constructor.
     * Host is the remote address to connect
     * Config can contains port (first param), timeout (second param)
     *
     * @param $host
     * @param mixed ...$config
     */
    public function __construct($host, ... $config)
    {
        if (!$host) {
            throw new Exception('Empty host!');
        }
        $this->connection = $this->connect($host, $config);
    }

    /**
     * Connect
     *
     * @param $host
     * @param $config
     * @return resource
     */
    public function connect($host, $config)
    {
        $port = $config[0] ?? $this->defaultPort;
        $timeout = $config[1] ?? $this->defaultTimeout;
        return ftp_connect($host, $port, $timeout);
    }

    /**
     * Disconnect
     *
     * @return bool
     */
    protected function disconnect()
    {
        return ftp_close($this->connection);
    }

    /**
     * Authorization
     *
     * @param $login
     * @param $password
     * @return bool
     */
    public function login($login, $password): bool
    {
        if ($this->connection && @ftp_login($this->connection, $login, $password)) {
            return $this->login = true;
        } else {
            return false;
        }
    }

    /**
     * Set passive mode
     *
     * @return bool
     */
    protected function setPassiveMode(): bool
    {
        return ftp_pasv($this->connection, true);
    }

    /**
     * Get list of files with dirs by the desired path
     *
     * @param $path
     * @param bool $sortByDateModify
     * @param string $type
     * @return array|bool
     */
    protected function scanFtpDir($path, bool $sortByDateModify = false, string $type = '')
    {
        $entities = ftp_mlsd($this->connection, $path);
        if (is_array($entities)) {
            $entities = $this->filterList($entities, $type);
            if ($sortByDateModify) {
                $entities = $this->sortList($entities);
            }
            return $entities;
        }
        return false;
    }

    /**
     * Remove remote file
     *
     * @param string $path
     * @return bool
     */
    protected function removeFile(string $path): bool
    {
        return @ftp_delete($this->connection, $path);
    }

    /**
     * Clear dir with files and sub-dirs recursively
     *
     * @param string $path
     * @return bool
     */
    protected function removeDir(string $path): bool
    {
        if ($list = $this->scanFtpDir($path)) {
            if ($list) {
                foreach ($list as $entity) {
                    $fullName = $path . '/' . $entity['name'];
                    if ($entity['type'] == 'dir') {
                        $this->removeDir($fullName);
                    } else {
                        $this->removeFile($fullName);
                    }
                }
            }
        }
        return ftp_rmdir($this->connection, $path);
    }

    /**
     * Upload local file to server
     *
     * @param string $remoteFile
     * @param string $localFile
     * @param int $mode
     * @param int $startpos
     * @return bool
     */
    protected function upload(string $remoteFile , string $localFile, int $mode = FTP_BINARY, int $startpos = 0): bool
    {
        return ftp_put($this->connection, $remoteFile, $localFile, $mode, $startpos);
    }

    /**
     * Rename remote file/dir
     *
     * @param $oldName
     * @param $newName
     * @return bool
     */
    protected function rename(string $oldName, string $newName): bool
    {
        return ftp_rename($this->connection, $oldName, $newName);
    }

    /**
     * Call main methods through checking authorization to ftp server
     *
     * @param $method
     * @param $args
     * @return bool|mixed
     */
    public function __call($method, $args) {
        if (method_exists($this, $method)) {
            if ($this->login) {
                return call_user_func_array([$this, $method], $args);
            } else {
                return false;
            }
        }
    }

    /**
     * Destruct - disconnect
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Helper method
     * Filtering the scanned directory
     *
     * @param $entities
     * @param $type
     * @return bool
     */
    public function filterList($entities, $type)
    {
        return $entities = array_filter($entities, function($val) use($type) {
            if ($val['name'] != '.' && $val['name'] != '..') {
                if ($type) {
                    return $val['type'] == $type;
                }
                return true;
            }
            return false;
        });
    }

    /**
     * Helper method
     * Sorting the scanned directory by date modify
     *
     * @param $entities
     * @return bool
     */
    public function sortList($entities)
    {
        usort($entities, function ($a, $b) {
            return (strtotime($a['modify']) < strtotime($b['modify'])) ? -1 : 1;
        });
        return $entities;
    }
}