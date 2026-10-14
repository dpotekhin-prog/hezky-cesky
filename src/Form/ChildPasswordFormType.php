<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ChildPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'first_options' => ['label' => 'form.new_password', 'attr' => ['autocomplete' => 'new-password']],
            'second_options' => ['label' => 'form.password_repeat', 'attr' => ['autocomplete' => 'new-password']],
            'invalid_message' => 'user.password_mismatch',
            'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 6, max: 4096)],
        ]);
    }
}
