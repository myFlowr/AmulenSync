<?php

namespace Flowr\AmulenSyncBundle\Command;

use Flowcode\DashboardBundle\Entity\Job;
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
    const COMMAND_NAME = 'amulen:flowr:syncusers';
    const FLOWR_URL_LOGIN = "/api/users/login";
    const FLOWR_URL_ACCOUNT_CREATE = "/api/clients/accounts/contact_type";
    const FLOWR_ACCOUNT_ALREADY_CREATED = "Account already synced";

    private $entityManager;

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Syncronize users with Flowr.');
    }

    /**
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getContainer()->get("logger")->info(date("Y-m-d H:i:s") . " - user sync started.");
        $output->writeln(date("Y-m-d H:i:s") . " - user sync started.");


        $this->entityManager = $this->getContainer()->get("doctrine.orm.entity_manager");

        $job = new Job();
        $job->setName(self::COMMAND_NAME);
        $job->setStatus(Job::STATUS_DOING);
        $this->getEM()->persist($job);
        $this->getEM()->flush();

        try {
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
            $settingContactSource = $this->getEM()->getRepository('FlowrAmulenSyncBundle:Setting')->findOneBy(array(
                'name' => Setting::FLOWR_CONTACT_SOURCE
            ));
            $settingTimeOut = $this->getEM()->getRepository('FlowrAmulenSyncBundle:Setting')->findOneBy(array(
                'name' => Setting::SERVICE_TIMEOUT
            ));

            $client = new Client([
                'base_uri' => $settingUrl->getValue(),
                'timeout' => $settingTimeOut ? $settingTimeOut->getValue() : "10.0",
            ]);

            $entities = $this->getEM()->getRepository('AmulenUserBundle:User')->findBy(array(
                'flowrSyncEnabled' => true
            ));

            /* login */
            $resLogin = $client->request('POST', self::FLOWR_URL_LOGIN, array(
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
                    $res = $client->request('POST', self::FLOWR_URL_ACCOUNT_CREATE, array(
                        'content_type' => "application/x-www-form-urlencoded",
                        'headers' => array(
                            'Authorization' => "Bearer $token",
                        ),
                        'form_params' => array(
                            'firstname' => $user->getFirstname(),
                            'lastname' => $user->getLastname(),
                            'email' => $user->getEmail(),
                            'contact_source' => $settingContactSource ? $settingContactSource->getValue() : "Amulen Web",
                        ),
                    ));

                    $code = $res->getStatusCode();
                    if ($code == 200) {
                        $body = $res->getBody();
                        $responseArr = json_decode($body, true);
                        $flowrUser = $responseArr['entity'];

                        $user->setFlowrSynced(true);
                        $user->setFlowrId($flowrUser['id']);
                        if ($responseArr['message'] == self::FLOWR_ACCOUNT_ALREADY_CREATED) {
                            $output->writeln("Account already exists.");
                            if (isset($flowrUser['firstname'])) {
                                $user->setFirstname($flowrUser['firstname']);
                            }
                            if (isset($flowrUser['lastname'])) {
                                $user->setLastname($flowrUser['lastname']);
                            }
                            if (isset($flowrUser['code'])) {
                                $user->setFlowrCode($flowrUser['code']);
                            }
                        }
                    }

                    $this->getEM()->flush();
                }
            }

            $job->setStatus(Job::STATUS_DONE_OK);
            $this->getEM()->flush();

            $this->getContainer()->get("logger")->info(date("Y-m-d H:i:s") . " - flowr upgraded.");

        } catch (\Exception $exception) {

            $job->setStatus(Job::STATUS_DONE_FAIL);
            $this->getEM()->flush();

        }


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
