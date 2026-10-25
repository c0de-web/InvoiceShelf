<?php

namespace App\Http\Controllers\V1\Admin\Item;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemCategoryRequest;
use App\Http\Resources\ItemCategoryResource;
use App\Models\ItemCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ItemCategoriesController extends Controller
{
    /**
     * Every category of the company: top-level ones first, then by name.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ItemCategory::class);

        $categories = ItemCategory::whereCompany()
            ->with('parent')
            ->withCount('items')
            ->orderByRaw('parent_id is not null')
            ->orderBy('name')
            ->get();

        return ItemCategoryResource::collection($categories);
    }

    public function store(ItemCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', ItemCategory::class);

        $category = ItemCategory::create($request->getItemCategoryPayload());

        return (new ItemCategoryResource($category->loadCount('items')))->response()->setStatusCode(201);
    }

    public function show(ItemCategory $itemCategory): ItemCategoryResource
    {
        $this->authorize('view', $itemCategory);

        return new ItemCategoryResource($itemCategory->loadCount('items'));
    }

    public function update(ItemCategoryRequest $request, ItemCategory $itemCategory): ItemCategoryResource
    {
        $this->authorize('update', $itemCategory);

        $itemCategory->update($request->getItemCategoryPayload());

        return new ItemCategoryResource($itemCategory->loadCount('items'));
    }

    /**
     * Deleting is refused while items or subcategories are filed under it; they're never deleted along with it.
     */
    public function destroy(ItemCategory $itemCategory): JsonResponse
    {
        $this->authorize('delete', $itemCategory);

        $items = $itemCategory->items()->count();
        $children = $itemCategory->children()->count();

        if ($items || $children) {
            return response()->json([
                'message' => 'Move its '.trans_choice('{0} no items|{1} 1 item|[2,*] :count items', $items)
                    .' and '.trans_choice('{0} no subcategories|{1} 1 subcategory|[2,*] :count subcategories', $children)
                    .' elsewhere first.',
                'items_count' => $items,
                'children_count' => $children,
            ], 422);
        }

        $itemCategory->delete();

        return response()->json(['success' => true]);
    }
}
