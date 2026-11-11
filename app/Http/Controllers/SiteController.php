<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function home()
    {
        $page = Page::published()->where('is_homepage', true)->first();

        if (! $page) {
            return view('site.welcome');
        }

        return $this->renderPage($page);
    }

    public function page(string $slug)
    {
        $page = Page::published()->where('slug', $slug)->firstOrFail();

        if ($page->is_homepage) {
            return redirect()->route('home', status: 301);
        }

        return $this->renderPage($page);
    }

    protected function renderPage(Page $page)
    {
        if ($page->isMembersOnly() && ! auth()->check()) {
            return redirect()->guest(route('login'))->with('status', 'Please log in to see this page.');
        }

        return view('site.page', ['page' => $page]);
    }

    public function blog(Request $request)
    {
        abort_unless(setting('blog_enabled', true), 404);

        $posts = Post::published()
            ->with('author')
            ->when($request->query('tag'), fn ($q, $tag) => $q->whereJsonContains('tags', $tag))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('site.blog', ['posts' => $posts, 'tag' => $request->query('tag')]);
    }

    public function post(string $slug)
    {
        abort_unless(setting('blog_enabled', true), 404);

        $post = Post::published()->with('author')->where('slug', $slug)->firstOrFail();

        if ($post->isMembersOnly() && ! auth()->check()) {
            return redirect()->guest(route('login'))->with('status', 'Please log in to read this post.');
        }

        $related = Post::published()->whereKeyNot($post->id)->latest('published_at')->limit(3)->get();

        return view('site.post', ['post' => $post, 'related' => $related]);
    }
}
