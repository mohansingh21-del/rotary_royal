<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Members;
use App\Models\User;
use App\Models\Booking;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Controller strictly governing localized Club Member profiles and Guest lifecycles.
 * Serves Member identification mapping, Image bounds, and explicit system suspension features.
 */
class MembersController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of members only.
     */
    /**
     * Retrieve a paginated array of exclusively authenticated official 'Members'.
     * Maps deep relations extracting Profile metadata alongside primary User identities.
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

            $query = User::with('member')->where('role', 'Member')->whereHas('member');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                });
            }

            if ($limit) {
                $users = $query->orderBy('created_at', 'DESC')
                    ->paginate($limit, ['*'], 'page', $page);

                $users->getCollection()->transform(function ($user) {
                    return [
                        'id' => $user->member->id,
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'status' => $user->status,
                        'image' => isset($user->member->image) ? asset($user->member->image) : null,
                        'date_of_joining' => $user->member->date_of_joining,
                        'member_id' => $user->member->member_id,
                    ];
                });

                $response = [
                    'data' => $users->items(),
                    'pagination' => [
                        'total' => $users->total(),
                        'current_page' => $users->currentPage(),
                        'per_page' => $users->perPage(),
                        'last_page' => $users->lastPage(),
                        'from' => $users->firstItem(),
                        'to' => $users->lastItem(),
                        'next_page_url' => $users->nextPageUrl(),
                        'previous_page_url' => $users->previousPageUrl(),
                    ]
                ];
            } else {
                $users = $query->orderBy('created_at', 'DESC')->get();
                $users->transform(function ($user) {
                    return [
                        'id' => $user->member->id,
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'status' => $user->status,
                        'image' => isset($user->member->image) ? asset($user->member->image) : null,
                        'date_of_joining' => $user->member->date_of_joining,
                        'member_id' => $user->member->member_id,
                    ];
                });
                $response = [
                    'data' => $users,
                    'pagination' => [
                        'total' => $users->count(),
                        'current_page' => 1,
                        'per_page' => $users->count(),
                        'last_page' => 1,
                        'from' => $users->isEmpty() ? 0 : 1,
                        'to' => $users->count(),
                        'next_page_url' => null,
                        'previous_page_url' => null,
                    ]
                ];

            }

            return $this->successResponse($response['data'], 'Members retrieved successfully', 200, ['pagination' => $response['pagination'] ?? null]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch members: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Isolate a specific Member ID profile deeply parsing structural profile completion metadata.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $member = Members::with('user')->find($id);
            if (!$member) {
                return $this->notFoundResponse('Member not found');
            }

            if ($member->image) {
                $member->image = asset($member->image);
            }

            return $this->successResponse($member, 'Member details retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Member not found');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to retrieve member details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Safely enforce strict Upsert capabilities executing atomic SQL transactions for User+Member relations.
     * Handles dynamic logic generating randomized unusable passwords enabling administrative bulk imports.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $id = $request->input('id');
            $member = $id ? Members::with('user')->findOrFail($id) : new Members();
            $user = $id ? $member->user : new User();

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email' . ($id ? ',' . $user->id : ''),
            'phone' => 'required|regex:/^[0-9]{10}$/|unique:users,phone' . ($id ? ',' . $user->id : ''),
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'member_id' => 'required|string|max:255|unique:members,member_id' . ($id ? ',' . $id : ''),
            'dob' => 'required|date',
            'gender' => 'required|in:Male,Female,Other',
            'address' => 'required|string|max:255',
            'work' => 'nullable|string',
            'date_of_joining' => 'required|date',
        ];

        $request->validate($rules);

        DB::beginTransaction();
        try {
            // Update/Create User
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            if (!$id) {
                $user->password = Hash::make(Str::random(16));
                $user->role = 'Member';
                $user->status = 1;
            }
            $user->save();

            // Handle Image Upload/Replacement
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($member->image && file_exists(public_path($member->image))) {
                    @unlink(public_path($member->image));
                }
                $file = $request->file('image');
                $filename = $file->hashName();
                $file->move(public_path('members'), $filename);
                $member->image = '/members/' . $filename;
            }

            // Handle Member ID
            $memberId = $request->member_id;

            // Update/Create Member Profile
            $member->user_id = $user->id;
            $member->member_id = $memberId;
            $member->address = $request->address;
            $member->dob = $request->dob;
            $member->gender = $request->gender;
            $member->work = $request->work;
            $member->date_of_joining = $request->date_of_joining;
            $member->save();

            DB::commit();

            // Load member relationship for response
            $user->load('member');
            if ($user->member && $user->member->image) {
                $user->member->image = asset($user->member->image);
            }

            if ($id) {
                return $this->successResponse($user, 'Member updated successfully');
            } else {
                return $this->createdResponse($user, 'Member created successfully');
            }

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return $this->notFoundResponse('Member not found');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to save member: ' . $e->getMessage(), 500);
        }
    }


    /**
     * Get details of non-members (guests who auto-registered).
     */
    /**
     * Reveal dynamically registered `Non-Member` guests accessing the systems for Bookings or Donations.
     * Extends standard lookup excluding explicitly validated native Rotary bindings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function nonMembers(Request $request)
    {
        try {
            $limit = $request->input('limit', null);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);

            $query = User::where('role', 'Non-Member')->whereDoesntHave('member');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                });
            }

            if ($limit) {
                $users = $query->orderBy('created_at', 'DESC')
                    ->paginate($limit, ['*'], 'page', $page);

                $users->getCollection()->transform(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'status' => $user->status
                    ];
                });

                $response = [
                    'data' => $users->items(),
                    'pagination' => [
                        'total' => $users->total(),
                        'current_page' => $users->currentPage(),
                        'per_page' => $users->perPage(),
                        'last_page' => $users->lastPage(),
                        'from' => $users->firstItem(),
                        'to' => $users->lastItem(),
                        'next_page_url' => $users->nextPageUrl() ?? null,
                        'previous_page_url' => $users->previousPageUrl() ?? null,
                    ]
                ];
            } else {
                $users = $query->orderBy('created_at', 'DESC')->get();
                $users->transform(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'status' => $user->status
                    ];
                });
                $response = [
                    'data' => $users,
                    'pagination' => [
                        'total' => $users->count(),
                        'current_page' => 1,
                        'per_page' => $users->count(),
                        'last_page' => 1,
                        'from' => $users->isEmpty() ? 0 : 1,
                        'to' => $users->count(),
                        'next_page_url' => null,
                        'previous_page_url' => null,
                    ]
                ];
            }

            return $this->successResponse($response['data'], 'Non-members retrieved successfully', 200, ['pagination' => $response['pagination'] ?? null]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch non-members: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Explicitly toggle global System Authentication access for official Rotary Members.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->notFoundResponse('User not found');
            }

            if ($user->role === 'Super Admin') {
                return $this->forbiddenResponse('Status of a Super Admin cannot be toggled');
            }

            $user->status = ($user->status == 1) ? 0 : 1;
            $user->save();

            return $this->successResponse($user, 'Status updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Operation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Terminate or restore temporary access boundaries bound directly against shadow Guest users.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatusNonMembers(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->status = ($user->status == 1) ? 0 : 1;
            $user->save();

            return $this->successResponse($user, 'Status updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Operation failed: ' . $e->getMessage(), 500);
        }
    }
}
