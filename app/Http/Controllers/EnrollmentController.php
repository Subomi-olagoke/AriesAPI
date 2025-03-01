<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Notifications\CourseEnrollmentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EnrollmentController extends Controller
{
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

        // If course is free, enroll directly
        if ($course->price == 0) {
            $enrollment = $this->createEnrollment($user, $course, 'free_' . uniqid());
            
            // Notify the educator
            $educator = User::find($course->user_id);
            $educator->notify(new CourseEnrollmentNotification($enrollment));
            
            return response()->json([
                'message' => 'Successfully enrolled in free course',
                'enrollment' => $enrollment
            ]);
        }

        // For paid courses, initiate payment with Paystack
        $secretKey = config('services.paystack.secret_key');
        
        // Create a pending enrollment
        $enrollment = new CourseEnrollment([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'pending'
        ]);
        $enrollment->save();
        
        // Prepare payment info
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $user->email,
            'amount' => $course->price * 100, // Amount in kobo
            'callback_url' => route('enrollment.verify'),
            'metadata' => [
                'enrollment_id' => $enrollment->id,
                'course_id' => $course->id,
                'user_id' => $user->id
            ]
        ]);
        
        if (!$response->successful()) {
            // Delete the pending enrollment on failure
            $enrollment->delete();
            
            return response()->json([
                'message' => 'Payment initialization failed',
                'error' => $response->json()
            ], 500);
        }
        
        // Update the enrollment with transaction reference
        $paymentData = $response->json()['data'];
        $enrollment->transaction_reference = $paymentData['reference'];
        $enrollment->save();
        
        return response()->json([
            'message' => 'Payment initialized',
            'payment_url' => $paymentData['authorization_url'],
            'reference' => $paymentData['reference'],
            'enrollment_id' => $enrollment->id
        ]);
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
        $secretKey = config('services.paystack.secret_key');
        
        // Verify the payment with Paystack
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
        ])->get("https://api.paystack.co/transaction/verify/{$reference}");
        
        if (!$response->successful()) {
            return response()->json([
                'message' => 'Payment verification failed',
                'error' => $response->json()
            ], 500);
        }
        
        $paymentData = $response->json()['data'];
        
        // If payment was successful
        if ($paymentData['status'] === 'success') {
            // Find the enrollment by transaction reference
            $enrollment = CourseEnrollment::where('transaction_reference', $reference)->first();
            
            if (!$enrollment) {
                return response()->json([
                    'message' => 'Enrollment not found'
                ], 404);
            }
            
            // Activate the enrollment
            $enrollment->status = 'active';
            $enrollment->save();
            
            // Notify the educator about the new enrollment
            $course = Course::find($enrollment->course_id);
            $educator = User::find($course->user_id);
            $educator->notify(new CourseEnrollmentNotification($enrollment));
            
            return response()->json([
                'message' => 'Payment verified and enrollment activated',
                'enrollment' => $enrollment
            ]);
        }
        
        return response()->json([
            'message' => 'Payment was not successful',
            'status' => $paymentData['status']
        ], 400);
    }
    
    /**
     * Webhook handler for Paystack notifications.
     */
    public function handleWebhook(Request $request)
    {
        // Verify webhook signature
        $secretKey = config('services.paystack.secret_key');
        
        if (!hash_equals(
            hash_hmac('sha512', $request->getContent(), $secretKey),
            $request->header('x-paystack-signature')
        )) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }
        
        $event = $request->input('event');
        $data = $request->input('data');
        
        // Handle charge.success event
        if ($event === 'charge.success') {
            $reference = $data['reference'];
            
            // Find the enrollment by transaction reference
            $enrollment = CourseEnrollment::where('transaction_reference', $reference)->first();
            
            if ($enrollment && $enrollment->status === 'pending') {
                // Activate the enrollment
                $enrollment->status = 'active';
                $enrollment->save();
                
                // Notify the educator about the new enrollment
                $course = Course::find($enrollment->course_id);
                $educator = User::find($course->user_id);
                $educator->notify(new CourseEnrollmentNotification($enrollment));
            }
        }
        
        return response()->json(['message' => 'Webhook processed']);
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
        return $enrollment;
    }
}