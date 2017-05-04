<?php

namespace Flowr\AmulenSyncBundle\Controller;

use Flowr\AmulenSyncBundle\Command\SyncServicesCommand;
use Flowr\AmulenSyncBundle\Entity\Setting;
use Flowr\AmulenSyncBundle\Form\Type\UserType;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Amulen\UserBundle\Entity\User;

/**
 * User controller.
 *
 * @Route("/admin/flowrsync/service")
 */
class SyncServiceController extends Controller
{

    /**
     * Lists all User entities.
     *
     * @Route("/", name="admin_flowr_sync_service")
     * @Method("GET")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $services = $em->getRepository('AmulenShopBundle:Service')->findAll();

        return array(
            'entities' => $services,
        );
    }

    /**
     * Lists all User entities.
     *
     * @Route("/sync", name="admin_flowr_sync_service_do_sync")
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

        $commandCall = "php " . $rootDir . "/console ";
        $commandCall .= SyncServicesCommand::COMMAND_NAME;
        $commandCall .= " --env=" . $env . " > /dev/null &";

        exec($commandCall);

        $this->addFlash('success', 'Syncronization started ok.');

        return $this->redirect($this->generateUrl('admin_flowr_sync_service'));
    }


    /**
     * Finds and displays a User entity.
     *
     * @Route("/{id}", name="admin_flowr_sync_service_show")
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

        return array(
            'entity' => $entity,
        );
    }

    /**
     * Finds and displays a User entity.
     *
     * @Route("/{id}/edit", name="admin_flowr_sync_service_edit")
     * @Method("GET")
     * @Template()
     */
    public function editAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('AmulenUserBundle:User')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find User entity.');
        }

        $form = $this->createForm(new UserType(), $entity, array(
            'action' => $this->generateUrl('admin_flowr_sync_user_update', array('id' => $entity->getId())),
            'method' => 'PUT',
        ));
        $form->add('submit', 'submit', array('label' => 'Update'));

        return array(
            'entity' => $entity,
            'form' => $form->createView(),
        );
    }

    /**
     * Edits an existing User entity.
     *
     * @Route("/{id}", name="admin_flowr_sync_service_update")
     * @Method("PUT")
     * @Template("FlowcodeUserBundle:User:edit.html.twig")
     */
    public function updateAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('AmulenUserBundle:User')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find User entity.');
        }

        $form = $this->createForm(new UserType(), $entity, array(
            'action' => $this->generateUrl('admin_flowr_sync_user_update', array('id' => $entity->getId())),
            'method' => 'PUT',
        ));
        $form->add('submit', 'submit', array('label' => 'Update'));

        $form->handleRequest($request);

        if ($form->isValid()) {

            /* get user manager */
            $userManager = $this->container->get('flowcode.user');
            $userManager->update($entity);

            return $this->redirect($this->generateUrl('admin_flowr_sync_user_show', array('id' => $id)));
        }

        return array(
            'entity' => $entity,
            'form' => $form->createView(),
        );
    }

}
