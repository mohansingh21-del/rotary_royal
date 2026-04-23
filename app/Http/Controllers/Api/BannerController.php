<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BannerController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of banners for admin.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);

            $query = Banner::query();

            if ($search) {
                $query->where('title', 'like', '%' . $search . '%');
            }

            if ($limit) {
                $banners = $query->orderBy('order', 'ASC')
                    ->orderBy('created_at', 'DESC')
                    ->paginate($limit, ['*'], 'page', $page);

                $banners->getCollection()->transform(function ($banner) {
                    return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'link' => $banner->link,
                    'order' => $banner->order,
                    'image' => $banner->image,
                    'status' => $banner->status
                    ];
                });

                $response = [
                    'data' => $banners->items(),
                    'pagination' => [
                        'total' => $banners->total(),
                        'current_page' => $banners->currentPage(),
                        'per_page' => $banners->perPage(),
                        'last_page' => $banners->lastPage(),
                        'from' => $banners->firstItem(),
                        'to' => $banners->lastItem(),
                        'next_page_url' => $banners->nextPageUrl(),
                        'previous_page_url' => $banners->previousPageUrl(),
                    ]
                ];
            }
            else {
                $banners = $query->orderBy('order', 'ASC')
                    ->orderBy('created_at', 'DESC')
                    ->get();

                $banners->transform(function ($banner) {
                    return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'link' => $banner->link,
                    'order' => $banner->order,
                    'image' => $banner->image,
                    'status' => $banner->status
                    ];
                });

                $response = [
                    'data' => $banners,
                    'pagination' => [
                        'total' => $banners->count(),
                        'current_page' => 1,
                        'per_page' => $banners->count(),
                        'last_page' => 1,
                        'from' => $banners->isEmpty() ? 0 : 1,
                        'to' => $banners->count(),
                        'next_page_url' => null,
                        'previous_page_url' => null,
                    ]
                ];
            }

            return response()->json([
                'status' => 200,
                'message' => 'Banners retrieved successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store or update a banner.
     */
    public function store(Request $request)
    {
        $id = $request->input('id');

        $rules = [
            'title' => 'nullable|string|max:255',
            'link' => 'nullable|url|max:255',
            'order' => 'nullable|integer',
            'image' => ($id ? 'nullable' : 'required') . '|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ];

        try {
            $validated = $request->validate($rules);

            $banner = $id ?Banner::findOrFail($id) : new Banner();

            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($id && $banner->image && file_exists(public_path($banner->image))) {
                    @unlink(public_path($banner->image));
                }

                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('banners'), $filename);
                $banner->image = '/banners/' . $filename;
            }

            $banner->title = $request->input('title');
            $banner->link = $request->input('link');
            $banner->order = $request->input('order', 0);
            $banner->save();

            return $this->successResponse($banner, 'Banner saved successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Toggle the status of a banner.
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $banner = Banner::findOrFail($id);
            $banner->status = ($banner->status == 1) ? 0 : 1;
            $banner->save();

            return $this->successResponse($banner, 'Banner status updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified banner.
     */
    public function destroy($id)
    {
        try {
            $banner = Banner::findOrFail($id);
            if ($banner->image && file_exists(public_path($banner->image))) {
                @unlink(public_path($banner->image));
            }
            $banner->delete();

            return $this->successResponse(null, 'Banner deleted successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Fetch active banners for the user side.
     */
    public function publicBanners()
    {
        try {
            $banners = Banner::where('status', 1)
                ->orderBy('order', 'ASC')
                ->orderBy('created_at', 'DESC')
                ->get();

            return $this->successResponse($banners, 'Active banners retrieved successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}