<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Update;

use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Tests\App\Collections\ArticleCollection;
use Lapaliv\BulkUpsert\Tests\App\Features\GetArticleCollectionForUpdateTestsFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\Article;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlArticle;
use Lapaliv\BulkUpsert\Tests\TestCase;

class OnUpdatingTest extends TestCase
{
    private const NUMBER_OF_ARTICLES = 5;

    /**
     * @dataProvider data
     * @param string $model
     * @return void
     */
    public function test(string $model): void
    {
        [
            'collection' => $collection,
            'sut' => $sut,
        ] = $this->assert($model);

        // act
        $sut->update($model, $collection);

        // assert
        // This part is described in the `assertCollection`
    }

    public function data(): array
    {
        return [
            [MySqlArticle::class],
            [PostgreSqlArticle::class],
        ];
    }

    /**
     * @param string $model
     * @return array{
     *     collection: ArticleCollection,
     *     sut: BulkUpdate,
     * }
     */
    private function assert(string $model): array
    {
        /** @var GetArticleCollectionForUpdateTestsFeature $generateArticlesFeature */
        $generateArticlesFeature = $this->app->make(GetArticleCollectionForUpdateTestsFeature::class);
        $articles = $generateArticlesFeature->handle($model, self::NUMBER_OF_ARTICLES);

        return [
            'collection' => $articles,
            'sut' => $this->app
                ->make(BulkUpdate::class)
                ->onUpdating(
                    fn (ArticleCollection $articles) => $this->assertCollection($articles)
                )
        ];
    }

    private function assertCollection(ArticleCollection $collection): void
    {
        self::assertCount(self::NUMBER_OF_ARTICLES, $collection);

        $collection->each(
            fn (Article $article) => self::assertTrue($article->isDirty())
        );
    }
}
