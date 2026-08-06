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
use App\Models\User;
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

    /**
     * Public, token-less by explicit product decision. There is no session and
     * no ownership check: the caller names an account by id and it is disabled.
     * Anyone who can guess an id can deactivate that member, and a deactivated
     * member cannot log back in — findUserForOtp() only resolves active users,
     * so every recovery is a manual database edit. Restoring authentication is
     * the only real fix; the throttle on the route merely slows enumeration.
     *
     * Every outcome answers HTTP 200. The real code — 422, 404, 403, 500 — is
     * carried in the body's `status` field, so callers must read the payload
     * rather than the transport status to tell success from failure.
     */
    public function deactivate(Request $request, $userId)
    {
        try {
            // The id arrives as a route segment, which $request->validate() does
            // not see, so it is validated here by hand. The route deliberately
            // drops whereNumber(): a segment rejected by the router would 404 at
            // the transport level, which is the one status this endpoint must
            // never emit.
            $validator = Validator::make(['user_id' => $userId], [
                'user_id' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation error', 422, $validator->errors())
                    ->setStatusCode(200);
            }

            $user = User::find($userId);

            if (!$user) {
                return $this->errorResponse('User not found', 404)->setStatusCode(200);
            }

            // Deactivating sets status = 0, and the OTP login requires an active
            // user — the review account would lock itself out for good.
            if ($blocked = $this->blockIfDummy($user, self::DUMMY_PROFILE_MESSAGE)) {
                return $blocked->setStatusCode(200);
            }

            $user->update(['status' => 0]);

            // Without this, an already-issued app token keeps working against
            // every other endpoint even though the account is disabled.
            $user->tokens()->delete();

            return $this->successResponse(null, 'Profile deactivated successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Failed to deactivate profile: ' . $e->getMessage(), 500)
                ->setStatusCode(200);
        }
    }
}

