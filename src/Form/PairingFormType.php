<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class PairingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'parent.pairing_code',
            'attr' => ['autocomplete' => 'off', 'maxlength' => 12, 'placeholder' => 'ABCD2345'],
            'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 12)],
        ]);
    }
}
