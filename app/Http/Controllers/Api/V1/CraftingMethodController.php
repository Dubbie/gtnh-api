<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListCraftingMethodsRequest;
use App\Http\Requests\StoreCraftingMethodRequest;
use App\Http\Requests\UpdateCraftingMethodRequest;
use App\Http\Resources\CraftingMethodResource;
use App\Services\CraftingMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CraftingMethodController extends Controller
{
    protected $craftingMethodService;

    public function __construct(CraftingMethodService $craftingMethodService)
    {
        $this->craftingMethodService = $craftingMethodService;
    }

    /**
     * Index
     *
     * Returns a paginated list of crafting methods
     *
     * @response array{
     *     data: list<\App\Http\Resources\CraftingMethodResource>,
     *     links: array{
     *         first: string,
     *         last: string,
     *         prev: string,
     *         next: string
     *     },
     *     meta: array{
     *         current_page: integer,
     *         from: integer,
     *         last_page: integer,
     *         links: list<array{url: ?string, label: string, active: boolean}>,
     *         path: string,
     *         per_page: integer,
     *         to: integer,
     *         total: integer
     *     }
     * }
     */
    public function index(ListCraftingMethodsRequest $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 15);
        $craftingMethods = $this->craftingMethodService->getCraftingMethods($perPage);
        return CraftingMethodResource::collection($craftingMethods);
    }

    /**
     * Store
     *
     * Creates a new crafting method in storage.
     */
    public function store(StoreCraftingMethodRequest $request): CraftingMethodResource
    {
        $craftingMethod = $this->craftingMethodService->createCraftingMethod($request->validated());

        return (new CraftingMethodResource($craftingMethod));
    }

    /**
     * Show
     *
     * Displays the crafting resource.
     */
    public function show(int $id): CraftingMethodResource
    {
        $craftingMethod = $this->craftingMethodService->findCraftingMethodById($id);
        return new CraftingMethodResource($craftingMethod);
    }

    /**
     * Update
     *
     * Updates the crafting method in storage.
     */
    public function update(UpdateCraftingMethodRequest $request, int $id): CraftingMethodResource
    {
        Log::info($request->all());
        $craftingMethod = $this->craftingMethodService->updateCraftingMethod($id, $request->validated());
        return new CraftingMethodResource($craftingMethod);
    }

    /**
     * Destroy
     *
     * Removes the crafting method from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->craftingMethodService->deleteCraftingMethod($id);
        // Return 204 No Content on successful deletion
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
