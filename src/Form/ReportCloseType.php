<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

final class ReportCloseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('closureReason', TextareaType::class, [
            'label' => 'Raison de la clôture',
            'mapped' => false,
            'required' => false,
            'attr' => [
                'rows' => 4,
                'placeholder' => 'Ex. : doublon, traité en interne, hors périmètre…',
            ],
            'constraints' => [
                new Length(max: 4000),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'report_close',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'report_close';
    }
}
