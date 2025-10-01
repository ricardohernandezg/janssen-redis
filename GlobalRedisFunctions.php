<?php 

namespace Janssen\Helpers;

use Janssen\App;
use Janssen\Engine\Config;
use Janssen\Engine\Event;
use Exception;

/**
 * add the alias to DefaultResolver to use the adaptor provided 
 */

if (!class_exists('Janssen\App')) {

    throw New \Exception('This package must run inside Janssen App', 500);

} 

if (!extension_loaded('redis')) {

    throw New \Exception('This package needs Redis to work', 500);

} 

Event::listen('app.beforeinit', function(){
    // push this class into Janssen alias
    DefaultResolver::append(['redis' => '\Janssen\Helpers\Database\Adaptors\RedisAdaptor']);
});

Event::listen('app.afterinit', function(){
    // set configuration from rbac file
    $rbac_conf_candidate = App::getPathCandidate('rbac');
    Config::append((is_file($rbac_conf_candidate)) ? (include $rbac_conf_candidate) : []);

    // - read the configuration and check for redis
    // - check for the rbac redis key/values
    


    // check the database scaffolding
    // look for table roles
    // $re = Database::getAdaptor()->tableExists('roles');
    
    //if(!$re)
    //    throw new \Exception('RBAC needs the Roles table',500);
    // look for table module
    // look for table permisology



});


/**
 * <?php
// Crear una instancia de Redis
$redis = new Redis();

try {
    // Conectar a Redis en localhost y puerto 6379 (puerto por defecto)
    $redis->connect('127.0.0.1', 6379);
    echo "Conexión exitosa a Redis\n";

    // Guardar un valor en Redis con clave "producto"
    $redis->set("producto", "Zapatos de Cuero Marrones");

    // Recuperar el valor guardado en Redis
    $valor = $redis->get("producto");

    echo "Dato almacenado correctamente en Redis: " . $valor . "\n";

} catch (Exception $e) {
    echo "Error al conectar o usar Redis: " . $e->getMessage() . "\n";
}
?>
 */

/**
 * <?php
// Crear instancia de Redis
$redis = new Redis();

try {
    // Conectar al servidor Redis local en el puerto por defecto 6379
    $redis->connect('127.0.0.1', 6379);
    echo "Conexión exitosa con el servidor Redis\n";

    // Comandos básicos:

    // 1. Guardar un valor en Redis (clave - valor)
    $redis->set("sitio", "guiadev.com");
    echo "Valor guardado: " . $redis->get("sitio") . "\n";

    // 2. Uso de listas: insertar elementos al principio
    $redis->lpush("lista-bases-datos", "MongoDB");
    $redis->lpush("lista-bases-datos", "MySQL");
    $redis->lpush("lista-bases-datos", "PostgreSQL");

    // 3. Obtener elementos de la lista (desde índice 0 hasta 2)
    $lista = $redis->lrange("lista-bases-datos", 0, 2);
    echo "Elementos en la lista:\n";
    foreach ($lista as $elemento) {
        echo "- $elemento\n";
    }

    // 4. Obtener todas las claves almacenadas
    $claves = $redis->keys("*");
    echo "Claves almacenadas:\n";
    foreach ($claves as $clave) {
        echo "- $clave\n";
    }

} catch (RedisException $e) {
    echo "Error al conectar o usar Redis: " . $e->getMessage() . "\n";
}
?>
 */

/** 
 * <?php
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$key = "cache:producto:1001";
$value = json_encode(["nombre" => "Zapatos", "precio" => 59.99]);

// Guardar en cache con expiración de 1 hora (3600 segundos)
$redis->set($key, $value, 3600);

// Obtener desde cache
$cachedValue = $redis->get($key);
if($cachedValue) {
    $producto = json_decode($cachedValue, true);
    echo "Producto desde cache: " . $producto['nombre'];
} else {
    echo "Cache no encontrado, consultar origen de datos.";
}
?>
 */