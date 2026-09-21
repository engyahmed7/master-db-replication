<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplicationDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_dashboard_can_be_rendered(): void
    {
        Post::factory()->create([
            'title' => 'Replica demo',
            'body' => 'Written through the default connection.',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Database replication');
        $response->assertSee('Replica demo');
        $response->assertSee('two read-only replicas');
        $response->assertSee('Splitting disabled');
    }

    public function test_a_post_can_be_created(): void
    {
        $response = $this->post(route('posts.store'), [
            'title' => 'Hello primary',
            'body' => 'This should be stored on the write host.',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('posts', [
            'title' => 'Hello primary',
        ]);
    }

    public function test_a_post_requires_a_title_and_body(): void
    {
        $response = $this->from(route('home'))->post(route('posts.store'), [
            'title' => '',
            'body' => '',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHasErrors(['title', 'body']);
        $this->assertDatabaseCount('posts', 0);
    }
}
