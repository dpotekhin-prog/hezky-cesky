<?php

namespace App\Controller;

use App\Service\UserGuide;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HelpController extends AbstractController
{
    #[Route('/{_locale}/help', name: 'app_help', requirements: ['_locale' => 'cs|en'])]
    public function __invoke(Request $request, UserGuide $guide): Response
    {
        return $this->render('help/index.html.twig', ['guide' => $guide->html($request->getLocale())]);
    }
}
