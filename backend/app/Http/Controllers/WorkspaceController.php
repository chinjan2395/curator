<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $workspaces = $request->user()
            ->workspaces()
            ->orderBy('name')
            ->get();

        return response()->json($workspaces);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace = $request->user()->workspaces()->create([
            'name' => $validated['name'],
        ]);

        return response()->json($workspace, 201);
    }

    public function show(Request $request, Workspace $workspace)
    {
        if ($workspace->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($workspace);
    }

    public function update(Request $request, Workspace $workspace)
    {
        if ($workspace->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace->update($validated);

        return response()->json($workspace);
    }

    public function destroy(Request $request, Workspace $workspace)
    {
        if ($workspace->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $workspace->delete();

        return response()->json(null, 204);
    }
}
