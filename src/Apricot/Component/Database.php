<?php

namespace Apricot\Component;

trait Database
{
    protected $pdo;

    public static function query($query, $parameters, callable $callback = null)
    {
        if (!is_array($parameters) && is_callable($parameters)) {
            $callback = $parameters;
        }

        $apricot = self::getInstance();

        $statement = $apricot->getPDO()->prepare($query);

        if (!$statement->execute()) {
            throw new \RuntimeException("Query failed.");
        }

        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        call_user_func_array($callback, $data);
    }

    protected function getPDO()
    {
        if (null === $this->pdo) {
            $driver = self::get('db._driver');
            $host = self::get('db._host');
            $dbname = self::get('db._name');
            $user = self::get('db._user');
            $pass = self::get('db._password');
            $this->pdo = new \PDO($driver.':host='.$host.';dbname='.$dbname, $user, $pass, array(
                \PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ));
        }

        return $this->pdo;
    }
}