<?php

namespace Tests;

use Hyperf\DbConnection\Model\Model;
use Qbhy\HyperfAuth\Authenticatable;

class User extends Model implements Authenticatable
{
    protected array $fillable = ['name'];

    public function getId()
    {
        return $this->id;
    }

    public static function retrieveById($key): ?Authenticatable
    {
        return self::find($key);
    }
}
