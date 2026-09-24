<?php

declare(strict_types=1);

namespace Rcalicdan\SmsApi\Notifications;

use BadMethodCallException;
use Illuminate\Notifications\Notification;
use Rcalicdan\SmsApi\SmsApi;

class SmsApiChannel
{
    public function __construct(
        protected SmsApi $client
    ) {
    }

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $notification): mixed
    {
        $to = $notifiable->routeNotificationFor('sms_api', $notification)
            ?? $notifiable->routeNotificationFor('sms', $notification);

        if (empty($to)) {
            return null;
        }

        if (! method_exists($notification, 'toSmsApi')) {
            throw new BadMethodCallException(
                \sprintf('Notification [%s] must define a [toSmsApi] method for [%s].', $notification::class, self::class)
            );
        }

        /** @var object{toSmsApi: callable(mixed): (SmsApiMessage|string)} $notification */
        $message = $notification->toSmsApi($notifiable);

        if (\is_string($message)) {
            $message = new SmsApiMessage($message);
        }

        if (! $message instanceof SmsApiMessage) {
            return null;
        }

        return $this->client->sendMessage(
            to: $to,
            message: $message->content,
            extraParams: $message->params,
            extraHeaders: $message->headers
        );
    }
}
