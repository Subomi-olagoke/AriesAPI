<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title></title>
    <!-- Force reload the app CSS and JS -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f9fafb;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background-color: #fff;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn:hover {
            background-color: #f9fafb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background-color: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        
        .stat-icon.blue {
            background-color: #eff6ff;
            color: #2563eb;
        }
        
        .stat-icon.green {
            background-color: #ecfdf5;
            color: #10b981;
        }
        
        .stat-icon.purple {
            background-color: #f5f3ff;
            color: #7c3aed;
        }
        
        .stat-icon.yellow {
            background-color: #fffbeb;
            color: #d97706;
        }
        
        .stat-title {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .stat-change {
            display: flex;
            align-items: center;
            font-size: 14px;
        }
        
        .stat-change.positive {
            color: #10b981;
        }
        
        .stat-change.negative {
            color: #ef4444;
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .chart-card {
            background-color: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .chart-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .chart-title {
            font-size: 16px;
            font-weight: 500;
            margin: 0;
        }
        
        .chart-body {
            padding: 20px;
            height: 300px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(100%, 1fr));
            }
            
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <a href="{{ route('admin.export-stats') }}" class="btn">
                <i class="fa-solid fa-file-export"></i>
                Export Data
            </a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fa-solid fa-users fa-lg"></i>
                </div>
                <div class="stat-title">Total Users</div>
                <div class="stat-value">{{ number_format($stats['users']['total'] ?? 0) }}</div>
                <div class="stat-change positive">
                    <i class="fa-solid fa-arrow-up mr-1"></i>
                    <span>{{ $stats['users']['new_this_week'] ?? 0 }} new this week</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fa-solid fa-dollar-sign fa-lg"></i>
                </div>
                <div class="stat-title">Total Revenue</div>
                <div class="stat-value">${{ number_format($stats['revenue']['total'] ?? 0) }}</div>
                <div class="stat-change positive">
                    <i class="fa-solid fa-arrow-up mr-1"></i>
                    <span>${{ number_format($stats['revenue']['this_month'] ?? 0) }} this month</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fa-solid fa-file-lines fa-lg"></i>
                </div>
                <div class="stat-title">Content Posts</div>
                <div class="stat-value">{{ number_format($stats['content']['total_posts'] ?? 0) }}</div>
                <div class="stat-change positive">
                    <i class="fa-solid fa-arrow-up mr-1"></i>
                    <span>{{ $stats['content']['posts_today'] ?? 0 }} new today</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow">
                    <i class="fa-solid fa-book fa-lg"></i>
                </div>
                <div class="stat-title">Libraries</div>
                <div class="stat-value">{{ number_format($stats['content']['total_libraries'] ?? 0) }}</div>
                <div class="stat-change">
                    <span>{{ $stats['content']['pending_libraries'] ?? 0 }} pending approval</span>
                </div>
            </div>
        </div>
        
        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">User Growth</h3>
                </div>
                <div class="chart-body">
                    <canvas id="userGrowthChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Revenue Trends</h3>
                </div>
                <div class="chart-body">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Recent Users</h3>
            </div>
            <div style="padding: 20px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <th style="text-align: left; padding: 10px; font-weight: 500; color: #4b5563;">User</th>
                            <th style="text-align: left; padding: 10px; font-weight: 500; color: #4b5563;">Email</th>
                            <th style="text-align: left; padding: 10px; font-weight: 500; color: #4b5563;">Registration Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentUsers as $user)
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 10px;">
                                <div style="display: flex; align-items: center;">
                                    <div style="width: 36px; height: 36px; border-radius: 50%; background-color: #6b7280; color: white; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                                        {{ strtoupper(substr($user->first_name ?? 'U', 0, 1)) }}{{ strtoupper(substr($user->last_name ?? 'U', 0, 1)) }}
                                    </div>
                                    <div>{{ $user->first_name ?? '' }} {{ $user->last_name ?? '' }}</div>
                                </div>
                            </td>
                            <td style="padding: 10px;">{{ $user->email ?? 'No email' }}</td>
                            <td style="padding: 10px;">{{ $user->created_at ? $user->created_at->format('M d, Y') : 'Unknown' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // User Growth Chart
            const userGrowthData = {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'New Users',
                    data: [65, 78, 90, 105, 125, 138, 156, 170, 186, 199, 210, 225],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            };
            
            const userCtx = document.getElementById('userGrowthChart').getContext('2d');
            new Chart(userCtx, {
                type: 'line',
                data: userGrowthData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            }
                        },
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            }
                        }
                    }
                }
            });
            
            // Revenue Chart
            const revenueData = {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Revenue',
                    data: [1500, 1700, 1900, 2100, 2300, 2500, 2700, 2900, 3100, 3300, 3500, 3700],
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderRadius: 4
                }]
            };
            
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'bar',
                data: revenueData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            }
                        },
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>