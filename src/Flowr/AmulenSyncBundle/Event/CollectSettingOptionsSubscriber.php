<?php

namespace Flowr\AmulenSyncBundle\Event;


use Flowcode\DashboardBundle\Event\CollectSettingOptionsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Translation\TranslatorInterface;


/**
 * CollectSettingOptionsSubscriber
 */
class CollectSettingOptionsSubscriber implements EventSubscriberInterface
{
    protected $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return array(
            CollectSettingOptionsEvent::NAME => array('handler', 1000),
        );
    }


    public function handler(CollectSettingOptionsEvent $event)
    {
        $event->addSettingOption([
            "key" => \Flowr\AmulenSyncBundle\Model\Setting::FLOWR_URL,
            "label" => $this->translator->trans('Flowr Url', [], 'FlowrAmulenSync'),
        ]);

        $event->addSettingOption([
            "key" => \Flowr\AmulenSyncBundle\Model\Setting::FLOWR_USERNAME,
            "label" => $this->translator->trans('Flowr Username', [], 'FlowrAmulenSync'),
        ]);

        $event->addSettingOption([
            "key" => \Flowr\AmulenSyncBundle\Model\Setting::FLOWR_PASSWORD,
            "label" => $this->translator->trans('Flowr Password', [], 'FlowrAmulenSync'),
        ]);


        $event->addSettingOption([
            "key" => \Flowr\AmulenSyncBundle\Model\Setting::FLOWR_CONTACT_SOURCE,
            "label" => $this->translator->trans('Contact Source', [], 'FlowrAmulenSync'),
        ]);

        $event->addSettingOption([
            "key" => \Flowr\AmulenSyncBundle\Model\Setting::FLOWR_SALES_SALE_CATEGORY,
            "label" => $this->translator->trans('Sale category', [], 'FlowrAmulenSync'),
        ]);

        $event->addSettingOption([
            "key" => \Flowr\AmulenSyncBundle\Model\Setting::FLOWR_SALES_SALE_POINT,
            "label" => $this->translator->trans('Sale point', [], 'FlowrAmulenSync'),
        ]);

        $event->addSettingOption([
            "key" => \Flowr\AmulenSyncBundle\Model\Setting::FLOWR_PRODUCTS_PRICE_LIST,
            "label" => $this->translator->trans('Product price list', [], 'FlowrAmulenSync'),
        ]);

        $event->addSettingOption([
            "key" => \Flowr\AmulenSyncBundle\Model\Setting::FLOWR_SERVICES_PRICE_LIST,
            "label" => $this->translator->trans('Service price list', [], 'FlowrAmulenSync'),
        ]);


    }
}