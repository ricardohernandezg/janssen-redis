<?php 

namespace Janssen\Helpers\Database\Adaptors;

use Janssen\Traits\InstanceGetter;
use Janssen\Traits\StaticCall;

class RedisAdaptor
{
    use InstanceGetter;
    use StaticCall;

    private $_cnx;

    // conectar al servidor redis
    public function connect()
    {}

    // guardar un valor

    // guardar muchos valores

    // obtener un valor

    // obtener el objeto redis nativo
    public function getConnector(){
        return self::$_cnx;
    }

    // borrar todo

}