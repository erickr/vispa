<?php

namespace Tests\Unit\Import;

use App\Recipes\Import\RecipeImportException;
use App\Recipes\Import\RecipePhotoReader;
use App\Recipes\Import\RecipeSource;
use Tests\TestCase;

class RecipePhotoReaderTest extends TestCase
{
    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** @return array{0: int, 1: int} */
    private function dimensions(RecipeSource $source): array
    {
        $image = imagecreatefromstring(base64_decode($source->content));

        return [imagesx($image), imagesy($image)];
    }

    public function test_a_photo_becomes_a_jpeg_image_source(): void
    {
        $source = (new RecipePhotoReader)->read($this->png(800, 600));

        $this->assertSame(RecipeSource::KIND_IMAGE, $source->kind);
        $this->assertSame('image/jpeg', $source->mediaType);
        $this->assertSame([800, 600], $this->dimensions($source));
        $this->assertSame('image', $source->contentBlocks()[0]['type']);
    }

    public function test_a_large_photo_is_scaled_down_to_the_longest_edge_the_model_reads(): void
    {
        $source = (new RecipePhotoReader)->read($this->png(3000, 4000));

        $this->assertSame([1932, RecipePhotoReader::MAX_EDGE], $this->dimensions($source));
    }

    public function test_bytes_that_are_no_image_are_refused(): void
    {
        $this->expectException(RecipeImportException::class);

        (new RecipePhotoReader)->read('not an image');
    }
}
