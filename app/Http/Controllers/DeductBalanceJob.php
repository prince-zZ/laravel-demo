<?php

namespace App\Http\Controllers;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class DeductBalanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $user_id;    // 用户ID
    public $amount;     // 扣减金额

    public function __construct(int $user_id, float $amount)
    {
        $this->user_id = $user_id;
        $this->amount = $amount;
    }

    public function handle()
    {
        // 使用 Redis 来确保任务的唯一性
        $lockKey = "deduct_balance_job_{$this->user_id}_{$this->amount}";
        if (!Redis::setnx($lockKey, 1)) {
            // 如果无法获取锁，说明任务已在执行或已执行过，直接返回
            return;
        }

        // 设置锁的过期时间，例如 60 秒，防止死锁
        Redis::expire($lockKey, 60);

        // 获取用户模型并扣减余额
        $user = \App\Models\User::find($this->user_id);
        if ($user) {
            $user->balance -= $this->amount;
            $user->save();
            \Log::info("成功为用户 {$this->user_id} 扣减余额 {$this->amount}，当前余额为 {$user->balance}");
        } else {
            \Log::error("用户 {$this->user->id} 不存在，无法扣减余额");
        }

        // 任务完成后释放锁
        Redis::del($lockKey);
    }
}
