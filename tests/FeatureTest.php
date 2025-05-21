<?php

namespace Tests;

use Illuminate\Support\Carbon;
use Overtrue\LaravelVersionable\Diff;
use Overtrue\LaravelVersionable\Version;
use Overtrue\LaravelVersionable\VersionStrategy;

class FeatureTest extends TestCase
{
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        Post::enableVersioning();

        config([
            'auth.providers.users.model' => User::class,
            'versionable.user_model' => User::class,
        ]);

        $this->user = User::create(['name' => 'overtrue']);
        $this->actingAs($this->user);
    }

    /**
     * @test
     */
    public function versions_can_be_created()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content', 'extends' => ['foo' => 'bar']]);

        $this->assertCount(1, $post->versions);
        $this->assertDatabaseCount('versions', 1);

        $version = $post->lastVersion;

        $this->assertSame($post->title, $version->contents['title']);
        $this->assertSame($post->content, $version->contents['content']);

        // json cast
        $this->assertIsString($version->contents['extends']);
        $this->assertSame($post->getRawOriginal('extends'), $version->contents['extends']);

        // version2
        $post->update(['title' => 'version2']);
        $post->refresh();

        $this->assertCount(2, $post->versions);
        $this->assertSame($post->only('title'), $post->lastVersion->contents);
        $this->assertDatabaseCount('versions', 2);
    }

    /**
     * @test
     */
    public function it_can_create_version_with_diff_strategy()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content', 'user_id' => 1234]);

        // default strategy is diff
        $this->assertCount(1, $post->versions);

        // `title` + `content` + `extends`, `user_id` is not in $versionable
        $this->assertCount(3, $post->lastVersion->contents);
        $this->assertArrayHasKey('title', $post->lastVersion->contents);
        $this->assertArrayHasKey('content', $post->lastVersion->contents);
        $this->assertArrayHasKey('extends', $post->lastVersion->contents);

        $post->update(['title' => 'version2', 'content' => 'version2 content', 'user_id' => 1234]);
        $post->refresh();

        $this->assertCount(2, $post->versions);

        // 'user_id' is not in $versionable
        $this->assertSame($post->only('title', 'content'), $post->lastVersion->contents);

        // version3
        $post->update(['title' => 'version3']);
        $post->refresh();

        $this->assertCount(3, $post->versions);

        // version 3 only has 'title'
        $this->assertCount(1, $post->lastVersion->contents);
        $this->assertArrayHasKey('title', $post->lastVersion->contents);
        $this->assertArrayNotHasKey('content', $post->lastVersion->contents);
        $this->assertSame('version3', $post->lastVersion->contents['title']);
    }

    /**
     * @test
     */
    public function it_can_create_version_with_snapshot_strategy()
    {
        $post = new Post(['title' => 'version1', 'content' => 'version1 content', 'user_id' => 1234]);

        // change strategy to snapshot
        $post->setVersionStrategy(VersionStrategy::SNAPSHOT); // snapshot

        $post->save(); // version 1

        $post->update(['title' => 'version2']); // version 2

        $post->refresh();

        $this->assertCount(2, $post->versions);

        foreach ($post->getVersionable() as $key) {
            $this->assertArrayHasKey($key, $post->lastVersion->contents);
        }

        // only has $versionable attributes
        $this->assertSame(count($post->getVersionable()), count($post->latestVersion->contents));

        $this->assertSame('version2', $post->lastVersion->contents['title']);

        // content not changed but also in $version->contents
        $this->assertSame('version1 content', $post->lastVersion->contents['content']);
    }

    /**
     * @test
     */
    public function it_can_revert_to_target_version()
    {
        Version::disableOrderingVersionsByTimestamp();

        // v1
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        // v2
        $post->update(['title' => 'version2', 'extends' => ['foo2' => 'bar2']]);
        // v3
        $post->update(['title' => 'version3', 'content' => 'version3 content', 'extends' => ['name' => 'overtrue']]);
        // v4
        $post->update(['title' => 'version4', 'content' => 'version4 content']);

        $versions = $post->versions()->orderBy('id', 'asc')->get()->keyBy('id');

        // #29
        $version1 = $versions[1];
        $post = $version1->revertWithoutSaving(); // revert to v1

        $this->assertSame('version1', $post->title);
        $this->assertSame('version1 content', $post->content);
        $this->assertNull($post->extends);

        $post->refresh(); // v4

        // revert to version 2
        $post->revertToVersion(2);
        $post->refresh();

        // only title updated, result = v1+v2
        $this->assertSame('version2', $post->title);
        $this->assertSame('version1 content', $post->content);
        $this->assertSame(['foo2' => 'bar2'], $post->extends);

        // revert to version 3
        $post->revertToVersion(3);
        $post->refresh();

        // title and content are updated
        $this->assertSame('version3', $post->title);
        $this->assertSame('version3 content', $post->content);
        $this->assertSame(['name' => 'overtrue'], $post->extends);

        // revert to version 4
        $post->revertToVersion(4);
        $post->refresh();

        // title and content are updated
        $this->assertSame('version4', $post->title);
        $this->assertSame('version4 content', $post->content);
        $this->assertSame(['name' => 'overtrue'], $post->extends);

        Version::enableOrderingVersionsByTimestamp();
    }

    /**
     * @test
     */
    public function it_can_revert_to_target_version_using_diff_strategy()
    {
        Version::disableOrderingVersionsByTimestamp();

        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']); // v1
        $post->update(['title' => 'version2']); // v2
        $post->update(['content' => 'version3 content']); // v3

        $version2 = $post->firstVersion->nextVersion();
        $this->assertSame('version2', $version2->contents['title']);

        $post->revertToVersion($version2->id);
        $post->refresh();

        $this->assertSame('version2', $post->title);
        $this->assertSame('version1 content', $post->content);

        Version::enableOrderingVersionsByTimestamp();
    }

    /**
     * @test
     */
    public function it_can_revert_to_target_version_using_diff_strategy()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']); #v1
        $post->update(['title' => 'version2']); #v2
        $post->update(['content' => 'version3 content']); #v3

        $version2 = $post->firstVersion->nextVersion();
        $this->assertSame('version2', $version2->contents['title']);

        $post->revertToVersion($version2->id);
        $post->refresh();

        $this->assertSame('version2', $post->title);
        $this->assertSame('version1 content', $post->content);
    }

    /**
     * @test
     */
    public function user_can_get_diff_of_version()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertInstanceOf(Diff::class, $post->lastVersion->diff());

        $post->update(['title' => 'version2']);
        $post->refresh();

        $this->assertSame(['title' => ['old' => 'version1', 'new' => 'version2']], $post->lastVersion->diff()->toArray());
    }

    /**
     * @test
     */
    public function user_can_get_previous_version()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->update(['title' => 'version2']);
        $post->update(['title' => 'version3']);

        $post->refresh();

        $this->assertEquals('version3', $post->latestVersion->contents['title']);
        $this->assertEquals('version2', $post->latestVersion->previousVersion()->contents['title']);
        $this->assertEquals('version1', $post->latestVersion->previousVersion()->previousVersion()->contents['title']);
        $this->assertNull($post->latestVersion->previousVersion()->previousVersion()->previousVersion());
    }

    public function user_can_detect_whether_the_version_is_the_latest_version(): void
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->update(['title' => 'version2']);
        $post->update(['title' => 'version3']);

        $post->refresh();

        $this->assertTrue($post->latestVersion->isLatest());
        $this->assertFalse($post->latestVersion->previousVersion()->isLatest());
    }

    /**
     * @test
     */
    public function user_can_get_next_version()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->update(['title' => 'version2']);
        $post->update(['title' => 'version3']);

        $post->refresh();

        $this->assertEquals('version1', $post->firstVersion->contents['title']);
        $this->assertEquals('version2', $post->firstVersion->nextVersion()->contents['title']);
        $this->assertEquals('version3', $post->firstVersion->nextVersion()->nextVersion()->contents['title']);
        $this->assertNull($post->firstVersion->nextVersion()->nextVersion()->nextVersion());
    }

    /**
     * @test
     */
    public function previous_versions_created_later_on_will_have_correct_order()
    {

        $this->travelTo(Carbon::create(2022, 10, 2, 14, 0));

        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->update(['title' => 'version2']);
        $post->refresh();

        $this->travelTo(Carbon::create(2022, 10, 2, 15, 0));
        $post->update(['title' => 'version5']);
        $post->refresh();

        $this->travelTo(Carbon::create(2022, 10, 2, 14, 30));
        $post->update(['title' => 'version4']);
        $post->refresh();

        $this->travelTo(Carbon::create(2022, 10, 2, 14, 0));
        $post->update(['title' => 'version3']);
        $post->refresh();

        $this->assertEquals('version3', $post->title);
        $this->assertEquals('version5', $post->latestVersion->contents['title']);
        $this->assertEquals('version4', $post->latestVersion->previousVersion()->contents['title']);
        $this->assertEquals('version3', $post->latestVersion->previousVersion()->previousVersion()->contents['title']);
        $this->assertEquals('version2', $post->latestVersion->previousVersion()->previousVersion()->previousVersion()->contents['title']);
        $this->assertEquals('version1', $post->latestVersion->previousVersion()->previousVersion()->previousVersion()->previousVersion()->contents['title']);
        $this->assertNull($post->latestVersion->previousVersion()->previousVersion()->previousVersion()->previousVersion()->previousVersion());
    }

    /**
     * @test
     */
    public function user_can_get_ordered_history()
    {
        Version::enableOrderingVersionsByTimestamp();

        $post = Post::create(['title' => 'version2', 'content' => 'version2 content']);
        $post->update(['title' => 'version3']);
        $post->update(['title' => 'version4']);

        $post->createVersion(['title' => 'version1'], Carbon::now()->subDay(1));

        $this->assertEquals(
            ['version4', 'version3', 'version2', 'version1'],
            $post->latestVersions->pluck('contents.title')->toArray(),
        );
    }

    /**
     * @test
     */
    public function post_will_keep_versions()
    {
        \config(['versionable.keep_versions' => 3]);

        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->update(['title' => 'version2']);
        $post->update(['title' => 'version3', 'content' => 'version3 content']);
        $post->update(['title' => 'version4', 'content' => 'version4 content']);
        $post->update(['title' => 'version5', 'content' => 'version5 content']);

        $this->assertCount(3, $post->versions);

        $post->removeAllVersions();
        $post->refresh();

        $this->assertCount(0, $post->versions);
    }

    /**
     * @test
     */
    public function user_can_disable_version_control()
    {
        $post = new Post;

        Post::withoutVersion(function () use (&$post) {
            $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        });

        $this->assertCount(0, $post->versions);

        // version2
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->refresh();
        $this->assertCount(1, $post->versions);

        $this->assertTrue(Post::getVersioning());

        Post::withoutVersion(function () use ($post) {
            $post->update(['title' => 'version2']);
        });

        $this->assertTrue(Post::getVersioning());
        $post->refresh();

        // before
        $this->assertCount(1, $post->versions);

        Post::disableVersioning();
        Post::withoutVersion(function () use ($post) {
            $post->update(['title' => 'version2']);
        });

        // after
        $this->assertCount(1, $post->versions);

        $this->assertFalse(Post::getVersioning());
        $post->refresh();
    }

    /**
     * @test
     */
    public function versions_can_be_soft_delete_and_restore()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertCount(1, $post->versions);
        $this->assertDatabaseCount('versions', 1);

        // version2
        $post->update(['title' => 'version2']);
        $post->refresh();

        $this->assertCount(2, $post->versions);
        $this->assertDatabaseCount('versions', 2);

        // version3
        $post->update(['title' => 'version3']);
        $post->refresh();
        $this->assertDatabaseCount('versions', 3);

        // soft delete
        $post->refresh();

        // first
        $lastVersion = $post->lastVersion;
        $post->removeVersion($lastVersion->id);
        $this->assertDatabaseCount('versions', 3);
        $this->assertCount(1, $post->getThrashedVersions());

        // delete second version
        $post->refresh();
        $lastVersion = $post->lastVersion;
        $post->removeVersion($lastVersion->id);
        $this->assertDatabaseCount('versions', 3);
        $this->assertCount(1, $post->refresh()->versions);
        $this->assertCount(2, $post->getThrashedVersions());

        // restore second deleted version
        $post->restoreTrashedVersion($lastVersion->id);
        $this->assertCount(2, $post->refresh()->versions);
    }

    /**
     * @test
     */
    public function init_version_should_include_all_versionable_attributes()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertCount(1, $post->versions);
        $this->assertDatabaseCount('versions', 1);

        foreach ($post->getVersionable() as $key) {
            $this->assertArrayHasKey($key, $post->lastVersion->contents);
        }

        $this->assertSame($post->title, $post->lastVersion->contents['title']);
        $this->assertSame($post->content, $post->lastVersion->contents['content']);
    }

    /**
     * @test
     */
    public function versions_can_be_force_deleted()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertCount(1, $post->versions);
        $this->assertDatabaseCount('versions', 1);

        // version2
        $post->update(['title' => 'version2']);
        $post->refresh();

        $this->assertCount(2, $post->versions);
        $this->assertDatabaseCount('versions', 2);

        // version3
        $post->update(['title' => 'version3']);
        $post->refresh();
        $this->assertDatabaseCount('versions', 3);

        // forced delete
        $post->enableForceDeleteVersion();

        // first
        $post->refresh();
        $lastVersion = $post->lastVersion;
        $post->removeVersion($lastVersion->id);
        $this->assertDatabaseCount('versions', 2);

        // second delete
        $post->refresh();
        $lastVersion = $post->lastVersion;
        $post->removeVersion($lastVersion->id);
        $this->assertDatabaseCount('versions', 1);
    }

    /**
     * @test
     */
    public function relations_will_not_in_version_contents()
    {
        $post = new Post;

        Post::withoutVersion(function () use (&$post) {
            $user = User::create(['name' => 'overtrue']);
            $post = Post::create(['title' => 'version1', 'content' => 'version1 content', 'user_id' => $user->id]);
        });

        $post->user;
        $this->assertArrayHasKey('user', $post->toArray());

        $post->update(['title' => 'version2']);

        $this->assertArrayNotHasKey('user', $post->latestVersion->contents);
    }

    /**
     * @test
     */
    public function it_creates_initial_version_if_not_exists()
    {
        $post = new Post;

        Post::withoutVersion(function () use (&$post) {
            $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        });

        $this->assertCount(0, $post->versions);

        $post->update(['title' => 'version2']);

        $post->refresh();

        $this->assertCount(2, $post->versions);
        $this->assertSame('version1', $post->firstVersion->contents['title']);
        $this->assertSame('version2', $post->lastVersion->contents['title']);
    }
}
