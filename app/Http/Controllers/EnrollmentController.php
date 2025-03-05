<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Models\PaymentLog;
use App\Services\PaystackService;
use App\Notifications\CourseEnrollmentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnrollmentController extends Controller
{
    protected $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Initialize a course enrollment with payment.
     */
    public function enrollInCourse(Request $request, $courseId)
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
                $enrollment = $this->createEnrollment($user, $course, 'free_' . uniqid());
                
                // Notify the educator
                $educator = User::find($course->user_id);
                $educator->notify(new CourseEnrollmentNotification($enrollment));
                
                DB::commit();
                
                return response()->json([
                    'message' => 'Successfully enrolled in free course',
                    'enrollment' => $enrollment
                ]);
            }

            // For paid courses, initiate payment with Paystack
            // Create a pending enrollment
            $enrollment = new CourseEnrollment([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => 'pending'
            ]);
            $enrollment->save();
            
            // Generate payment reference
            $reference = 'enroll_' . $course->id . '_' . uniqid();
            
            // Initialize payment
            $initResponse = $this->paystackService->initializeTransaction(
                $user->email,
                $course->price,
                route('enrollment.verify'),
                [
                    'enrollment_id' => $enrollment->id,
                    'course_id' => $course->id,
                    'user_id' => $user->id,
                    'payment_type' => 'course_enrollment'
                ]
            );
            
            if (!$initResponse['success']) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Payment initialization failed',
                    'error' => $initResponse['message']
                ], 500);
            }
            
            // Get response data
            $paymentData = $initResponse['data']['data'];
            
            // Update the enrollment with transaction reference
            $enrollment->transaction_reference = $paymentData['reference'];
            $enrollment->save();
            
            // Create payment log
            PaymentLog::create([
                'user_id' => $user->id,
                'transaction_reference' => $paymentData['reference'],
                'payment_type' => 'course_enrollment',
                'status' => 'pending',
                'amount' => $course->price,
                'course_id' => $course->id,
                'response_data' => $initResponse['data']
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Payment initialized',
                'payment_url' => $paymentData['authorization_url'],
                'reference' => $paymentData['reference'],
                'enrollment_id' => $enrollment->id
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course enrollment failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred during enrollment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Verify payment and activate enrollment.
     */
    public function verifyEnrollment(Request $request)
    {
        $request->validate([
            'reference' => 'required|string'
        ]);
        
        $reference = $request->reference;
        
        try {
            DB::beginTransaction();
            
            // Check for existing enrollment with this reference
            $enrollment = CourseEnrollment::where('transaction_reference', $reference)
                ->where('status', 'pending')
                ->first();
                
            if (!$enrollment) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Enrollment not found'
                ], 404);
            }
            
            // Find or create payment log
            $paymentLog = PaymentLog::where('transaction_reference', $reference)->first();
            
            if (!$paymentLog) {
                $paymentLog = PaymentLog::create([
                    'user_id' => $enrollment->user_id,
                    'transaction_reference' => $reference,
                    'payment_type' => 'course_enrollment',
                    'status' => 'pending',
                    'amount' => $enrollment->course->price,
                    'course_id' => $enrollment->course_id
                ]);
            }
            
            // Verify payment with Paystack
            $verification = $this->paystackService->verifyTransaction($reference);
            
            if (!$verification['success']) {
                // Update payment log status
                $paymentLog->status = 'failed';
                $paymentLog->response_data = array_merge($paymentLog->response_data ?? [], [
                    'verification_error' => $verification['message']
                ]);
                $paymentLog->save();
                
                DB::commit();
                
                return response()->json([
                    'message' => 'Payment verification failed',
                    'error' => $verification['message']
                ], 400);
            }
            
            $paymentData = $verification['data']['data'];
            
            // Check if payment was successful
            if ($paymentData['status'] !== 'success') {
                // Update payment log status
                $paymentLog->status = 'failed';
                $paymentLog->response_data = array_merge($paymentLog->response_data ?? [], [
                    'verification' => $paymentData
                ]);
                $paymentLog->save();
                
                DB::commit();
                
                return response()->json([
                    'message' => 'Payment was not successful',
                    'status' => $paymentData['status']
                ], 400);
            }
            
            // Update payment log status
            $paymentLog->status = 'success';
            $paymentLog->response_data = array_merge($paymentLog->response_data ?? [], [
                'verification' => $paymentData
            ]);
            $paymentLog->save();
            
            // Activate the enrollment
            $enrollment->status = 'active';
            $enrollment->save();
            
            // Notify the educator about the new enrollment
            $course = Course::find($enrollment->course_id);
            $educator = User::find($course->user_id);
            $educator->notify(new CourseEnrollmentNotification($enrollment));
            
            DB::commit();
            
            return response()->json([
                'message' => 'Payment verified and enrollment activated',
                'enrollment' => $enrollment
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment verification failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'An error occurred during payment verification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Webhook handler for Paystack notifications.
     */
    public function handleWebhook(Request $request)
    {
        // Verify webhook signature
        if (!$this->paystackService->verifyWebhookSignature(
            $request->getContent(),
            $request->header('x-paystack-signature')
        )) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }
        
        $event = $request->input('event');
        $data = $request->input('data');
        
        // Log the webhook event
        Log::info('Enrollment webhook received: ' . $event);
        
        try {
            if ($event === 'charge.success') {
                $metadata = $data['metadata'] ?? null;
                
                // Only process if this is a course enrollment payment
                if ($metadata && isset($metadata['payment_type']) && $metadata['payment_type'] === 'course_enrollment') {
                    $this->processSuccessfulEnrollmentPayment($data);
                }
            }
            
            return response()->json(['message' => 'Webhook processed']);
            
        } catch (\Exception $e) {
            Log::error('Error processing enrollment webhook: ' . $e->getMessage());
            return response()->json(['message' => 'Error processing webhook'], 500);
        }
    }
    
    /**
     * Process successful enrollment payment from webhook
     */
    private function processSuccessfulEnrollmentPayment($paymentData)
    {
        $reference = $paymentData['reference'];
        $metadata = $paymentData['metadata'] ?? null;
        
        if (!$metadata || !isset($metadata['enrollment_id'])) {
            return;
        }
        
        $enrollmentId = $metadata['enrollment_id'];
        
        DB::transaction(function () use ($enrollmentId, $reference, $paymentData) {
            // Find the enrollment
            $enrollment = CourseEnrollment::find($enrollmentId);
            
            if (!$enrollment || $enrollment->status !== 'pending') {
                return;
            }
            
            // Find or create payment log
            $paymentLog = PaymentLog::where('transaction_reference', $reference)->first();
            
            if (!$paymentLog) {
                $paymentLog = PaymentLog::create([
                    'user_id' => $enrollment->user_id,
                    'transaction_reference' => $reference,
                    'payment_type' => 'course_enrollment',
                    'status' => 'success',
                    'amount' => $enrollment->course->price,
                    'course_id' => $enrollment->course_id,
                    'response_data' => $paymentData
                ]);
            } else {
                $paymentLog->status = 'success';
                $paymentLog->response_data = array_merge($paymentLog->response_data ?? [], [
                    'webhook' => $paymentData
                ]);
                $paymentLog->save();
            }
            
            // Update enrollment status
            $enrollment->status = 'active';
            $enrollment->save();
            
            // Notify educator
            $course = Course::find($enrollment->course_id);
            $educator = User::find($course->user_id);
            $educator->notify(new CourseEnrollmentNotification($enrollment));
        });
    }
    
    /**
     * Get enrolled courses for the current user.
     */
    public function getUserEnrollments()
    {
        $user = auth()->user();
        $enrollments = $user->enrollments()
            ->with('course')
            ->whereIn('status', ['active', 'completed'])
            ->latest()
            ->get();
        
        return response()->json([
            'enrollments' => $enrollments
        ]);
    }
    
    /**
     * Update enrollment progress.
     */
    public function updateProgress(Request $request, $enrollmentId)
    {
        $request->validate([
            'progress' => 'required|numeric|min:0|max:100'
        ]);
        
        $user = auth()->user();
        $enrollment = CourseEnrollment::where('id', $enrollmentId)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $enrollment->updateProgress($request->progress);
        
        return response()->json([
            'message' => 'Progress updated',
            'enrollment' => $enrollment
        ]);
    }
    
    /**
     * Helper method to create direct enrollment (for free courses).
     */
    private function createEnrollment(User $user, Course $course, $reference)
    {
        $enrollment = new CourseEnrollment([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'transaction_reference' => $reference,
            'status' => 'active'
        ]);
        
        $enrollment->save();
        
        // Create payment log for tracking
        PaymentLog::create([
            'user_id' => $user->id,
            'transaction_reference' => $reference,
            'payment_type' => 'course_enrollment',
            'status' => 'success',
            'amount' => 0,
            'course_id' => $course->id,
            'metadata' => json_encode(['free_course' => true])
        ]);
        
        return $enrollment;
    }
}