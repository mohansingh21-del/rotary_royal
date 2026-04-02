<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of active members only (Public API).
     */
    public function activeMembers(Request $request)
    {
        try {
            $users = User::where('role', 'Member')->where('status', '1')
                ->orderBy('id', 'DESC')
                ->get();

            return $this->successResponse($users, 'Active members retrieved successfully');
        }
        catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display a listing of members only.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', null);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);

            $query = User::where('role', 'Member');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            }

            if ($limit) {
                $users = $query->orderBy('id', 'DESC')
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
                        'next_page_url' => $users->nextPageUrl(),
                        'previous_page_url' => $users->previousPageUrl(),
                    ]
                ];
            }
            else {
                $users = $query->orderBy('id', 'DESC')->get();
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
                'message' => 'Members retrieved successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);

        }
        catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

    }

    /**
     * Store a newly created member.
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email' . ($request->id ? ',' . $request->id : ''),
            'phone' => 'required|regex:/^[0-9]{10}$/|unique:users,phone' . ($request->id ? ',' . $request->id : ''),
        ];

        $request->validate($rules);

        $user = $request->id ?User::findOrFail($request->id) : new User();

        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->password = Hash::make(Str::random(16)); // Randomized unusable password
        $user->role = 'Member';
        $user->status = 1;
        $user->save();

        return $this->successResponse($user, 'Member created successfully', 201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        if ($user->role !== 'Member') {
            return $this->errorResponse('Unauthorized access', 403);
        }

        return $this->successResponse($user, 'Member details retrieved successfully');
    }

    /**
     * Update the specified member.
     */
    public function update(Request $request, User $user)
    {
    // if ($user->role !== 'Member') {
    //     return $this->errorResponse('Unauthorized access', 403);
    // }

    // $request->validate([
    //     'name' => 'sometimes|required|string|max:255',
    //     'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
    //     'phone' => 'sometimes|required|unique:users,phone,' . $user->id . '|regex:/^[0-9]{10}$/',
    //     'status' => 'sometimes|required|in:0,1',
    // ]);

    // $user->update($request->all());

    // return $this->successResponse($user, 'Member updated successfully');
    }

    /**
     * Remove the specified member.
     */
    public function destroy(User $user)
    {
        if ($user->role !== 'Member') {
            return $this->errorResponse('Unauthorized access', 403);
        }

        $user->delete();

        return $this->successResponse(null, 'Member deleted successfully');
    }

    public function toggleStatus(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->status = ($user->status == 1) ? 0 : 1;
            $user->save();

            return $this->successResponse($user, 'Member status updated successfully');
        }
        catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}