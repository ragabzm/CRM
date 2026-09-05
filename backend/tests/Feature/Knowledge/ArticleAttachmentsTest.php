<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Modules\Platform\Attachments\Domain\Attachment;
use App\Modules\Platform\Attachments\Domain\AttachmentOwnerType;
use App\Modules\Security\Domain\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Platform\Attachments\InteractsWithAttachments;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * A screenshot on a help article is not a special kind of file.
 *
 * The point of these tests is what they DON'T find: no second uploader, no
 * second scan path, no article-shaped exception to the quarantine rule. An
 * article attachment is an attachment, and it goes through Story 3.2's
 * subsystem exactly as a ticket's does.
 */
final class ArticleAttachmentsTest extends TestCase
{
    use InteractsWithAttachments;
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private string $articleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAttachments(Roles::ADMINISTRATOR);

        $categoryId = (int) DB::table('article_categories')->insertGetId([
            'name_en' => 'Billing',
            'name_ar' => 'الفوترة',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->articleId = (string) $this->withIdempotencyKey()
            ->postJson('/api/v1/admin/knowledge/articles', [
                'type' => 'guide',
                'category_id' => $categoryId,
                'default_locale' => 'en',
            ])->json('id');
    }

    public function test_an_article_is_a_valid_attachment_owner(): void
    {
        $response = $this->withIdempotencyKey()->post('/api/v1/attachments', [
            'owner_type' => AttachmentOwnerType::Article->value,
            'owner_id' => $this->articleId,
            'file' => $this->pngFile('diagram.png'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $attachment = Attachment::query()->sole();
        $this->assertSame('article', $attachment->owner_type);
        $this->assertSame($this->articleId, $attachment->owner_id);
    }

    public function test_it_goes_through_the_same_scan_and_quarantine_as_every_other_attachment(): void
    {
        $this->withIdempotencyKey()->post('/api/v1/attachments', [
            'owner_type' => AttachmentOwnerType::Article->value,
            'owner_id' => $this->articleId,
            'file' => $this->pngFile('diagram.png'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $attachment = Attachment::query()->sole();

        /*
         * The test scanner returns clean, so the file has moved out of
         * quarantine — through the same job, into the same prefix, with the
         * same status column an article knows nothing about.
         */
        $this->assertSame('clean', $attachment->scan_status);
        Storage::disk('attachments')->assertExists($attachment->stored_path);
        $this->assertStringStartsWith('clean/', (string) $attachment->stored_path);
    }

    public function test_the_allow_list_applies_to_articles_too(): void
    {
        /*
         * No article-shaped exception. The size cap and the type allow-list
         * are runtime settings an administrator controls, and they govern
         * every owner — an article that could carry what a ticket could not
         * would be a way around the rule rather than a feature.
         */
        $response = $this->withIdempotencyKey()->post('/api/v1/attachments', [
            'owner_type' => AttachmentOwnerType::Article->value,
            'owner_id' => $this->articleId,
            'file' => \Illuminate\Http\UploadedFile::fake()->create('payload.exe', 8, 'application/x-msdownload'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertSame(0, Attachment::query()->count());
    }

    public function test_attachments_are_listed_for_the_article_that_owns_them(): void
    {
        $this->withIdempotencyKey()->post('/api/v1/attachments', [
            'owner_type' => AttachmentOwnerType::Article->value,
            'owner_id' => $this->articleId,
            'file' => $this->pngFile('diagram.png'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $listed = $this->getJson(
            '/api/v1/attachments?owner_type=article&owner_id='.$this->articleId,
        );

        $listed->assertOk();
        $this->assertCount(1, $listed->json('data'));
        $this->assertSame('diagram.png', $listed->json('data.0.filename'));

        // Where the bytes live is not the client's business.
        $this->assertArrayNotHasKey('stored_path', $listed->json('data.0'));
    }

    public function test_deleting_an_unpublished_article_does_not_orphan_its_files_silently(): void
    {
        $this->withIdempotencyKey()->post('/api/v1/attachments', [
            'owner_type' => AttachmentOwnerType::Article->value,
            'owner_id' => $this->articleId,
            'file' => $this->pngFile('diagram.png'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->withIdempotencyKey()
            ->deleteJson('/api/v1/admin/knowledge/articles/'.$this->articleId)
            ->assertOk();

        /*
         * The row survives the article, and that is recorded here rather than
         * asserted as correct. `attachments` is polymorphic with no foreign
         * key — deliberately, see its migration — so nothing cascades. This
         * test exists so the next person deciding whether that matters finds
         * the fact rather than discovering it.
         */
        $this->assertSame(1, Attachment::query()->count());
        $this->assertSame($this->articleId, Attachment::query()->value('owner_id'));
    }
}
