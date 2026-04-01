<?php

namespace App\Form;

use App\Entity\Bus;
use App\Entity\Driver;
use App\Entity\Report;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextType::class)
            ->add('severity', TextType::class)
            ->add('category', TextType::class)
            ->add('driver', EntityType::class, [
                'class' => Driver::class,
                'choice_label' => static fn (Driver $d): string => 'Driver #'.$d->getId(),
                'placeholder' => '',
            ])
            ->add('Bus', EntityType::class, [
                'class' => Bus::class,
                'choice_label' => static fn (Bus $b): string => 'Bus #'.$b->getId(),
                'placeholder' => '',
            ])
            ->add('ReportDate', DateTimeType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Report date',
            ])
            ->add('reporterContact', TextareaType::class, [
                'label' => 'Reporter contact (JSON)',
                'required' => false,
                'attr' => ['rows' => 6, 'class' => 'font-monospace'],
                'help' => 'JSON object, e.g. {"name":"Jane","email":"j@example.com"}',
            ]);

        $builder->get('reporterContact')->addModelTransformer(new CallbackTransformer(
            function (mixed $contact): string {
                if ($contact === null || $contact === '' || $contact === []) {
                    return '';
                }

                return json_encode($contact, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            },
            function (?string $s): mixed {
                $s = trim((string) $s);
                if ($s === '') {
                    return [];
                }
                try {
                    return json_decode($s, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new TransformationFailedException('Invalid JSON: '.$e->getMessage());
                }
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Report::class,
        ]);
    }
}
