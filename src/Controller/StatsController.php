<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\StatsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/{_locale}', requirements: ['_locale' => 'cs|en'])]
final class StatsController extends AbstractController
{
    public function __construct(private readonly StatsProvider $stats)
    {
    }

    #[Route('/stats', name: 'stats_mine')]
    public function mine(#[CurrentUser] User $user): Response
    {
        return $this->render('stats/user.html.twig', ['child' => $user, 'stats' => $this->stats->full($user), 'history' => null]);
    }
}
