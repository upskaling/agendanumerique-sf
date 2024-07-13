<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\SelectionAbonnementDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SelectionAbonnementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subscription', ChoiceType::class, [
                'choices' => [
                    'Cobalt Poitiers' => 1,
                    'PWN' => 2,
                    'EMF' => 3,
                ],
                'expanded' => true,
                'multiple' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SelectionAbonnementDTO::class,
        ]);
    }
}
