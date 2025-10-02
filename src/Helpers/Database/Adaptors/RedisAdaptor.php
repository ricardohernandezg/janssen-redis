<?php 

namespace Janssen\Helpers\Database\Adaptors;

use Janssen\Engine\Config;
use Janssen\Helpers\Exception;
use Janssen\Traits\InstanceGetter;
use Janssen\Traits\StaticCall;
use \Redis;
use \RedisException;

class RedisAdaptor
{
    use InstanceGetter;
    use StaticCall;

    private $_cnx;
    private $last_error;

    // conectar al servidor redis
    public function connect(string $connection_name = '')
    {
        // get the settings from config
        $cfg = Config::get('connections')[$connection_name] ?? false;
        if(!$cfg)
            throw new Exception("$connection_name is not configured");

        $auth = [];
        if(($cfg['user'] ?? '') !== ''){
            $auth['user'] = $cfg['user'];
        }
        if(($cfg['pass'] ?? '') !== ''){
            $auth['pass'] = $cfg['pass'];
        }

        $opt = [
            'host' => $cfg['host'],
            'port' => $cfg['port'],
            'connectTimeout' => 2.5,
            'database' => $cfg['db'],
            'ssl' => ['verify_peer' => false],
            'backoff' => [
                'algorithm' => Redis::BACKOFF_ALGORITHM_DECORRELATED_JITTER,
                'base' => 500,
                'cap' => 750,
            ],
        ];
        if($auth) $opt['auth'] = $auth;

        try {
            $cnx = new Redis($opt);

            if($cnx){

                $this->_cnx = $cnx;

            }else{
                $this->setLastError(500, 'Couldn\'t connect to Redis');
                return false;    
            }

        }catch (RedisException $e){
            $this->setLastError(500, $e->getMessage());
            return false;
        }

        return $this;
    }

    // save a hash value
    public function setHash(string $key, string $value){

    }

    // saave a JSON value
    public function setJSON(string $key, string $value){

    }

    // get a hash value
    public function getHash(string $key){

    }

    // get a JSON value
    public function getJSON(string $key, string $path){


    }

    /**
     * Returs the connection native object
     * 
     * @return Object
     */
    public function getConnector(){
        return $this->_cnx;
    }

    // erase all
    public function flush(){
        $this->_cnx->flushDb();
    }

    // hacer ping
    public function isConnected()
    {
        return $this->_cnx->ping();
    }
    
    /**
     * Set the last error in a internal variable to allow the user 
     * to know what happened if the statement returns false
     * 
     * @param String $code
     * @param String $message
     * @return $this
     */
    public function setLastError($code, $message)
    {
        $this->last_error = [
            'code' => $code,
            'message' => $message,
        ];
        return $this;
    }
    
    /**
     * Return the user an array with the last error data
     *
     * @return Array
     */
    public function getLastError()
    {
        return $this->last_error;
    }

}