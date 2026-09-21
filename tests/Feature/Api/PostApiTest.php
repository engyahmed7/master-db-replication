<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_can_be_listed(): void
    {
        Post::factory()->create([
            'title' => 'Replica list',
        ]);

        $response = $this->getJson('/api/posts');

        $response->assertOk();
        $response->assertJsonFragment(['title' => 'Replica list']);
    }

    public function test_a_post_can_be_created(): void
    {
        $response = $this->postJson('/api/posts', [
            'title' => 'From Postman',
            'body' => 'Written through the API to the primary.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('post.title', 'From Postman');
        $this->assertDatabaseHas('posts', [
            'title' => 'From Postman',
        ]);
    }

    public function test_a_post_can_be_shown(): void
    {
        $post = Post::factory()->create([
            'title' => 'Show me',
        ]);

        $response = $this->getJson("/api/posts/{$post->id}");

        $response->assertOk();
        $response->assertJsonPath('post.title', 'Show me');
    }

    public function test_a_post_requires_a_title_and_body(): void
    {
        $response = $this->postJson('/api/posts', [
            'title' => '',
            'body' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['title', 'body']);
    }

    public function test_replication_status_can_be_fetched(): void
    {
        $response = $this->getJson('/api/replication/status');

        $response->assertOk();
        $response->assertJsonPath('enabled', false);
        $response->assertJsonPath('healthy', false);
        $response->assertJsonPath('replicas', []);
    }
}
