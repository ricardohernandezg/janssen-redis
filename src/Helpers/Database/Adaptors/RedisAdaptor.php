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
            /*'ssl' => ['verify_peer' => false],*/
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

                $cnx->select($cfg['db']);
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

    // HANDLE TEXT VALUES

    public function setText(){

    }

    public function getText(){

    }

    public function delText(){
        
    }

    // HANDLE HASH VALUES

    /**
     * Set a hash value, mode can be one of this options
     * 0 - create the key and overwrite if exist (default)
     * 1 - create the key only if already exists
     * 2 - create the key only if not exists
     * 
     * $ttl is the time to live in miliseconds
     */
    public function setHash(string $key, string $value, int $mode = 0, int $ttl = -1){

        $opt = [];

        switch ($mode){
            case 1:
                $opt[] = 'xx';
                break;
            case 2:
                $opt[] = 'nx';
        }
        
        if($ttl >= 0) $opt['px'] = $ttl;

        return ($opt) ? $this->_cnx->set($key, $value, $opt) : $this->_cnx->set($key, $value);

    }

    /**
     * get a hash value
     */ 
    public function getHash(string $key){

    }

    /**
     * Delete a hash value
     */
    public function delHash(string $key)
    {

    }


    // HANDLE JSON VALUES

    /**
     * saave a JSON value
     */ 
    public function jsonSet(string $key, string $value, string $path = '.'){
        return $this->_cnx->rawCommand('JSON.SET', $key, $path, $value);
    }

    /**
     * get a JSON value
     */ 
    public function jsonGet(string $key, string $path = '.'){
        return $this->_cnx->rawCommand('JSON.GET', $key, $path);
    }

    /**
     * delete a JSON value
     */
    public function jsonDel(string $key, string $path = '.')
    {
        return $this->_cnx->rawCommand('JSON.DEL', $key, $path);
    }

    /**
     * returns the type of the value in path
     * (object, array, string, number, boolean, null)
     */
    public function jsonType(string $key, string $path = '.')
    {
        return $this->_cnx->rawCommand('JSON.TYPE', $key, $path);
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

    /**
     * Check if the redis object is connected
     */
    public function isConnected()
    {
       return $this->_cnx->isConnected();
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

    /**
     * Change the database to another using its index
     */
    public function changeDB(int $newDB)
    {
        return $this->_cnx->select($newDB);
    }

    /**
     * Get the info from server
     */
    public function getInfo()
    {

    }

}