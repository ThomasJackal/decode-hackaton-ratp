<?php

namespace App\Form;

use App\Entity\Report;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class ReportSubmissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fromUrl = (bool) $options['bus_identifier_from_url'];

        $busAttr = ['autocomplete' => 'off'];
        if ($fromUrl) {
            $busAttr['readonly'] = 'readonly';
        }

        $builder
            ->add('reportDate', DateTimeType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Date et heure du trajet',
            ]);

        if ($fromUrl) {
            $builder->add('busIdentifier', TextType::class, [
                'mapped' => false,
                'label' => 'Identifiant du bus',
                'help' => 'Bus présélectionné via le lien ; l’identifiant est renseigné automatiquement.',
                'constraints' => [new NotBlank(message: 'Identifiant de bus manquant.')],
                'attr' => $busAttr,
            ]);
        } else {
            $builder
                ->add('lineId', TextType::class, [
                    'mapped' => false,
                    'label' => 'Ligne',
                    'help' => 'Identifiant ou code de la ligne.',
                    'constraints' => [new NotBlank(message: 'Indiquez la ligne.')],
                    'attr' => ['autocomplete' => 'off'],
                ])
                ->add('stopId', TextType::class, [
                    'mapped' => false,
                    'label' => 'Arrêt',
                    'help' => 'Identifiant ou nom de l’arrêt.',
                    'constraints' => [new NotBlank(message: 'Indiquez l’arrêt.')],
                    'attr' => ['autocomplete' => 'off'],
                ])
                ->add('direction', TextType::class, [
                    'mapped' => false,
                    'label' => 'Direction',
                    'help' => 'Sens ou terminus (ex. nord, A→B, …).',
                    'constraints' => [new NotBlank(message: 'Indiquez la direction.')],
                    'attr' => ['autocomplete' => 'off'],
                ]);
        }

        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Description du signalement',
                'attr' => ['rows' => 8],
                'constraints' => [new NotBlank(message: 'Veuillez décrire le signalement.')],
            ])
            ->add('reporterEmail', EmailType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'E-mail',
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [
                    new Email(message: 'Adresse e-mail invalide.'),
                ],
            ])
            ->add('reporterTelephone', TelType::class, [
                'mapped' => false,
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['autocomplete' => 'tel'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Report::class,
            'bus_identifier_from_url' => false,
        ]);
        $resolver->setAllowedTypes('bus_identifier_from_url', 'bool');
    }
}
