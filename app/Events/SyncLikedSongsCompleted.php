<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncLikedSongsCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $status;
    public $status_code;
    public $user_id;
    public $current_time;

    public function __construct($response) {
        $this->status = $response['status'];
        $this->status_code = $response['status_code'];
        $this->user_id = $response['user_id'];
        $this->current_time = now();
        Log::info('SyncLikedSongsCompleted event fired.');

    }
    public function broadcastOn()
    {
        return new PrivateChannel('user-'.$this->user_id);
    }
    public function broadcastAs()
    {
        return 'SyncLikedSongsCompleted';
    }
}
