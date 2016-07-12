<?php

namespace Flowr\AmulenSyncBundle\Form\Type;

use Flowr\AmulenSyncBundle\Entity\Setting;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class SettingType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', ChoiceType::class, array(
                'choices' => array(
                    Setting::FLOWR_URL => "Flowr Url",
                    Setting::FLOWR_USERNAME => "Flowr Username",
                    Setting::FLOWR_PASSWORD => "Flowr Password",
                    Setting::FLOWR_CONTACT_SOURCE => "Contact Source",
                    Setting::FLOWR_FIELDS => "Users fields",
                    Setting::SERVICE_TIMEOUT => "Service Timeout",
                )
            ))
            ->add('value');
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Flowr\AmulenSyncBundle\Entity\Setting',
            'translation_domain' => 'FlowrAmulenSync',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'setting';
    }
}
