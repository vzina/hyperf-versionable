<?php

namespace Vzina\HyperfVersionable;

use Carbon\Carbon;
use Hyperf\Collection\Arr;
use Hyperf\Collection\Collection;
use Hyperf\Database\Model\Events\Created;
use Hyperf\Database\Model\Events\Deleted;
use Hyperf\Database\Model\Events\Updated;
use Hyperf\Database\Model\Events\Updating;
use Hyperf\Database\Model\Relations\MorphMany;
use Hyperf\Database\Model\Relations\MorphOne;
use Hyperf\DbConnection\Model\Model;

/**
 * @property Collection<Version> $versions
 */
trait Versionable
{
    protected static bool $versioning = true;

    protected bool $forceDeleteVersion = false;

    // You can add these properties to you versionable model
    // protected $versionable = [];
    // protected $dontVersionable = ['*'];

    // You can define this variable in class, that used this trait to change Model(table) for versions
    // Model MUST extend \Overtrue\LaravelVersionable\Version
    // public string $versionModel;
    // public string $userForeignKeyName;

    public function created(Created $event)
    {
        if (static::$versioning) {
            // init version should include all $versionable fields.
            /** @var Versionable|Model $model */
            $model = $event->getModel();
            $model->createInitialVersion($model);
        }
    }

    public function updating(Updating $event)
    {
        // ensure the initial version exists before updating
        /** @var Versionable|Model $model */
        $model = $event->getModel();
        if (static::$versioning && $model->versions()->count() === 0) {
            $model->createInitialVersion($model);
        }
    }

    public function updated(Updated $event)
    {
        // ensure the initial version exists before updating
        /** @var Versionable|Model $model */
        $model = $event->getModel();
        if (static::$versioning && $model->shouldBeVersioning()) {
            /** @var Versionable $model */
            return tap(Version::createForModel($model), function () use ($model) {
                $model->removeOldVersions($model->getKeepVersionsCount());
            });
        }
    }

