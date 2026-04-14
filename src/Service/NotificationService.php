<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function notify(
        User $user,
        string $title,
        string $message,
        string $type = 'INFO',
        ?string $actionUrl = null,
        ?string $actionLabel = null,
    ): Notification {
        unset($actionUrl, $actionLabel);

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setType($type);

        $this->em->persist($notification);

        return $notification;
    }
}
