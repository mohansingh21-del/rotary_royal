<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Traits\ApiResponse;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Exception;

class AssetController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of assets with search and pagination.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', null);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);

            $query = Asset::withCount([
                'bookings as approved_bookings_count' => function ($q) {
                    $q->where('status', 'Approved');
                }
            ]);

            if ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%');
            }

            if ($limit) {
                $assets = $query->orderBy('created_at', 'DESC')
                    ->paginate($limit, ['*'], 'page', $page);

                $assets->getCollection()->transform(function ($asset) {
                    return [
                        'id' => $asset->id,
                        'name' => $asset->name,
                        'category' => $asset->category,
                        'quantity' => $asset->quantity,
                        'left_quantity' => $asset->left_quantity,
                        'buffer_time' => $asset->buffer_time,
                        'price' => $asset->price,
                        'image' => $asset->image,
                        'status' => ($asset->left_quantity === 0) ? 0 : $asset->status
                    ];
                });

                $response = [
                    'data' => $assets->items(),
                    'pagination' => [
                        'total' => $assets->total(),
                        'current_page' => $assets->currentPage(),
                        'per_page' => $assets->perPage(),
                        'last_page' => $assets->lastPage(),
                        'from' => $assets->firstItem(),
                        'to' => $assets->lastItem(),
                        'next_page_url' => $assets->nextPageUrl(),
                        'previous_page_url' => $assets->previousPageUrl(),
                    ]
                ];
            } else {
                $assets = $query->orderBy('created_at', 'DESC')->get();
                $assets->transform(function ($asset) {
                    return [
                        'id' => $asset->id,
                        'name' => $asset->name,
                        'category' => $asset->category,
                        'quantity' => $asset->quantity,
                        'left_quantity' => $asset->left_quantity,
                        'buffer_time' => $asset->buffer_time,
                        'price' => $asset->price,
                        'image' => $asset->image,
                        'status' => ($asset->left_quantity === 0) ? 0 : $asset->status
                    ];
                });
                $response = [
                    'data' => $assets,
                    'pagination' => [
                        'total' => $assets->count(),
                        'current_page' => 1,
                        'per_page' => $assets->count(),
                        'last_page' => 1,
                        'from' => $assets->isEmpty() ? 0 : 1,
                        'to' => $assets->count(),
                        'next_page_url' => null,
                        'previous_page_url' => null,
                    ]
                ];
            }

            return response()->json([
                'status' => 200,
                'message' => 'Assets retrieved successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

    }

    public function store(Request $request)
    {
        $id = $request->input('id');

        $request->merge([
            'name' => trim((string) $request->input('name', '')),
        ]);

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('assets')
                    ->ignore($id)
                    ->where(function ($query) use ($request) {
                        return $query
                            ->where('category', $request->input('category'))
                            ->whereNull('deleted_at');
                    }),
            ],
            'category' => 'required|in:Asset,Consumable',
            'quantity' => 'required|integer|min:1',
            'buffer_time' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
            'image' => ($id ? 'nullable' : 'required') . '|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120|dimensions:width=64,height=64',
        ];

        $validated = $request->validate(
            $id ? array_merge(['id' => 'required|exists:assets,id'], $rules) : $rules,
            [
                'name.unique' => 'An asset with this name and category already exists.',
                'quantity.min' => 'Quantity must be greater than 0.',
            ]
        );

        $asset = $request->id ? Asset::findOrFail($request->id) : new Asset();

        if ($request->hasFile('image')) {
            if ($request->id && $asset->image && file_exists(public_path($asset->image))) {
                unlink(public_path($asset->image));
            }

            $file = $request->file('image');
            $filename = $file->hashName();
            $file->move(public_path('inventory_assets'), $filename);

            $asset->image = '/inventory_assets/' . $filename;
        }

        $asset->name = $validated['name'];
        $asset->category = $validated['category'];
        $asset->quantity = $validated['quantity'];
        $asset->buffer_time = $validated['buffer_time'];
        $asset->price = $validated['price'];

        // Recalculate left_quantity based on approved bookings
        $approvedCount = $asset->id
            ? Booking::where('asset_id', $asset->id)->where('status', 'Approved')->count()
            : 0;
        $asset->left_quantity = max(0, $validated['quantity'] - $approvedCount);

        // Auto-enable asset if stock is now available
        if ($asset->left_quantity > 0) {
            $asset->status = 1;
        }

        $asset->save();

        return response()->json([
            'status' => 200,
            'message' => 'Asset saved successfully',
            'data' => $asset
        ]);
    }


    /**
     * Toggle the status of an asset.
     */
    public function toggleStatus(Request $request, $id)
    {

        try {
            $asset = Asset::findOrFail($id);

            // Block enabling if no stock is left
            if (!$asset->status && $asset->left_quantity === 0) {
                return $this->errorResponse('Cannot mark as available: no stock left (left_quantity is 0)', 422);
            }

            $asset->status = (int) (!$asset->status);
            $asset->save();

            return $this->successResponse($asset, 'Asset status updated successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified asset.
     */
    public function destroy($id)
    {
        try {
            $asset = Asset::findOrFail($id);

            $is_mapped = Booking::where('asset_id', $id)->exists();

            if ($is_mapped) {
                return $this->errorResponse('Cannot be deleted, Asset is mapped to a booking', 422);
            }
            $asset->delete();

            return $this->successResponse(null, 'Asset deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}