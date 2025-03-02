<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseSection;
use App\Services\CourseSectionService;
use Illuminate\Http\Request;

class CourseSectionController extends Controller
{
    protected $sectionService;
    
    public function __construct(CourseSectionService $sectionService)
    {
        $this->sectionService = $sectionService;
    }
    
    /**
     * Get all sections for a course
     */
    public function index($courseId)
    {
        $course = Course::findOrFail($courseId);
        
        $sections = $course->sections()
            ->with('lessons')
            ->orderBy('order')
            ->get();
            
        return response()->json([
            'sections' => $sections
        ]);
    }
    
    /**
     * Create a new section for a course
     */
    public function store(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        
        $this->validate($request, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0'
        ]);
        
        $result = $this->sectionService->createSection(
            auth()->user(),
            $course,
            $request->only(['title', 'description', 'order'])
        );
        
        return response()->json([
            'message' => $result['message'],
            'section' => $result['success'] ? $result['section'] : null
        ], $result['code']);
    }
    
    /**
     * Display a course section
     */
    public function show($courseId, $sectionId)
    {
        // Make sure course exists
        Course::findOrFail($courseId);
        
        $section = CourseSection::where('course_id', $courseId)
            ->where('id', $sectionId)
            ->with('lessons')
            ->firstOrFail();
            
        return response()->json([
            'section' => $section
        ]);
    }
    
    /**
     * Update a course section
     */
    public function update(Request $request, $courseId, $sectionId)
    {
        // Make sure course exists
        Course::findOrFail($courseId);
        
        $section = CourseSection::where('course_id', $courseId)
            ->where('id', $sectionId)
            ->firstOrFail();
            
        $this->validate($request, [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0'
        ]);
        
        $result = $this->sectionService->updateSection(
            auth()->user(),
            $section,
            $request->only(['title', 'description', 'order'])
        );
        
        return response()->json([
            'message' => $result['message'],
            'section' => $result['success'] ? $result['section'] : null
        ], $result['code']);
    }
    
    /**
     * Delete a course section
     */
    public function destroy($courseId, $sectionId)
    {
        // Make sure course exists
        Course::findOrFail($courseId);
        
        $section = CourseSection::where('course_id', $courseId)
            ->where('id', $sectionId)
            ->firstOrFail();
            
        $result = $this->sectionService->deleteSection(
            auth()->user(),
            $section
        );
        
        return response()->json([
            'message' => $result['message']
        ], $result['code']);
    }
}