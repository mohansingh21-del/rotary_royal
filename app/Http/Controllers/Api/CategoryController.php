<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;

class CategoryController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of categories.
     */
    public function index(Request $request)
    {
        try {
            $categories = Category::get();
            return $this->successResponse($categories, 'Categories retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store or update a category.
     */
    public function store(Request $request)
    {
        $id = $request->input('id');

        $request->validate([
            'id' => $id ? 'required|exists:categories,id' : 'nullable',
            'name' => 'required|string|max:255',
        ]);

        try {
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
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
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
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function publicCategories()
    {
        try {
            $categories = Category::where('is_active', 1)->get();
            return $this->successResponse($categories, 'Categories retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
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
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $category = Category::findOrFail($id);
            return $this->successResponse($category, 'Category retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
