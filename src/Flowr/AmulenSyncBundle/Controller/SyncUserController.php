<?php

namespace Flowr\AmulenSyncBundle\Controller;

use Flowr\AmulenSyncBundle\Entity\Setting;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Amulen\UserBundle\Entity\User;
use Flowcode\UserBundle\Form\UserType;
use Flowcode\UserBundle\Form\UserEditType;

/**
 * User controller.
 *
 * @Route("/admin/flowrsync/user")
 */
class SyncUserController extends Controller
{

    /**
     * Lists all User entities.
     *
     * @Route("/", name="admin_flowr_sync_user")
     * @Method("GET")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $entities = $em->getRepository('AmulenUserBundle:User')->findAll();

        return array(
            'entities' => $entities,
        );
    }

    /**
     * Lists all User entities.
     *
     * @Route("/sync", name="admin_flowr_sync_do_sync")
     * @Method("GET")
     * @Template()
     */
    public function doSyncAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $settingUrl = $em->getRepository('FlowrAmulenSyncBundle:Setting')->findOneBy(array(
            'name' => Setting::FLOWR_URL
        ));
        $settingUsername = $em->getRepository('FlowrAmulenSyncBundle:Setting')->findOneBy(array(
            'name' => Setting::FLOWR_USERNAME
        ));
        $settingPassword = $em->getRepository('FlowrAmulenSyncBundle:Setting')->findOneBy(array(
            'name' => Setting::FLOWR_PASSWORD
        ));

        /* check flowr settings */
        if (!$settingUrl || !$settingUsername || !$settingPassword) {

            $this->addFlash('warning', 'Check your Flowr settings.');

            return $this->redirect($this->generateUrl('admin_flowr_sync_user'));
        }

        /* launch process */
        $rootDir = $this->get('kernel')->getRootDir();
        $env = $this->container->get('kernel')->getEnvironment();
        $commandCall = "php " . $rootDir . "/console amulen:flowr:syncusers --env=" . $env . " > /dev/null &";
        exec($commandCall);

        $this->addFlash('success', 'Syncronization started ok.');

        return $this->redirect($this->generateUrl('admin_flowr_sync_user'));
    }


    /**
     * Finds and displays a User entity.
     *
     * @Route("/{id}", name="admin_flowr_sync_user_show")
     * @Method("GET")
     * @Template()
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('AmulenUserBundle:User')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find User entity.');
        }

        $deleteForm = $this->createDeleteForm($id);

        return array(
            'entity' => $entity,
            'delete_form' => $deleteForm->createView(),
        );
    }

}
