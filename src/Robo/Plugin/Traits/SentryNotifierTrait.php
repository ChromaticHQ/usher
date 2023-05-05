<?php

namespace Usher\Robo\Plugin\Traits;

use Robo\Result;

/**
 * Trait to provide integration with Sentry.
 */
trait SentryNotifierTrait
{
    protected function isSentryConfigured(): bool
    {
        return true;
    }

    protected function initializeSentry(): void
    {
        // @TODO: Get Sentry DSN from config.
        \Sentry\init(['dsn' => 'https://4fd9a07e2d7d4f1b9ec4e00df85bf041@o71799.ingest.sentry.io/6269595' ]);
    }

    /**
     * TKTK
     */
    protected function sentryJobBeginning(): void
    {
        $this->initializeSentry();
        // 🟡 Notify Sentry your job is running:
        $event = Event::createCheckIn();
        $checkIn = new CheckIn(
            monitorSlug: 'test-cron-job',
            status: CheckInStatus::inProgress(),
        );
        $event->setCheckIn($checkIn);
        SentrySdk::getCurrentHub()->captureEvent($event);
    }

    protected function sentryJobCompleted(): void
    {
        // 🟢 Notify Sentry your job has completed successfully:
        $event = Event::createCheckIn();
        $event->setCheckIn(new CheckIn(
            id: $checkIn->getId(),
            monitorSlug: 'test-cron-job',
            status: CheckInStatus::ok(),
        ));
        SentrySdk::getCurrentHub()->captureEvent($event);
    }

    protected function sentryJobFailed(): void
    {
        // 🔴 Notify Sentry your job has failed:
        $event = Event::createCheckIn();
        $event->setCheckIn(new CheckIn(
            id: $checkIn->getId(),
            monitorSlug: 'test-cron-job',
            status: CheckInStatus::error(),
        ));
        SentrySdk::getCurrentHub()->captureEvent($event);
    }
}
