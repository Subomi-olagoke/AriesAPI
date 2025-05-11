<?php

namespace App\Http\Controllers;

use App\Models\Waitlist;
use App\Models\WaitlistEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Mail\WaitlistMail;

class WaitlistController extends Controller
{
    /**
     * Register a new user to the waitlist.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:waitlist',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $waitlist = Waitlist::create([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully joined the waitlist',
            'data' => $waitlist
        ], 201);
    }

    /**
     * Get all waitlist entries (admin only).
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authorize('admin');
        
        $waitlist = Waitlist::orderBy('created_at', 'desc')->paginate(50);
        
        return response()->json([
            'success' => true,
            'data' => $waitlist
        ]);
    }
    
    /**
     * Admin view to manage waitlist.
     *
     * @return \Illuminate\Http\Response
     */
    public function adminIndex()
    {
        $waitlist = Waitlist::orderBy('created_at', 'desc')->paginate(20);
        $emails = WaitlistEmail::orderBy('created_at', 'desc')->paginate(10);
        
        return view('admin.waitlist.index', compact('waitlist', 'emails'));
    }
    
    /**
     * Send an email to all or selected waitlist users (admin only).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendEmail(Request $request)
    {
        $this->authorize('admin');
        
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'recipients' => 'sometimes|array',
            'recipients.*' => 'exists:waitlist,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create email record
        $email = WaitlistEmail::create([
            'subject' => $request->subject,
            'content' => $request->content,
        ]);

        // Get recipients
        if ($request->has('recipients') && !empty($request->recipients)) {
            $recipients = Waitlist::whereIn('id', $request->recipients)->get();
        } else {
            $recipients = Waitlist::all();
        }

        // Send emails and track
        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->send(new WaitlistMail($email->subject, $email->content, $recipient->name));
            
            // Record this email was sent to this recipient
            $email->recipients()->attach($recipient->id, ['sent_at' => now()]);
        }

        $email->sent_at = now();
        $email->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Email sent to ' . count($recipients) . ' recipients',
                'data' => $email
            ]);
        }

        return redirect()->route('admin.waitlist')->with('success', 'Email sent to ' . count($recipients) . ' recipients');
    }
}