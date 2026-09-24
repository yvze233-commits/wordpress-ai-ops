<?php

namespace Tests\Feature;

use App\Models\ContentItem;
use App\Models\WordPressConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_operations_tables_have_the_required_columns(): void
    {
        foreach ([
            'content_batches' => ['run_date', 'target_count', 'status', 'completed_count'],
            'topic_candidates' => ['source_type', 'source_key', 'title', 'normalized_title', 'status', 'locked_until'],
            'content_items' => ['content_batch_id', 'topic_candidate_id', 'state', 'idempotency_key', 'wordpress_post_id', 'generation_meta', 'review_result', 'evidence_snapshot', 'writing_skill_snapshot', 'review_skill_snapshot'],
            'content_runs' => ['content_item_id', 'stage', 'attempt', 'status', 'payload'],
            'audit_events' => ['content_item_id', 'event_type', 'payload'],
            'wordpress_connections' => ['name', 'base_url', 'username', 'application_password_encrypted', 'default_post_status', 'category_mapping', 'settings', 'status'],
            'topic_sources' => ['name', 'type', 'url', 'parser_config', 'trust_score', 'enabled', 'status', 'last_fetched_at', 'last_http_status', 'last_error'],
            'topic_feeds' => ['topic_source_id', 'source_key', 'title', 'normalized_title', 'url', 'normalized_url', 'event_fingerprint', 'summary', 'published_at', 'raw_payload'],
            'title_library_entries' => ['raw_title', 'normalized_title', 'keywords', 'category', 'priority', 'enabled', 'use_count', 'last_used_at', 'content_item_id'],
            'daily_selections' => ['content_batch_id', 'topic_candidate_id', 'title_library_entry_id', 'source', 'selection_score', 'locked_until', 'result'],
            'knowledge_bases' => ['name', 'description', 'enabled'],
            'knowledge_documents' => ['knowledge_base_id', 'name', 'source_url', 'source_type', 'review_status', 'risk_level', 'content_hash', 'content_length', 'text_content', 'metadata'],
            'knowledge_chunks' => ['knowledge_document_id', 'position', 'heading', 'content', 'content_hash', 'embedding_metadata'],
            'image_libraries' => ['name', 'description', 'enabled'],
            'library_images' => ['image_library_id', 'path', 'mime_type', 'width', 'height', 'caption', 'alt_text', 'notes', 'keywords', 'applicable_categories', 'scenes', 'copyright_state', 'ocr_text', 'usage_count', 'enabled'],
            'image_placements' => ['content_item_id', 'library_image_id', 'role', 'paragraph_index', 'paragraph_anchor', 'confidence', 'reason', 'position', 'metadata'],
            'skills' => ['name', 'slug', 'kind', 'source', 'enabled', 'current_version'],
            'skill_versions' => ['skill_id', 'version', 'raw_text', 'parsed_rules', 'output_schema', 'prohibited_terms', 'pass_threshold', 'validation_report', 'content_hash'],
        ] as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table));

            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), $table.'.'.$column);
            }
        }
    }

    public function test_json_fields_are_cast_and_wordpress_password_is_encrypted(): void
    {
        $item = new ContentItem([
            'generation_meta' => ['model' => 'test-model'],
            'review_result' => ['score' => 88],
            'evidence_snapshot' => ['version' => 'v1'],
        ]);
        $connection = new WordPressConnection([
            'application_password_encrypted' => 'secret-value',
            'category_mapping' => ['news' => 3],
            'settings' => ['upload_media' => true],
        ]);

        $this->assertSame(['model' => 'test-model'], $item->generation_meta);
        $this->assertSame(['score' => 88], $item->review_result);
        $this->assertSame(['version' => 'v1'], $item->evidence_snapshot);
        $this->assertSame(['news' => 3], $connection->category_mapping);
        $this->assertSame(['upload_media' => true], $connection->settings);
        $this->assertSame('secret-value', $connection->application_password_encrypted);
        $this->assertSame('encrypted', $connection->getCasts()['application_password_encrypted']);
    }
}
