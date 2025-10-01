<?php 

namespace Janssen\Helpers\Database\Adaptors;

use Janssen\Engine\Config;
use Janssen\Helpers\Exception;
use Janssen\Traits\InstanceGetter;
use Janssen\Traits\StaticCall;

class RedisAdaptor
{
    use InstanceGetter;
    use StaticCall;

    private $_cnx;

    // conectar al servidor redis
    public function connect(string $connection_name)
    {
        // get the settings from config
        $cfg = Config::get('connections')[$connection_name] ?? false;
        if(!$cfg)
            throw new Exception("$connection_name is not configured");

        $opt = [
            'host' => $cfg['db_host']
        ];

    }

    // guardar un valor

    // guardar muchos valores

    // obtener un valor

    /**
     * Returs the connection native object
     * 
     * @return Object
     */
    public function getConnector(){
        return $this->_cnx;
    }

    // borrar todo

}