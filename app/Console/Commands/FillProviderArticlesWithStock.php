<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Tenant\Article;
use App\Tenant\ArticleProvider;

class FillProviderArticlesWithStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'provider:fillwithstockactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fills the Provider with all Products which have a Stock';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $articles = Article::all();
        $articlesWithStock = [];
        foreach($articles as $article) {
            $variations = $article->variations()->get();
            $hasStock = false;
            foreach($variations as $variation) {
                if($variation->getStock() > 0) {
                    $hasStock = true;
                }
            }
            if($hasStock) {
                $articlesWithStock[] = $article;
            }
        }

        foreach($articlesWithStock as $articleWS) {
            $articleWS->active = 1;
            $articleWS->save();
            ArticleProvider::updateOrCreate(
                [
                    'fk_provider_id' => 1,
                    'fk_article_id' => $articleWS->id,
                    'fk_article_variation_id' => null,
                ],
                [
                'active' => true,
                ]
        );
        }
    }
}
