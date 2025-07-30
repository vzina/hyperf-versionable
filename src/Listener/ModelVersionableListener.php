<?php
/**
 * ModelVersionableListener.php
 * PHP version 7
 *
 * @package tool-os
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\HyperfVersionable\Listener;

use Hyperf\Database\Model\Events\Created;
use Hyperf\Database\Model\Events\Deleted;
use Hyperf\Database\Model\Events\Event;
use Hyperf\Database\Model\Events\Restored;
use Hyperf\Database\Model\Events\Updated;
use Hyperf\Database\Model\Events\Updating;
use Hyperf\DbConnection\Model\Model;
use Hyperf\Event\Contract\ListenerInterface;
use Vzina\HyperfVersionable\Versionable;

class ModelVersionableListener implements ListenerInterface
{

    public function listen(): array
    {
        return [
            Created::class,
            Updating::class,
            Updated::class,
            Restored::class,
            Deleted::class,
        ];
    }

    /**
     * @param Event $event
     * @return void
     */
    public function process(object $event): void
    {
        /** @var Versionable|Model $model */
        $model = $event->getModel();
        if (! method_exists($model, 'getVersioning') || ! $model::getVersioning()) {
            return;
        }

        match (true) {
            $event instanceof Created => $model->createInitialVersion($model),
            $event instanceof Updating => $model->versions()->count() === 0 && $model->createInitialVersion($model),
            $event instanceof Updated, $event instanceof Restored => $model->shouldBeVersioning() && $model->createVersion(),
            $event instanceof Deleted => method_exists($model, 'isForceDeleting') && $model->isForceDeleting() && $model->forceRemoveAllVersions(),
        };
    }
}
