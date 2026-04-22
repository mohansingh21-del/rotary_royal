<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProjectController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of projects.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', null);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);
            $status = $request->input('status', null);
            $from_date = $request->input('from_date', null);
            $to_date = $request->input('to_date', null);

            $query = Project::with('images');

            if ($status) {
                $query->where('status', $status);
            }

            if ($from_date) {
                $query->where('start_date', '>=', $from_date);
            }

            if ($to_date) {
                $query->where('end_date', '<=', $to_date);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            $query->orderBy('created_at', 'DESC');

            if ($limit) {
                $projects = $query->orderBy('created_at', 'DESC')
                    ->paginate($limit, ['*'], 'page', $page);
                $projects->getCollection()->transform(function ($project) {
                    return $this->formatProject($project);
                });

                $response = [
                    'data' => $projects->items(),
                    'pagination' => [
                        'total' => $projects->total(),
                        'current_page' => $projects->currentPage(),
                        'per_page' => $projects->perPage(),
                        'last_page' => $projects->lastPage(),
                        'from' => $projects->firstItem(),
                        'to' => $projects->lastItem(),
                        'next_page_url' => $projects->nextPageUrl(),
                        'previous_page_url' => $projects->previousPageUrl(),
                    ]
                ];
            } else {
                $projects = $query->get()->map(function ($project) {
                    return $this->formatProject($project);
                });
                $response = [
                    'data' => $projects,
                    'pagination' => [
                        'total' => $projects->count(),
                        'current_page' => 1,
                        'per_page' => $projects->count(),
                        'last_page' => 1,
                        'from' => $projects->isEmpty() ? 0 : 1,
                        'to' => $projects->count(),
                        'next_page_url' => null,
                        'previous_page_url' => null,
                    ]
                ];
            }

            return response()->json([
                'status' => 200,
                'message' => 'Projects fetched successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Helper to format project data.
     */
    private function formatProject($project)
    {
        $project->banner_image = $project->banner_image ? asset($project->banner_image) : null;
        $project->images->transform(function ($image) {
            $image->image_path = asset($image->image_path);
            return $image;
        });
        return $project;
    }

    /**
     * Store or update a project.
     */
    public function store(Request $request)
    {
        $id = $request->input('id');
        $project = $id ? Project::findOrFail($id) : new Project();

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'banner_image' => ($id ? 'nullable' : 'required') . '|image|mimes:jpeg,png,jpg|max:5120|dimensions:ratio=2/1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_funding_available' => 'boolean',
            'status' => 'nullable|in:upcoming,ongoing,completed',
            'gallery_images' => 'nullable|array',
            'gallery_images.*' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value instanceof \Illuminate\Http\UploadedFile) {
                        $validator = \Illuminate\Support\Facades\Validator::make(
                            ['file' => $value],
                            ['file' => 'image|dimensions:ratio=1/1']
                        );
                        if ($validator->fails()) {
                            $fail("The gallery image must have an aspect ratio of 1:1.");
                        }
                    }
                }
            ],
        ];

        $request->validate($rules);

        DB::beginTransaction();
        try {
            $project->name = $request->name;
            $project->description = $request->description;
            $project->start_date = $request->start_date;
            $project->end_date = $request->end_date;
            $project->is_funding_available = $request->boolean('is_funding_available', false);
            $project->is_active = $request->input('is_active', 1) ? 1 : 0;

            if (now() < $request->start_date) {
                $project->status = 'upcoming';
            } else {
                $project->status = 'ongoing';
            }

            if (!$id) {
                $project->is_active = 1;
            }

            // Handle Banner Image
            if ($request->hasFile('banner_image')) {
                if ($id && $project->banner_image && file_exists(public_path($project->banner_image))) {
                    unlink(public_path($project->banner_image));
                }
                $file = $request->file('banner_image');
                $filename = $file->hashName();
                $file->move(public_path('projects'), $filename);
                $project->banner_image = '/projects/' . $filename;
            }

            $project->save();

            // Handle Gallery Images Syncing (Mixed: paths to keep + new files)
            $galleryInput = $request->input('gallery_images', []);
            $pathsToKeep = [];

            if (is_array($galleryInput)) {
                foreach ($galleryInput as $item) {
                    if (is_string($item) && !empty($item)) {
                        // Extract relative path if a full URL is sent
                        $relative = str_replace(url('/'), '', $item);
                        $pathsToKeep[] = $relative;
                    }
                }
            }

            if ($id) {
                $imagesToDelete = ProjectImage::where('project_id', $project->id)
                    ->whereNotIn('image_path', $pathsToKeep)
                    ->get();

                foreach ($imagesToDelete as $img) {
                    if (file_exists(public_path($img->image_path))) {
                        unlink(public_path($img->image_path));
                    }
                    $img->delete();
                }
            }

            // Handle new file uploads
            if ($request->hasFile('gallery_images')) {
                $files = $request->file('gallery_images');
                if (!is_array($files)) {
                    $files = [$files];
                }

                foreach ($files as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $filename = $file->hashName();
                        $file->move(public_path('projects/gallery'), $filename);
                        ProjectImage::create([
                            'project_id' => $project->id,
                            'image_path' => '/projects/gallery/' . $filename
                        ]);
                    }
                }
            }

            DB::commit();

            return $this->successResponse($this->formatProject($project->load('images')), $id ? 'Project updated successfully' : 'Project created successfully', $id ? 200 : 201);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified project.
     */
    public function show($id)
    {
        try {
            $project = Project::with(['images', 'donations'])->find($id);
            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }

            $totalFunding = $project->donations->sum('amount');

            $contributors = $project->donations->map(function ($donation) {
                return [
                    'donor_name' => $donation->donor_name,
                    'amount' => $donation->amount,
                    'date' => $donation->date,
                ];
            });

            $data = [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'banner_image' => $project->banner_image ? asset($project->banner_image) : null,
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'status' => $project->status,
                'is_active' => $project->is_active,
                'is_funding_available' => $project->is_funding_available,
                'total_funding' => $totalFunding,
                'gallery' => $project->images->map(function ($image) {
                    return asset($image->image_path);
                }),
                'contributors' => $contributors,
            ];

            return $this->successResponse($data, 'Project details retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }


    /**
     * Remove the specified project.
     */
    public function destroy($id)
    {
        try {
            $project = Project::with('images')->findOrFail($id);

            // Delete banner
            if ($project->banner_image && file_exists(public_path($project->banner_image))) {
                unlink(public_path($project->banner_image));
            }

            // Delete gallery images
            foreach ($project->images as $image) {
                if (file_exists(public_path($image->image_path))) {
                    unlink(public_path($image->image_path));
                }
            }

            $project->delete();

            return $this->successResponse(null, 'Project deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Toggle funding status.
     */
    public function toggleStatus($id)
    {
        try {
            $project = Project::findOrFail($id);
            $project->is_active = ($project->is_active == 1) ? 0 : 1;
            $project->save();

            return $this->successResponse($project, 'Status updated successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function publicProjects(Request $request)
    {
        try {
            $limit = $request->input('limit', null);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);
            $status = $request->input('status', null);

            $query = Project::with('images')->where('is_active', 1);

            if ($status) {
                $query->where('status', $status);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            $query->orderBy('created_at', 'DESC');

            if ($limit) {
                $projects = $query->paginate($limit, ['*'], 'page', $page);
                $projects->getCollection()->transform(function ($project) {
                    return $this->formatProject($project);
                });

                $response = [
                    'data' => $projects->items(),
                    'pagination' => [
                        'total' => $projects->total(),
                        'current_page' => $projects->currentPage(),
                        'per_page' => $projects->perPage(),
                        'last_page' => $projects->lastPage(),
                        'from' => $projects->firstItem(),
                        'to' => $projects->lastItem(),
                        'next_page_url' => $projects->nextPageUrl(),
                        'previous_page_url' => $projects->previousPageUrl(),
                    ]
                ];
            } else {
                $projects = $query->get()->map(function ($project) {
                    return $this->formatProject($project);
                });
                $response = [
                    'data' => $projects,
                    'pagination' => [
                        'total' => $projects->count(),
                        'current_page' => 1,
                        'per_page' => $projects->count(),
                        'last_page' => 1,
                        'from' => $projects->isEmpty() ? 0 : 1,
                        'to' => $projects->count(),
                        'next_page_url' => null,
                        'previous_page_url' => null,
                    ]
                ];
            }

            return response()->json([
                'status' => 200,
                'message' => 'Projects fetched successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Private override for pagination response if needed
     * Note: The index method already handles it, but successResponse is a bit different here.
     * I'll adjust index to match the format expected by the frontend (based on MembersController).
     */
}
