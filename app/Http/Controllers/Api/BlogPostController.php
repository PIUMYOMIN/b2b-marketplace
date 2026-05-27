<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BlogPostController extends Controller
{
    public function index(Request $request)
    {
        $posts = BlogPost::query()
            ->visible()
            ->with('author:id,name')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->search . '%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('title_en', 'like', $term)
                        ->orWhere('title_mm', 'like', $term)
                        ->orWhere('excerpt_en', 'like', $term)
                        ->orWhere('excerpt_mm', 'like', $term)
                        ->orWhere('content_en', 'like', $term)
                        ->orWhere('content_mm', 'like', $term);
                });
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->paginate((int) $request->get('per_page', 12));

        return response()->json([
            'success' => true,
            'data' => $posts->through(fn (BlogPost $post) => $this->formatPost($post, false)),
            'categories' => $this->publishedCategories(),
        ]);
    }

    public function show(string $slug)
    {
        $post = BlogPost::query()
            ->visible()
            ->with('author:id,name')
            ->where('slug', $slug)
            ->firstOrFail();

        $post->increment('views');

        $related = BlogPost::query()
            ->visible()
            ->where('id', '!=', $post->id)
            ->when($post->category, fn ($q) => $q->where('category', $post->category))
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn (BlogPost $item) => $this->formatPost($item, false));

        return response()->json([
            'success' => true,
            'data' => $this->formatPost($post->fresh('author'), true),
            'related' => $related,
        ]);
    }

    public function adminIndex(Request $request)
    {
        $posts = BlogPost::query()
            ->with('author:id,name')
            ->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->search . '%';
                $q->where(fn ($qq) => $qq
                    ->where('title_en', 'like', $term)
                    ->orWhere('title_mm', 'like', $term)
                    ->orWhere('slug', 'like', $term));
            })
            ->latest()
            ->paginate((int) $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $posts->through(fn (BlogPost $post) => $this->formatPost($post, true)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['author_id'] = $request->user()?->id;
        $data['slug'] = $this->resolveSlug($data);
        $data['published_at'] = $this->resolvePublishedAt($data);

        $post = BlogPost::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Blog post created successfully',
            'data' => $this->formatPost($post->load('author'), true),
        ], 201);
    }

    public function update(Request $request, BlogPost $post)
    {
        $data = $this->validatedData($request, $post);
        $data['slug'] = $this->resolveSlug($data, $post);
        $data['published_at'] = $this->resolvePublishedAt($data, $post);

        $post->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Blog post updated successfully',
            'data' => $this->formatPost($post->fresh('author'), true),
        ]);
    }

    public function destroy(BlogPost $post)
    {
        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Blog post deleted successfully',
        ]);
    }

    public function publish(BlogPost $post)
    {
        $post->update([
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => $post->published_at ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatPost($post->fresh('author'), true),
        ]);
    }

    public function archive(BlogPost $post)
    {
        $post->update(['status' => BlogPost::STATUS_ARCHIVED]);

        return response()->json([
            'success' => true,
            'data' => $this->formatPost($post->fresh('author'), true),
        ]);
    }

    private function validatedData(Request $request, ?BlogPost $post = null): array
    {
        $data = $request->validate([
            'title_en' => 'required|string|max:180',
            'title_mm' => 'nullable|string|max:180',
            'slug' => 'nullable|string|max:200',
            'excerpt_en' => 'nullable|string|max:500',
            'excerpt_mm' => 'nullable|string|max:500',
            'content_en' => 'required|string',
            'content_mm' => 'nullable|string',
            'featured_image' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:80',
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|string|max:60',
            'status' => 'required|in:draft,published,archived',
            'is_featured' => 'boolean',
            'published_at' => 'nullable|date',
            'seo_title_en' => 'nullable|string|max:180',
            'seo_title_mm' => 'nullable|string|max:180',
            'seo_description_en' => 'nullable|string|max:500',
            'seo_description_mm' => 'nullable|string|max:500',
        ]);

        $data['tags'] = collect($data['tags'] ?? [])
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $data['is_featured'] = (bool) ($data['is_featured'] ?? false);

        return $data;
    }

    private function resolveSlug(array $data, ?BlogPost $post = null): string
    {
        $source = trim((string) ($data['slug'] ?? '')) ?: $data['title_en'];
        $slug = Str::slug($source);

        if ($post && $slug === $post->slug) {
            return $post->slug;
        }

        return BlogPost::uniqueSlug($slug ?: $data['title_en'], $post?->id);
    }

    private function resolvePublishedAt(array $data, ?BlogPost $post = null)
    {
        if (($data['status'] ?? null) !== BlogPost::STATUS_PUBLISHED) {
            return $data['published_at'] ?? $post?->published_at;
        }

        return $data['published_at'] ?? $post?->published_at ?? now();
    }

    private function formatPost(BlogPost $post, bool $includeContent): array
    {
        $contentEn = $includeContent ? $post->content_en : null;
        $contentMm = $includeContent ? $post->content_mm : null;

        return [
            'id' => $post->id,
            'title_en' => $post->title_en,
            'title_mm' => $post->title_mm,
            'slug' => $post->slug,
            'excerpt_en' => $post->excerpt_en ?: Str::limit(strip_tags($post->content_en), 180),
            'excerpt_mm' => $post->excerpt_mm ?: ($post->content_mm ? Str::limit(strip_tags($post->content_mm), 180) : null),
            'content_en' => $contentEn,
            'content_mm' => $contentMm,
            'featured_image' => $post->featured_image,
            'category' => $post->category,
            'tags' => $post->tags ?? [],
            'status' => $post->status,
            'is_featured' => $post->is_featured,
            'published_at' => $post->published_at?->toIso8601String(),
            'seo_title_en' => $post->seo_title_en,
            'seo_title_mm' => $post->seo_title_mm,
            'seo_description_en' => $post->seo_description_en,
            'seo_description_mm' => $post->seo_description_mm,
            'views' => $post->views,
            'author' => $post->author ? Arr::only($post->author->toArray(), ['id', 'name']) : null,
            'created_at' => $post->created_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
        ];
    }

    private function publishedCategories(): array
    {
        return BlogPost::query()
            ->visible()
            ->whereNotNull('category')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values()
            ->all();
    }
}
