<?php

namespace App\Twig;

use App\Repository\UserRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class PlatformExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private UserRepository $userRepo)
    {
    }

    public function getGlobals(): array
    {
        return [
            'njPlatformUserCount' => $this->userRepo->count([]),
        ];
    }
}
