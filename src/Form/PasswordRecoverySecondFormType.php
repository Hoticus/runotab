<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class PasswordRecoverySecondFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('recovery_code', TextType::class, [
                'attr' => ['placeholder' => 'XXXXXX'],
                'constraints' => [
                    new Type('digit', 'This code is not a 6-digit code.'),
                    new NotBlank([
                        'message' => 'This code should not be blank.'
                    ]),
                    new Length([
                        'min' => 6,
                        'max' => 6,
                        'exactMessage' => 'This code should have exactly {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'attr' => ['placeholder' => 'New Password'],
                ],
                'second_options' => [
                    'attr' => ['placeholder' => 'Confirm Password'],
                ],
                'invalid_message' => 'Passwords must match.',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password.',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters.',
                        // max length allowed by Symfony for security reasons
                        'max' => 4096,
                        'maxMessage' => 'Your password should have {{ limit }} characters or less.'
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
