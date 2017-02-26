<?php

namespace Flowr\AmulenSyncBundle\Event;


use Flowcode\DashboardBundle\Event\CollectJobsEvent;
use Flowr\AmulenSyncBundle\Command\SyncOrdersCommand;
use Flowr\AmulenSyncBundle\Command\SyncProductsCommand;
use Flowr\AmulenSyncBundle\Command\SyncUsersCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CollectJobsSubscriber implements EventSubscriberInterface
{


    public static function getSubscribedEvents()
    {
        return array(
            CollectJobsEvent::NAME => array('handler', 1000),
        );
    }

    public function handler(CollectJobsEvent $event)
    {
        $syncUserJob = [
            'name' => SyncUsersCommand::COMMAND_NAME,
            'command' => SyncUsersCommand::COMMAND_NAME,
        ];
        $event->pushJob($syncUserJob);

        $syncProductsJob = [
            'name' => SyncProductsCommand::COMMAND_NAME,
            'command' => SyncProductsCommand::COMMAND_NAME,
        ];
        $event->pushJob($syncProductsJob);

        $syncOrdersJob = [
            'name' => SyncOrdersCommand::COMMAND_NAME,
            'command' => SyncOrdersCommand::COMMAND_NAME,
        ];
        $event->pushJob($syncOrdersJob);
    }
}