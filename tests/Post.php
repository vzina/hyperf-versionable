<?php

namespace Tests;


use Hyperf\Database\Model\Events\Saving;
use Hyperf\DbConnection\Model\Model;
use Vzina\HyperfVersionable\Versionable;
use Vzina\HyperfVersionable\VersionStrategy;

class Post extends Model
{
    use Versionable;

    protected array $fillable = ['title', 'content', 'user_id', 'extends', 'not_versionable_field'];

    protected $versionable = ['title', 'content', 'extends'];

    protected $versionStrategy = VersionStrategy::DIFF;

    protected array $casts = [
        'extends' => 'array',
    ];

    public function saving(Saving $event)
    {
        $this->user_id = 1;
    }

    public function enableForceDeleteVersion()
    {
        $this->forceDeleteVersion = true;
    }

    public function disableForceDeleteVersion()
    {
        $this->forceDeleteVersion = false;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
