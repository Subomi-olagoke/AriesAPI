<?php

namespace App\Http\Controllers;

use App\Models\PaymentSplit;
use App\Models\User;
use App\Models\Course;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PaymentSplitController extends Controller
{
    protected $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Test a split payment for course enrollment
     * This is an example endpoint to demonstrate how to set up and process a split payment
     * The real implementation should be integrated into the EnrollmentController and HireRequestController
     */
    public function testCourseSplit(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $user = Auth::user();
        $course = Course::findOrFail($request->course_id);
        $educator = User::findOrFail($course->user_id);

        if ($user->isEnrolledIn($course)) {
            return response()->json([
                'message' => 'You are already enrolled in this course'
            ], 400);
        }

        // Platform fee percentage (5%)
        $platformFeePercentage = 5;
        
        try {
            DB::beginTransaction();

            // Check if educator already has a subaccount in their profile
            $educatorProfile = $educator->profile;
            $educatorSubaccountCode = null;
            
            if ($educatorProfile && $educatorProfile->paystack_subaccount_code) {
                // Use existing subaccount
                $educatorSubaccountCode = $educatorProfile->paystack_subaccount_code;
            } else {
                // Create a test subaccount for demonstration
                $educatorSubaccountResult = $this->paystackService->createSubaccount(
                    $educator->first_name . ' ' . $educator->last_name, // Business name
                    '044', // Access Bank code
                    '0000000000', // Example account number
                    '0', // No additional charges
                    'Educator subaccount for ' . $educator->username,
                    $educator->email,
                    $educator->first_name . ' ' . $educator->last_name,
                    '08000000000' // Example phone
                );
                
                if (!$educatorSubaccountResult['success']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Failed to create educator subaccount',
                        'error' => $educatorSubaccountResult['message']
                    ], 500);
                }
                
                $educatorSubaccountCode = $educatorSubaccountResult['data']['data']['subaccount_code'];
                
                // Save the subaccount to the educator's profile if possible
                if ($educatorProfile) {
                    $educatorProfile->paystack_subaccount_code = $educatorSubaccountCode;
                    $educatorProfile->save();
                }
            }

            // Create a split payment configuration
            $splitResult = $this->paystackService->createSplitConfig(
                'Course_' . $course->id . '_Split',
                [
                    [
                        'subaccount' => $educatorSubaccountCode,
                        'share' => 100 - $platformFeePercentage // 95%
                    ]
                ],
                'percentage',
                'NGN'
            );

            if (!$splitResult['success']) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Failed to create split configuration',
                    'error' => $splitResult['message']
                ], 500);
            }

            $splitCode = $splitResult['data']['data']['split_code'];

            // Initialize a transaction with split code
            $initResult = $this->paystackService->initializeTransaction(
                $user->email,
                $course->price,
                route('enrollment.verify'),
                [
                    'course_id' => $course->id,
                    'user_id' => $user->id,
                    'payment_type' => 'course_enrollment',
                    'is_split' => true,
                    'platform_fee_percentage' => $platformFeePercentage,
                    'educator_id' => $educator->id
                ],
                ['type' => 'split', 'split_code' => $splitCode]
            );

            if (!$initResult['success']) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Payment initialization failed',
                    'error' => $initResult['message']
                ], 500);
            }

            $reference = $initResult['data']['data']['reference'];

            // Create enrollment record (pending)
            $enrollment = $course->enrollments()->create([
                'user_id' => $user->id,
                'status' => 'pending',
                'transaction_reference' => $reference
            ]);

            // Create payment log
            $paymentLog = DB::table('payment_logs')->insertGetId([
                'user_id' => $user->id,
                'transaction_reference' => $reference,
                'payment_type' => 'course_enrollment',
                'status' => 'pending',
                'amount' => $course->price,
                'course_id' => $course->id,
                'response_data' => json_encode($initResult['data']),
                'metadata' => json_encode([
                    'is_split' => true,
                    'split_code' => $splitCode,
                    'platform_fee_percentage' => $platformFeePercentage
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create payment splits
            $platformFeeAmount = PaymentSplit::calculateSplitAmount($course->price, $platformFeePercentage);
            $educatorAmount = $course->price - $platformFeeAmount;

            // Platform fee split
            PaymentSplit::create([
                'payment_log_id' => $paymentLog,
                'recipient_type' => 'platform',
                'recipient_id' => 0, // Platform itself
                'amount' => $platformFeeAmount,
                'percentage' => $platformFeePercentage,
                'status' => 'pending',
                'transaction_reference' => $reference,
                'metadata' => [
                    'description' => 'Platform fee'
                ]
            ]);

            // Educator split
            PaymentSplit::create([
                'payment_log_id' => $paymentLog,
                'recipient_type' => User::class,
                'recipient_id' => $educator->id,
                'amount' => $educatorAmount,
                'percentage' => 100 - $platformFeePercentage,
                'status' => 'pending',
                'transaction_reference' => $reference,
                'metadata' => [
                    'description' => 'Educator payment',
                    'subaccount_code' => $educatorSubaccountCode,
                    'payment_type' => 'course_enrollment',
                    'related_id' => $enrollment->id,
                    'related_type' => 'enrollment'
                ]
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Split payment initialized',
                'payment_url' => $initResult['data']['data']['authorization_url'],
                'reference' => $reference,
                'split_details' => [
                    'total_amount' => $course->price,
                    'platform_fee' => $platformFeeAmount,
                    'educator_amount' => $educatorAmount,
                    'platform_percentage' => $platformFeePercentage,
                    'educator_percentage' => 100 - $platformFeePercentage
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Split payment initialization failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while initializing split payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test a split payment for hiring an educator
     */
    public function testHireSplit(Request $request)
    {
        $request->validate([
            'educator_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'hours' => 'required|integer|min:1'
        ]);

        $user = Auth::user();
        $educator = User::findOrFail($request->educator_id);

        // Check if educator has the right role
        if ($educator->role !== User::ROLE_EDUCATOR) {
            return response()->json([
                'message' => 'Selected user is not an educator'
            ], 400);
        }

        // Check if educator is verified
        if (!$educator->is_verified) {
            return response()->json([
                'message' => 'This educator is not verified yet and cannot be hired',
                'verification_status' => $educator->verification_status
            ], 400);
        }

        // Platform fee percentage (5%)
        $platformFeePercentage = 5;
        $totalAmount = $request->amount;
        
        try {
            DB::beginTransaction();

            // Check if educator already has a subaccount in their profile
            $educatorProfile = $educator->profile;
            $educatorSubaccountCode = null;
            
            if ($educatorProfile && $educatorProfile->paystack_subaccount_code) {
                // Use existing subaccount
                $educatorSubaccountCode = $educatorProfile->paystack_subaccount_code;
            } else {
                // Create a test subaccount for demonstration
                $educatorSubaccountResult = $this->paystackService->createSubaccount(
                    $educator->first_name . ' ' . $educator->last_name, // Business name
                    '044', // Access Bank code
                    '0000000000', // Example account number
                    '0', // No additional charges
                    'Educator subaccount for ' . $educator->username,
                    $educator->email,
                    $educator->first_name . ' ' . $educator->last_name,
                    '08000000000' // Example phone
                );
                
                if (!$educatorSubaccountResult['success']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Failed to create educator subaccount',
                        'error' => $educatorSubaccountResult['message']
                    ], 500);
                }
                
                $educatorSubaccountCode = $educatorSubaccountResult['data']['data']['subaccount_code'];
                
                // Save the subaccount to the educator's profile if possible
                if ($educatorProfile) {
                    $educatorProfile->paystack_subaccount_code = $educatorSubaccountCode;
                    $educatorProfile->save();
                }
            }

            // Create a split payment configuration
            $splitResult = $this->paystackService->createSplitConfig(
                'Hire_' . $educator->id . '_Split',
                [
                    [
                        'subaccount' => $educatorSubaccountCode,
                        'share' => 100 - $platformFeePercentage // 95%
                    ]
                ],
                'percentage',
                'NGN'
            );

            if (!$splitResult['success']) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Failed to create split configuration',
                    'error' => $splitResult['message']
                ], 500);
            }

            $splitCode = $splitResult['data']['data']['split_code'];

            // Initialize a transaction with split code
            $initResult = $this->paystackService->initializeTransaction(
                $user->email,
                $totalAmount,
                route('tutoring.payment.verify'),
                [
                    'user_id' => $user->id,
                    'payment_type' => 'tutoring',
                    'is_split' => true,
                    'platform_fee_percentage' => $platformFeePercentage,
                    'educator_id' => $educator->id,
                    'hours' => $request->hours
                ],
                ['type' => 'split', 'split_code' => $splitCode]
            );

            if (!$initResult['success']) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Payment initialization failed',
                    'error' => $initResult['message']
                ], 500);
            }

            $reference = $initResult['data']['data']['reference'];

            // Create a hire request (normally you would do this in HireRequestController)
            $hireRequest = DB::table('hire_requests')->insertGetId([
                'client_id' => $user->id,
                'tutor_id' => $educator->id,
                'status' => 'pending',
                'hours' => $request->hours,
                'amount' => $totalAmount,
                'message' => 'Test hire request with split payment',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create payment log
            $paymentLog = DB::table('payment_logs')->insertGetId([
                'user_id' => $user->id,
                'transaction_reference' => $reference,
                'payment_type' => 'tutoring',
                'status' => 'pending',
                'amount' => $totalAmount,
                'response_data' => json_encode($initResult['data']),
                'metadata' => json_encode([
                    'is_split' => true,
                    'split_code' => $splitCode,
                    'platform_fee_percentage' => $platformFeePercentage,
                    'hire_request_id' => $hireRequest
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create payment splits
            $platformFeeAmount = PaymentSplit::calculateSplitAmount($totalAmount, $platformFeePercentage);
            $educatorAmount = $totalAmount - $platformFeeAmount;

            // Platform fee split
            PaymentSplit::create([
                'payment_log_id' => $paymentLog,
                'recipient_type' => 'platform',
                'recipient_id' => 0, // Platform itself
                'amount' => $platformFeeAmount,
                'percentage' => $platformFeePercentage,
                'status' => 'pending',
                'transaction_reference' => $reference,
                'metadata' => [
                    'description' => 'Platform fee'
                ]
            ]);

            // Educator split
            PaymentSplit::create([
                'payment_log_id' => $paymentLog,
                'recipient_type' => User::class,
                'recipient_id' => $educator->id,
                'amount' => $educatorAmount,
                'percentage' => 100 - $platformFeePercentage,
                'status' => 'pending',
                'transaction_reference' => $reference,
                'metadata' => [
                    'description' => 'Educator payment',
                    'subaccount_code' => $educatorSubaccountCode,
                    'payment_type' => 'tutoring',
                    'related_id' => $hireRequest,
                    'related_type' => 'hire_request'
                ]
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Split payment for tutor hire initialized',
                'payment_url' => $initResult['data']['data']['authorization_url'],
                'reference' => $reference,
                'split_details' => [
                    'total_amount' => $totalAmount,
                    'platform_fee' => $platformFeeAmount,
                    'educator_amount' => $educatorAmount,
                    'platform_percentage' => $platformFeePercentage,
                    'educator_percentage' => 100 - $platformFeePercentage
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Split payment initialization failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while initializing split payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get split payment details by reference
     */
    public function getSplitDetails($reference)
    {
        $paymentLog = DB::table('payment_logs')->where('transaction_reference', $reference)->first();
        
        if (!$paymentLog) {
            return response()->json([
                'message' => 'Payment not found'
            ], 404);
        }
        
        $splits = PaymentSplit::where('payment_log_id', $paymentLog->id)->get();
        
        if ($splits->isEmpty()) {
            return response()->json([
                'message' => 'No split details found for this payment'
            ], 404);
        }
        
        return response()->json([
            'payment_reference' => $reference,
            'total_amount' => $paymentLog->amount,
            'status' => $paymentLog->status,
            'splits' => $splits
        ]);
    }
}