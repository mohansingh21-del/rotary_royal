<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Exception;
use App\Traits\ApiResponse;
use App\Traits\ProtectsDummyRecords;
use App\Models\Members;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponse, ProtectsDummyRecords;

    /**
     * Shown to a store reviewer, so it reads as an intentional demo-account
     * limit rather than a fault.
     */
    private const DUMMY_PROFILE_MESSAGE = 'Profile changes are disabled for the demo account.';

    public function profile(Request $request)
    {
        try {
            $user = $request->user();
            $member = $user->member;

            return $this->successResponse([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'member_id' => $member->member_id ?? null,
                'address' => $member->address ?? null,
                'dob' => $member->dob ?? null,
                'gender' => $member->gender ?? null,
                'image' => ($member && $member->image) ? asset($member->image) : null,
                'work' => $member->work ?? null,
                'profile_completed' => $member ? true : false
            ], 'Profile fetched successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch profile: ' . $e->getMessage(), 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            // Editing this account would break review itself: changing the phone
            // orphans the fixed-OTP login the store reviewers were given.
            if ($blocked = $this->blockIfDummy($user, self::DUMMY_PROFILE_MESSAGE)) {
                return $blocked;
            }

            $member = $user->member;

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

            $profileData = [
                'member_id' => $request->member_id,
                'address' => $request->address,
                'dob' => $request->dob,
                'gender' => $request->gender,
                'work' => $request->work,
            ];

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
                $profileData['image'] = '/members/' . $filename;
            }

            // Sync Member details
            $member = $user->member()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );

            // Re-fetch to get fresh state
            $user->load('member');
            $member = $user->member;

            return $this->successResponse([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'member_id' => $member->member_id ?? null,
                'address' => $member->address ?? null,
                'dob' => $member->dob ?? null,
                'gender' => $member->gender ?? null,
                'image' => ($member && $member->image) ? asset($member->image) : null,
                'work' => $member->work ?? null,
                'profile_completed' => true
            ], 'Profile updated successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }

    public function deactivate(Request $request)
    {
        try {
            $user = $request->user();

            // Deactivating would set status = 0, and the OTP login requires an
            // active user — the review account would lock itself out for good.
            if ($blocked = $this->blockIfDummy($user, self::DUMMY_PROFILE_MESSAGE)) {
                return $blocked;
            }

            $user->update(['status' => 0]);

            return $this->successResponse(null, 'Profile deactivated successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Failed to deactivate profile: ' . $e->getMessage(), 500);
        }
    }
}

