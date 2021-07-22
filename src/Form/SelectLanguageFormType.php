<?php

namespace App\Form;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Intl\Locales;

class SelectLanguageFormType extends AbstractType
{
    public function __construct(private ParameterBagInterface $parameter_bag)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $supported_locales = explode("|", $this->parameter_bag->get('app.supported_locales'));
        foreach ($supported_locales as $supported_locale) {
            $choices[mb_convert_case(
                Locales::getName($supported_locale, $supported_locale),
                MB_CASE_TITLE,
                'UTF-8'
            )] = $supported_locale;
        }
        $builder
            ->add('language', ChoiceType::class, [
                'choices' => $choices,
                'data' => $options['user_locale']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'user_locale' => null
        ]);
    }
}
