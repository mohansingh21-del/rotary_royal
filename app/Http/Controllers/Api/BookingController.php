<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Asset;
use App\Traits\ApiResponse;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingRejectedNotification;
use App\Notifications\NewBookingAdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Exception;

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

            $query = Booking::with(['user', 'asset']);

            if ($search) {
                $query->where('id', 'like', '%' . $search . '%')
                      ->orWhere('user_phone', 'like', '%' . $search . '%')
                      ->orWhere('user_name', 'like', '%' . $search . '%');
            }

           if($limit){
             $bookings = $query->orderBy('id', 'DESC')
                    ->paginate($limit, ['*'], 'page', $page);

            $bookings->getCollection()->transform(function ($booking) {
                    return [
                    'id' => $booking->id,
                    'user_name' => $booking->user_name,
                    'user_phone' => $booking->user_phone,
                    'user_email' => $booking->user_email,
                    'start_date' => $booking->start_date,
                    'end_date' => $booking->end_date,
                    'id_type' => $booking->id_type,
                    'id_number' => $booking->id_number,
                    'id_image_path' => $booking->id_image_path,
                    'status' => $booking->status
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
                    ]
                ];
            }else{
                $bookings = $query->orderBy('id', 'DESC')->get();
                $bookings->transform(function ($booking) {
                    return [
                    'id' => $booking->id,
                    'user_name' => $booking->user_name,
                    'user_phone' => $booking->user_phone,
                    'user_email' => $booking->user_email,
                    'start_date' => $booking->start_date,
                    'end_date' => $booking->end_date,
                    'id_type' => $booking->id_type,
                    'id_number' => $booking->id_number,
                    'id_image_path' => $booking->id_image_path,
                    'status' => $booking->status,
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
                    ]
                ];
            }

          return response()->json([
                'status' => 200,
                'message' => 'Bookings retrieved successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);
            
        }catch(Exception $e){
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created booking (Guest Friendly).
     */
    public function store(Request $request)
    {
        $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'payment_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'user_name' => 'required|string|max:255',
            'user_phone' => 'required|regex:/^[0-9]{10}$/',
            'user_email' => 'required|email',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'id_type' => 'required|string',
            'id_number' => 'required|string',
            'id_image_path' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'reference' => 'nullable|string',
        ]);

        // Availability Check
        $asset = Asset::findOrFail($request->asset_id);
        $bookedCount = Booking::where('asset_id', $request->asset_id)
            ->where('status', 'Approved')
            ->count();

        if ($bookedCount >= $asset->quantity) {
            return $this->errorResponse('Not Available: All units of this asset are currently booked.', 422);
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

        // Generate custom Booking ID (BR-1001 style)
        $lastBooking = Booking::latest('id')->first();
        if ($lastBooking && preg_match('/BR-(\d+)/', $lastBooking->id, $matches)) {
            $lastId = (int)$matches[1];
            $newId = 'BR-' . ($lastId + 1);
        } else {
            $newId = 'BR-1001';
        }

        // Create Booking (Default to Approved)
        $booking = Booking::create(array_merge($request->except(['payment_image', 'id_image_path']), [
            'id' => $newId,
            'user_id' => $request->user()?->id, // Null if guest
            'status' => 'Approved',
            'payment_image' => $paymentImagePath,
            'id_image_path' => $idImagePath,
        ]));


        // Notify all Super Admins
        $admins = User::where('role', 'Super Admin')->get();
        Notification::send($admins, new NewBookingAdminNotification($booking));

        // Notify User (including guests)
        Notification::route('mail', $booking->user_email)->notify(new BookingConfirmedNotification($booking));

        return $this->successResponse($booking, 'Booking confirmed successfully', 201);
    }

    /**
     * Reject a booking and notify the user.
     */
    public function reject(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:bookings,id',
            'reason' => 'required|string',
        ]);

        $booking = Booking::findOrFail($request->id);

        $booking->update([
            'status' => 'Rejected',
            'rejection_reason' => $request->reason,
        ]);

        // Update asset status
        Asset::updateStatusById($booking->asset_id);

        // Notify User (including guests)
        Notification::route('mail', $booking->user_email)->notify(new BookingRejectedNotification($booking, $request->reason));

        return $this->successResponse($booking, 'Booking rejected successfully.');
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
}