    public function deleted(Deleted $event)
    {
        // ensure the initial version exists before updating
        /** @var Versionable|Model $model */
        $model = $event->getModel();
        /* @var Versionable|Version $model */
        if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
            $model->forceRemoveAllVersions();
        }
    }

    public function getRefreshedModel(Model $model): Model
    {
        return $model->newQueryWithoutScopes()->findOrFail($model->getKey());
    }

    public function createInitialVersion(Model $model): Version
    {
        /** @var Versionable|Model $refreshedModel */
        $refreshedModel = $this->getRefreshedModel($model);

        /**
         * As initial version should include all $versionable fields,
         * we need to get the latest version from database.
         * so we force to create a snapshot version.
         */
        $attributes = $refreshedModel->getVersionableAttributes(VersionStrategy::SNAPSHOT);

        return Version::createForModel($refreshedModel, $attributes, $refreshedModel->updated_at);
    }

    public function versions(): MorphMany
    {
        return $this->morphMany($this->getVersionModel(), 'versionable');
    }

    public function latestVersions()
    {
        return $this->versions()->orderLatestFirst();
    }

    public function oldestVersions()
    {
        return $this->versions()->orderOldestFirst();
    }

    public function lastVersion(): MorphOne
    {
        return $this->latestVersion();
    }

    public function latestVersion(): MorphOne
    {
        return $this->morphOne($this->getVersionModel(), 'versionable')->orderLatestFirst();
    }

    public function firstVersion(): MorphOne
    {
        return $this->morphOne($this->getVersionModel(), 'versionable')->orderOldestFirst();
    }

    /**
     * Get the version for a specific time.
     *
     * @param  string|\DateTimeInterface|null  $time
     * @param  \DateTimeZone|string|null  $tz
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public function versionAt($time = null, $tz = null): ?Version
    {
        return $this->latestVersions()
            ->where('created_at', '<=', Carbon::parse($time, $tz))
            ->first();
    }

    public function getVersion(int|string $id): ?Version
    {
        return $this->versions()->find($id);
    }

    public function getTrashedVersions()
    {
        return $this->versions()->onlyTrashed()->get();
    }

    public function restoreTrashedVersion(int|string $id)
    {
        return $this->versions()->onlyTrashed()->whereId($id)->restore();
    }

    public function revertToVersion(int|string $id): bool
    {
        return $this->versions()->findOrFail($id)->revert();
    }

    public function removeOldVersions(int $keep = 1): void
    {
        if ($keep <= 0) {
            return;
        }

        $this->latestVersions()->skip($keep)->take(PHP_INT_MAX)->get()->each->delete();
    }

    public function removeVersions(array $ids)
    {
        if ($this->forceDeleteVersion) {
            return $this->forceRemoveVersions($ids);
        }

        return $this->versions()->findMany($ids)->each->delete();
    }

    public function removeVersion(int|string $id)
    {
        if ($this->forceDeleteVersion) {
            return $this->forceRemoveVersion($id);
        }

        return $this->versions()->findOrFail($id)->delete();
    }

    public function removeAllVersions(): void
    {
        if ($this->forceDeleteVersion) {
            $this->forceRemoveAllVersions();
        }

        $this->versions->each->delete();
    }

    public function forceRemoveVersion(int|string $id)
    {
        return $this->versions()->findOrFail($id)->forceDelete();
    }

    public function forceRemoveVersions(array $ids)
    {
        return $this->versions()->findMany($ids)->each->forceDelete();
    }

    public function forceRemoveAllVersions(): void
    {
        $this->versions->each->forceDelete();
    }

    public function shouldBeVersioning(): bool
    {
        // xxx: fix break change
        if (method_exists($this, 'shouldVersioning')) {
            return call_user_func([$this, 'shouldVersioning']);
        }

        $versionableAttributes = $this->getVersionableAttributes($this->getVersionStrategy());

        return $this->versions()->count() === 0 || Arr::hasAny($this->getDirty(), array_keys($versionableAttributes));
    }

    public function getVersionableAttributes(VersionStrategy $strategy, array $replacements = []): array
    {
        $versionable = $this->getVersionable();
        $dontVersionable = $this->getDontVersionable();
        $refreshedModel = $this->getRefreshedModel($this);

        $keys = match ($strategy) {
            VersionStrategy::DIFF => array_keys($this->getDirty()),
            // To avoid some attributes are empty (not sync to database)
            // we should get the latest version from database.
            VersionStrategy::SNAPSHOT => array_keys($refreshedModel?->attributesToArray() ?? []),
        };

        // get the original attributes to avoid the attributes that are castable.
        $attributes = Arr::only($refreshedModel->getRawOriginal(), $keys);

        if (count($versionable) > 0) {
            $attributes = Arr::only($attributes, $versionable);
        }

        return Arr::except(array_merge($attributes, $replacements), $dontVersionable);
    }

    /**
     * @throws \Exception
     */
    public function setVersionable(array $attributes): static
    {
        if (! \property_exists($this, 'versionable')) {
            throw new \Exception('Property $versionable not exist.');
        }

        $this->versionable = $attributes;

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function setDontVersionable(array $attributes): static
    {
        if (! \property_exists($this, 'dontVersionable')) {
            throw new \Exception('Property $dontVersionable not exist.');
        }

        $this->dontVersionable = $attributes;

        return $this;
    }

    public function getVersionable(): array
    {
        return \property_exists($this, 'versionable') ? $this->versionable : [];
    }

    public function getDontVersionable(): array
    {
        return \property_exists($this, 'dontVersionable') ? $this->dontVersionable : [];
    }

    public function getVersionStrategy(): VersionStrategy
    {
        if (\property_exists($this, 'versionStrategy')) {
            return $this->versionStrategy instanceof VersionStrategy ? $this->versionStrategy : VersionStrategy::from($this->versionStrategy);
        }

        return VersionStrategy::DIFF;
    }

    /**
     * @throws \Exception
     */
    public function setVersionStrategy(VersionStrategy|string $strategy): static
    {
        if (is_string($strategy)) {
            $strategy = VersionStrategy::tryFrom(strtoupper($strategy));
        }

        if (! \property_exists($this, 'versionStrategy')) {
            throw new \Exception('Property $versionStrategy not exist.');
        }

        $this->versionStrategy = $strategy;

        return $this;
    }

    public function getVersionModel(): string
    {
        return $this->versionModel ?? config('versionable.version_model');
    }

    public function getUserForeignKeyName(): string
    {
        return $this->userForeignKeyName ?? config('versionable.user_foreign_key');
    }

    public function getVersionUserId()
    {
        return $this->getAttribute($this->getUserForeignKeyName()) ?? 0;
    }

    public function getKeepVersionsCount(): string
    {
        return config('versionable.keep_versions', 0);
    }

    public static function getVersioning(): bool
    {
        return static::$versioning;
    }

    public static function withoutVersion(callable $callback): void
    {
        $lastState = static::$versioning;

        static::disableVersioning();

        \call_user_func($callback);

        static::$versioning = $lastState;
    }

    public static function disableVersioning(): void
    {
        static::$versioning = false;
    }

    public static function enableVersioning(): void
    {
        static::$versioning = true;
    }
}
