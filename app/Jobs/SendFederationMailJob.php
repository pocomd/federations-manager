<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Federation;
use App\Models\User;
use App\Services\Mail\MailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFederationMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public Federation $federation,
        public string $subject,
        public string $body,
        public string $entityType = 'all',
        public array $contactTypes = ['technical'],
        public ?User $sentBy = null,
    ) {
        $this->onQueue('low');
    }

    public function handle(MailTemplateService $service): void
    {
        $result = $service->sendToFederation(
            $this->federation,
            $this->subject,
            $this->body,
            $this->entityType,
            $this->contactTypes,
            $this->sentBy,
        );

        Log::info('Federation mail sent', [
            'federation' => $this->federation->id,
            'sent'       => $result['sent'],
            'failed'     => $result['failed'],
            'skipped'    => $result['skipped'],
        ]);
    }
}
