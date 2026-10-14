<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['with_account_type']) {
            $builder->add('accountType', ChoiceType::class, [
                'label' => 'form.account_type',
                'mapped' => false,
                'expanded' => true,
                'choices' => ['form.account_child' => User::ROLE_CHILD, 'form.account_parent' => User::ROLE_PARENT],
                'data' => User::ROLE_CHILD,
            ]);
        }

        $builder
            ->add('username', TextType::class, ['label' => 'form.username'])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'form.password', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'form.password_repeat', 'attr' => ['autocomplete' => 'new-password']],
                'invalid_message' => 'user.password_mismatch',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 6, max: 4096),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            // Off when a parent creates a child account from the dashboard.
            'with_account_type' => true,
        ]);
        $resolver->setAllowedTypes('with_account_type', 'bool');
    }
}
