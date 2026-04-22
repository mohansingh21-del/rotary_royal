<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;
use App\Models\MarqueeMessage;

class DonationController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of donations with search and filters.
     */
    public function index(Request $request)
    {

        try {
            $limit = $request->input('limit', null);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);

            $query = Donation::query();

            if ($search) {
                $query->where('donor_name', 'like', '%' . $search . '%');
            }

            if ($limit) {
                $donations = $query->orderBy('created_at', 'DESC')
                    ->paginate($limit, ['*'], 'page', $page);

                $donations->getCollection()->transform(function ($donation) {
                    return [
                        'id' => $donation->id,
                        'donor_name' => $donation->donor_name,
                        'mobile_no' => $donation->mobile_no,
                        'amount' => $donation->amount,
                        'donated_for' => $donation->project?->name ?? 'Rotary Club',
                        'date' => $donation->date,
                        'is_marquee' => $donation->is_marquee,
                        'payment_receipt' => $donation->payment_receipt ? asset($donation->payment_receipt) : null,
                    ];
                });

                $response = [
                    'data' => $donations->items(),
                    'pagination' => [
                        'total' => $donations->total(),
                        'current_page' => $donations->currentPage(),
                        'per_page' => $donations->perPage(),
                        'last_page' => $donations->lastPage(),
                        'from' => $donations->firstItem(),
                        'to' => $donations->lastItem(),
                        'next_page_url' => $donations->nextPageUrl(),
                        'previous_page_url' => $donations->previousPageUrl(),
                    ]
                ];
            } else {
                $donations = $query->orderBy('created_at', 'DESC')->get();

                $donations->transform(function ($donation) {
                    return [
                        'id' => $donation->id,
                        'donor_name' => $donation->donor_name,
                        'mobile_no' => $donation->mobile_no,
                        'amount' => $donation->amount,
                        'donated_for' => $donation->project?->name ?? 'Rotary Club',
                        'date' => $donation->date,
                        'is_marquee' => $donation->is_marquee,
                        'payment_receipt' => $donation->payment_receipt ? asset($donation->payment_receipt) : null,
                    ];
                });

                $response = [
                    'data' => $donations,
                    'pagination' => [
                        'total' => $donations->count(),
                        'current_page' => 1,
                        'per_page' => $donations->count(),
                        'last_page' => 1,
                        'from' => $donations->isEmpty() ? 0 : 1,
                        'to' => $donations->count(),
                        'next_page_url' => null,
                        'previous_page_url' => null,
                    ]
                ];
            }

            return response()->json([
                'status' => 200,
                'message' => 'Donations retrieved successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a new donation record.
     */
    public function store(Request $request)
    {
        $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'donor_name' => 'required|string|max:255',
            'mobile_no' => 'required|string|max:20',
            'amount' => 'required|numeric|min:0',
            'payment_receipt' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
        ]);

        $paymentReceiptPath = null;
        if ($request->hasFile('payment_receipt')) {
            $file = $request->file('payment_receipt');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('donations'), $filename);
            $paymentReceiptPath = '/donations/' . $filename;
        }

        $donation = Donation::create([
            'project_id' => $request->project_id,
            'donor_name' => $request->donor_name,
            'mobile_no' => $request->mobile_no,
            'amount' => $request->amount,
            'date' => $request->date ?? now()->toDateString(),
            'foundation_name' => $request->foundation_name,
            'is_marquee' => true,
            'payment_receipt' => $paymentReceiptPath,
        ]);

        return $this->successResponse($donation, 'Donation recorded successfully', 201);
    }

    /**
     * Toggle the marquee status of a donation.
     */
    public function toggleMarquee(Request $request, Donation $donation)
    {
        try {
            $donation->is_marquee = !$donation->is_marquee;
            $donation->save();

            return response()->json([
                'status' => 200,
                'message' => 'Marquee status updated successfully',
                'data' => $donation,
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function storeMarqueeMessage(Request $request)
    {
        try {
            $request->validate([
                'marquee_message' => 'required|string|max:255',
            ]);
            $marqueeMessage = MarqueeMessage::first();
            if ($marqueeMessage) {
                $marqueeMessage->update([
                    'marquee_message' => $request->marquee_message,
                ]);
            } else {
                $marqueeMessage = MarqueeMessage::create([
                    'marquee_message' => $request->marquee_message,
                ]);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Marquee message stored successfully',
                'data' => $marqueeMessage,
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getMarqueeMessage()
    {
        try {
            $marqueeMessage = MarqueeMessage::first();
            return response()->json([
                'status' => 200,
                'message' => 'Marquee message retrieved successfully',
                'data' => $marqueeMessage,
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getMarqueeMessagePublic()
    {
        try {
            $marqueeMessage = MarqueeMessage::first();
            $donors = Donation::where('is_marquee', true)
                ->orderBy('created_at', 'DESC')
                ->pluck('donor_name')
                ->toArray();

            $marqueeList = [];
            if ($marqueeMessage && $marqueeMessage->marquee_message) {
                $marqueeList[] = $marqueeMessage->marquee_message;
            }

            if (!empty($donors)) {
                $donorsText = "Special thanks to our donors: " . implode(', ', $donors);
                $marqueeList[] = $donorsText;
            }

            return response()->json([
                'status' => 200,
                'message' => 'Marquee message and donor list retrieved successfully',
                'data' => [
                    'marquee_message' => $marqueeMessage ? $marqueeMessage->marquee_message : null,
                    'marquee_donors' => $donors,
                    'combined_list' => $marqueeList,
                ],
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getDonors()
    {
        try {
            $donors = Donation::where('date', '>=', now()->subDays(7)->toDateString())
                ->orderBy('date', 'DESC')
                ->orderBy('created_at', 'DESC')
                ->get(['donor_name', 'amount', 'date']);

            return response()->json([
                'status' => 200,
                'message' => 'Donors for the last 7 days retrieved successfully',
                'data' => $donors,
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}