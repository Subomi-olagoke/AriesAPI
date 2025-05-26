<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Post;
use App\Models\Course;
use App\Models\OpenLibrary;
use App\Models\PaymentLog;
use Carbon\Carbon;

class AdminDashboardStatsController extends Controller
{
    /**
     * Get admin dashboard statistics
     */
    public function getStats(Request $request)
    {
        // Check admin authentication
        if (!Auth::user() || Auth::user()->isAdmin !== true) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $period = $request->query('period', 'last30');
        
        // Initialize Carbon for date operations
        $carbon = new Carbon();
        
        // Calculate start date based on period
        $startDate = null;
        switch ($period) {
            case 'last30':
                $startDate = $carbon->now()->subDays(30);
                break;
            case 'last90':
                $startDate = $carbon->now()->subDays(90);
                break;
            case 'thisYear':
                $startDate = $carbon->now()->startOfYear();
                break;
            case 'allTime':
                // No start date restriction for all time
                break;
            default:
                $startDate = $carbon->now()->subDays(30);
                break;
        }
        
        // Get user growth data (monthly for the last year)
        $userGrowthData = $this->getUserGrowthData();
        
        // Get revenue data (monthly for the last year)
        $revenueData = $this->getRevenueData();
        
        // Return JSON response with all dashboard stats
        return response()->json([
            'userGrowthData' => $userGrowthData,
            'revenueData' => $revenueData,
            'period' => $period
        ]);
    }
    
    /**
     * Get user growth data for charts
     */
    private function getUserGrowthData()
    {
        $months = [];
        $data = [];
        
        // Get the last 12 months
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthLabel = $date->format('M');
            $months[] = $monthLabel;
            
            // Count users registered in this month
            $monthStart = (clone $date)->startOfMonth();
            $monthEnd = (clone $date)->endOfMonth();
            
            $count = User::where('created_at', '>=', $monthStart)
                        ->where('created_at', '<=', $monthEnd)
                        ->count();
            
            $data[] = $count;
        }
        
        return [
            'labels' => $months,
            'data' => $data
        ];
    }
    
    /**
     * Get revenue data for charts
     */
    private function getRevenueData()
    {
        $months = [];
        $data = [];
        
        // Get the last 12 months
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthLabel = $date->format('M');
            $months[] = $monthLabel;
            
            // Sum revenue for this month
            $monthStart = (clone $date)->startOfMonth();
            $monthEnd = (clone $date)->endOfMonth();
            
            try {
                $revenue = PaymentLog::where('status', 'success')
                            ->where('created_at', '>=', $monthStart)
                            ->where('created_at', '<=', $monthEnd)
                            ->sum('amount');
                
                $data[] = $revenue ?? 0;
            } catch (\Exception $e) {
                // Handle case where PaymentLog doesn't exist or other errors
                $data[] = 0;
            }
        }
        
        return [
            'labels' => $months,
            'data' => $data
        ];
    }
}