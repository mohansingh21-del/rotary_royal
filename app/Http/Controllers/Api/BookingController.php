<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingRejectedNotification;
use App\Notifications\NewBookingAdminNotification;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of bookings with search and filters.
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
                    return [
                        'id' => $booking->id,
                        'asset_id' => $booking->asset_id,
                        'assets_name' => $booking->asset?->name,
                        'price' => $booking->asset?->price,
                        'user_name' => $booking->user_name,
                        'user_phone' => $booking->user_phone,
                        'user_email' => $booking->user_email,
                        'start_date' => $booking->start_date,
                        'end_date' => $booking->end_date,
                        'id_number' => $booking->id_number,
                        'id_image_path' => $booking->id_image_path,
                        'payment_image' => $booking->payment_image,
                        'reference' => $booking->referrer?->name ?? $booking->reference,
                        'status' => $booking->status,
                        'buffer_time' => $booking->buffer_time,
                    ];
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
                    return [
                        'id' => $booking->id,
                        'asset_id' => $booking->asset_id,
                        'assets_name' => $booking->asset?->name,
                        'price' => $booking->asset?->price,
                        'user_name' => $booking->user_name,
                        'user_phone' => $booking->user_phone,
                        'user_email' => $booking->user_email,
                        'start_date' => $booking->start_date,
                        'end_date' => $booking->end_date,
                        'id_number' => $booking->id_number,
                        'id_image_path' => $booking->id_image_path,
                        'payment_image' => $booking->payment_image,
                        'reference' => $booking->referrer?->name ?? $booking->reference,
                        'status' => $booking->status,
                        'buffer_time' => $booking->buffer_time,
                    ];
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

            return response()->json([
                'status' => 200,
                'message' => 'Bookings retrieved successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created booking (Guest Friendly).
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
                'start_date' => 'required|date|after_or_equal:now',
                'end_date' => 'required|date|after:start_date',
                'id_number' => 'required|string',
                'id_image_path' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
                'reference' => 'nullable|string',
            ], [
                'payment_image.required' => 'Payment image is required when the selected asset price is greater than 0.',
                'id_image_path.required' => 'ID image is required.',
            ]);

            if ($asset->left_quantity <= 0) {
                return $this->errorResponse('Not Available: No more units of this asset are available.', 422);
            }

            // Availability Check
            $newEndWithBuffer = \Illuminate\Support\Carbon::parse($request->end_date)->addHours($asset->buffer_time);

            $overlappingCount = Booking::where('asset_id', $request->asset_id)
                ->where('status', 'Approved')
                ->where('start_date', '<', $newEndWithBuffer)
                ->whereRaw('DATE_ADD(end_date, INTERVAL buffer_time HOUR) > ?', [$request->start_date])
                ->count();

            if ($overlappingCount >= $asset->quantity) {
                return $this->errorResponse('Not Available: This asset is fully booked for the selected time slot.', 422);
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
            $user = User::where('email', $request->user_email)
                ->orWhere('phone', $request->user_phone)
                ->first();

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

            // Create Booking
            $booking = Booking::create(array_merge($request->except(['payment_image', 'id_image_path']), [
                'id' => $newId,
                'user_id' => $user->id,
                'status' => 'Approved',
                'buffer_time' => $asset->buffer_time,
                'payment_image' => $paymentImagePath,
                'id_image_path' => $idImagePath,
            ]));

            // Notify all Super Admins
            $admins = User::where('role', 'Super Admin')->get();
            Notification::send($admins, new NewBookingAdminNotification($booking));

            // Notify User
            Notification::route('mail', $booking->user_email)->notify(new BookingConfirmedNotification($booking));

            // Keep left_quantity in sync
            if ($asset->left_quantity > 0) {
                $asset->decrement('left_quantity');
            }

            return $this->successResponse($booking, 'Your booking is confirmed. We’ve sent the details to your email.', 201);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Asset not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to create booking: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject a booking and notify the user.
     */
    public function reject(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|exists:bookings,id',
                'reason' => 'required|string',
            ]);

            $booking = Booking::findOrFail($request->id);

            $booking->update([
                'status' => 'Rejected',
                'rejection_reason' => $request->reason,
            ]);

            // Restore left_quantity for this asset
            $rejectedAsset = Asset::find($booking->asset_id);
            if ($rejectedAsset) {
                $rejectedAsset->left_quantity = min($rejectedAsset->quantity, $rejectedAsset->left_quantity + 1);
                $rejectedAsset->saveQuietly();
            }

            // Notify User (including guests)
            Notification::route('mail', $booking->user_email)->notify(new BookingRejectedNotification($booking, $request->reason));

            return $this->successResponse($booking, 'Booking rejected successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Booking not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to reject booking: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the status of a booking.
     */
    public function updateStatus(Request $request, Booking $booking)
    {
        $request->validate([
            'status' => 'required|in:Approved,Rejected',
        ]);

        $booking->update(['status' => $request->status]);

        return $this->successResponse($booking, 'Booking status updated successfully');
    }

    /**
     * Get bookings made by the logged-in user.
     */
    public function userBookings(Request $request)
    {
        try {
            $user = $request->user();
            $bookings = Booking::with(['asset', 'referrer'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'DESC')
                ->get();

            $formattedBookings = $bookings->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'asset_id' => $booking->asset_id,
                    'assets_name' => $booking->asset?->name,
                    'price' => $booking->asset?->price,
                    'user_name' => $booking->user_name,
                    'user_phone' => $booking->user_phone,
                    'user_email' => $booking->user_email,
                    'start_date' => $booking->start_date,
                    'end_date' => $booking->end_date,
                    'id_number' => $booking->id_number,
                    'id_image_path' => $booking->id_image_path,
                    'payment_image' => $booking->payment_image,
                    'reference' => $booking->referrer?->name ?? $booking->reference,
                    'status' => $booking->status,
                    'buffer_time' => $booking->buffer_time,
                    'rejection_reason' => $booking->rejection_reason,
                ];
            });

            return response()->json([
                'status' => 200,
                'message' => 'Your bookings retrieved successfully',
                'data' => $formattedBookings,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}