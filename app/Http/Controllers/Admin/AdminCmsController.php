<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\Storage\StorageService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCmsController extends Controller
{
    use ApiResponse;

    public function __construct(private StorageService $storage) {}

    private function resolveFeaturedImage(Request $request, ?string $existing = null): ?string
    {
        if ($request->hasFile('featured_image')) {
            $uploaded = $this->storage->uploadMedia($request->file('featured_image'), 'blog');
            return $uploaded['url'];
        }
        return $request->input('featured_image', $existing);
    }

    // ══════════════════════════════════════════════════════════════════
    // PAGES
    // ══════════════════════════════════════════════════════════════════

    // GET /api/v1/admin/cms/pages
    public function indexPages(): JsonResponse
    {
        $pages = Page::orderBy('created_at', 'desc')->get();
        return $this->successResponse(data: ['pages' => $pages]);
    }

    // POST /api/v1/admin/cms/pages
    public function storePage(Request $request): JsonResponse
    {
        $request->validate([
            'title'   => 'required|string|max:200',
            'content' => 'required|string',
        ]);

        $page = Page::create([
            'title'            => $request->title,
            'slug'             => Str::slug($request->title),
            'content'          => $request->content,
            'meta_title'       => $request->meta_title,
            'meta_description' => $request->meta_description,
            'is_published'     => $request->boolean('is_published', false),
        ]);

        return $this->successResponse(data: ['page' => $page], message: 'Page created.', statusCode: 201);
    }

    // PUT /api/v1/admin/cms/pages/{id}
    public function updatePage(Request $request, string $id): JsonResponse
    {
        $page = Page::findOrFail($id);

        $page->update([
            'title'            => $request->title ?? $page->title,
            'slug'             => $request->title ? Str::slug($request->title) : $page->slug,
            'content'          => $request->content ?? $page->content,
            'meta_title'       => $request->meta_title ?? $page->meta_title,
            'meta_description' => $request->meta_description ?? $page->meta_description,
            'is_published'     => $request->has('is_published') ? $request->boolean('is_published') : $page->is_published,
        ]);

        return $this->successResponse(data: ['page' => $page], message: 'Page updated.');
    }

    // DELETE /api/v1/admin/cms/pages/{id}
    public function destroyPage(string $id): JsonResponse
    {
        Page::findOrFail($id)->delete();
        return $this->successResponse(message: 'Page deleted.');
    }

    // ══════════════════════════════════════════════════════════════════
    // BLOG POSTS
    // ══════════════════════════════════════════════════════════════════

    // GET /api/v1/admin/cms/blog
    public function indexBlog(): JsonResponse
    {
        $posts = BlogPost::orderBy('created_at', 'desc')->get();
        return $this->successResponse(data: ['posts' => $posts]);
    }

    // POST /api/v1/admin/cms/blog
    public function storeBlog(Request $request): JsonResponse
    {
        $request->validate([
            'title'          => 'required|string|max:200',
            'content'        => 'required|string',
            'category_id'    => 'nullable|string',
            'featured_image' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);

        $post = BlogPost::create([
            'title'            => $request->title,
            'slug'             => Str::slug($request->title),
            'content'          => $request->content,
            'excerpt'          => $request->excerpt,
            'featured_image'   => $this->resolveFeaturedImage($request),
            'category_id'      => $request->category_id,
            'meta_title'       => $request->meta_title,
            'meta_description' => $request->meta_description,
            'is_published'     => $request->boolean('is_published', false),
            'published_at'     => $request->boolean('is_published') ? now() : null,
        ]);

        return $this->successResponse(data: ['post' => $post], message: 'Blog post created.', statusCode: 201);
    }

    // PUT /api/v1/admin/cms/blog/{id}
    public function updateBlog(Request $request, string $id): JsonResponse
    {
        $post = BlogPost::findOrFail($id);

        $request->validate([
            'featured_image' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);

        $post->update([
            'title'            => $request->title ?? $post->title,
            'slug'             => $request->title ? Str::slug($request->title) : $post->slug,
            'content'          => $request->content ?? $post->content,
            'excerpt'          => $request->excerpt ?? $post->excerpt,
            'featured_image'   => $this->resolveFeaturedImage($request, $post->featured_image),
            'category_id'      => $request->category_id ?? $post->category_id,
            'meta_title'       => $request->meta_title ?? $post->meta_title,
            'meta_description' => $request->meta_description ?? $post->meta_description,
            'is_published'     => $request->has('is_published') ? $request->boolean('is_published') : $post->is_published,
            'published_at'     => $request->boolean('is_published') && ! $post->published_at ? now() : $post->published_at,
        ]);

        return $this->successResponse(data: ['post' => $post], message: 'Blog post updated.');
    }

    // DELETE /api/v1/admin/cms/blog/{id}
    public function destroyBlog(string $id): JsonResponse
    {
        BlogPost::findOrFail($id)->delete();
        return $this->successResponse(message: 'Blog post deleted.');
    }

    // ══════════════════════════════════════════════════════════════════
    // FAQs
    // ══════════════════════════════════════════════════════════════════

    // GET /api/v1/admin/cms/faqs
    public function indexFaqs(): JsonResponse
    {
        $faqs = Faq::orderBy('category')->orderBy('order')->get();
        return $this->successResponse(data: ['faqs' => $faqs]);
    }

    // POST /api/v1/admin/cms/faqs
    public function storeFaq(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string',
            'answer'   => 'required|string',
            'category' => 'required|in:shipping,tracking,payment,general',
        ]);

        $faq = Faq::create([
            'question'     => $request->question,
            'answer'       => $request->answer,
            'category'     => $request->category,
            'order'        => $request->order ?? 99,
            'is_published' => $request->boolean('is_published', true),
        ]);

        return $this->successResponse(data: ['faq' => $faq], message: 'FAQ created.', statusCode: 201);
    }

    // PUT /api/v1/admin/cms/faqs/{id}
    public function updateFaq(Request $request, string $id): JsonResponse
    {
        $faq = Faq::findOrFail($id);

        $faq->update([
            'question'     => $request->question ?? $faq->question,
            'answer'       => $request->answer ?? $faq->answer,
            'category'     => $request->category ?? $faq->category,
            'order'        => $request->order ?? $faq->order,
            'is_published' => $request->has('is_published') ? $request->boolean('is_published') : $faq->is_published,
        ]);

        return $this->successResponse(data: ['faq' => $faq], message: 'FAQ updated.');
    }

    // DELETE /api/v1/admin/cms/faqs/{id}
    public function destroyFaq(string $id): JsonResponse
    {
        Faq::findOrFail($id)->delete();
        return $this->successResponse(message: 'FAQ deleted.');
    }

    // ══════════════════════════════════════════════════════════════════
    // SITE SETTINGS
    // ══════════════════════════════════════════════════════════════════

    // GET /api/v1/admin/cms/settings
    public function indexSettings(): JsonResponse
    {
        $settings = SiteSetting::all()->pluck('value', 'key');
        return $this->successResponse(data: ['settings' => $settings]);
    }

    // POST /api/v1/admin/cms/settings
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'settings'       => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($request->settings as $item) {
            SiteSetting::set($item['key'], $item['value'], $item['type'] ?? 'text');
        }

        return $this->successResponse(message: 'Settings updated.');
    }

    // ══════════════════════════════════════════════════════════════════
    // BLOG CATEGORIES
    // ══════════════════════════════════════════════════════════════════

    // GET /api/v1/admin/cms/blog/categories
    public function indexCategories(): JsonResponse
    {
        $categories = BlogCategory::orderBy('name')->get();
        return $this->successResponse(data: ['categories' => $categories]);
    }

    // POST /api/v1/admin/cms/blog/categories
    public function storeCategory(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:100']);

        $category = BlogCategory::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return $this->successResponse(data: ['category' => $category], message: 'Category created.', statusCode: 201);
    }
}