<?php

namespace App\Http\Controllers\API\V1\CMS;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsController extends Controller
{
    use ApiResponse;

    // ── Pages ──────────────────────────────────────────────────────────

    // GET /api/v1/cms/pages/{slug}
    public function getPage(string $slug): JsonResponse
    {
        $page = Page::published()->where('slug', $slug)->first();

        if (! $page) {
            return $this->errorResponse('Page not found.', 404);
        }

        return $this->successResponse(data: ['page' => $page]);
    }

    // ── Blog ───────────────────────────────────────────────────────────

    // GET /api/v1/cms/blog
    public function getBlogPosts(Request $request): JsonResponse
    {
        $posts = BlogPost::published()
            ->when($request->category, fn($q) =>
                $q->whereHas('category', fn($q2) =>
                    $q2->where('slug', $request->category)
                )
            )
            ->orderBy('published_at', 'desc')
            ->paginate(10);

        return $this->successResponse(data: ['posts' => $posts]);
    }

    // GET /api/v1/cms/blog/{slug}
    public function getBlogPost(string $slug): JsonResponse
    {
        $post = BlogPost::published()->where('slug', $slug)->first();

        if (! $post) {
            return $this->errorResponse('Blog post not found.', 404);
        }

        return $this->successResponse(data: ['post' => $post]);
    }

    // GET /api/v1/cms/blog/categories
    public function getBlogCategories(): JsonResponse
    {
        $categories = BlogCategory::orderBy('name')->get();
        return $this->successResponse(data: ['categories' => $categories]);
    }

    // ── FAQs ───────────────────────────────────────────────────────────

    // GET /api/v1/cms/faqs
    public function getFaqs(Request $request): JsonResponse
    {
        $faqs = Faq::published()
            ->when($request->category, fn($q) =>
                $q->where('category', $request->category)
            )
            ->orderBy('order')
            ->get();

        // Group by category
        $grouped = $faqs->groupBy('category');

        return $this->successResponse(data: ['faqs' => $grouped]);
    }

    // ── Site Settings ──────────────────────────────────────────────────

    // GET /api/v1/cms/settings
    public function getSettings(): JsonResponse
    {
        $settings = SiteSetting::all()
            ->pluck('value', 'key');

        return $this->successResponse(data: ['settings' => $settings]);
    }
}