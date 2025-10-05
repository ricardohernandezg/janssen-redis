<?php 

namespace Janssen\Helpers\Database\Adaptors;

use Janssen\Engine\Config;
use Janssen\Helpers\Exception;
use Janssen\Traits\InstanceGetter;
use Janssen\Traits\StaticCall;
use \Redis;
use \RedisException;
use Redislabs\Module\RedisJson\RedisJson; 


class RedisAdaptor
{
    use InstanceGetter;
    use StaticCall;

    private $_cnx;
    private $_cnxjson;
    private $last_error;


    public function __construct(string $connection_name) {

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

                $this->_cnx = $cnx;
                $this->_cnx->connect();
                $this->_cnx->select($$cfg['db']);

                // Crear instancia RedisJSON con phpredis
                $this->_cnxjson = RedisJson::createWithPhpRedis($this->_cnx);

            }else{
                $this->setLastError(500, 'Couldn\'t connect to Redis');
                return false;    
            }

        }catch (RedisException $e){
            $this->setLastError(500, $e->getMessage());
            return false;
        }

    }

    // HANDLE KEYS

    /**
     * Check that $key exists
     */
    public function keyExists(array $keys): int|bool 
    {
        return $this->_cnx->exists($keys);
    }    

    /**
     * Delete $keys
     */
    public function deleteKey(array $keys): int {
        return $this->_cnx->del($keys);
    }    

    /**
     * Set expiration in seconds
     */
    public function setExpire(string $key, int $ttl): bool
    {
        return $this->_cnx->expire($key, $ttl);
    }

    /**
     * Set expiration in miliseconds
     */
    public function setPexpire(string $key, int $ttl): bool
    {
        return $this->_cnx->pexpire($key, $ttl);
    }

    // HANDLE STRING  VALUES

    /**
     * Set a string value
     *  
     * $mode can be one of this options
     * 0 - create the key and overwrite if exist (default)
     * 1 - create the key only if already exists
     * 2 - create the key only if not exists
     * 
     * $ttl is the time to live in miliseconds. -1 is permanent (default)
     */    
    public function setString(string $key, string $value, int $mode = 0, int $ttl = -1): bool {
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
     * Get one string by $key
     */
    public function getString(string $key): ?string {
        $val = $this->_cnx->get($key);
        return $val === false ? null : $val;
    }


    // HANDLE HASH VALUES

    /**
     * Set a hash value
     *  
     * $mode can be one of this options
     * 0 - create the key and overwrite if exist (default)
     * 1 - create the key only if already exists
     * 2 - create the key only if not exists
     * 
     * $ttl is the time to live in miliseconds. -1 is permanent (default)
     */    
    public function setHashField(string $key, string $field, string $value, int $mode = 0, int $ttl = -1): int {
        
        $r = false;
        $apply = false;

        switch ($mode){
            case 1:
                if ($this->keyExists([$key])) {
                    $apply = true;
                }else
                    return false;

                break;
            case 2:
                if (!$this->keyExists([$key])) {
                    $apply = true;
                }else
                    return false;
                
                break;
            default:
                $apply = true;
        }
        
        if($apply){
            $r = $this->_cnx->hSet($key, $field, $value);
            if($r && $ttl > 0) $this->setPexpire($key, $ttl);
        }

        return $r;
    }

    /**
     * Set several values to one hash
     * 
     * $mode can be one of this options
     * 0 - create the key and overwrite if exist (default)
     * 1 - create the key only if already exists
     * 2 - create the key only if not exists
     * 
     * $ttl is the time to live in miliseconds. -1 is permanent (default)
     */
    public function setHashFields(string $key, array $fields, int $mode = 0, int $ttl = -1): bool {
        $r = false;
        $apply = false;

        switch ($mode){
            case 1:
                if ($this->keyExists([$key])) {
                    $apply = true;
                }else
                    return false;

                break;
            case 2:
                if (!$this->keyExists([$key])) {
                    $apply = true;
                }else
                    return false;
                
                break;
            default:
                $apply = true;
        }
        
        if($apply){
            $r = $this->_cnx->hMSet($key, $fields);
            if($r && $ttl > 0) $this->setPexpire($key, $ttl);
        }

        return $r;
    }

    /**
     * Get one hash by $key and $field
     */
    public function getHashField(string $key, string $field): ?string {
        $val = $this->_cnx->hGet($key, $field);
        return $val === false ? null : $val;
    }

    /**
     * Get all hashes by $key
     */
    public function getHashAll(string $key): array {
        return $this->_cnx->hGetAll($key);
    }

    // HANDLE LISTS

    public function pushToList(string $key, string $value, bool $left = true): int {
        return $left ? $this->_cnx->lPush($key, $value) : $this->_cnx->rPush($key, $value);
    }
    public function popFromList(string $key, bool $left = true): ?string {
        $val = $left ? $this->_cnx->lPop($key) : $this->_cnx->rPop($key);
        return $val === false ? null : $val;
    }
    public function getListRange(string $key, int $start = 0, int $end = -1): array {
        return $this->_cnx->lRange($key, $start, $end);
    }
    
    // HANDLE SETS

    public function addToSet(string $key, string ...$members): int {
        return $this->_cnx->sAdd($key, ...$members);
    }
    public function removeFromSet(string $key, string ...$members): int {
        return $this->_cnx->sRem($key, ...$members);
    }
    public function isMemberOfSet(string $key, string $member): bool {
        return $this->_cnx->sIsMember($key, $member);
    }
    public function getSetMembers(string $key): array {
        return $this->_cnx->sMembers($key);
    }

    // HANDLE SORTED SETS

    public function addToZSet(string $key, float $score, string $member): int {
        return $this->_cnx->zAdd($key, $score, $member);
    }
    public function removeFromZSet(string $key, string $member): int {
        return $this->_cnx->zRem($key, $member);
    }
    public function getZSetRange(string $key, int $start = 0, int $end = -1, bool $withScores = false): array {
        return $withScores ? $this->_cnx->zRange($key, $start, $end, true) : $this->_cnx->zRange($key, $start, $end);
    }
    public function getZSetRevRange(string $key, int $start = 0, int $end = -1, bool $withScores = false): array {
        return $withScores ? $this->_cnx->zRevRange($key, $start, $end, true) : $this->_cnx->zRevRange($key, $start, $end);
    }

    // HANDLE HYPERLOGLOG 

    /**
     * HyperLogLog en Redis es una estructura de datos probabilística que estima la cardinalidad (cantidad de elementos únicos) 
     * de un conjunto. A diferencia de otras estructuras que requieren almacenar todos los elementos para contar los únicos, 
     * HyperLogLog utiliza una cantidad fija y muy pequeña de memoria (hasta 12 KB) para proporcionar una estimación con un 
     * margen de error estándar alrededor del 0.81%. Esto lo hace muy eficiente para contar valores únicos en grandes volúmenes 
     * de datos sin almacenar los datos originales.
     * 
     * Usos comunes
     * - Contar visitantes únicos en un sitio web.
     * - Contar IPs únicas en logs.
     * - Medir usuarios activos o eventos únicos sin consumir gran cantidad de memoria.
     *
     */
    public function addToHyperLogLog(string $key, string ...$elements): bool {
        return $this->_cnx->pfAdd($key, ...$elements);
    }
    public function countHyperLogLog(string $key): int {
        return $this->_cnx->pfCount($key);
    }
    public function mergeHyperLogLogs(string $destKey, array $sourceKeys): bool {
        return $this->_cnx->pfMerge($destKey, ...$sourceKeys);
    }

    // HANDLE GEOLOCALIZATION
    /**
     * La geolocalización en Redis es una funcionalidad que permite almacenar, consultar y 
     * manipular datos geoespaciales (coordenadas geográficas) de forma eficiente. 
     * Redis usa estructuras de datos internas basadas en conjuntos ordenados para guardar 
     * ubicaciones con latitud y longitud, y provee comandos especializados para agregar 
     * puntos geográficos, calcular distancias, obtener zonas de proximidad alrededor de un punto, y ordenar ubicaciones por cercanía.
     * 
     * Usos comunes
     * - Rastreo en tiempo real de ubicaciones.
     * - Búsqueda de puntos de interés cercanos a una ubicación dada.
     * - Sistemas de seguimiento y geocercas que alertan cuando un objeto o usuario entra o sale de un área definida.
     * - Aplicaciones de marketing local que segmentan campañas y promociones según la ubicación geográfica de los usuarios.
     * - Redes sociales y aplicaciones móviles que permiten etiquetar ubicaciones.
     * - Gestión de inventarios o recursos distribuidos geográficamente para optimizar rutas o asignación en función de la distancia.
     * 
     */
    public function geoAdd(string $key, float $longitude, float $latitude, string $member): int {
        return $this->_cnx->geoAdd($key, $longitude, $latitude, $member);
    }

    public function geoDist(string $key, string $member1, string $member2, string $unit = 'm'): ?float {
        $dist = $this->_cnx->geoDist($key, $member1, $member2, $unit);
        return $dist === false ? null : $dist;
    }

    public function geoRadius(string $key, float $longitude, float $latitude, float $radius, string $unit = 'm', int $count = 0, bool $withDist = false): array {
        return $this->_cnx->georadius($key, $longitude, $latitude, $radius, $unit, ['COUNT' => $count ?: null, 'WITHDIST' => $withDist]);
    }

    // HANDLE JSON WITH REDISJSON
    public function jsonSet(string $key, string $path, $json): bool {
        return $this->_cnxjson->set($key, $path, $json);
    }

    public function jsonGet(string $key, string $path = '.'): mixed {
        return $this->_cnxjson->get($key, $path);
    }

    public function jsonDel(string $key, string $path = '.'): int {
        return $this->_cnxjson->del($key, $path);
    }

    /**
     * Returns the connection native object
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
        return $this->_cnx->rawCommand("INFO", "server");
    }

}
