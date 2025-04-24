<?php

namespace LiveControls\Trellog\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use LiveControls\Trellog\Trellog;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Throwable;

class SendTrellog implements ShouldQueue
{
    use Queueable, Batchable;

    public $ex;

    /**
     * Create a new job instance.
     */
    public function __construct(Throwable $ex)
    {
        $this->ex = FlattenException::createFromThrowable($ex);
        $this->onQueue(config('trellog.queue'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Trellog::error($this->ex);
    }
}
