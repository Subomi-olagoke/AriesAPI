<?php

namespace App\Http\Controllers;

use App\Models\LiveClass;
use Illuminate\Http\Request;
use App\Events\UserJoinedClass;
use App\Events\ClassEnded;

class LiveClassController extends Controller
{
    public function show(LiveClass $liveClass)
    {
        return view('live-class.show', compact('liveClass'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'required|date|after:now',
        ]);

        $liveClass = LiveClass::create([
            ...$validated,
            'teacher_id' => auth()->id(),
        ]);

        return redirect()->route('live-class.show', $liveClass);
    }

    public function join(LiveClass $liveClass)
    {
        if ($liveClass->status !== 'live') {
            $liveClass->update(['status' => 'live']);
        }

        broadcast(new UserJoinedClass($liveClass, auth()->user()))->toOthers();

        return response()->json(['message' => 'Joined successfully']);
    }

    public function end(LiveClass $liveClass)
    {
        if (auth()->id() !== $liveClass->teacher_id) {
            abort(403);
        }

        $liveClass->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        broadcast(new ClassEnded($liveClass))->toOthers();

        return response()->json(['message' => 'Class ended successfully']);
    }
}
