@extends('educators.dashboard-layout')

@section('title', 'Students')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">Your Students</h1>
    </div>
    
    <!-- Filters -->
    <div class="card p-4">
        <form action="{{ route('educator.students') }}" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" 
                    class="form-input" placeholder="Search by name or email">
            </div>
            <div>
                <label for="course" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                <select name="course" id="course" class="form-input">
                    <option value="">All Courses</option>
                    @foreach($courses ?? [] as $courseOption)
                        <option value="{{ $courseOption->id }}" {{ request('course') == $courseOption->id ? 'selected' : '' }}>
                            {{ $courseOption->title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="progress" class="block text-sm font-medium text-gray-700 mb-1">Progress</label>
                <select name="progress" id="progress" class="form-input">
                    <option value="">All Progress Levels</option>
                    <option value="not_started" {{ request('progress') == 'not_started' ? 'selected' : '' }}>Not Started</option>
                    <option value="in_progress" {{ request('progress') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="completed" {{ request('progress') == 'completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="btn btn-secondary">
                    <i class="fa-solid fa-filter mr-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
    
    <!-- Students List -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="table-header">Student</th>
                        <th scope="col" class="table-header">Course</th>
                        <th scope="col" class="table-header">Enrolled</th>
                        <th scope="col" class="table-header">Progress</th>
                        <th scope="col" class="table-header">Last Active</th>
                        <th scope="col" class="table-header">Status</th>
                        <th scope="col" class="table-header">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($students ?? [] as $student)
                        <tr>
                            <td class="table-cell">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 flex-shrink-0">
                                        @if($student->avatar)
                                            <img class="h-10 w-10 rounded-full" src="{{ $student->avatar }}" alt="{{ $student->username }}">
                                        @else
                                            <div class="h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center">
                                                <span class="text-primary-800 font-medium">{{ substr($student->first_name, 0, 1) }}{{ substr($student->last_name, 0, 1) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $student->first_name }} {{ $student->last_name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ $student->email }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="table-cell">
                                @foreach($student->enrollments as $enrollment)
                                    <div class="text-sm {{ !$loop->first ? 'mt-1' : '' }}">
                                        <a href="{{ route('educator.courses.show', $enrollment->course_id) }}" class="text-primary-600 hover:text-primary-900">
                                            {{ $enrollment->course->title }}
                                        </a>
                                    </div>
                                @endforeach
                            </td>
                            <td class="table-cell">
                                @foreach($student->enrollments as $enrollment)
                                    <div class="text-sm text-gray-500 {{ !$loop->first ? 'mt-1' : '' }}">
                                        {{ $enrollment->created_at->format('M d, Y') }}
                                    </div>
                                @endforeach
                            </td>
                            <td class="table-cell">
                                @foreach($student->enrollments as $enrollment)
                                    <div class="flex items-center {{ !$loop->first ? 'mt-1' : '' }}">
                                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                                            <div class="bg-primary-600 h-2.5 rounded-full" style="width: {{ $enrollment->progress }}%"></div>
                                        </div>
                                        <span class="text-sm text-gray-500 ml-2">{{ number_format($enrollment->progress) }}%</span>
                                    </div>
                                @endforeach
                            </td>
                            <td class="table-cell">
                                @foreach($student->enrollments as $enrollment)
                                    <div class="text-sm text-gray-500 {{ !$loop->first ? 'mt-1' : '' }}">
                                        {{ $enrollment->updated_at->diffForHumans() }}
                                    </div>
                                @endforeach
                            </td>
                            <td class="table-cell">
                                @foreach($student->enrollments as $enrollment)
                                    <div class="{{ !$loop->first ? 'mt-1' : '' }}">
                                        @if($enrollment->status == 'active')
                                            <span class="badge badge-success">Active</span>
                                        @elseif($enrollment->status == 'completed')
                                            <span class="badge badge-info">Completed</span>
                                        @elseif($enrollment->status == 'refunded')
                                            <span class="badge badge-danger">Refunded</span>
                                        @else
                                            <span class="badge badge-secondary">{{ ucfirst($enrollment->status) }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </td>
                            <td class="table-cell">
                                <div class="flex space-x-2">
                                    <a href="#" class="text-primary-600 hover:text-primary-900" title="Send Message">
                                        <i class="fa-solid fa-envelope"></i>
                                    </a>
                                    <a href="#" class="text-primary-600 hover:text-primary-900" title="View Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fa-solid fa-users text-4xl mb-4 text-gray-400"></i>
                                    <p class="text-base font-medium text-gray-900 mb-1">No students found</p>
                                    <p>You don't have any students enrolled in your courses yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    @if(isset($students) && $students->hasPages())
        <div class="flex justify-center mt-6">
            {{ $students->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection