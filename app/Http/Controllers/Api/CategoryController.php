<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CategoryController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of categories.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);

            $query = Category::query();

            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            if ($limit) {
                $categories = $query->orderBy('name', 'ASC')
                    ->paginate($limit, ['*'], 'page', $page);

                $response = [
                    'data' => $categories->items(),
                    'pagination' => [
                        'total' => $categories->total(),
                        'current_page' => $categories->currentPage(),
                        'per_page' => $categories->perPage(),
                        'last_page' => $categories->lastPage(),
                        'from' => $categories->firstItem(),
                        'to' => $categories->lastItem(),
                        'next_page_url' => $categories->nextPageUrl(),
                        'previous_page_url' => $categories->previousPageUrl(),
                    ]
                ];
            } else {
                $categories = $query->orderBy('name', 'ASC')->get();
                $response = [
                    'data' => $categories,
                    'pagination' => [
                        'total' => $categories->count(),
                        'current_page' => 1,
                        'per_page' => $categories->count(),
                        'last_page' => 1,
                        'from' => $categories->isEmpty() ? 0 : 1,
                        'to' => $categories->count(),
                        'next_page_url' => null,
                        'previous_page_url' => null,
                    ]
                ];
            }

            return response()->json([
                'status' => 200,
                'message' => 'Categories retrieved successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store or update a category.
     */
    public function store(Request $request)
    {
        try {
            $id = $request->input('id');

            $request->validate([
                'id' => $id ? 'required|exists:categories,id' : 'nullable',
                'name' => 'required|string|max:255',
            ]);

            $category = $id ? Category::findOrFail($id) : new Category();

            $category->name = $request->name;
            if (!$id) {
                $category->is_active = 1;
            }

            $category->save();

            return $this->successResponse(
                $category,
                $id ? 'Category updated successfully' : 'Category created successfully',
                $id ? 200 : 201
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Category not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to save category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified category.
     */
    public function destroy($id)
    {
        try {
            $category = Category::findOrFail($id);
            // Check if it's being used by events
            if ($category->events()->exists()) {
                return $this->errorResponse('Category cannot be deleted as it is associated with events', 400);
            }
            $category->delete();
            return $this->successResponse(null, 'Category deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Category not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to delete category: ' . $e->getMessage(), 500);
        }
    }

    public function publicCategories()
    {
        try {
            $categories = Category::where('is_active', 1)->get();
            return $this->successResponse($categories, 'Categories retrieved successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch public categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle the status of a category.
     */
    public function toggleStatus($id)
    {
        try {
            $category = Category::findOrFail($id);
            $category->is_active = ($category->is_active == 1) ? 0 : 1;
            $category->save();

            return $this->successResponse($category, 'Status updated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Category not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to update status: ' . $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $category = Category::findOrFail($id);
            return $this->successResponse($category, 'Category retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Category not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to retrieve category: ' . $e->getMessage(), 500);
        }
    }
}
