@extends('admin.dashboard-layout')

@section('title', 'System Settings')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">System Settings</h1>
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Application Settings -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="border-b px-6 py-4">
                <h2 class="text-lg font-semibold">Application Settings</h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Application Name</h3>
                        <p class="text-base">{{ $app['name'] }}</p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Environment</h3>
                        <p class="text-base">
                            <span class="badge {{ $app['environment'] === 'production' ? 'badge-success' : 'badge-warning' }}">
                                {{ ucfirst($app['environment']) }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Debug Mode</h3>
                        <p class="text-base">
                            <span class="badge {{ $app['debug'] ? 'badge-danger' : 'badge-success' }}">
                                {{ $app['debug'] ? 'Enabled' : 'Disabled' }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Application URL</h3>
                        <p class="text-base">{{ $app['url'] }}</p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Version</h3>
                        <p class="text-base">{{ $app['version'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Storage Settings -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="border-b px-6 py-4">
                <h2 class="text-lg font-semibold">Storage Settings</h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Default Storage Driver</h3>
                        <p class="text-base">{{ ucfirst($storage['driver']) }}</p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Amazon S3</h3>
                        <p class="text-base">
                            <span class="badge {{ $storage['s3_enabled'] ? 'badge-success' : 'badge-info' }}">
                                {{ $storage['s3_enabled'] ? 'Configured' : 'Not Configured' }}
                            </span>
                        </p>
                    </div>
                    @if($storage['s3_enabled'])
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">S3 Bucket</h3>
                        <p class="text-base">{{ $storage['s3_bucket'] }}</p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">S3 Region</h3>
                        <p class="text-base">{{ $storage['s3_region'] }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Mail Settings -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="border-b px-6 py-4">
                <h2 class="text-lg font-semibold">Mail Settings</h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Status</h3>
                        <p class="text-base">
                            <span class="badge {{ $mail['enabled'] ? 'badge-success' : 'badge-danger' }}">
                                {{ $mail['enabled'] ? 'Configured' : 'Not Configured' }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Mail Driver</h3>
                        <p class="text-base">{{ $mail['driver'] }}</p>
                    </div>
                    @if($mail['enabled'])
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Mail Host</h3>
                        <p class="text-base">{{ $mail['host'] }}</p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Mail Port</h3>
                        <p class="text-base">{{ $mail['port'] }}</p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Mail Encryption</h3>
                        <p class="text-base">{{ $mail['encryption'] ?? 'None' }}</p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">From Address</h3>
                        <p class="text-base">{{ $mail['from_address'] }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- AI Features -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="border-b px-6 py-4">
                <h2 class="text-lg font-semibold">AI Features</h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">OpenAI Integration</h3>
                        <p class="text-base">
                            <span class="badge {{ $services['openai']['enabled'] ? 'badge-success' : 'badge-danger' }}">
                                {{ $services['openai']['enabled'] ? 'Configured' : 'Not Configured' }}
                            </span>
                        </p>
                    </div>
                    @if($services['openai']['enabled'])
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Default AI Model</h3>
                        <p class="text-base">{{ $services['openai']['model'] }}</p>
                    </div>
                    @endif
                    <div class="pt-3 border-t">
                        <h3 class="text-base font-semibold mb-3">AI-Powered Features</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm">Personalized Learning Paths</span>
                                <span class="badge {{ $features['ai_learning_paths'] ? 'badge-success' : 'badge-info' }}">
                                    {{ $features['ai_learning_paths'] ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm">AI Teaching Assistant</span>
                                <span class="badge {{ $features['ai_teaching_assistant'] ? 'badge-success' : 'badge-info' }}">
                                    {{ $features['ai_teaching_assistant'] ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm">Social Learning Suggestions</span>
                                <span class="badge {{ $features['social_learning'] ? 'badge-success' : 'badge-info' }}">
                                    {{ $features['social_learning'] ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Third-Party Integrations -->
    <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
        <div class="border-b px-6 py-4">
            <h2 class="text-lg font-semibold">Third-Party Integrations</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Cloudinary Integration -->
                <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-medium">Cloudinary</h3>
                        <span class="badge {{ $services['cloudinary']['enabled'] ? 'badge-success' : 'badge-info' }}">
                            {{ $services['cloudinary']['enabled'] ? 'Configured' : 'Not Configured' }}
                        </span>
                    </div>
                    @if($services['cloudinary']['enabled'])
                        <p>Cloud Name: {{ $services['cloudinary']['cloud_name'] }}</p>
                    @endif
                </div>

                <!-- Paystack Integration -->
                <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-medium">Paystack</h3>
                        <span class="badge {{ $services['paystack']['enabled'] ? 'badge-success' : 'badge-info' }}">
                            {{ $services['paystack']['enabled'] ? 'Configured' : 'Not Configured' }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600">Payment processing service for accepting payments online.</p>
                    @if($services['paystack']['enabled'])
                    <div class="mt-3 text-xs text-gray-500">
                        <p>Integration Mode: Live</p>
                    </div>
                    @endif
                </div>

                <!-- Google Integration -->
                <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-medium">Google</h3>
                        <span class="badge {{ $services['google']['enabled'] ? 'badge-success' : 'badge-info' }}">
                            {{ $services['google']['enabled'] ? 'Configured' : 'Not Configured' }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600">Google OAuth for user authentication and login.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information -->
    <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
        <div class="border-b px-6 py-4">
            <h2 class="text-lg font-semibold">System Information</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">PHP Version</h3>
                    <p class="text-base">{{ phpversion() }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Laravel Version</h3>
                    <p class="text-base">{{ app()->version() }}</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Server</h3>
                    <p class="text-base">{{ isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection