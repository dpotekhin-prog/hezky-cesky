<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RootController extends AbstractController
{
    #[Route('/', name: 'app_root')]
    public function __invoke(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('app_home', ['_locale' => $request->getPreferredLanguage(['cs', 'en']) ?? 'cs']);
    }
}
