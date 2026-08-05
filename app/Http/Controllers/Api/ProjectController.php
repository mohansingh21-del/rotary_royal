<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\User;
use App\Notifications\ProjectAdminNotification;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
                $this->applyStatusFilter($query, $status);
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

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch projects: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper to format project data.
     */
    private function formatProject($project)
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'banner_image' => $project->banner_image ? asset($project->banner_image) : null,
            'start_date' => $project->start_date,
            'end_date' => $project->end_date,
            'status' => $this->resolveProjectStatus($project),
            'is_active' => $project->is_active,
            'is_funding_available' => $project->is_funding_available,
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
            'images' => $project->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'project_id' => $image->project_id,
                    'image_path' => asset($image->image_path),
                    'created_at' => $image->created_at,
                    'updated_at' => $image->updated_at,
                ];
            })->values(),
        ];
    }

    private function resolveProjectStatus(Project $project): string
    {
        $today = Carbon::today();
        $startDate = $project->start_date instanceof Carbon ? $project->start_date->copy()->startOfDay() : Carbon::parse($project->start_date)->startOfDay();
        $endDate = $project->end_date instanceof Carbon ? $project->end_date->copy()->endOfDay() : Carbon::parse($project->end_date)->endOfDay();

        if ($today->lt($startDate)) {
            return 'upcoming';
        }

        if ($today->gt($endDate)) {
            return 'completed';
        }

        return 'ongoing';
    }

    private function applyStatusFilter($query, string $status): void
    {
        $today = Carbon::today()->toDateString();

        if ($status === 'upcoming') {
            $query->whereDate('start_date', '>', $today);
            return;
        }

        if ($status === 'completed') {
            $query->whereDate('end_date', '<', $today);
            return;
        }

        if ($status === 'ongoing') {
            $query->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today);
        }
    }

    /**
     * Store or update a project.
     */
    public function store(Request $request)
    {
        try {
            $id = $request->input('id');
            $project = $id ? Project::findOrFail($id) : new Project();

            $rules = [
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'banner_image' => ($id ? 'nullable' : 'required') . '|image|mimes:jpeg,png,jpg|max:5120|dimensions:ratio=2/1',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'is_funding_available' => 'boolean',
                'gallery_images' => 'nullable|array|max:6',
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

            $project->name = $request->name;
            $project->description = $request->description;
            $project->start_date = $request->start_date;
            $project->end_date = $request->end_date;
            $project->is_funding_available = $request->boolean('is_funding_available', false);
            $project->is_active = $request->input('is_active', 1) ? 1 : 0;

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

            // Handle Gallery Images Syncing
            $galleryInput = $request->input('gallery_images', []);
            $pathsToKeep = [];

            if (is_array($galleryInput)) {
                foreach ($galleryInput as $item) {
                    if (is_string($item) && !empty($item)) {
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

            Notification::send(
                User::whereIn('role', ['Member', 'Non-Member'])->where('status', 1)->get(),
                new ProjectAdminNotification($project->fresh(), $id ? 'updated' : 'created')
            );

            return $this->successResponse($this->formatProject($project->load('images')), $id ? 'Project updated successfully' : 'Project created successfully', $id ? 200 : 201);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->errorResponse('Project not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to save project: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified project.
     */
    public function show($id)
    {
        try {
            // Funding totals and the contributor list are real money — a seeded
            // review donation must never inflate them, not even for admins.
            $project = Project::with([
                'images',
                'donations' => function ($query) {
                    $query->realOnly();
                },
            ])->findOrFail($id);

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
                'status' => $this->resolveProjectStatus($project),
                'is_active' => $project->is_active,
                'is_funding_available' => $project->is_funding_available,
                'total_funding' => $totalFunding,
                'gallery' => $project->images->map(function ($image) {
                    return asset($image->image_path);
                }),
                'contributors' => $contributors,
            ];

            return $this->successResponse($data, 'Project details retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to retrieve project details: ' . $e->getMessage(), 500);
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
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to delete project: ' . $e->getMessage(), 500);
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

            Notification::send(
                User::whereIn('role', ['Member', 'Non-Member'])->where('status', 1)->get(),
                new ProjectAdminNotification($project, $project->is_active ? 'activated' : 'deactivated')
            );

            return $this->successResponse($this->formatProject($project->load('images')), 'Status updated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to update status: ' . $e->getMessage(), 500);
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
                $this->applyStatusFilter($query, $status);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            $query->orderBy('start_date', 'DESC')->orderBy('end_date', 'DESC');

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

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch public projects: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Private override for pagination response if needed
     * Note: The index method already handles it, but successResponse is a bit different here.
     * I'll adjust index to match the format expected by the frontend (based on MembersController).
     */
}
