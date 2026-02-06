<?php

namespace App\Http\Controllers;

use App\Models\AlexPointsTransaction;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\HireRequest;
use App\Models\PaymentLog;
use App\Models\User;
use App\Notifications\CourseEnrollmentNotification;
use App\Notifications\HireRequestNotification;
use App\Services\AlexPointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AlexPointsPaymentController extends Controller
{
    protected $alexPointsService;

    public function __construct(AlexPointsService $alexPointsService)
    {
        $this->alexPointsService = $alexPointsService;
    }

    /**
     * Get point balance and conversion information for the current user
     */
    public function getPointsBalance()
    {
        $user = auth()->user();
        
        return response()->json([
            'points_balance' => $user->alex_points,
            'next_level' => $this->alexPointsService->getNextLevel($user),
            'current_level' => $this->alexPointsService->getUserLevel($user),
            'points_to_next_level' => $this->alexPointsService->getPointsToNextLevel($user)
        ]);
    }

    /**
     * Calculate points needed for a course purchase
     */
    public function calculatePointsForCourse(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        $user = auth()->user();
        
        // Free courses don't need points
        if ($course->price == 0) {
            return response()->json([
                'message' => 'This course is free and does not require points',
                'points_needed' => 0,
                'can_afford' => true
            ]);
        }
        
        // Calculate points needed based on user's level
        $pointsNeeded = $this->alexPointsService->currencyToPoints($course->price, $user);
        $canAfford = $this->alexPointsService->hasEnoughPoints($user, $pointsNeeded);
        
        return response()->json([
            'course' => $course->only('id', 'title', 'price'),
            'points_needed' => $pointsNeeded,
            'user_points' => $user->alex_points,
            'can_afford' => $canAfford,
            'points_remaining_after_purchase' => $canAfford ? $user->alex_points - $pointsNeeded : 0
        ]);
    }

    /**
     * Purchase a course using Alex points
     */
    public function purchaseCourseWithPoints(Request $request, $courseId)
    {
        // Find the course
        $course = Course::findOrFail($courseId);
        $user = auth()->user();
        
        // Check if user is already enrolled
        if ($user->isEnrolledIn($course)) {
            return response()->json([
                'message' => 'You are already enrolled in this course'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // If course is free, enroll directly
            if ($course->price == 0) {
                $enrollment = $this->createFreeEnrollment($user, $course);
                
                // Notify the educator
                $educator = User::find($course->user_id);
                $educator->notify(new CourseEnrollmentNotification($enrollment));
                
                DB::commit();
                
                return response()->json([
                    'message' => 'Successfully enrolled in free course',
                    'enrollment' => $enrollment
                ]);
            }

            // Calculate points needed
            $pointsNeeded = $this->alexPointsService->currencyToPoints($course->price, $user);
            
            // Check if user has enough points
            if (!$this->alexPointsService->hasEnoughPoints($user, $pointsNeeded)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Not enough points to purchase this course',
                    'points_needed' => $pointsNeeded,
                    'user_points' => $user->alex_points
                ], 400);
            }
            
            // Generate reference
            $reference = 'points_enroll_' . $course->id . '_' . uniqid();
            
            // Spend points
            $transaction = $this->alexPointsService->spendPoints(
                $user, 
                $pointsNeeded, 
                'course_enrollment', 
                $course->id,
                "Enrolled in course: {$course->title}",
                [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'course_price' => $course->price
                ]
            );
            
            if (!$transaction) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Failed to process points transaction'
                ], 500);
            }
            
            // Create enrollment
            $enrollment = new CourseEnrollment([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => 'active',
                'transaction_reference' => $reference,
                'payment_method' => 'alex_points',
                'points_used' => $pointsNeeded
            ]);
            $enrollment->save();
            
            // Create payment log
            try {
                PaymentLog::create([
                    'user_id' => $user->id,
                    'transaction_reference' => $reference,
                    'payment_type' => 'course_enrollment',
                    'payment_method' => 'alex_points',
                    'status' => 'success',
                    'amount' => $course->price,
                    'points_used' => $pointsNeeded,
                    'course_id' => $course->id,
                    'metadata' => json_encode([
                        'points_transaction_id' => $transaction->id,
                        'points_used' => $pointsNeeded
                    ])
                ]);
            } catch (\Exception $e) {
                // Log error but continue if payment log table doesn't exist
                Log::warning('Error creating payment log: ' . $e->getMessage());
            }
            
            // Notify the educator
            $educator = User::find($course->user_id);
            $educator->notify(new CourseEnrollmentNotification($enrollment));
            
            DB::commit();
            
            return response()->json([
                'message' => 'Successfully enrolled in course using Alex points',
                'enrollment' => $enrollment,
                'points_used' => $pointsNeeded,
                'points_remaining' => $user->alex_points
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course enrollment with points failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred during enrollment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate points needed for hiring an educator
     */
    public function calculatePointsForHiring(Request $request)
    {
        $validated = $request->validate([
            'tutor_id' => 'required|uuid|exists:users,id',
            'duration' => 'required|string',
        ]);
        
        $user = auth()->user();
        $tutor = User::findOrFail($validated['tutor_id']);
        
        // Check if tutor is an educator
        if ($tutor->role !== User::ROLE_EDUCATOR) {
            return response()->json([
                'message' => 'The selected user is not an educator'
            ], 400);
        }
        
        // Check if educator is verified
        if (!$tutor->is_verified) {
            return response()->json([
                'message' => 'This educator is not verified yet and cannot be hired',
                'verification_status' => $tutor->verification_status
            ], 400);
        }
        
        // Get the educator's hire rate
        $profile = $tutor->profile;
        if (!$profile || !$profile->hire_rate) {
            return response()->json([
                'message' => 'This educator has not set up their hiring rate yet'
            ], 400);
        }
        
        $baseRate = $profile->hire_rate;
        $currency = $profile->hire_currency ?? 'USD';
        
        // Parse duration to calculate total hours
        $duration = $validated['duration'];
        $hours = $this->calculateHoursFromDuration($duration);
        
        // Calculate total amount
        $totalAmount = $baseRate * $hours;
        
        // Calculate points needed
        $pointsNeeded = $this->alexPointsService->currencyToPoints($totalAmount, $user);
        $canAfford = $this->alexPointsService->hasEnoughPoints($user, $pointsNeeded);
        
        return response()->json([
            'tutor' => $tutor->only('id', 'name', 'username'),
            'base_rate' => $baseRate,
            'currency' => $currency,
            'duration' => $duration,
            'hours' => $hours,
            'total_amount' => $totalAmount,
            'points_needed' => $pointsNeeded,
            'user_points' => $user->alex_points,
            'can_afford' => $canAfford,
            'points_remaining_after_purchase' => $canAfford ? $user->alex_points - $pointsNeeded : 0
        ]);
    }

    /**
     * Hire an educator using Alex points
     */
    public function hireEducatorWithPoints(Request $request)
    {
        $validated = $request->validate([
            'tutor_id' => 'required|uuid|exists:users,id',
            'topic' => 'required|string|max:255',
            'message' => 'nullable|string',
            'medium' => 'nullable|string',
            'duration' => 'required|string',
        ]);
        
        try {
            $client = auth()->user();
            $tutor = User::findOrFail($validated['tutor_id']);
            
            // Check if tutor is an educator
            if ($tutor->role !== User::ROLE_EDUCATOR) {
                return response()->json([
                    'message' => 'The selected user is not an educator'
                ], 400);
            }
            
            // Check if educator is verified
            if (!$tutor->is_verified) {
                return response()->json([
                    'message' => 'This educator is not verified yet and cannot be hired',
                    'verification_status' => $tutor->verification_status
                ], 400);
            }
            
            // Get the educator's hire rate
            $profile = $tutor->profile;
            if (!$profile || !$profile->hire_rate) {
                return response()->json([
                    'message' => 'This educator has not set up their hiring rate yet'
                ], 400);
            }
            
            $baseRate = $profile->hire_rate;
            $currency = $profile->hire_currency ?? 'USD';
            
            // Parse duration to calculate total hours
            $duration = $validated['duration'];
            $hours = $this->calculateHoursFromDuration($duration);
            
            // Calculate total amount
            $totalAmount = $baseRate * $hours;
            
            // Calculate points needed
            $pointsNeeded = $this->alexPointsService->currencyToPoints($totalAmount, $client);
            
            // Check if user has enough points
            if (!$this->alexPointsService->hasEnoughPoints($client, $pointsNeeded)) {
                return response()->json([
                    'message' => 'Not enough points to hire this educator',
                    'points_needed' => $pointsNeeded,
                    'user_points' => $client->alex_points
                ], 400);
            }
            
            DB::beginTransaction();
            
            // Generate reference
            $reference = 'points_hire_' . uniqid() . '_' . time();
            
            // Spend points
            $transaction = $this->alexPointsService->spendPoints(
                $client, 
                $pointsNeeded, 
                'hire_educator', 
                $tutor->id,
                "Hired educator: {$tutor->name} for {$validated['topic']}",
                [
                    'tutor_id' => $tutor->id,
                    'tutor_name' => $tutor->name,
                    'topic' => $validated['topic'],
                    'duration' => $duration,
                    'base_rate' => $baseRate,
                    'total_amount' => $totalAmount,
                    'currency' => $currency
                ]
            );
            
            if (!$transaction) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Failed to process points transaction'
                ], 500);
            }
            
            // Create hire request
            $hireRequest = new HireRequest([
                'client_id' => $client->id,
                'tutor_id' => $tutor->id,
                'status' => 'pending',
                'topic' => $validated['topic'],
                'message' => $validated['message'] ?? '',
                'medium' => $validated['medium'] ?? 'online',
                'duration' => $duration,
                'rate_per_session' => $totalAmount,
                'currency' => $currency,
                'payment_status' => 'paid',
                'transaction_reference' => $reference,
                'payment_method' => 'alex_points',
                'points_used' => $pointsNeeded
            ]);
            
            $hireRequest->save();
            
            // Create payment log
            try {
                PaymentLog::create([
                    'user_id' => $client->id,
                    'transaction_reference' => $reference,
                    'payment_type' => 'hire_educator',
                    'payment_method' => 'alex_points',
                    'status' => 'success',
                    'amount' => $totalAmount,
                    'points_used' => $pointsNeeded,
                    'metadata' => json_encode([
                        'points_transaction_id' => $transaction->id,
                        'points_used' => $pointsNeeded,
                        'tutor_id' => $tutor->id,
                        'topic' => $validated['topic']
                    ])
                ]);
            } catch (\Exception $e) {
                // Log error but continue if payment log table doesn't exist
                Log::warning('Error creating payment log: ' . $e->getMessage());
            }
            
            // Notify the educator
            $tutor->notify(new HireRequestNotification(
                $client, 
                "A user has requested a session with you on the topic: {$validated['topic']}. Payment has been made with Alex points.",
                'new_request'
            ));
            
            DB::commit();
            
            return response()->json([
                'message' => 'Hire request created and paid for with Alex points',
                'hire_request' => $hireRequest,
                'points_used' => $pointsNeeded,
                'points_remaining' => $client->alex_points
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error hiring educator with points: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get points transaction history for current user
     */
    public function getTransactionHistory(Request $request)
    {
        $user = auth()->user();
        $type = $request->query('type'); // purchase, refund, earned
        
        $query = AlexPointsTransaction::where('user_id', $user->id);
        
        if ($type === 'purchase') {
            $query->where('action_type', 'purchase');
        } elseif ($type === 'refund') {
            $query->where('action_type', 'refund');
        } elseif ($type === 'earned') {
            $query->where('points', '>', 0)->where('action_type', '!=', 'refund');
        }
        
        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return response()->json([
            'transactions' => $transactions
        ]);
    }

    /**
     * Helper method to create free enrollment
     */
    private function createFreeEnrollment(User $user, Course $course)
    {
        $reference = 'free_' . Str::uuid();
        
        $enrollment = new CourseEnrollment([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'transaction_reference' => $reference,
            'payment_method' => 'free',
            'status' => 'active'
        ]);
        
        $enrollment->save();
        
        // Create payment log
        try {
            PaymentLog::create([
                'user_id' => $user->id,
                'transaction_reference' => $reference,
                'payment_type' => 'course_enrollment',
                'payment_method' => 'free',
                'status' => 'success',
                'amount' => 0,
                'course_id' => $course->id,
                'metadata' => json_encode(['free_course' => true])
            ]);
        } catch (\Exception $e) {
            // Log error but continue if payment log table doesn't exist
            Log::warning('Error creating payment log: ' . $e->getMessage());
        }
        
        return $enrollment;
    }

    /**
     * Helper method to calculate hours from duration string
     */
    private function calculateHoursFromDuration($duration)
    {
        // Default to 1 hour if parsing fails
        $hours = 1;
        
        // Try to extract the numeric value and unit
        if (preg_match('/(\d+)\s*(\w+)/', $duration, $matches)) {
            $value = (int) $matches[1];
            $unit = strtolower(rtrim($matches[2], 's')); // Remove trailing 's' to handle plurals
            
            switch ($unit) {
                case 'hour':
                    $hours = $value;
                    break;
                case 'day':
                    $hours = $value * 24; // Assuming 24 hours per day
                    break;
                case 'week':
                    $hours = $value * 24 * 7; // Assuming 7 days per week
                    break;
                case 'month':
                    $hours = $value * 24 * 30; // Assuming 30 days per month
                    break;
                default:
                    $hours = $value; // Default to treating as hours
            }
        }
        
        return $hours;
    }
}