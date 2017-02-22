<?php

namespace Flowr\AmulenSyncBundle\Service;

use Doctrine\ORM\EntityRepository;
use Flowcode\DashboardBundle\Repository\SettingRepository;
use Flowr\AmulenSyncBundle\Entity\Setting;

class SettingService
{

    /**
     * @var EntityRepository
     */
    private $settingRepository;

    /**
     * SettingService constructor.
     * @param $settingRepository
     */
    public function __construct(EntityRepository $settingRepository)
    {
        $this->settingRepository = $settingRepository;
    }

    /**
     * @param $key
     * @return null|string
     */
    public function get($key)
    {
        /** @var Setting $setting */
        $setting = $this->settingRepository->findOneBy(['name' => $key]);
        if ($setting) {
            return $setting->getValue();
        }

        return null;

    }
}