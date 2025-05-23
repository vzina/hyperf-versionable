<?php

namespace Tests;


use Vzina\HyperfVersionable\VersionStrategy;

class ManualVersionTest extends TestCase
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

        $this->user = User::create(['name' => 'marijoo']);
        $this->actingAs($this->user);
    }

    public function test_user_can_create_versions_manually()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertCount(1, $post->refresh()->versions);
        $this->assertEquals('version1', $post->latestVersion->contents['title']);

        $post->title = 'version2';

        $this->assertNotNull($post->createVersion());
        $this->assertCount(2, $post->refresh()->versions);
        $this->assertEquals('version2', $post->latestVersion->contents['title']);

        $post->title = 'version3';

        $this->assertNotNull($post->createVersion());
        $this->assertCount(3, $post->refresh()->versions);
        $this->assertEquals('version3', $post->latestVersion->contents['title']);
    }

    public function test_user_cannot_create_versions_manually_without_changes()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertCount(1, $post->refresh()->versions);
        $this->assertEquals('version1', $post->latestVersion->contents['title']);

        $this->assertNull($post->createVersion());
        $this->assertNull($post->createVersion());
        $this->assertNull($post->createVersion());

        $this->assertCount(1, $post->refresh()->versions);
    }

    public function test_user_cannot_create_versions_manually_by_passing_attributes()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertCount(1, $post->refresh()->versions);
        $this->assertEquals('version1', $post->latestVersion->contents['title']);

        $this->assertNull($post->createVersion());

        $this->assertNotNull($post->createVersion(['title' => 'version2']));
        $this->assertCount(2, $post->refresh()->versions);
        $this->assertEquals('version2', $post->latestVersion->contents['title']);

        $this->assertNotNull($post->createVersion(['title' => 'version3']));
        $this->assertCount(3, $post->refresh()->versions);
        $this->assertEquals('version3', $post->latestVersion->contents['title']);
    }

    public function test_user_can_create_versions_manually_if_versioning_is_disabled()
    {
        Post::disableVersioning();

        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertCount(0, $post->refresh()->versions);

        $post->update(['title' => 'version2']);

        $this->assertCount(0, $post->refresh()->versions);

        $this->assertNotNull($post->createVersion(['title' => 'version3']));
        $this->assertCount(1, $post->refresh()->versions);
        $this->assertEquals('version3', $post->latestVersion->contents['title']);
    }

    public function test_attributes_will_be_merged_in_snapshot_mode()
    {
        Post::disableVersioning();

        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->setVersionStrategy(VersionStrategy::DIFF);

        $this->assertNotNull($post->createVersion(['title' => 'version3']));
        $this->assertCount(1, $post->refresh()->versions);
        $this->assertEquals(['title' => 'version3'], $post->latestVersion->contents);

        $post->setVersionStrategy(VersionStrategy::SNAPSHOT);

        $this->assertNotNull($post->createVersion(['title' => 'version4']));
        $this->assertCount(2, $post->refresh()->versions);

        $this->assertTrue(collect($post->latestVersion->contents)->has([
            'title',
            'content',
            'extends',
        ]));

        $this->assertCount(3, $post->latestVersion->contents);
    }
}
