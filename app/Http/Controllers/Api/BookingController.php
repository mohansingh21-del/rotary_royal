<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingRejectedNotification;
use App\Notifications\BookingSubmittedNotification;
use App\Notifications\NewBookingAdminNotification;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Controller orchestrating Asset reservations and lifecycle states.
 * Strictly enforces real-time stock limits, overlap bounds, and role-based tracking rules.
 */
class BookingController extends Controller
{
    use ApiResponse;

    /**
     * Retrieve a detailed, paginated list of system Booking requests.
     * Incorporates exhaustive search, specific Asset association targeting, and chronological range filtering.
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
            $assetId = $request->input('asset_id');
            $fromDate = $request->input('startDate');
            $toDate = $request->input('endDate');

            $request->validate([
                'asset_id' => 'nullable|exists:assets,id',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
            ]);

            $query = Booking::with(['user', 'asset', 'referrer']);

            if ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'like', '%' . $search . '%')
                        ->orWhere('user_phone', 'like', '%' . $search . '%')
                        ->orWhere('user_name', 'like', '%' . $search . '%')
                        ->orWhereHas('asset', function ($assetQuery) use ($search) {
                            $assetQuery->where('name', 'like', '%' . $search . '%');
                        });
                });
            }

            if ($assetId) {
                $query->where('asset_id', $assetId);
            }

            if ($fromDate) {
                $query->whereDate('start_date', '>=', $fromDate);
            }

            if ($toDate) {
                $query->whereDate('end_date', '<=', $toDate);
            }

            if ($limit) {
                $bookings = $query->orderBy('created_at', 'DESC')
                    ->paginate($limit, ['*'], 'page', $page);
                $bookings->getCollection()->transform(function ($booking) {
                    return $this->formatBooking($booking);
                });

                $response = [
                    'data' => $bookings->items(),
                    'pagination' => [
                        'total' => $bookings->total(),
                        'current_page' => $bookings->currentPage(),
                        'per_page' => $bookings->perPage(),
                        'last_page' => $bookings->lastPage(),
                        'from' => $bookings->firstItem(),
                        'to' => $bookings->lastItem(),
                        'next_page_url' => $bookings->nextPageUrl(),
                        'previous_page_url' => $bookings->previousPageUrl(),
                    ]
                ];
            } else {
                $bookings = $query->orderBy('created_at', 'DESC')->get();
                $bookings->transform(function ($booking) {
                    return $this->formatBooking($booking);
                });
                $response = [
                    'data' => $bookings,
                    'pagination' => [
                        'total' => $bookings->count(),
                        'current_page' => 1,
                        'per_page' => $bookings->count(),
                        'last_page' => 1,
                        'from' => $bookings->isEmpty() ? 0 : 1,
                        'to' => $bookings->count(),
                        'next_page_url' => null,
                        'previous_page_url' => null,
                    ]
                ];
            }

            return $this->successResponse($response['data'], 'Bookings retrieved successfully', 200, ['pagination' => $response['pagination'] ?? null]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created Booking from a generic or authenticated Guest request.
     * Handles complex concurrency resolution predicting stock quantity collisions utilizing exact chronologic interval buffers.
     * Generates a temporary `Non-Member` shadow tracking ID automatically mapping recurring guests reliably.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'asset_id' => 'required|exists:assets,id',
            ]);

            $asset = Asset::findOrFail($request->asset_id);

            $request->validate([
                'asset_id' => 'required|exists:assets,id',
                'payment_image' => ($asset->price > 0 ? 'required' : 'nullable') . '|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
                'user_name' => 'required|string|max:255',
                'user_phone' => 'required|regex:/^[0-9]{10}$/',
                'user_email' => 'required|email',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after:start_date',
                'id_number' => 'required|string',
                'id_image_path' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
                'reference' => 'nullable|string',
            ], [
                'payment_image.required' => 'Payment image is required when the selected asset price is greater than 0.',
                'id_image_path.required' => 'ID image is required.',
            ]);

            if ($asset->left_quantity <= 0) {
                return $this->conflictResponse('Not Available: No more units of this asset are available.');
            }

            // Comprehensive Concurrency Availability Validation Check
            // Projects End Date buffer ensuring Asset checkout delays overlap exactly
            $newEndWithBuffer = \Illuminate\Support\Carbon::parse($request->end_date)->addHours($asset->buffer_time);

            $overlappingCount = Booking::where('asset_id', $request->asset_id)
                ->where('status', 'Approved')
                ->where('start_date', '<', $newEndWithBuffer)
                ->whereRaw('DATE_ADD(end_date, INTERVAL buffer_time HOUR) > ?', [$request->start_date])
                ->count();

            if ($overlappingCount >= $asset->quantity) {
                return $this->conflictResponse('Not Available: This asset is fully booked for the selected time slot.');
            }

            // Handle Payment Image Upload
            $paymentImagePath = null;
            if ($request->hasFile('payment_image')) {
                $file = $request->file('payment_image');
                $filename = $file->hashName();
                $file->move(public_path('payments'), $filename);
                $paymentImagePath = '/payments/' . $filename;
            }

            // Handle ID Image Upload
            $idImagePath = null;
            if ($request->hasFile('id_image_path')) {
                $file = $request->file('id_image_path');
                $filename = $file->hashName();
                $file->move(public_path('payments'), $filename);
                $idImagePath = '/payments/' . $filename;
            }

            // Generate custom Booking ID
            $lastBooking = Booking::latest('id')->first();
            if ($lastBooking && preg_match('/BR-(\d+)/', $lastBooking->id, $matches)) {
                $lastId = (int) $matches[1];
                $newId = 'BR-' . ($lastId + 1);
            } else {
                $newId = 'BR-1001';
            }

            // Auto-registration logic
            $phone = trim($request->user_phone);
            $email = strtolower(trim($request->user_email));
            $userByPhone = User::where('phone', $phone)->first();
            $userByEmail = User::where('email', $email)->first();

            if ($userByPhone && $userByPhone->email !== $email) {
                return $this->validationResponse([
                    'user_phone' => ['This phone number is already associated with another email address.']
                ]);
            }

            if ($userByEmail && $userByEmail->phone !== $request->user_phone) {
                return $this->validationResponse([
                    'user_email' => ['This email address is already associated with another phone number.']
                ]);
            }

            $user = $userByPhone;

            if (!$user) {
                $user = User::create([
                    'name' => $request->user_name,
                    'email' => $request->user_email,
                    'phone' => $request->user_phone,
                    'password' => Hash::make(Str::random(16)),
                    'role' => 'Non-Member',
                    'status' => 1,
                ]);
            }

            // Create Booking (auto-approved by default)
            $booking = Booking::create(array_merge($request->except(['payment_image', 'id_image_path']), [
                'id' => $newId,
                'user_id' => $user->id,
                'status' => 'Approved',
                'buffer_time' => $asset->buffer_time,
                'payment_image' => $paymentImagePath,
                'id_image_path' => $idImagePath,
            ]));

            // Decrement asset left_quantity since booking is auto-approved
            if ($asset->left_quantity > 0) {
                $asset->decrement('left_quantity');
            }

            $booking->load(['asset', 'user', 'referrer']);

            // Notify all Super Admins
            $admins = User::where('role', 'Super Admin')->get();
            Notification::send($admins, new NewBookingAdminNotification($booking));
            $user->notify(new BookingConfirmedNotification($booking));

            return $this->createdResponse($this->formatBooking($booking), 'Your booking has been approved successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Asset not found');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to create booking: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject a booking and notify the user.
     */
    /**
     * Explicitly reject a pending Booking request providing a direct reason to the User.
     * Frees previously bounded capacity lock incrementing exactly 1 back to standard quantity availability.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|exists:bookings,id',
                'reason' => 'required|string',
            ]);

            $booking = Booking::with(['user', 'asset'])->findOrFail($request->id);

            $wasApproved = $booking->status === 'Approved';
            $booking->update([
                'status' => 'Rejected',
                'rejection_reason' => $request->reason,
            ]);

            // Restore left_quantity for this asset
            $rejectedAsset = Asset::find($booking->asset_id);
            if ($wasApproved && $rejectedAsset) {
                $rejectedAsset->left_quantity = min($rejectedAsset->quantity, $rejectedAsset->left_quantity + 1);
                $rejectedAsset->saveQuietly();
            }

            // Notify User (including guests)
            if ($booking->user) {
                $booking->user->notify(new BookingRejectedNotification($booking, $request->reason));
            } else {
                Notification::route('mail', $booking->user_email)->notify(new BookingRejectedNotification($booking, $request->reason));
            }

            return $this->successResponse($booking, 'Booking rejected successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Booking not found');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to reject booking: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the status of a booking.
     */
    /**
     * Patch a Booking directly mapping approval lifecycle events dynamically via unified REST logic.
     * Automatically captures internal Asset deduction decrement triggering only upon strictly 'Approved' bindings.
     *
     * @param Request $request
     * @param Booking $booking
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, Booking $booking)
    {
        $request->validate([
            'status' => 'required|in:Approved,Rejected',
        ]);

        $booking->loadMissing(['user', 'asset']);
        $previousStatus = $booking->status;
        $booking->update(['status' => $request->status]);

        $asset = Asset::find($booking->asset_id);
        if ($asset) {
            if ($request->status === 'Approved' && $previousStatus !== 'Approved' && $asset->left_quantity > 0) {
                $asset->decrement('left_quantity');
            }

            if ($request->status === 'Rejected' && $previousStatus === 'Approved') {
                $asset->left_quantity = min($asset->quantity, $asset->left_quantity + 1);
                $asset->saveQuietly();
            }
        }

        if ($request->status === 'Approved') {
            if ($booking->user) {
                $booking->user->notify(new BookingConfirmedNotification($booking));
            } else {
                Notification::route('mail', $booking->user_email)->notify(new BookingConfirmedNotification($booking));
            }
        }

        if ($request->status === 'Rejected') {
            if ($booking->user) {
                $booking->user->notify(new BookingRejectedNotification($booking, 'Booking was rejected by admin.'));
            } else {
                Notification::route('mail', $booking->user_email)->notify(new BookingRejectedNotification($booking, 'Booking was rejected by admin.'));
            }
        }

        return $this->successResponse($booking, 'Booking status updated successfully');
    }

    /**
     * Get bookings made by the logged-in user.
     */
    /**
     * Yield all personal Bookings specifically filtered strictly against an authenticated user session.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userBookings(Request $request)
    {
        try {
            $user = $request->user();
            $limit = $request->input('limit', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);

            $query = Booking::with(['asset', 'referrer'])
                ->where('user_id', $user->id);

            if ($search) {
                $query->whereHas('asset', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                });
            }

            if ($limit) {
                $bookings = $query->orderBy('created_at', 'DESC')
                    ->paginate($limit, ['*'], 'page', $page);

                $formattedItems = collect($bookings->items())->map(function ($booking) {
                    return $this->formatBooking($booking);
                });

                $response = [
                    'data' => $formattedItems,
                    'pagination' => [
                        'total' => $bookings->total(),
                        'current_page' => $bookings->currentPage(),
                        'per_page' => $bookings->perPage(),
                        'last_page' => $bookings->lastPage(),
                        'from' => $bookings->firstItem(),
                        'to' => $bookings->lastItem(),
                        'next_page_url' => $bookings->nextPageUrl(),
                        'previous_page_url' => $bookings->previousPageUrl(),
                    ]
                ];
            } else {
                $bookings = $query->orderBy('created_at', 'DESC')->get();
                $formattedItems = $bookings->map(function ($booking) {
                    return $this->formatBooking($booking);
                });
                $response = [
                    'data' => $formattedItems,
                    'pagination' => [
                        'total' => $bookings->count(),
                        'current_page' => 1,
                        'per_page' => $bookings->count(),
                        'last_page' => 1,
                        'from' => $bookings->isEmpty() ? 0 : 1,
                        'to' => $bookings->count(),
                        'next_page_url' => null,
                        'previous_page_url' => null,
                    ]
                ];
            }

            return $this->successResponse($response['data'], 'Your bookings retrieved successfully', 200, ['pagination' => $response['pagination'] ?? null]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch bookings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper to format booking data consistently.
     */
    private function formatBooking($booking)
    {
        return [
            'id' => $booking->id,
            'asset_id' => $booking->asset_id,
            'assets_name' => $booking->asset?->name,
            'assets_image' => $booking->asset?->image ? asset($booking->asset->image) : null,
            'assets_is_active' => $booking->asset?->status,
            'price' => $booking->asset?->price,
            'user_id' => $booking->user_id,
            'user_name' => $booking->user_name,
            'user_phone' => $booking->user_phone,
            'user_email' => $booking->user_email,
            'start_date' => $booking->start_date,
            'end_date' => $booking->end_date,
            'id_number' => $booking->id_number,
            'id_image_path' => $booking->id_image_path ? asset($booking->id_image_path) : null,
            'payment_image' => $booking->payment_image ? asset($booking->payment_image) : null,
            'reference' => $booking->reference,
            'referrer' => $booking->referrer ? [
                'id' => $booking->referrer->id,
                'name' => $booking->referrer->name,
                'phone' => $booking->referrer->phone,
            ] : null,
            'asset' => $booking->asset,
            'user' => $booking->user ? [
                'id' => $booking->user->id,
                'name' => $booking->user->name,
                'phone' => $booking->user->phone,
            ] : null,
            'status' => $booking->status,
            'buffer_time' => $booking->buffer_time,
            'rejection_reason' => $booking->rejection_reason,
            'created_at' => $booking->created_at,
        ];
    }

    /**
     * Reveal granular details of a specific Booking ensuring strict authorization mapped entirely to the logged-in user.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showBooking($id)
    {
        try {
            $user = request()->user();
            $booking = Booking::with(['asset', 'referrer'])
                ->where('user_id', $user->id)
                ->findOrFail($id);

            return $this->successResponse($this->formatBooking($booking), 'Booking details retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Booking not found or access denied');
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch booking details: ' . $e->getMessage(), 500);
        }
    }
}
