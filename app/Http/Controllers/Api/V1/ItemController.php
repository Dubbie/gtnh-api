<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListItemsRequest;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ItemController extends Controller
{
    protected $itemService;

    public function __construct(ItemService $itemService)
    {
        $this->itemService = $itemService;
    }

    /**
     * Index
     *
     * Returns a paginated list of items
     *
     * @response array{
     *     data: list<\App\Http\Resources\ItemResource>,
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
     * @unauthenticated
     */
    public function index(ListItemsRequest $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 15);
        $items = $this->itemService->getItems($perPage);
        return ItemResource::collection($items);
    }

    /**
     * Store
     *
     * Creates a new item in storage.
     */
    public function store(StoreItemRequest $request): ItemResource
    {
        $item = $this->itemService->createItem($request->validated());

        return (new ItemResource($item));
    }

    /**
     * Show
     *
     * Displays the item.
     * @unauthenticated
     */
    public function show(int $id): ItemResource
    {
        $item = $this->itemService->findItemById($id);
        return new ItemResource($item);
    }

    /**
     * Update
     *
     * Updates the item in storage.
     */
    public function update(UpdateItemRequest $request, int $id): ItemResource
    {
        Log::info($request->all());
        $item = $this->itemService->updateItem($id, $request->validated());
        return new ItemResource($item);
    }

    /**
     * Destroy
     *
     * Removes the item from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->itemService->deleteItem($id);
        // Return 204 No Content on successful deletion
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
