<?php

namespace App\Recipes\Import;

use RuntimeException;

/** An import failure whose message is written for the user and shown on the status page. */
class RecipeImportException extends RuntimeException {}
