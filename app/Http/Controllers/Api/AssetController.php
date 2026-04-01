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
        $this->refreshAssetAvailability();

        try {
            $limit = $request->input('limit', null);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);

            $query = Asset::query();

            if ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%');
            }

            if ($limit) {
                $assets = $query->orderBy('id', 'DESC')
                    ->paginate($limit, ['*'], 'page', $page);

                $assets->getCollection()->transform(function ($asset) {
                    return [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'category' => $asset->category,
                    'quantity' => $asset->quantity,
                    'buffer_time' => $asset->buffer_time,
                    'price' => $asset->price,
                    'image' => $asset->image,
                    'status' => $asset->status
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
                    ]
                ];
            }
            else {
                $assets = $query->orderBy('id', 'DESC')->get();
                $assets->transform(function ($asset) {
                    return [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'category' => $asset->category,
                    'quantity' => $asset->quantity,
                    'buffer_time' => $asset->buffer_time,
                    'price' => $asset->price,
                    'image' => $asset->image,
                    'status' => $asset->status
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
                    ]
                ];
            }

            return response()->json([
                'status' => 200,
                'message' => 'Assets retrieved successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);

        }
        catch (Exception $e) {
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
            'image' => ($id ? 'nullable' : 'required') . '|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048|dimensions:width=48,height=48',
        ];

        $validated = $request->validate(
            $id ? array_merge(['id' => 'required|exists:assets,id'], $rules) : $rules,
            [
                'name.unique' => 'An asset with this name and category already exists.',
                'quantity.min' => 'Quantity must be greater than 0.',
            ]
        );

        $asset = $request->id ?Asset::findOrFail($request->id) : new Asset();

        if ($request->hasFile('image')) {
            if ($request->id && file_exists(public_path($asset->image))) {
                unlink(public_path($asset->image));
            }

            $file = $request->file('image');
            $filename = $file->hashName();
            $file->move(public_path('assets'), $filename);

            $asset->image = '/assets/' . $filename;
        }

        $asset->name = $validated['name'];
        $asset->category = $validated['category'];
        $asset->quantity = $validated['quantity'];
        $asset->buffer_time = $validated['buffer_time'];
        $asset->price = $validated['price'];
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
            $asset->status = !$asset->status;
            $asset->save();

            return $this->successResponse($asset, 'Asset status updated successfully');
        }
        catch (Exception $e) {
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
            $asset->delete();

            return $this->successResponse(null, 'Asset deleted successfully');
        }
        catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
    /**
     * Refresh asset availability based on current bookings.
     */
    protected function refreshAssetAvailability()
    {
        $now = now();

        // 1. Auto-complete bookings where end_date + buffer_time has passed
        $bookings = Booking::where('status', 'Approved')->with('asset')->get();
        foreach ($bookings as $booking) {
            $bufferHours = $booking->asset->buffer_time ?? 0;
            $completionDate = $booking->end_date->addHours($bufferHours);
            if ($now->greaterThan($completionDate)) {
                $booking->update(['status' => 'Completed']);
            }
        }

        // 2. Update asset statuses based on approved bookings vs quantity
        $assets = Asset::all();
        foreach ($assets as $asset) {
            $bookedCount = Booking::where('asset_id', $asset->id)
                ->where('status', 'Approved')
                ->count();

            // 1 for available, 0 for unavailable
            $newStatus = ($bookedCount < $asset->quantity) ? 1 : 0;

            if ($asset->status != $newStatus) {
                $asset->update(['status' => $newStatus]);
            }
        }
    }
}
