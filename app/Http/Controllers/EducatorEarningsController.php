<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use App\Models\PaymentSplit;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EducatorEarningsController extends Controller
{
    protected $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Get educator banking information
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBankInfo()
    {
        $user = Auth::user();
        
        if ($user->role !== User::ROLE_EDUCATOR) {
            return response()->json([
                'message' => 'Only educators can access this feature'
            ], 403);
        }
        
        $profile = $user->profile;
        
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found',
                'has_bank_info' => false
            ], 404);
        }
        
        return response()->json([
            'has_bank_info' => !empty($profile->account_number) && !empty($profile->bank_code),
            'bank_info' => [
                'bank_name' => $profile->bank_name,
                'bank_code' => $profile->bank_code,
                'account_number' => $profile->account_number ? substr_replace($profile->account_number, '****', 3, 4) : null,
                'account_name' => $profile->account_name,
                'is_verified' => $profile->payment_info_verified,
                'has_subaccount' => !empty($profile->paystack_subaccount_code)
            ]
        ]);
    }
    
    /**
     * Update educator banking information
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBankInfo(Request $request)
    {
        $user = Auth::user();
        
        if ($user->role !== User::ROLE_EDUCATOR) {
            return response()->json([
                'message' => 'Only educators can access this feature'
            ], 403);
        }
        
        $request->validate([
            'bank_code' => 'required|string',
            'account_number' => 'required|string|size:10',
            'account_name' => 'nullable|string|max:255',
        ]);
        
        try {
            $profile = $user->profile;
            
            if (!$profile) {
                $profile = new Profile();
                $profile->user_id = $user->id;
            }
            
            // Verify account number with Paystack
            $resolveAccountResult = $this->paystackService->makeRequest('GET', 
                "/bank/resolve?account_number={$request->account_number}&bank_code={$request->bank_code}"
            );
            
            if (!$resolveAccountResult['success']) {
                return response()->json([
                    'message' => 'Unable to verify bank account',
                    'error' => $resolveAccountResult['message']
                ], 400);
            }
            
            $accountData = $resolveAccountResult['data']['data'];
            
            // Create or update profile with bank info
            $profile->bank_code = $request->bank_code;
            $profile->account_number = $request->account_number;
            $profile->account_name = $accountData['account_name'] ?? $request->account_name;
            
            // Get bank name from the bank code
            $banksResult = $this->paystackService->makeRequest('GET', "/bank");
            
            if ($banksResult['success']) {
                $banks = $banksResult['data']['data'];
                foreach ($banks as $bank) {
                    if ($bank['code'] === $request->bank_code) {
                        $profile->bank_name = $bank['name'];
                        break;
                    }
                }
            }
            
            // Create Paystack subaccount
            $businessName = $user->first_name . ' ' . $user->last_name;
            $subaccountResult = $this->paystackService->createSubaccount(
                $businessName,
                $request->bank_code,
                $request->account_number,
                '0', // No percentage charge
                'Educator account for ' . $user->username,
                $user->email,
                $businessName,
                null,
                [
                    'user_id' => $user->id,
                    'is_educator' => true
                ]
            );
            
            if (!$subaccountResult['success']) {
                return response()->json([
                    'message' => 'Failed to create payment account',
                    'error' => $subaccountResult['message']
                ], 500);
            }
            
            // Save the subaccount code
            $profile->paystack_subaccount_code = $subaccountResult['data']['data']['subaccount_code'];
            $profile->payment_info_verified = true;
            
            if ($profile->save()) {
                return response()->json([
                    'message' => 'Banking information updated successfully',
                    'bank_info' => [
                        'bank_name' => $profile->bank_name,
                        'bank_code' => $profile->bank_code,
                        'account_number' => substr_replace($profile->account_number, '****', 3, 4),
                        'account_name' => $profile->account_name,
                        'is_verified' => $profile->payment_info_verified,
                        'subaccount_code' => $profile->paystack_subaccount_code
                    ]
                ]);
            }
            
            return response()->json([
                'message' => 'Failed to update banking information'
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Error updating banking information: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while updating banking information',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get educator earnings
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEarnings(Request $request)
    {
        $user = Auth::user();
        
        if ($user->role !== User::ROLE_EDUCATOR) {
            return response()->json([
                'message' => 'Only educators can access earnings'
            ], 403);
        }
        
        // Define date filters
        $startDate = $request->start_date ? date('Y-m-d', strtotime($request->start_date)) : date('Y-m-01'); // First day of current month
        $endDate = $request->end_date ? date('Y-m-d', strtotime($request->end_date)) : date('Y-m-d'); // Today
        
        // Get payment splits for this educator
        $earnings = PaymentSplit::where('recipient_type', User::class)
            ->where('recipient_id', $user->id)
            ->where('status', 'success')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        // Calculate totals
        $totalEarnings = $earnings->sum('amount');
        $courseEarnings = $earnings->filter(function($split) {
                $metadata = json_decode($split->metadata, true) ?? [];
                return isset($metadata['payment_type']) && $metadata['payment_type'] === 'course_enrollment';
            })
            ->sum('amount');
            
        $tutoringEarnings = $earnings->filter(function($split) {
                $metadata = json_decode($split->metadata, true) ?? [];
                return isset($metadata['payment_type']) && $metadata['payment_type'] === 'tutoring';
            })
            ->sum('amount');
        
        // Get earnings by month for chart
        $monthlyEarnings = PaymentSplit::where('recipient_type', User::class)
            ->where('recipient_id', $user->id)
            ->where('status', 'success')
            ->where('created_at', '>=', date('Y-m-d', strtotime('-12 months')))
            ->select(
                DB::raw('SUM(amount) as total'),
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();
            
        return response()->json([
            'total_earnings' => $totalEarnings,
            'course_earnings' => $courseEarnings,
            'tutoring_earnings' => $tutoringEarnings,
            'earnings_breakdown' => $earnings->map(function($split) {
                $metadata = json_decode($split->metadata, true) ?? [];
                return [
                    'id' => $split->id,
                    'amount' => $split->amount,
                    'date' => $split->created_at->format('Y-m-d H:i:s'),
                    'transaction_reference' => $split->transaction_reference,
                    'payment_type' => $metadata['payment_type'] ?? 'unknown',
                    'description' => $metadata['description'] ?? 'Payment',
                    'platform_fee' => $metadata['platform_fee'] ?? 0,
                    'related_id' => $metadata['related_id'] ?? null,
                    'related_type' => $metadata['related_type'] ?? null,
                ];
            }),
            'monthly_earnings' => $monthlyEarnings
        ]);
    }
    
    /**
     * Get details of a specific earning
     * 
     * @param int $splitId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEarningDetails($splitId)
    {
        $user = Auth::user();
        
        if ($user->role !== User::ROLE_EDUCATOR) {
            return response()->json([
                'message' => 'Only educators can access earnings'
            ], 403);
        }
        
        $split = PaymentSplit::where('id', $splitId)
            ->where('recipient_type', User::class)
            ->where('recipient_id', $user->id)
            ->first();
            
        if (!$split) {
            return response()->json([
                'message' => 'Earning record not found'
            ], 404);
        }
        
        $metadata = json_decode($split->metadata, true) ?? [];
        $paymentType = $metadata['payment_type'] ?? 'unknown';
        $relatedId = $metadata['related_id'] ?? null;
        
        $additionalData = [];
        
        // Get additional details based on payment type
        if ($paymentType === 'course_enrollment' && $relatedId) {
            // Fetch course and enrollment details
            $enrollment = \App\Models\CourseEnrollment::with(['course', 'user'])
                ->where('id', $relatedId)
                ->first();
                
            if ($enrollment) {
                $additionalData['course'] = [
                    'id' => $enrollment->course->id,
                    'title' => $enrollment->course->title,
                    'price' => $enrollment->course->price,
                ];
                $additionalData['student'] = [
                    'id' => $enrollment->user->id,
                    'name' => $enrollment->user->first_name . ' ' . $enrollment->user->last_name,
                ];
            }
        }
        else if ($paymentType === 'tutoring' && $relatedId) {
            // Fetch hire session details
            $hireSession = DB::table('hire_sessions')
                ->join('users', 'users.id', '=', 'hire_sessions.learner_id')
                ->select('hire_sessions.*', 'users.first_name', 'users.last_name')
                ->where('hire_sessions.id', $relatedId)
                ->first();
                
            if ($hireSession) {
                $additionalData['tutoring_session'] = [
                    'id' => $hireSession->id,
                    'hours' => $hireSession->hours,
                    'total_amount' => $hireSession->payment_amount,
                    'student' => [
                        'id' => $hireSession->learner_id,
                        'name' => $hireSession->first_name . ' ' . $hireSession->last_name,
                    ]
                ];
            }
        }
        
        return response()->json([
            'id' => $split->id,
            'amount' => $split->amount,
            'percentage' => $split->percentage,
            'date' => $split->created_at->format('Y-m-d H:i:s'),
            'transaction_reference' => $split->transaction_reference,
            'status' => $split->status,
            'payment_type' => $paymentType,
            'description' => $metadata['description'] ?? 'Payment',
            'platform_fee' => $metadata['platform_fee'] ?? 0,
            'additional_data' => $additionalData
        ]);
    }
}