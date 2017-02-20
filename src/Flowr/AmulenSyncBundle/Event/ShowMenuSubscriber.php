<?php

namespace Flowr\AmulenSyncBundle\Event;


use Flowcode\DashboardBundle\Event\ShowMenuEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;

class ShowMenuSubscriber implements EventSubscriberInterface
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
            ShowMenuEvent::NAME => array('handler', 1000),
        );
    }

    public function handler(ShowMenuEvent $event)
    {
        $menuOptions = $event->getMenuOptions();

        /* add default */
        $menuOptions[] = array(
            "icon" => "fa fa-refresh",
            "url" => $this->router->generate('admin_flowr_sync'),
            "title" => $this->translator->trans('Flowr Sync'),
            "submenu" => array(
                array(
                    "url" => $this->router->generate('admin_flowr_sync_user'),
                    "title" => $this->translator->trans('Sync Users'),
                ),
                array(
                    "url" => $this->router->generate('admin_flowr_sync_product'),
                    "title" => $this->translator->trans('Sync Products'),
                ),
            ),
        );

        $event->setMenuOptions($menuOptions);

    }
}