<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Exception;
use App\Traits\ApiResponse;
use App\Models\Members;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponse;

    public function profile(Request $request)
    {
        try {
            $user = $request->user();

            $member = $user->member; // may be null

            return response()->json([
                'status' => 200,
                'message' => 'Profile fetched successfully',
                'data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,

                    'member_id' => $member->member_id ?? null,
                    'address' => $member->address ?? null,
                    'dob' => $member->dob ?? null,
                    'gender' => $member->gender ?? null,
                    'image' => isset($member->image)
                        ? asset($member->image)
                        : null,
                    'work' => $member->work ?? null,

                    'profile_completed' => $member ? true : false
                ]
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            $member = $user->member; // may be null

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'phone' => 'required|regex:/^[0-9]{10}$/|unique:users,phone,' . $user->id,
                'member_id' => 'required|string|unique:members,member_id,' . ($member?->id ?? 'NULL'),
                'address' => 'nullable|string|max:500',
                'dob' => 'nullable|date',
                'gender' => 'nullable|in:Male,Female,Other',
                'work' => 'nullable|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:5120',
            ]);

            // Update User core data
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
            ]);

            // Handle Profile Image
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($member && $member->image && file_exists(public_path($member->image))) {
                    @unlink(public_path($member->image));
                }
                // Store new image
                $file = $request->file('image');
                $filename = $file->hashName();
                $file->move(public_path('members'), $filename);
                $imagePath = '/members/' . $filename;
            }

            // Sync Member details
            $user->member()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'member_id' => $request->member_id,
                    'address' => $request->address,
                    'dob' => $request->dob,
                    'gender' => $request->gender,
                    'work' => $request->work,
                    'image' => $imagePath,
                ]
            );

            // Re-fetch user with member to get updated data
            return response()->json([
                'status' => 200,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,

                    'member_id' => $member->member_id ?? null,
                    'address' => $member->address ?? null,
                    'dob' => $member->dob ?? null,
                    'gender' => $member->gender ?? null,
                    'image' => isset($member->image)
                        ? asset($member->image)
                        : null,
                    'work' => $member->work ?? null,

                    'profile_completed' => $member ? true : false
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deactivate(Request $request)
    {
        try {
            $user = $request->user();
            $user->update([
                'status' => 0,
            ]);
            return response()->json([
                'status' => 200,
                'message' => 'Profile deactivated successfully',
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}

