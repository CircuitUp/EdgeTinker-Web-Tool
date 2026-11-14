<?php

namespace Tests\Feature\Studio;

use App\Enums\RevisionAction;
use App\Enums\RevisionStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Revision;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\Studio\RevisionReviewed;
use App\Notifications\Studio\SubmissionReceived;
use App\Studio\RevisionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsCatalogue;
use Tests\TestCase;

/** The core rule: the live site only ever shows the last approved version. */
class ApprovalWorkflowTest extends TestCase
{
    use BuildsCatalogue;
    use RefreshDatabase;

    private User $admin;

    private User $creator;

    private Category $category;

    private RevisionService $revisions;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        $this->creator = User::factory()->create(['name' => 'Cleo Creator']);
        $this->category = $this->makeCategory('Sensor Boards');
        $this->revisions = app(RevisionService::class);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'New Sensor Board',
            'sku' => 'et-new-01',
            'category_id' => $this->category->id,
            'short_description' => 'A brand new sensor board.',
            'description' => '<p>Full description.</p>',
            'key_features' => ['Small', '', 'Accurate'],
            'specs' => [['label' => 'Supply', 'value' => '3.3 V'], ['label' => '', 'value' => '']],
            'price_usd' => '9.5',
            'price_lkr' => '2900',
            'stock_status' => 'in_stock',
            'new_tags' => ['Brand New Tag'],
        ];
    }

    private function liveProduct(array $overrides = []): Product
    {
        return $this->makeProduct($this->category, 'Live Board', $overrides + ['short_description' => 'Original description.']);
    }

    // ── New items ────────────────────────────────────────────────────────────

    public function test_a_submitted_new_product_is_not_live_until_approved(): void
    {
        $revision = $this->revisions->save($this->creator, 'product', null, $this->payload(), submit: true);

        $this->assertSame(RevisionStatus::Submitted, $revision->status);
        $this->assertSame(RevisionAction::Create, $revision->action);
        $this->assertNull($revision->entity_id);
        $this->assertSame(0, Product::count(), 'Nothing may be written to the live table before approval.');
        $this->assertSame(0, Tag::count());

        $this->get('/product/new-sensor-board')->assertNotFound();
        $this->get('/products')->assertDontSee('New Sensor Board');
        $this->get('/search?q=sensor')->assertDontSee('New Sensor Board');

        Notification::assertSentTo($this->admin, SubmissionReceived::class);
    }

    public function test_approving_a_new_product_publishes_it_in_its_category(): void
    {
        $revision = $this->revisions->save($this->creator, 'product', null, $this->payload(), submit: true);

        $this->revisions->approve($revision, $this->admin);

        $product = Product::with(['specs', 'tags'])->sole();
        $this->assertSame('New Sensor Board', $product->name);
        $this->assertSame('new-sensor-board', $product->slug, 'The web address fills itself in.');
        $this->assertSame('ET-NEW-01', $product->sku);
        $this->assertSame('New Sensor Board', $product->seo_title, 'SEO fields fill themselves in.');
        $this->assertSame(['Small', 'Accurate'], $product->key_features, 'Empty rows are dropped.');
        $this->assertSame(['Supply'], $product->specs->pluck('label')->all());
        $this->assertSame(['Brand New Tag'], $product->tags->pluck('name')->all(), 'Tags typed in the form are created on approval.');

        $revision->refresh();
        $this->assertSame(RevisionStatus::Approved, $revision->status);
        $this->assertSame($product->id, $revision->entity_id);
        $this->assertSame($this->admin->id, $revision->reviewer_id);

        $this->get('/product/new-sensor-board')->assertOk()->assertSee('A brand new sensor board.');
        $this->get('/products/sensor-boards')->assertSee('New Sensor Board');

        Notification::assertSentTo($this->creator, RevisionReviewed::class);
        $this->assertTrue(AuditLog::where('event', 'revision.approved')->exists());
    }

    public function test_a_draft_is_private_and_notifies_nobody(): void
    {
        $revision = $this->revisions->save($this->creator, 'product', null, $this->payload(), submit: false);

        $this->assertSame(RevisionStatus::Draft, $revision->status);
        $this->assertSame(0, Product::count());
        Notification::assertNothingSent();

        $this->expectException(ValidationException::class);
        $this->revisions->approve($revision, $this->admin); // drafts cannot be approved
    }

    // ── Edits to live content ────────────────────────────────────────────────

    public function test_editing_a_live_product_leaves_the_live_version_untouched_until_approval(): void
    {
        $product = $this->liveProduct();
        $payload = ['short_description' => 'Changed description.', 'price_usd' => '99'] + app('App\Studio\ContentTypes\ContentTypeRegistry')->get('product')->current($product->id);

        $revision = $this->revisions->save($this->creator, 'product', $product->id, $payload, submit: true);

        $this->assertSame(RevisionAction::Update, $revision->action);
        $this->assertSame('Original description.', $product->fresh()->short_description);
        $this->get("/product/{$product->slug}")->assertSee('Original description.')->assertDontSee('Changed description.');

        $this->revisions->approve($revision, $this->admin);

        $this->assertSame('Changed description.', $product->fresh()->short_description);
        $this->assertSame('99.00', $product->fresh()->price_usd);
        $this->get("/product/{$product->slug}")->assertSee('Changed description.');
        $this->assertSame('Original description.', $revision->fresh()->previous['short_description'], 'The replaced version is kept for history.');
    }

    public function test_rejection_needs_a_comment_and_returns_the_change_to_its_author(): void
    {
        $revision = $this->revisions->save($this->creator, 'product', null, $this->payload(), submit: true);

        try {
            $this->revisions->reject($revision, $this->admin, '   ');
            $this->fail('A rejection without a comment must be refused.');
        } catch (ValidationException) {
        }

        $this->revisions->reject($revision, $this->admin, 'Please add the pinout.');

        $revision->refresh();
        $this->assertSame(RevisionStatus::Rejected, $revision->status);
        $this->assertSame('Please add the pinout.', $revision->comment);
        $this->assertSame(0, Product::count());
        Notification::assertSentTo($this->creator, RevisionReviewed::class);

        // The creator fixes it and submits again; it can then be approved.
        $again = $this->revisions->save($this->creator, 'product', null, $this->payload(['short_description' => 'Now with pinout.']), submit: true, revision: $revision);
        $this->assertSame($revision->id, $again->id);
        $this->assertSame(RevisionStatus::Submitted, $again->status);
        $this->assertNull($again->comment);

        $this->revisions->approve($again, $this->admin);
        $this->assertSame('Now with pinout.', Product::sole()->short_description);
    }

    // ── Deletion ─────────────────────────────────────────────────────────────

    public function test_a_deletion_request_keeps_the_item_live_until_approved(): void
    {
        $product = $this->liveProduct();

        $revision = $this->revisions->requestDeletion($this->creator, 'product', $product->id);

        $this->assertSame(RevisionAction::Delete, $revision->action);
        $this->assertSame(RevisionStatus::Submitted, $revision->status);
        $this->assertNotNull($product->fresh());
        $this->get("/product/{$product->slug}")->assertOk();

        $this->revisions->approve($revision, $this->admin);

        $this->assertNull($product->fresh());
        $this->get("/product/{$product->slug}")->assertNotFound();
        $this->assertSame('Live Board', $revision->fresh()->previous['name'], 'The deleted content is kept so it can be restored.');
    }

    public function test_a_second_deletion_request_for_the_same_item_is_refused(): void
    {
        $product = $this->liveProduct();
        $this->revisions->requestDeletion($this->creator, 'product', $product->id);

        $this->expectException(ValidationException::class);
        $this->revisions->requestDeletion($this->creator, 'product', $product->id);
    }

    public function test_a_category_that_still_has_products_cannot_be_deleted(): void
    {
        $this->liveProduct();
        $revision = $this->revisions->requestDeletion($this->creator, 'category', $this->category->id);

        try {
            $this->revisions->approve($revision, $this->admin);
            $this->fail('Deleting a category with products must be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('still contains 1 product', collect($e->errors())->flatten()->first());
        }

        $this->assertNotNull($this->category->fresh());
        $this->assertSame(RevisionStatus::Submitted, $revision->fresh()->status, 'A refused approval changes nothing.');
    }

    // ── Two pending changes to the same item ─────────────────────────────────

    public function test_approving_one_change_flags_other_pending_changes_to_the_same_item(): void
    {
        $product = $this->liveProduct();
        $other = User::factory()->create();
        $current = app('App\Studio\ContentTypes\ContentTypeRegistry')->get('product')->current($product->id);

        $first = $this->revisions->save($this->creator, 'product', $product->id, ['short_description' => 'From Cleo.'] + $current, submit: true);
        $second = $this->revisions->save($other, 'product', $product->id, ['short_description' => 'From the other creator.'] + $current, submit: true);

        $this->revisions->approve($first, $this->admin);

        $second->refresh();
        $this->assertTrue($second->needs_rebase);
        $this->assertStringContainsString('Another change to this item was approved', $second->rebase_note);
        $this->assertFalse($first->fresh()->needs_rebase);

        // The out-of-date change cannot be approved as it is…
        try {
            $this->revisions->approve($second, $this->admin);
            $this->fail('An out-of-date change must not be approvable.');
        } catch (ValidationException) {
        }
        $this->assertSame('From Cleo.', $product->fresh()->short_description);

        // …until its author has looked at it again and re-submitted.
        $resubmitted = $this->revisions->save($other, 'product', $product->id, $second->payload, submit: true, revision: $second);
        $this->assertFalse($resubmitted->needs_rebase);
        $this->revisions->approve($resubmitted, $this->admin);
        $this->assertSame('From the other creator.', $product->fresh()->short_description);
    }

    public function test_approving_a_deletion_rejects_other_pending_changes_to_that_item(): void
    {
        $product = $this->liveProduct();
        $current = app('App\Studio\ContentTypes\ContentTypeRegistry')->get('product')->current($product->id);
        $edit = $this->revisions->save($this->creator, 'product', $product->id, ['name' => 'Renamed'] + $current, submit: true);
        $deletion = $this->revisions->requestDeletion(User::factory()->create(), 'product', $product->id);

        $this->revisions->approve($deletion, $this->admin);

        $this->assertSame(RevisionStatus::Rejected, $edit->fresh()->status);
        $this->assertStringContainsString('was deleted', $edit->fresh()->comment);
    }

    public function test_a_sku_taken_since_submission_is_caught_at_approval(): void
    {
        $first = $this->revisions->save($this->creator, 'product', null, $this->payload(), submit: true);
        $second = $this->revisions->save(User::factory()->create(), 'product', null, $this->payload(['name' => 'Another Board']), submit: true);

        $this->revisions->approve($first, $this->admin);

        try {
            $this->revisions->approve($second, $this->admin);
            $this->fail('A duplicate SKU must not be published.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('SKU', collect($e->errors())->flatten()->first());
        }

        $this->assertSame(1, Product::count());
        $this->assertSame(RevisionStatus::Submitted, $second->fresh()->status);
    }

    // ── Content Admin ────────────────────────────────────────────────────────

    public function test_the_admins_own_changes_publish_immediately_and_are_recorded(): void
    {
        $revision = $this->revisions->save($this->admin, 'product', null, $this->payload(), submit: true);

        $this->assertSame(RevisionStatus::Approved, $revision->status);
        $this->assertSame($this->admin->id, $revision->author_id);
        $this->assertSame($this->admin->id, $revision->reviewer_id);
        $this->assertSame(1, Product::count());
        Notification::assertNothingSent();
    }

    public function test_only_the_admin_can_approve_or_reject(): void
    {
        $revision = $this->revisions->save($this->creator, 'product', null, $this->payload(), submit: true);

        foreach ([fn () => $this->revisions->approve($revision, $this->creator), fn () => $this->revisions->reject($revision, $this->creator, 'No.')] as $attempt) {
            try {
                $attempt();
                $this->fail('A Content Creator must not be able to review.');
            } catch (AuthorizationException) {
            }
        }

        // A suspended admin account cannot review either.
        $this->admin->status = UserStatus::Suspended;
        $this->expectException(AuthorizationException::class);
        $this->revisions->approve($revision, $this->admin);
    }

    public function test_a_creator_cannot_edit_or_discard_someone_elses_change(): void
    {
        $revision = $this->revisions->save($this->creator, 'product', null, $this->payload(), submit: false);
        $intruder = User::factory()->create();

        try {
            $this->revisions->save($intruder, 'product', null, $this->payload(['name' => 'Hijacked']), submit: true, revision: $revision);
            $this->fail('Editing another creator’s change must be refused.');
        } catch (AuthorizationException) {
        }

        try {
            $this->revisions->discard($revision, $intruder);
            $this->fail('Discarding another creator’s change must be refused.');
        } catch (AuthorizationException) {
        }

        $this->assertSame('New Sensor Board', $revision->fresh()->title);

        $this->revisions->discard($revision, $this->creator);
        $this->assertNull(Revision::find($revision->id));
    }

    // ── History ──────────────────────────────────────────────────────────────

    public function test_an_earlier_version_can_be_restored(): void
    {
        $created = $this->revisions->save($this->admin, 'product', null, $this->payload(), submit: true);
        $product = Product::sole();
        $this->revisions->save($this->admin, 'product', $product->id, $this->payload(['short_description' => 'Second version.']), submit: true);
        $this->assertSame('Second version.', $product->fresh()->short_description);

        $restored = $this->revisions->restore($created, $this->admin);

        $this->assertSame('A brand new sensor board.', $product->fresh()->short_description);
        $this->assertSame(RevisionAction::Update, $restored->action);
        $this->assertSame(3, Revision::forEntity('product', $product->id)->where('status', 'approved')->count(), 'Restoring adds to the history rather than rewriting it.');
    }

    public function test_a_deleted_item_can_be_restored_from_its_history(): void
    {
        $product = $this->liveProduct();
        $deletion = $this->revisions->requestDeletion($this->admin, 'product', $product->id);
        $this->assertSame(0, Product::count());

        $this->revisions->restore($deletion, $this->admin);

        $this->assertSame('Live Board', Product::sole()->name);
    }

    // ── Safety of submitted content ──────────────────────────────────────────

    public function test_the_store_link_is_saved_and_must_be_a_safe_https_address(): void
    {
        foreach (['javascript:alert(1)', 'http://insecure.example', 'not a link', '//evil.example'] as $bad) {
            try {
                $this->revisions->save($this->creator, 'product', null, $this->payload(['store_url' => $bad]), submit: true);
                $this->fail("The store link “{$bad}” must be refused.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('store_url', $e->errors());
            }
        }

        $revision = $this->revisions->save($this->creator, 'product', null, $this->payload(['store_url' => 'https://shop.example.com/p/1']), submit: true);
        $this->revisions->approve($revision, $this->admin);

        $this->assertSame('https://shop.example.com/p/1', Product::sole()->store_url);
        $this->get('/product/new-sensor-board')->assertSee('href="https://shop.example.com/p/1"', false)->assertSee('Buy');
    }

    public function test_unsafe_html_is_removed_before_it_is_stored(): void
    {
        $revision = $this->revisions->save($this->creator, 'product', null, $this->payload([
            'description' => '<p onclick="x()">Hello</p><script>alert(1)</script><a href="javascript:alert(2)">link</a>',
        ]), submit: true);

        $this->assertStringNotContainsString('script', $revision->payload['description']);
        $this->assertStringNotContainsString('onclick', $revision->payload['description']);
        $this->assertStringNotContainsString('javascript:', $revision->payload['description']);
        $this->assertStringContainsString('Hello', $revision->payload['description']);
    }

    public function test_invalid_content_is_refused_with_field_errors(): void
    {
        try {
            $this->revisions->save($this->creator, 'product', null, $this->payload([
                'name' => '', 'sku' => 'has spaces!', 'category_id' => 999, 'stock_status' => 'maybe', 'price_usd' => '-1',
                'images' => [['path' => '../../etc/passwd', 'alt' => 'x']],
            ]), submit: true);
            $this->fail('Invalid content must be refused.');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(
                ['name', 'slug', 'sku', 'category_id', 'stock_status', 'price_usd', 'seo_title', 'images.0.path'],
                array_keys($e->errors()),
            );
        }

        $this->assertSame(0, Revision::count());
    }

    public function test_categories_and_tags_use_the_same_workflow_and_refresh_the_menu_on_approval(): void
    {
        $this->get('/')->assertDontSee('Robotics Boards');

        $revision = $this->revisions->save($this->creator, 'category', null, ['name' => 'Robotics Boards', 'tagline' => 'Motor drivers and more.'], submit: true);
        $this->assertNull(Category::where('slug', 'robotics-boards')->first());
        $this->get('/')->assertDontSee('Robotics Boards');

        $this->revisions->approve($revision, $this->admin);
        $this->get('/')->assertSee('Robotics Boards'); // the cached menu was cleared

        $tag = $this->revisions->save($this->creator, 'tag', null, ['name' => 'Zigbee'], submit: true);
        $this->assertSame(0, Tag::where('slug', 'zigbee')->count());
        $this->revisions->approve($tag, $this->admin);
        $this->assertSame(1, Tag::where('slug', 'zigbee')->count());
    }
}
