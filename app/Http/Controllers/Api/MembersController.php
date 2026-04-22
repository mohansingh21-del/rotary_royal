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

class MembersController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of members only.
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

            return response()->json([
                'status' => 200,
                'message' => 'Members retrieved successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $member = Members::with('user')->find($id);
            if (!$member) {
                return $this->errorResponse('Member not found', 404);
            }

            if ($member->image) {
                $member->image = asset($member->image);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Member details retrieved successfully',
                'data' => $member,
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

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

            return $this->successResponse($user, $id ? 'Member updated successfully' : 'Member created successfully', $id ? 200 : 201);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }


    /**
     * Get details of non-members (guests who auto-registered).
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

            return response()->json([
                'status' => 200,
                'message' => 'Non-members retrieved successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->errorResponse('User not found', 404);
            }

            if ($user->role === 'Super Admin') {
                return $this->errorResponse('Status of a Super Admin cannot be toggled', 403);
            }

            $user->status = ($user->status == 1) ? 0 : 1;
            $user->save();

            return $this->successResponse($user, 'Status updated successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function toggleStatusNonMembers(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->status = ($user->status == 1) ? 0 : 1;
            $user->save();

            return $this->successResponse($user, 'Status updated successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
