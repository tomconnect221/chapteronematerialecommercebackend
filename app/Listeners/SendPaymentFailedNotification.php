<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentFailedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(PaymentFailed $event): void
    {
        $this->notificationService->notifyPaymentFailed($event->order, $event->reason);
    }
}