<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventImage;
use App\Models\User;
use App\Notifications\EventAdminNotification;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Controller orchestrating community Events.
 * Coordinates geographical pins, media galleries, and strictly schedules recurring/one-off events.
 */
class EventController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of events.
     */
    /**
     * Securely yield paginated internal Event matrices customized explicitly via Category bounds.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', null);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);
            $category_id = $request->input('category_id', null);
            $from_date = $request->input('from_date', null);
            $to_date = $request->input('to_date', null);
            $event = $request->input('event', null);

            $query = Event::with(['images', 'category']);

            if ($event == "upcoming") {
                $query->where('date', '>=', date('Y-m-d'));
            } elseif ($event == "past") {
                $query->where('date', '<', date('Y-m-d'));
            }

            if ($category_id) {
                $query->where('category_id', $category_id);
            }

            if ($from_date) {
                $query->where('date', '>=', $from_date);
            }

            if ($to_date) {
                $query->where('date', '<=', $to_date);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('location', 'like', '%' . $search . '%');
                });
            }

            $query->orderBy('date', 'ASC');

            if ($limit) {
                $events = $query->paginate($limit, ['*'], 'page', $page);
                $events->getCollection()->transform(function ($event) {
                    return $this->formatEvent($event);
                });

                $response = [
                    'data' => $events->items(),
                    'pagination' => [
                        'total' => $events->total(),
                        'current_page' => $events->currentPage(),
                        'per_page' => $events->perPage(),
                        'last_page' => $events->lastPage(),
                        'from' => $events->firstItem(),
                        'to' => $events->lastItem(),
                        'next_page_url' => $events->nextPageUrl(),
                        'previous_page_url' => $events->previousPageUrl(),
                    ]
                ];
            } else {
                $events = $query->get()->map(function ($event) {
                    return $this->formatEvent($event);
                });

                $response = [
                    'data' => $events,
                    'pagination' => [
                        'total' => $events->count(),
                        'current_page' => 1,
                        'per_page' => $events->count(),
                        'last_page' => 1,
                        'from' => $events->isEmpty() ? 0 : 1,
                        'to' => $events->count(),
                        'next_page_url' => null,
                        'previous_page_url' => null,
                    ]
                ];
            }

            return response()->json([
                'status' => 200,
                'message' => 'Events fetched successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Operation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper to format event data.
     */
    private function formatEvent($event)
    {
        $event->banner_image = $event->banner_image ? asset($event->banner_image) : null;
        $event->images->transform(function ($image) {
            $image->image_path = asset($image->image_path);
            return $image;
        });

        // Ensure category is loaded if it exists
        if ($event->category) {
            $event->category_name = $event->category->name;
        }

        return $event;
    }

    /**
     * Store or update an event.
     */
    /**
     * Process robust form-data payloads attaching unified Map coordinates and Event Gallery images.
     * Dynamically cleans up ghost files preventing local storage expansion constraints.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $id = $request->input('id');
            $event = $id ? Event::findOrFail($id) : new Event();

            $rules = [
                'name' => 'required|string|max:255',
                'date' => 'required|date',
                'time' => 'required',
                'location' => 'required|string|max:255',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'category_id' => 'nullable|exists:categories,id',
                'description' => 'nullable|string',
                'banner_image' => ($id ? 'nullable' : 'required') . '|image|mimes:jpeg,png,jpg|max:5120|dimensions:ratio=2/1',
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
                                $fail('The gallery image must have an aspect ratio of 1:1.');
                            }
                        }
                    }
                ],
            ];

            $request->validate($rules);

            DB::beginTransaction();
            $event->name = $request->name;
            $event->date = $request->date;
            $event->time = $request->time;
            $event->location = $request->location;
            $event->latitude = $request->latitude;
            $event->longitude = $request->longitude;
            $event->category_id = $request->category_id;
            $event->description = $request->description;

            if (!$id) {
                $event->is_active = 1;
            }

            // Handle Banner Image
            if ($request->hasFile('banner_image')) {
                if ($id && $event->banner_image && file_exists(public_path($event->banner_image))) {
                    unlink(public_path($event->banner_image));
                }
                $file = $request->file('banner_image');
                $filename = $file->hashName();
                $file->move(public_path('events'), $filename);
                $event->banner_image = '/events/' . $filename;
            }

            $event->save();

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
                $imagesToDelete = EventImage::where('event_id', $event->id)
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
                        $file->move(public_path('events/gallery'), $filename);
                        EventImage::create([
                            'event_id' => $event->id,
                            'image_path' => '/events/gallery/' . $filename
                        ]);
                    }
                }
            }

            DB::commit();

            Notification::send(
                User::whereIn('role', ['Member', 'Non-Member'])->where('status', 1)->get(),
                new EventAdminNotification($event->fresh(), $id ? 'updated' : 'created')
            );

            if ($id) {
                return $this->successResponse($this->formatEvent($event->load(['images', 'category'])), 'Event updated successfully');
            } else {
                return $this->createdResponse($this->formatEvent($event->load(['images', 'category'])), 'Event created successfully');
            }

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->notFoundResponse('Event not found');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to save event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified event.
     */
    /**
     * Yield explicit geographical and gallery bounds for a hyper-targeted Event ID.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $event = Event::with(['images', 'category'])->find($id);
            if (!$event) {
                return $this->notFoundResponse('Event not found');
            }

            $data = [
                'id' => $event->id,
                'name' => $event->name,
                'description' => $event->description,
                'banner_image' => $event->banner_image ? asset($event->banner_image) : null,
                'date' => $event->date,
                'time' => $event->time,
                'location' => $event->location,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'category_id' => $event->category_id,
                'category_name' => $event->category ? $event->category->name : null,
                'is_active' => $event->is_active,
                'gallery' => $event->images->map(function ($image) {
                    return asset($image->image_path);
                }),
            ];

            return $this->successResponse($data, 'Event details retrieved successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Operation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified event.
     */
    /**
     * Deeply terminate an Event explicitly severing physical banner mappings ensuring absolute storage optimization.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $event = Event::with('images')->findOrFail($id);

            // Delete banner
            if ($event->banner_image && file_exists(public_path($event->banner_image))) {
                unlink(public_path($event->banner_image));
            }

            // Delete gallery images
            foreach ($event->images as $image) {
                if (file_exists(public_path($image->image_path))) {
                    unlink(public_path($image->image_path));
                }
            }

            $event->delete();

            return $this->successResponse(null, 'Event deleted successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Operation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle is_active status.
     */
    /**
     * Safely flip Public visibility booleans dynamically for quick Mobile App feed refreshes.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus($id)
    {
        try {
            $event = Event::findOrFail($id);
            $event->is_active = ($event->is_active == 1) ? 0 : 1;
            $event->save();

            Notification::send(
                User::whereIn('role', ['Member', 'Non-Member'])->where('status', 1)->get(),
                new EventAdminNotification($event, $event->is_active ? 'activated' : 'deactivated')
            );

            return $this->successResponse($event, 'Status updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Operation failed: ' . $e->getMessage(), 500);
        }
    }
    /**
     * Render the strictly explicit Active Events mapping natively for unauthenticated public mobile access.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function publicEvents(Request $request)
    {
        try {
            $limit = $request->input('limit', null);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);
            $category_id = $request->input('category_id', null);
            $from_date = $request->input('from_date', null);
            $to_date = $request->input('to_date', null);
            $event = $request->input('event', null);

            $query = Event::with(['images', 'category'])->where('is_active', 1);

            if ($event == "upcoming") {
                $query->where('date', '>=', date('Y-m-d'));
            } elseif ($event == "past") {
                $query->where('date', '<', date('Y-m-d'));
            }

            if ($category_id) {
                $query->where('category_id', $category_id);
            }

            if ($from_date) {
                $query->where('date', '>=', $from_date);
            }

            if ($to_date) {
                $query->where('date', '<=', $to_date);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('location', 'like', '%' . $search . '%');
                });
            }

            $query->orderBy('date', 'DESC');

            if ($limit) {
                $events = $query->paginate($limit, ['*'], 'page', $page);
                $events->getCollection()->transform(function ($event) {
                    return $this->formatEvent($event);
                });

                $response = [
                    'data' => $events->items(),
                    'pagination' => [
                        'total' => $events->total(),
                        'current_page' => $events->currentPage(),
                        'per_page' => $events->perPage(),
                        'last_page' => $events->lastPage(),
                        'from' => $events->firstItem(),
                        'to' => $events->lastItem(),
                        'next_page_url' => $events->nextPageUrl(),
                        'previous_page_url' => $events->previousPageUrl(),
                    ]
                ];
            } else {
                $events = $query->get()->map(function ($event) {
                    return $this->formatEvent($event);
                });

                $response = [
                    'data' => $events,
                    'pagination' => [
                        'total' => $events->count(),
                        'current_page' => 1,
                        'per_page' => $events->count(),
                        'last_page' => 1,
                        'from' => $events->isEmpty() ? 0 : 1,
                        'to' => $events->count(),
                        'next_page_url' => null,
                        'previous_page_url' => null,
                    ]
                ];
            }

            return $this->successResponse($response['data'], 'Events fetched successfully', 200, ['pagination' => $response['pagination'] ?? null]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Operation failed: ' . $e->getMessage(), 500);
        }
    }
}
