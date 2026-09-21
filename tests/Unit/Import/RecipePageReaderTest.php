<?php

namespace Tests\Unit\Import;

use App\Recipes\Import\RecipeImportException;
use App\Recipes\Import\RecipePageReader;
use App\Recipes\Import\RecipeSource;
use Tests\TestCase;

class RecipePageReaderTest extends TestCase
{
    public function test_it_sends_only_the_json_ld_recipe_found_inside_a_graph(): void
    {
        $source = (new RecipePageReader)->read(file_get_contents(base_path('tests/Fixtures/recipe-page.html')), 'https://www.ica.se/recept/plankstek-728945/');

        $this->assertSame(RecipeSource::KIND_JSON_LD, $source->kind);
        $this->assertSame('sv', $source->languageHint);

        $recipe = json_decode($source->content, true);
        $this->assertSame('Plankstek', $recipe['name']);
        $this->assertCount(7, $recipe['recipeIngredient']);
        // Unicode stays readable rather than \u-escaped, which keeps the prompt small.
        $this->assertStringContainsString('mjölig potatis', $source->content);

        $text = $source->contentBlocks()[0]['text'];
        $this->assertStringContainsString('<source>', $text);
        $this->assertStringContainsString('Source URL: https://www.ica.se/recept/plankstek-728945/', $text);
    }

    public function test_without_json_ld_it_falls_back_to_the_readable_main_text(): void
    {
        $html = '<html lang="en"><head><meta property="og:image" content="https://x.test/p.jpg"><script>var tracking = 1;</script></head>'
            .'<body><nav>Home · Recipes</nav><main><h1>Pancakes</h1><ul><li>3 dl flour</li><li>6 dl milk</li></ul>'
            .'<ol><li>Whisk everything together.</li><li>Fry thin pancakes in butter.</li></ol></main><footer>© Site</footer></body></html>';

        $source = (new RecipePageReader)->read($html);

        $this->assertSame(RecipeSource::KIND_TEXT, $source->kind);
        $this->assertSame("Pancakes\n3 dl flour\n6 dl milk\nWhisk everything together.\nFry thin pancakes in butter.", $source->content);
        $this->assertSame('https://x.test/p.jpg', $source->imageUrl);
        $this->assertSame('en', $source->languageHint);
    }

    public function test_a_page_with_nothing_to_read_is_refused(): void
    {
        $this->expectException(RecipeImportException::class);

        (new RecipePageReader)->read('<html><body><main>Hi</main></body></html>');
    }

    public function test_an_oversized_page_is_refused_rather_than_cut(): void
    {
        $this->expectException(RecipeImportException::class);

        (new RecipePageReader)->read('<main><p>'.str_repeat('lorem ipsum ', 20_000).'</p></main>');
    }
}
