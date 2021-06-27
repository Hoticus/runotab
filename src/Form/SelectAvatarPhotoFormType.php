<?php

namespace App\Form;

use App\Entity\Photo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class SelectAvatarPhotoFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('photo', FileType::class, [
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '16384k',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png'
                        ],
                        'mimeTypesMessage' => 'Please upload a valid PNG/JPEG photo.'
                    ])
                ],
                'attr' => ['accept' => '.jpeg, .jpg, .png'],
                'label_attr' => ['id' => 'select_avatar_photo_form_photo_label']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Photo::class,
        ]);
    }
}
