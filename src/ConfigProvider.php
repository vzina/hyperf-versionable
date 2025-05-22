<?php
/**
 * ConfigProvider.php
 * PHP version 7
 *
 * @package hyperf-versionable
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\HyperfVersionable;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                    'ignore_annotations' => [],
                    'collectors' => [],
                    'class_map' => [
                        // 需要映射的类名 => 类所在的文件地址
                     ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for versionable.',
                    'source' => __DIR__ . '/../config/versionable.php',
                    'destination' => BASE_PATH . '/config/autoload/versionable.php',
                ],
                [
                    'id' => 'migrations',
                    'description' => 'The config for versionable.',
                    'source' => __DIR__ . '/../migrations/2019_05_31_042934_create_versions_table.php',
                    'destination' => BASE_PATH . '/migrations/2019_05_31_042934_create_versions_table.php',
                ],
            ],
        ];
    }
}
