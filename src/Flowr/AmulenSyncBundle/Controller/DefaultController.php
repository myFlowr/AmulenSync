<?php

namespace Flowr\AmulenSyncBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class DefaultController extends Controller
{
    /**
     * @Route("/flowrsync", name="admin_flowr_sync")
     * @Template()
     */
    public function indexAction()
    {
        return array();
    }
}
