<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\DonationReceipt;
use App\Models\Scopes\DummyVisibilityScope;
use App\Models\User;
use App\Notifications\DonationReceivedAdminNotification;
use App\Notifications\DonationReceiptReadyNotification;
use App\Traits\ApiResponse;
use App\Traits\ProtectsDummyRecords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Exception;
use App\Models\MarqueeMessage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class DonationController extends Controller
{
    use ApiResponse, ProtectsDummyRecords;

    /**
     * Display a listing of donations with search and filters.
     */
    public function index(Request $request)
    {

        try {
            $limit = $request->input('limit', null);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);

            $query = Donation::with(['project', 'receipt']);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('donor_name', 'like', '%' . $search . '%')
                        ->orWhere('amount', 'like', '%' . $search . '%')
                        ->orWhereHas('project', function ($pq) use ($search) {
                            $pq->where('name', 'like', '%' . $search . '%');
                        });

                    if (stripos('Rotary Club', $search) !== false) {
                        $q->orWhereNull('project_id');
                    }
                });
            }

            if ($request->has('project_id') && $request->project_id) {
                $query->where('project_id', $request->project_id);
            }

            if ($request->has('amount') && $request->amount) {
                $query->where('amount', $request->amount);
            }

            if ($request->has('start_date') && $request->start_date) {
                $query->whereDate('date', '>=', $request->start_date);
            }

            if ($request->has('end_date') && $request->end_date) {
                $query->whereDate('date', '<=', $request->end_date);
            }

            if ($limit) {
                $donations = $query->orderBy('created_at', 'DESC')
                    ->paginate($limit, ['*'], 'page', $page);

                $donations->getCollection()->transform(function ($donation) {
                    return $this->formatDonation($donation);
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
                    return $this->formatDonation($donation);
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

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a new donation record.
     */
    public function store(Request $request)
    {
        try {
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

            DB::beginTransaction();

            $user = User::where('phone', $request->mobile_no)->first();

            // Public route, so the bearer token may be absent even for a signed-in
            // caller — resolve the review account from the phone number too, or a
            // reviewer's donation would land in real totals.
            $isDummy = DummyVisibilityScope::actingUserIsDummy() || (bool) $user?->is_dummy;

            if (!$user) {
                $user = User::create([
                    'name' => $request->donor_name,
                    'email' => 'donor_' . $request->mobile_no . '@rotary.local',
                    'phone' => $request->mobile_no,
                    'password' => Hash::make(Str::random(16)),
                    'role' => 'Non-Member',
                    'status' => 1,
                    'is_dummy' => $isDummy,
                ]);
            }

            $donation = Donation::create([
                'project_id' => $request->project_id,
                'donor_name' => $request->donor_name,
                'mobile_no' => $request->mobile_no,
                'amount' => $request->amount,
                'date' => $request->date ?? now()->toDateString(),
                'time' => now()->toTimeString(),
                'foundation_name' => $request->foundation_name,
                // Review donations stay off the public marquee.
                'is_marquee' => !$isDummy,
                'payment_receipt' => $paymentReceiptPath,
                'is_dummy' => $isDummy,
            ]);

            $receipt = DonationReceipt::create([
                'user_id' => $user->id,
                'donation_id' => $donation->id,
                'receipt_no' => 'REC-' . str_pad($donation->id, 6, '0', STR_PAD_LEFT),
                'transaction_id' => '#RR-' . (80000 + $donation->id),
                'status' => 'Completed',
            ]);

            $user->load('member');
            $pdfPath = $this->generateAndStoreReceiptPdf($donation->load('project'), $user, $receipt);
            $receipt->update(['pdf_path' => $pdfPath]);

            // Review donations are visible to admins in the list; don't page them.
            if (!$isDummy) {
                $admins = User::where('role', 'Super Admin')->get();
                Notification::send($admins, new DonationReceivedAdminNotification($donation));
            }

            DB::commit();

            return $this->successResponse([
                'donation' => $this->formatDonation($donation->load('project')),
                'receipt' => [
                    'id' => $receipt->id,
                    'receipt_no' => $receipt->receipt_no,
                    'transaction_id' => $receipt->transaction_id,
                    'status' => $receipt->status,
                    'pdf_url' => asset($receipt->pdf_path),
                ],
            ], 'Donation recorded successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return $this->errorResponse('Validation error', 422, $e->errors());
        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return $this->errorResponse('Failed to record donation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle the marquee status of a donation.
     */
    public function toggleMarquee(Request $request, Donation $donation)
    {
        try {
            if ($blocked = $this->blockIfDummy($donation)) {
                return $blocked;
            }

            $donation->is_marquee = !$donation->is_marquee;
            $donation->save();

            return response()->json([
                'status' => 200,
                'message' => 'Marquee status updated successfully',
                'data' => $donation,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getMarqueeMessagePublic()
    {
        try {
            $marqueeMessage = MarqueeMessage::first();
            $donors = Donation::realOnly()
                ->where('is_marquee', true)
                ->orderBy('created_at', 'DESC')
                ->pluck('donor_name')
                ->toArray();

            $marqueeList = [];
            $prefix = ($marqueeMessage && $marqueeMessage->marquee_message) ? $marqueeMessage->marquee_message : "";

            if (!empty($donors)) {
                foreach ($donors as $donor) {
                    $marqueeList[] = trim($prefix . " " . $donor);
                }
            } elseif ($prefix) {
                $marqueeList[] = $prefix;
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getDonors()
    {
        try {
            $donors = Donation::realOnly()
                ->where('date', '>=', now()->subDays(7)->toDateString())
                ->orderBy('date', 'DESC')
                ->orderBy('created_at', 'DESC')
                ->get(['donor_name', 'amount', 'date']);

            return response()->json([
                'status' => 200,
                'message' => 'Donors for the last 7 days retrieved successfully',
                'data' => $donors,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function myDonations(Request $request)
    {
        try {
            $user = $request->user();
            $limit = $request->input('limit', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search', null);

            $query = Donation::with('project')
                ->where('mobile_no', $user->phone);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('donor_name', 'like', '%' . $search . '%')
                        ->orWhere('amount', 'like', '%' . $search . '%')
                        ->orWhereHas('project', function ($pq) use ($search) {
                            $pq->where('name', 'like', '%' . $search . '%');
                        });

                    if (stripos('Rotary Club', $search) !== false) {
                        $q->orWhereNull('project_id');
                    }
                });
            }

            if ($request->has('start_date') && $request->start_date) {
                $query->whereDate('date', '>=', $request->start_date);
            }

            if ($request->has('end_date') && $request->end_date) {
                $query->whereDate('date', '<=', $request->end_date);
            }

            if ($limit) {
                $donations = $query->orderBy('created_at', 'DESC')
                    ->paginate($limit, ['*'], 'page', $page);

                $donations->getCollection()->transform(function ($donation) {
                    return [
                        'id' => $donation->id,
                        'amount' => $donation->amount,
                        'date' => $donation->date,
                        'time' => $donation->time ? date('h:i a', strtotime($donation->time)) : ($donation->created_at ? $donation->created_at->format('h:i a') : null),
                        'project_id' => $donation->project_id,
                        'project' => $donation->project,
                        'donated_for' => $donation->project?->name ?? 'Rotary Club',
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
                        'amount' => $donation->amount,
                        'date' => $donation->date,
                        'time' => $donation->time ? date('h:i a', strtotime($donation->time)) : ($donation->created_at ? $donation->created_at->format('h:i a') : null),
                        'project_id' => $donation->project_id,
                        'project' => $donation->project,
                        'donated_for' => $donation->project?->name ?? 'Rotary Club',
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
                'message' => 'Your donations retrieved successfully',
                'data' => $response['data'],
                'pagination' => $response['pagination'] ?? null,
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch donations: ' . $e->getMessage(), 500);
        }
    }

    public function showMyDonation(Request $request, $id)
    {
        try {
            $user = $request->user();
            $donation = Donation::with('project')
                ->where('mobile_no', $user->phone)
                ->findOrFail($id);

            return $this->successResponse($this->formatDonation($donation), 'Donation details retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Donation not found', 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch donation details: ' . $e->getMessage(), 500);
        }
    }

    public function generateReceipt(Request $request, $id)
    {
        try {
            $user = $request->user();
            $donation = Donation::with('project')
                ->where('mobile_no', $user->phone)
                ->where('id', $id)
                ->firstOrFail();

            $user->load('member');
            $receipt = $this->findOrCreateReceipt($donation, $user);
            $pdfPath = $this->generateAndStoreReceiptPdf($donation, $user, $receipt);

            if ($receipt->pdf_path !== $pdfPath) {
                $receipt->update(['pdf_path' => $pdfPath]);
            }

            return response()->download(
                public_path($pdfPath),
                'receipt_' . $donation->id . '.pdf',
                ['Content-Type' => 'application/pdf']
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Donation not found', 404);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to generate receipt: ' . $e->getMessage(), 500);
        }
    }

    private function formatDonation($donation)
    {
        return [
            'id' => $donation->id,
            'donor_name' => $donation->donor_name,
            'mobile_no' => $donation->mobile_no,
            'amount' => $donation->amount,
            'donated_for' => $donation->project?->name ?? 'Rotary Club',
            'project_id' => $donation->project_id,
            'project' => $donation->project,
            'date' => $donation->date,
            'time' => $donation->time ? date('h:i a', strtotime($donation->time)) : ($donation->created_at ? $donation->created_at->format('h:i a') : null),
            'is_marquee' => $donation->is_marquee,
            'payment_receipt' => $donation->receipt?->pdf_path ? asset($donation->receipt->pdf_path) : null,
            'is_dummy' => $donation->is_dummy,
        ];
    }

    private function findOrCreateReceipt(Donation $donation, User $user): DonationReceipt
    {
        return DonationReceipt::firstOrCreate(
            ['donation_id' => $donation->id],
            [
                'user_id' => $user->id,
                'receipt_no' => 'REC-' . str_pad($donation->id, 6, '0', STR_PAD_LEFT),
                'transaction_id' => '#RR-' . (80000 + $donation->id),
                'status' => 'Completed',
            ]
        );
    }

    private function generateAndStoreReceiptPdf(Donation $donation, User $user, DonationReceipt $receipt): string
    {
        $pdfDirectory = public_path('receipts');

        if (!File::exists($pdfDirectory)) {
            File::makeDirectory($pdfDirectory, 0755, true);
        }

        $relativePath = '/receipts/' . $receipt->receipt_no . '.pdf';
        $pdf = Pdf::loadView('receipts.donation', [
            'receipt' => $receipt,
            'donation' => $donation,
            'user' => $user,
            'receiptData' => [
                'receipt_no' => $receipt->receipt_no,
                'amount' => $donation->amount,
                'status' => $receipt->status,
                'donor_name' => $donation->donor_name,
                'date' => date('M d, Y', strtotime($donation->date)),
                'time' => $donation->time ? date('h:i a', strtotime($donation->time)) : ($donation->created_at ? $donation->created_at->format('h:i a') : null),
                'payment_method' => 'Online',
                'transaction_id' => $receipt->transaction_id,
                'member_id' => $user->member?->member_id,
                'donated_for' => $donation->project?->name,
                'foundation_name' => $donation->foundation_name,
            ],
        ]);

        File::put(public_path($relativePath), $pdf->output());

        return $relativePath;
    }
}
