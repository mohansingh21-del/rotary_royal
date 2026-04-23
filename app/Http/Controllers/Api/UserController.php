<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use App\Models\Members;
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
            $limit = $request->input('limit', null);
            $page = $request->input('page', 1);
            $search = $request->input('search');

            $query = User::with('member')
                ->where('role', 'Member')
                ->where('status', '1')
                ->whereHas('member');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhereHas('member', function ($mq) use ($search) {
                            $mq->where('member_id', 'like', '%' . $search . '%');
                        });
                });
            }

            $query->orderBy('id', 'DESC');

            if ($limit) {
                $users = $query->paginate($limit, ['*'], 'page', $page);
                $items = $users->getCollection()->map(function ($user) {
                    return $this->formatUser($user);
                });

                return response()->json([
                    'status' => 200,
                    'message' => 'Active members retrieved successfully',
                    'data' => $items,
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
                ]);
            } else {
                $users = $query->get();
                $data = $users->map(function ($user) {
                    return $this->formatUser($user);
                });

                return $this->successResponse($data, 'Active members retrieved successfully');
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Format user data for consistent API response.
     */
    private function formatUser($user)
    {
        return [
            'id' => $user->member->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'image' => $user->member->image ? asset($user->member->image) : null,
            'date_of_joining' => $user->member->date_of_joining,
            'member_id' => $user->member->member_id,
        ];
    }



    /**
     * Store a newly created member.
     */
    public function store(Request $request)
    {
        try {
            $rules = [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email' . ($request->id ? ',' . $request->id : ''),
                'phone' => 'required|regex:/^[0-9]{10}$/|unique:users,phone' . ($request->id ? ',' . $request->id : ''),
            ];

            $request->validate($rules);

            $user = $request->id ? User::findOrFail($request->id) : new User();

            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->password = Hash::make(Str::random(16)); // Randomized unusable password
            $user->role = 'Member';
            $user->status = 1;
            $user->save();

            // Create Member Profile if it doesn't exist (for manual management)
            if (!$user->member) {
                $lastMember = Members::latest('id')->first();
                $nextNum = $lastMember ? (int) substr($lastMember->member_id, 5) + 1 : 11; // Start from 11 if no members
                $memberId = 'M' . date('Y') . str_pad($nextNum, 2, '0', STR_PAD_LEFT);

                Members::create([
                    'user_id' => $user->id,
                    'member_id' => $memberId,
                    'status' => 1,
                ]);
            }

            return $this->successResponse($user, 'Member created successfully', 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to create member: ' . $e->getMessage(), 500);
        }
    }



}