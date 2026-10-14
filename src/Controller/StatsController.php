<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\AnswerRepository;
use App\Repository\UserRepository;
use App\Service\StatsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

    #[Route('/parent', name: 'parent_overview')]
    #[IsGranted(User::ROLE_PARENT)]
    public function overview(UserRepository $users): Response
    {
        $children = [];
        foreach ($users->findChildren() as $child) {
            $children[] = ['user' => $child, 'summary' => $this->stats->summary($child), 'progress' => $this->stats->progress($child)];
        }

        return $this->render('stats/parent.html.twig', ['children' => $children]);
    }

    #[Route('/parent/child/{id}', name: 'parent_child', requirements: ['id' => '\d+'])]
    #[IsGranted(User::ROLE_PARENT)]
    public function child(User $child, AnswerRepository $answers): Response
    {
        return $this->render('stats/user.html.twig', [
            'child' => $child,
            'stats' => $this->stats->full($child),
            'history' => $answers->findRecent($child, 100),
        ]);
    }
}
