<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ReportSubmittedNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected $report;

    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    public function toArray($notifiable = null)
    {
        // Safely get reportable type
        $reportableType = class_basename($this->report->reportable_type);
        
        // Safely get reporter name with fallback
        $reporterName = 'A user';
        if ($this->report->reporter) {
            $reporterName = $this->report->reporter->name ?? $reporterName;
        }

        return [
            'title' => "New {$reportableType} Report",
            'body' => "{$reporterName} has reported a {$reportableType}",
            'data' => [
                'report_id' => $this->report->id,
                'reportable_type' => $this->report->reportable_type,
                'reportable_id' => $this->report->reportable_id,
                'reason' => $this->report->reason,
            ],
            'type' => 'report_submitted',
        ];
    }
}