<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\AnswerRepository;
use App\Repository\TopicRepository;
use App\Service\StatsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class HomeController extends AbstractController
{
    #[Route('/{_locale}', name: 'app_home', requirements: ['_locale' => 'cs|en'])]
    public function index(#[CurrentUser] User $user, TopicRepository $topics, AnswerRepository $answers, StatsProvider $stats): Response
    {
        $progress = $stats->progress($user);
        $levels = [];
        foreach ($progress['levels'] as $level => $data) {
            $levelTopics = [];
            foreach ($topics->findAllOrdered() as $topic) {
                if (isset($data['topics'][$topic->getCode()])) {
                    $levelTopics[] = ['topic' => $topic] + $data['topics'][$topic->getCode()];
                }
            }
            $levels[$level] = ['topics' => $levelTopics] + $data;
        }

        return $this->render('home/index.html.twig', [
            'levels' => $levels,
            'progress' => $progress,
            'summary' => $stats->summary($user),
            'mistakes_count' => count($answers->findOpenMistakes($user)),
        ]);
    }
}
