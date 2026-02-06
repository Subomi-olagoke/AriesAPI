@extends('admin.dashboard-layout')

@section('title', 'Generate AI Libraries')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Generate Libraries Using AI</h1>
        <a href="{{ route('admin.libraries.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">Back to Libraries</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <div class="bg-white shadow-md rounded p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">AI Library Generation</h2>
        <p class="mb-4">
            This tool will use AI to analyze posts, categorize them into topics, and generate libraries with coherent collections of content.
            The AI will automatically group related posts together and generate appropriate library names, descriptions, and cover images.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-50 p-4 rounded">
                <h3 class="font-semibold">Total Posts</h3>
                <p class="text-2xl">{{ $totalPostCount }}</p>
            </div>
            <div class="bg-green-50 p-4 rounded">
                <h3 class="font-semibold">Recent Posts (30 days)</h3>
                <p class="text-2xl">{{ $recentPostCount }}</p>
            </div>
            <div class="bg-yellow-50 p-4 rounded">
                <h3 class="font-semibold">Recommended Min Posts</h3>
                <p class="text-2xl">10</p>
            </div>
        </div>

        <form action="{{ route('admin.libraries.generate') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="days" class="block text-sm font-medium text-gray-700">Posts from the last X days</label>
                    <select id="days" name="days" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="7">7 days</option>
                        <option value="14">14 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="60">60 days</option>
                        <option value="90">90 days</option>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Limit posts to this timeframe</p>
                </div>

                <div>
                    <label for="min_posts" class="block text-sm font-medium text-gray-700">Minimum posts per library</label>
                    <input type="number" id="min_posts" name="min_posts" min="5" max="50" value="10" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-sm text-gray-500 mt-1">Each library requires at least this many posts</p>
                </div>

                <div>
                    <label for="min_likes" class="block text-sm font-medium text-gray-700">Minimum likes (optional)</label>
                    <input type="number" id="min_likes" name="min_likes" min="0" value="0" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-sm text-gray-500 mt-1">Only include posts with at least this many likes</p>
                </div>

                <div>
                    <label for="min_comments" class="block text-sm font-medium text-gray-700">Minimum comments (optional)</label>
                    <input type="number" id="min_comments" name="min_comments" min="0" value="0" class="mt-1 block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-sm text-gray-500 mt-1">Only include posts with at least this many comments</p>
                </div>
            </div>

            <div class="mt-4">
                <div class="flex items-center">
                    <input type="checkbox" id="auto_approve" name="auto_approve" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="auto_approve" class="ml-2 block text-sm text-gray-900">
                        Auto-approve generated libraries
                    </label>
                </div>
                <p class="text-sm text-gray-500 mt-1">If unchecked, libraries will be created with 'pending' status</p>
            </div>

            <div class="mt-6 bg-yellow-50 p-4 rounded">
                <h3 class="font-semibold mb-2">How it works</h3>
                <ol class="list-decimal list-inside space-y-1 text-sm">
                    <li>Posts from the selected timeframe are collected</li>
                    <li>Cogni AI analyzes post content and categorizes posts into libraries</li>
                    <li>Each library gets an auto-generated name and description</li>
                    <li>GPT-4o generates abstract cover images for each library</li>
                    <li>Libraries are saved with pending or approved status based on your selection</li>
                </ol>
            </div>

            <div class="flex justify-end mt-6">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded">
                    Generate Libraries
                </button>
            </div>
        </form>
    </div>

    @if(isset($topPosts) && count($topPosts) > 0)
    <div class="bg-white shadow-md rounded p-6">
        <h2 class="text-xl font-semibold mb-4">Recent Popular Posts</h2>
        <p class="mb-4">These are some of the recent popular posts that will be used for library generation:</p>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th class="py-2 px-4 border-b text-left">ID</th>
                        <th class="py-2 px-4 border-b text-left">Title</th>
                        <th class="py-2 px-4 border-b text-center">Likes</th>
                        <th class="py-2 px-4 border-b text-center">Comments</th>
                        <th class="py-2 px-4 border-b text-left">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topPosts as $post)
                    <tr>
                        <td class="py-2 px-4 border-b">{{ $post->id }}</td>
                        <td class="py-2 px-4 border-b">{{ $post->title ?? 'Untitled' }}</td>
                        <td class="py-2 px-4 border-b text-center">{{ $post->likes_count }}</td>
                        <td class="py-2 px-4 border-b text-center">{{ $post->comments_count }}</td>
                        <td class="py-2 px-4 border-b">{{ $post->created_at->format('M d, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection