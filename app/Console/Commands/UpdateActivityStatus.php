<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Activity;
use Carbon\Carbon;

class UpdateActivityStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activities:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tự động cập nhật trạng thái các hoạt động dựa trên ngày giờ';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $updated = 0;

        $this->info('Bắt đầu cập nhật trạng thái hoạt động...');
        $this->info('Thời gian hiện tại: ' . $now->format('Y-m-d H:i:s'));

        // Cập nhật các hoạt động đang diễn ra (ongoing)
        $ongoingCount = Activity::where('status', 'upcoming')
            ->where('start_time', '<=', $now)
            ->where('end_time', '>', $now)
            ->update(['status' => 'ongoing']);

        $updated += $ongoingCount;
        if ($ongoingCount > 0) {
            $this->info("Đã cập nhật {$ongoingCount} hoạt động sang trạng thái 'ongoing'");
        }

        // Cập nhật các hoạt động đã hoàn thành (completed)
        $completedCount = Activity::whereIn('status', ['upcoming', 'ongoing'])
            ->where('end_time', '<=', $now)
            ->update(['status' => 'completed']);

        $updated += $completedCount;
        if ($completedCount > 0) {
            $this->info("Đã cập nhật {$completedCount} hoạt động sang trạng thái 'completed'");
        }

        // Thống kê tổng quan
        $total = Activity::count();
        $statusBreakdown = Activity::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $this->newLine();
        $this->info('=== THỐNG KÊ HOẠT ĐỘNG ===');
        $this->info("Tổng số hoạt động: {$total}");
        foreach ($statusBreakdown as $status => $count) {
            $this->line("  - {$status}: {$count}");
        }

        if ($updated === 0) {
            $this->warn('Không có hoạt động nào cần cập nhật trạng thái.');
        } else {
            $this->newLine();
            $this->info("Hoàn thành! Tổng cộng đã cập nhật {$updated} hoạt động.");
        }

        return Command::SUCCESS;
    }
}
