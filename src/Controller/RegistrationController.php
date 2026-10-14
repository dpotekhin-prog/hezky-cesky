<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/{_locale}/register', name: 'app_register', requirements: ['_locale' => 'cs|en'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        Security $security,
        #[Target('registration.limiter')] RateLimiterFactoryInterface $registrationLimiter,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$registrationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', 'register.too_many');

                return $this->render('security/register.html.twig', ['form' => $form], new Response(status: Response::HTTP_TOO_MANY_REQUESTS));
            }

            $user->setRoles([User::ROLE_PARENT === $form->get('accountType')->getData() ? User::ROLE_PARENT : User::ROLE_CHILD]);
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $em->persist($user);
            $em->flush();

            $security->login($user, 'form_login', 'main');

            return $this->redirectToRoute($user->isParent() ? 'parent_overview' : 'app_home');
        }

        return $this->render('security/register.html.twig', ['form' => $form]);
    }
}
