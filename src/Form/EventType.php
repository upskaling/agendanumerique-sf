<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Event;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('link')
            ->add('description', TextareaType::class, [
                'sanitize_html' => true,
                'attr' => [
                    'rows' => 6,
                ],
            ])
            ->add('startAt')
            ->add('endAt')
            ->add('organizer')
            ->add('organizerUrl', UrlType::class, [
                'required' => false,
                'default_protocol' => 'https',
            ])
            ->add('performer', null, [
                'required' => false,
            ])
            ->add('price', NumberType::class, [
                'required' => false,
                'scale' => 2,
            ])
            ->add('image')
            ->add('location')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
