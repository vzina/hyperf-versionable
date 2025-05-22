<?php

return [
    /*
     * Keep versions, you can redefine in target model.
     * Default: 0 - Keep all versions.
     */
    'keep_versions' => 0,

    /*
     * User foreign key name of versions table.
     */
    'user_foreign_key' => 'user_id',

    /*
     * The model class for store versions.
     */
    'version_model' => Vzina\HyperfVersionable\Version::class,

    /**
     * The model class for user.
     */
    'user_model' => \App\Model\User::class,

    /**
     * Use uuid for version id.
     */
    'uuid' => false,
];
