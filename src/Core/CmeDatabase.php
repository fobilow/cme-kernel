<?php
/**
 * Created by PhpStorm.
 * User: Okechukwu
 * Date: 5/18/2015
 * Time: 10:43 PM
 */

namespace CmeKernel\Core;

use CmeKernel\Data\Data;
use CmeKernel\Data\InitData;
use Illuminate\Database\Capsule\Manager as Capsule;

class CmeDatabase
{
  /**
   * @var Capsule $_instance
   */
  private static $_instance;
  private static $_config;

  public static function init(InitData $data)
  {
    self::$_config = [
      'driver'    => $data->dbDriver,
      'host'      => $data->dbHost,
      'database'  => $data->dbName,
      'username'  => $data->dbUsername,
      'password'  => $data->dbPassword,
      'charset'   => $data->dbCharset,
      'collation' => $data->dbCollation,
      'prefix'    => $data->dbPrefix,
    ];
  }

  public static function conn()
  {
    if(self::$_instance === null)
    {
      self::$_instance = new Capsule();
    }
    self::$_instance->addConnection(self::$_config);
    self::$_instance->setAsGlobal();
    self::$_instance->bootEloquent();

    return self::$_instance->connection();
  }

  public static function schema()
  {
    $conn = self::conn();
    $conn->useDefaultSchemaGrammar();
    return self::$_instance->schema();
  }

  public static function hydrate(Data $dataObj, array $data)
  {
    foreach($data as $key => $value)
    {
      $dataObj->{camel_case($key)} = $value;
    }
    return $dataObj;
  }

  public static function dataToArray(Data $dataObj)
  {
    $data   = (array)$dataObj;
    $return = [];
    foreach($data as $k => $v)
    {
      if(isset($v))
      {
        $return[snake_case($k)] = $v;
      }
    }

    return $return;
  }
}
