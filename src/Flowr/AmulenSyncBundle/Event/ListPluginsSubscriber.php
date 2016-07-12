<?php

namespace Flowr\AmulenSyncBundle\Event;

use Flowcode\DashboardBundle\Event\ListPluginsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;


/**
 *
 */
class ListPluginsSubscriber implements EventSubscriberInterface
{
    protected $router;
    protected $translator;

    public function __construct(RouterInterface $router, TranslatorInterface $translator)
    {
        $this->router = $router;
        $this->translator = $translator;
    }

    public static function getSubscribedEvents()
    {
        return array(
            ListPluginsEvent::NAME => array('handler', 0),
        );
    }


    public function handler(ListPluginsEvent $event)
    {
        $plugins = $event->getPluginDescriptors();

        /* add default */
        $plugins[] = array(
            "name" => "FlowrAmulenSync",
            "image" => null,
            "version" => "0.1.0",
            "settings" => $this->router->generate('admin_flowrsync_setting'),
            "description" => $this->translator->trans('flowr_amulensync.description', array(), 'FlowrAmulenSync'),
            "website" => null,
            "authors" => array(
                array(
                    "name" => "Flowr",
                    "email" => "hi@myflowr.com",
                    "website" => "http://myflowr.com",
                ),
            ),
        );

        $event->setPluginDescriptors($plugins);

    }

}