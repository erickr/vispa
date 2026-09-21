<?php

namespace Tests\Unit\Import;

use Anthropic\Client;
use App\Recipes\Import\ClaudeRecipeExtractor;
use App\Recipes\Import\RecipeImportException;
use App\Recipes\Import\RecipeSource;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

/**
 * The Claude call, with the SDK's HTTP transport swapped for a canned response — no network.
 */
class ClaudeRecipeExtractorTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $sent = [];

    private function extractor(Response ...$responses): ClaudeRecipeExtractor
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));

        return new ClaudeRecipeExtractor(
            new Client(apiKey: 'test-key', requestOptions: ['transporter' => new Guzzle(['handler' => $stack]), 'maxRetries' => 0]),
            'claude-opus-5',
        );
    }

    /**
     * @param  array<string, mixed>|null  $output
     */
    private static function message(?array $output, string $stopReason = 'end_turn'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5',
            'content' => $output === null ? [] : [['type' => 'text', 'text' => json_encode($output)]],
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 800, 'cache_read_input_tokens' => 300],
        ]));
    }

    public function test_it_sends_the_source_and_maps_the_structured_output(): void
    {
        $recipe = $this->extractor(self::message([
            'is_recipe' => true,
            'title' => 'Plankstek',
            'description' => null,
            'locale' => 'sv',
            'servings' => 4,
            'prep_minutes' => null,
            'cook_minutes' => null,
            'total_minutes' => 90,
            'source_credit' => 'ICA',
            'image_url' => 'https://assets.example.test/plankstek.jpg',
            'ingredient_groups' => [['title' => null, 'items' => [[
                'raw' => '1 kg mjölig potatis (ej mandelpotatis)', 'quantity' => 1, 'quantity_min' => null, 'quantity_max' => null,
                'unit' => 'kg', 'ingredient' => 'potatis', 'preparation_note' => 'mjölig, ej mandelpotatis', 'optional' => false,
            ]]]],
            'instruction_sections' => [['title' => null, 'steps' => [['text' => 'Sätt ugnen på 250°C.', 'timer_seconds' => null]]]],
        ]))->extract(RecipeSource::fromJsonLd('{"@type":"Recipe","name":"Plankstek"}'), ['dl', 'kg', 'msk']);

        $this->assertSame('Plankstek', $recipe->title);
        $this->assertSame(4, $recipe->servings);
        $this->assertSame(90, $recipe->totalMinutes);
        $this->assertSame('potatis', $recipe->ingredientGroups[0]['items'][0]['ingredient']);
        $this->assertSame(1.0, $recipe->ingredientGroups[0]['items'][0]['quantity']);
        $this->assertSame('claude-opus-5', $recipe->model);
        $this->assertSame(1500, $recipe->inputTokens);

        $body = json_decode((string) $this->sent[0]['request']->getBody(), true);
        $this->assertSame('claude-opus-5', $body['model']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertStringContainsString('dl, kg, msk', $body['system']);
        $this->assertStringContainsString('"name":"Plankstek"', $body['messages'][0]['content'][0]['text']);
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $this->sent[0]['request']->getHeaderLine('anthropic-beta'));
    }

    public function test_the_schema_stays_within_the_apis_union_type_limit(): void
    {
        // The API rejects schemas with more than 16 nullable/union-typed fields.
        $this->assertLessThanOrEqual(16, substr_count(json_encode(ClaudeRecipeExtractor::schema()), '"anyOf"'));
    }

    public function test_a_source_without_a_recipe_fails_with_a_readable_message(): void
    {
        $this->expectException(RecipeImportException::class);

        $this->extractor(self::message(['is_recipe' => false] + array_fill_keys(array_keys(ClaudeRecipeExtractor::schema()['properties']), null)))
            ->extract(RecipeSource::fromText('Contact us'), ['dl']);
    }

    public function test_a_refusal_fails_the_import(): void
    {
        $this->expectException(RecipeImportException::class);

        $this->extractor(self::message(null, 'refusal'))->extract(RecipeSource::fromText('...'), ['dl']);
    }

    public function test_an_api_error_fails_with_a_readable_message(): void
    {
        $this->expectException(RecipeImportException::class);

        $this->extractor(new Response(529, ['Content-Type' => 'application/json'], '{"type":"error","error":{"type":"overloaded_error","message":"Overloaded"}}'))
            ->extract(RecipeSource::fromText('...'), ['dl']);
    }
}
