<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChildPasswordFormType;
use App\Form\PairingFormType;
use App\Form\RegistrationFormType;
use App\Repository\AnswerRepository;
use App\Repository\UserRepository;
use App\Service\StatsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * A parent sees and manages only the children linked to them.
 */
#[Route('/{_locale}/parent', requirements: ['_locale' => 'cs|en'])]
#[IsGranted(User::ROLE_PARENT)]
final class ParentController extends AbstractController
{
    public function __construct(
        private readonly StatsProvider $stats,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route('', name: 'parent_overview', methods: ['GET'])]
    public function overview(#[CurrentUser] User $parent): Response
    {
        return $this->renderOverview($parent);
    }

    #[Route('/children', name: 'parent_add_child', methods: ['POST'])]
    public function addChild(Request $request, #[CurrentUser] User $parent): Response
    {
        $child = new User();
        $form = $this->createForm(RegistrationFormType::class, $child, ['with_account_type' => false]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderOverview($parent, addForm: $form);
        }

        $child->setRoles([User::ROLE_CHILD]);
        $child->setPassword($this->hasher->hashPassword($child, $form->get('plainPassword')->getData()));
        $child->addParent($parent);
        $this->em->persist($child);
        $this->em->flush();
        $this->addFlash('success', 'parent.child_added');

        return $this->redirectToRoute('parent_overview');
    }

    #[Route('/link', name: 'parent_link_child', methods: ['POST'])]
    public function linkChild(
        Request $request,
        #[CurrentUser] User $parent,
        UserRepository $users,
        #[Target('pairing.limiter')] RateLimiterFactoryInterface $pairingLimiter,
    ): Response {
        $form = $this->createForm(PairingFormType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderOverview($parent, linkForm: $form);
        }

        if (!$pairingLimiter->create($parent->getUserIdentifier())->consume()->isAccepted()) {
            $this->addFlash('error', 'parent.too_many_attempts');

            return $this->redirectToRoute('parent_overview');
        }

        $code = strtoupper(preg_replace('/\s+/', '', (string) $form->get('code')->getData()));
        $child = '' !== $code ? $users->findOneBy(['pairingCode' => $code]) : null;
        // Only child accounts can be linked; the same message for "not found" and "not a child".
        if (null === $child || $child->isParent() || $child === $parent) {
            $this->addFlash('error', 'parent.code_invalid');

            return $this->redirectToRoute('parent_overview');
        }

        $child->addParent($parent);
        // A used code is replaced, so it cannot be reused by someone who saw it.
        $child->regeneratePairingCode();
        $this->em->flush();
        $this->addFlash('success', 'parent.child_linked');

        return $this->redirectToRoute('parent_overview');
    }

    #[Route('/child/{id}', name: 'parent_child', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function child(User $child, #[CurrentUser] User $parent, AnswerRepository $answers): Response
    {
        $this->assertOwnChild($child, $parent);

        return $this->renderChild($child, $answers);
    }

    #[Route('/child/{id}/password', name: 'parent_child_password', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function childPassword(Request $request, User $child, #[CurrentUser] User $parent, AnswerRepository $answers): Response
    {
        $this->assertOwnChild($child, $parent);

        $form = $this->createForm(ChildPasswordFormType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderChild($child, $answers, $form);
        }

        $child->setPassword($this->hasher->hashPassword($child, $form->get('plainPassword')->getData()));
        $this->em->flush();
        $this->addFlash('success', 'parent.password_changed');

        return $this->redirectToRoute('parent_child', ['id' => $child->getId()]);
    }

    private function assertOwnChild(User $child, User $parent): void
    {
        if (!$child->isChildOf($parent)) {
            throw $this->createNotFoundException();
        }
    }

    private function renderOverview(User $parent, ?FormInterface $addForm = null, ?FormInterface $linkForm = null): Response
    {
        $children = [];
        foreach ($parent->getChildren() as $child) {
            $children[] = ['user' => $child, 'summary' => $this->stats->summary($child), 'progress' => $this->stats->progress($child)];
        }

        $addForm ??= $this->createForm(RegistrationFormType::class, new User(), ['with_account_type' => false]);
        $linkForm ??= $this->createForm(PairingFormType::class);
        $invalid = $addForm->isSubmitted() || $linkForm->isSubmitted();

        return $this->render('stats/parent.html.twig', [
            'children' => $children,
            'add_form' => $addForm,
            'link_form' => $linkForm,
        ], new Response(status: $invalid ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function renderChild(User $child, AnswerRepository $answers, ?FormInterface $passwordForm = null): Response
    {
        $passwordForm ??= $this->createForm(ChildPasswordFormType::class);

        return $this->render('stats/user.html.twig', [
            'child' => $child,
            'stats' => $this->stats->full($child),
            'history' => $answers->findRecent($child, 100),
            'password_form' => $passwordForm,
        ], new Response(status: $passwordForm->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
