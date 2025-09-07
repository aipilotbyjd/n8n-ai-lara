<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizations = $request->user()
            ->organizations()
            ->with(['owner', 'teams'])
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => OrganizationResource::collection($organizations),
            'current_page' => $organizations->currentPage(),
            'per_page' => $organizations->perPage(),
            'total' => $organizations->total(),
        ]);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = Organization::create([
            'name' => $request->name,
            'slug' => $request->slug ?? \Str::slug($request->name),
            'description' => $request->description,
            'owner_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new OrganizationResource($organization->load(['owner', 'teams'])),
            'message' => 'Organization created successfully',
        ], Response::HTTP_CREATED);
    }

    public function show(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return response()->json([
            'data' => new OrganizationResource($organization->load(['owner', 'teams', 'workflows'])),
        ]);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        $organization->update($request->validated());

        return response()->json([
            'data' => new OrganizationResource($organization->fresh(['owner', 'teams'])),
            'message' => 'Organization updated successfully',
        ]);
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
