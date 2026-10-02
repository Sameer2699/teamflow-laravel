<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function show(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        return response()->json([
            'project' => $project,
        ]);
    }
}