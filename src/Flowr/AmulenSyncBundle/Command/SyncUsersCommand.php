<?php

namespace Flowr\AmulenSyncBundle\Command;

use Flower\ModelBundle\Entity\Project\ProjectIteration;
use Flowr\AmulenSyncBundle\Entity\Setting;
use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Description of UpgradeCommand
 *
 * @author Juan Manuel Agüero <jaguero@flowcode.com.ar>
 */
class SyncUsersCommand extends ContainerAwareCommand
{

    private $entityManager;

    protected function configure()
    {
        $this
            ->setName('amulen:flowr:syncusers')
            ->setDescription('Syncronize users with Flowr.');
    }

    /**
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getContainer()->get("logger")->info(date("Y-m-d H:i:s") . " - starting upgrade.");

        $this->entityManager = $this->getContainer()->get("doctrine.orm.entity_manager");

        $token = null;


        $settingUrl = $this->getEM()->getRepository('FlowrAmulenSyncBundle:Setting')->findOneBy(array(
            'name' => Setting::FLOWR_URL
        ));
        $settingUsername = $this->getEM()->getRepository('FlowrAmulenSyncBundle:Setting')->findOneBy(array(
            'name' => Setting::FLOWR_USERNAME
        ));
        $settingPassword = $this->getEM()->getRepository('FlowrAmulenSyncBundle:Setting')->findOneBy(array(
            'name' => Setting::FLOWR_PASSWORD
        ));
        $client = new Client([
            'base_uri' => $settingUrl->getValue(),
            'timeout' => 10.0,
        ]);

        $entities = $this->getEM()->getRepository('AmulenUserBundle:User')->findAll();

        /* login */
        $resLogin = $client->request('POST', "/api/users/login", array(
            'content_type' => "application/x-www-form-urlencoded",
            'form_params' => array(
                'username' => $settingUsername->getValue(),
                'password' => $settingPassword->getValue(),
            ),
        ));
        $codeLogin = $resLogin->getStatusCode();
        if ($codeLogin == 200) {
            $body = $resLogin->getBody();
            $responseArr = json_decode($body, true);
            $token = $responseArr['token'];
        }

        if ($token) {

            /* @var $user User */
            foreach ($entities as $user) {

                $user->setFlowrSynced(false);
                $res = $client->request('POST', "/api/clients/accounts/contact_type", array(
                    'content_type' => "application/x-www-form-urlencoded",
                    'headers' => array(
                        'Authorization' => "Bearer $token",
                    ),
                    'form_params' => array(
                        'firstname' => $user->getFirstname(),
                        'lastname' => $user->getLastname(),
                        'email' => $user->getEmail(),
                    ),
                ));

                $code = $res->getStatusCode();
                if ($code == 200) {
                    $body = $res->getBody();
                    $responseArr = json_decode($body, true);
                    $flowrUser = $responseArr['entity'];

                    $user->setFlowrSynced(true);
                    $user->setFlowrId($flowrUser['id']);
                }

                $this->getEM()->flush();
            }
        }


        $this->getContainer()->get("logger")->info(date("Y-m-d H:i:s") . " - flowr upgraded.");
    }

    /**
     *
     * @return \Doctrine\ORM\EntityManagerInterface em
     */
    protected function getEM()
    {
        return $this->entityManager;
    }

}
