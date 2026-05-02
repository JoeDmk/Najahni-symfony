<?php

namespace App\Twig;

use App\Repository\UserRepository;
use Doctrine\DBAL\Exception as DbalException;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class PlatformExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private UserRepository $userRepo)
    {
    }

    public function getGlobals(): array
    {
        $userCount = 0;

        try {
            $userCount = $this->userRepo->count([]);
        } catch (DbalException | \PDOException) {
            // Keep Twig globals available when the local database is offline.
        }

        return [
            'njPlatformUserCount' => $userCount,
        ];
    }
}
