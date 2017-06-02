<?php

namespace Flowr\AmulenSyncBundle\Command;

use Amulen\UserBundle\Entity\User;
use Amulen\UserBundle\Entity\UserAddress;
use Flowcode\DashboardBundle\Command\AmulenCommand;
use Flowr\AmulenSyncBundle\Entity\Setting;
use GuzzleHttp\Client;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of UpgradeCommand
 *
 * @author Juan Manuel Agüero <jaguero@flowcode.com.ar>
 */
class SyncUsersCommand extends AmulenCommand
{
    const COMMAND_NAME = 'amulen:flowr:syncusers';
    const FLOWR_URL_LOGIN = "/api/users/login";
    const FLOWR_URL_ACCOUNT_CREATE = "/api/clients/accounts/contact_type";
    const FLOWR_ACCOUNT_ALREADY_CREATED = "Account already synced";

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Flowr user sync');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed
     */
    function task(InputInterface $input, OutputInterface $output)
    {
        $token = null;


        $settingUrl = $this->getSettings()->get(Setting::FLOWR_URL);
        $settingUsername = $this->getSettings()->get(Setting::FLOWR_USERNAME);
        $settingPassword = $this->getSettings()->get(Setting::FLOWR_PASSWORD);
        $settingTimeOut = $this->getSettings()->get(Setting::SERVICE_TIMEOUT);
        $settingContactSource = $this->getSettings()->get(Setting::FLOWR_CONTACT_SOURCE);

        $client = new Client([
            'base_uri' => $settingUrl,
            'timeout' => $settingTimeOut ?? "10.0",
        ]);

        $entities = $this->getEM()->getRepository('AmulenUserBundle:User')->findBy(array(
            'flowrSyncEnabled' => true
        ));

        /* login */
        $resLogin = $client->request('POST', self::FLOWR_URL_LOGIN, array(
            'content_type' => "application/x-www-form-urlencoded",
            'form_params' => array(
                'username' => $settingUsername,
                'password' => $settingPassword,
            ),
        ));
        $codeLogin = $resLogin->getStatusCode();
        if ($codeLogin == 200) {
            $body = $resLogin->getBody();
            $responseArr = json_decode($body, true);
            $token = $responseArr['token'];
        }

        if ($token) {

            $output->writeln($token);

            /* @var User $user */
            foreach ($entities as $user) {

                $addressesArr = [];

                /* @var UserAddress $address */
                foreach ($user->getAddresses() as $address) {
                    $addressesArrItem = [
                        'type' => $address->getType(),
                        'street' => $address->getStreet(),
                        'apartment' => $address->getApartment(),
                    ];
                    array_push($addressesArr, $addressesArrItem);
                }

                $params = [
                    'firstname' => $user->getFirstname(),
                    'lastname' => $user->getLastname(),
                    'updated' => $user->getUpdated()->format('c'),
                    'addresses' => $addressesArr,
                    'email' => $user->getEmail(),
                    'contact_source' => $settingContactSource ?? "Amulen Web",
                ];

                $user->setFlowrSynced(false);
                $res = $client->request('POST', self::FLOWR_URL_ACCOUNT_CREATE, array(
                    'content_type' => "application/x-www-form-urlencoded",
                    'headers' => array(
                        'Authorization' => "Bearer $token",
                    ),
                    'form_params' => $params,
                ));

                $code = $res->getStatusCode();
                if ($code == Response::HTTP_OK) {
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
                } else {
                    $body = $res->getBody();
                    $output->writeln($body);
                }

                $this->getEM()->flush();
            }
        }
    }
}
