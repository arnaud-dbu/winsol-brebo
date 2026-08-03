<?php

namespace App\Http\Controllers\Inspace;

use App\Http\Controllers\Controller;
use App\Inspace\SchemaBuilder;
use Illuminate\Http\JsonResponse;

class SchemaController extends Controller
{
    public function __invoke(SchemaBuilder $schema): JsonResponse
    {
        return response()->json($schema->build());
    }
}
