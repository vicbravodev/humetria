<?php

declare(strict_types=1);

use App\Modules\Blog\Contracts\ArticleLibrary;
use App\Modules\Blog\Support\FileArticleLibrary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

require_once __DIR__.'/BlogTestHelpers.php';

mutates(FileArticleLibrary::class);

beforeEach(fn () => Carbon::setTestNow('2026-11-20 12:00:00'));

it('lists the reviewed articles of today or earlier, the newest first', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'old', ['published_at' => 'published_at: 2026-11-01']);
    writeArticle($folder, 'new', ['published_at' => 'published_at: 2026-11-15']);
    writeArticle($folder, 'draft', ['reviewed_at' => 'reviewed_at: null', 'reviewed_by' => '']);
    writeArticle($folder, 'future', ['published_at' => 'published_at: 2026-11-21']);

    $slugs = array_map(fn ($article) => $article->slug, app(ArticleLibrary::class)->published());

    expect($slugs)->toBe(['new', 'old']);
});

it('finds a published article by its slug and nothing else', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'live');
    writeArticle($folder, 'draft', ['reviewed_at' => 'reviewed_at: null', 'reviewed_by' => '']);
    writeArticle($folder, 'future', ['published_at' => 'published_at: 2026-11-21']);

    $library = app(ArticleLibrary::class);

    expect($library->find('live')?->title)->toBe('Artículo live')
        ->and($library->find('draft'))->toBeNull()
        ->and($library->find('future'))->toBeNull()
        ->and($library->find('missing'))->toBeNull();
});

it('does not read outside the folder for a slug that is a path', function (): void {
    $folder = blogFolder();
    $extension = 'md';
    $outside = dirname($folder)."/blog-outside.{$extension}";
    writeArticle($folder, 'live');
    File::copy("{$folder}/live.{$extension}", $outside);

    expect(app(ArticleLibrary::class)->find('../blog-outside'))->toBeNull();

    File::delete($outside);
});

it('leaves a malformed article out and says so in the log', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'good');
    writeArticle($folder, 'broken', ['title' => '']);
    Log::spy();

    $slugs = array_map(fn ($article) => $article->slug, app(ArticleLibrary::class)->published());

    expect($slugs)->toBe(['good']);
    Log::shouldHaveReceived('warning')->once()->with('blog.article_invalid', ['slug' => 'broken', 'problems' => ['title is required.']]);
});

it('lists nothing when the folder does not exist', function (): void {
    config(['blog.path' => sys_get_temp_dir().'/blog-does-not-exist']);

    expect(app(ArticleLibrary::class)->published())->toBe([]);
});
