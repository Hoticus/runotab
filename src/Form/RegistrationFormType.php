<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('invitationCode', TextType::class, [
                'attr' => ['placeholder' => 'Invitation code'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter an invitation code.'
                    ]),
                    new Length([
                        'min' => 19,
                        'max' => 19,
                        'exactMessage' => 'An invitation code should have exactly {{ limit }} characters.'
                    ]),
                    new Regex('/^[A-Z0-9]{4}+-[A-Z0-9]{4}+-[A-Z0-9]{4}+-[A-Z0-9]{4}+$/')
                ],
                'mapped' => false
            ])
            ->add('name', TextType::class, [
                'attr' => ['placeholder' => 'Name'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a name.'
                    ]),
                    new Length([
                        'max' => 64,
                        'maxMessage' => 'A name should have {{ limit }} characters or less.'
                    ])
                ]
            ])
            ->add('surname', TextType::class, [
                'attr' => ['placeholder' => 'Surname'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a surname.'
                    ]),
                    new Length([
                        'max' => 64,
                        'maxMessage' => 'A surname should have {{ limit }} characters or less.'
                    ])
                ]
            ])
            ->add('email', EmailType::class, [
                'attr' => ['placeholder' => 'Email'],
                'constraints' => [
                    new Email([
                        'mode' => 'html5',
                        'message' => 'Please enter a valid email address.'
                    ]),
                    new NotBlank([
                        'message' => 'Please enter a email address.'
                    ]),
                    new Length([
                        'max' => 160,
                        'maxMessage' => 'A email should have {{ limit }} characters or less.'
                    ])
                ]
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'attr' => ['placeholder' => 'Password'],
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class
        ]);
    }
}
