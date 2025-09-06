<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @OA\Tag(
 *     name="Organizations",
 *     description="Organization management endpoints"
 * )
 */
class OrganizationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/organizations",
     *     summary="List organizations",
     *     description="Get a list of organizations the authenticated user belongs to",
     *     operationId="getOrganizations",
     *     tags={"Organizations"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organizations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Organization")),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="total", type="integer", example=25)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/organizations",
     *     summary="Create organization",
     *     description="Create a new organization",
     *     operationId="createOrganization",
     *     tags={"Organizations"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="My Company", description="Organization name"),
     *             @OA\Property(property="description", type="string", example="Company description", description="Organization description"),
     *             @OA\Property(property="slug", type="string", example="my-company", description="URL-friendly identifier")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Organization created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Organization"),
     *             @OA\Property(property="message", type="string", example="Organization created successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/organizations/{organization}",
     *     summary="Get organization",
     *     description="Get a specific organization by ID",
     *     operationId="getOrganization",
     *     tags={"Organizations"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Organization")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Organization not found")
     *         )
     *     )
     * )
     */
    public function show(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return response()->json([
            'data' => new OrganizationResource($organization->load(['owner', 'teams', 'workflows'])),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/organizations/{organization}",
     *     summary="Update organization",
     *     description="Update an existing organization",
     *     operationId="updateOrganization",
     *     tags={"Organizations"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Company Name"),
     *             @OA\Property(property="description", type="string", example="Updated description"),
     *             @OA\Property(property="slug", type="string", example="updated-company")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Organization"),
     *             @OA\Property(property="message", type="string", example="Organization updated successfully")
     *         )
     *     )
     * )
     */
    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        $organization->update($request->validated());

        return response()->json([
            'data' => new OrganizationResource($organization->fresh(['owner', 'teams'])),
            'message' => 'Organization updated successfully',
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/organizations/{organization}",
     *     summary="Delete organization",
     *     description="Delete an organization",
     *     operationId="deleteOrganization",
     *     tags={"Organizations"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Organization deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     )
     * )
     */
    public function destroy(Organization $organization): JsonResponse
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
