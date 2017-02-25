<?php

namespace Flowr\AmulenSyncBundle\Command;

use Amulen\MediaBundle\Entity\Gallery;
use Amulen\MediaBundle\Entity\GalleryItem;
use Amulen\MediaBundle\Entity\Media;
use Amulen\MediaBundle\Entity\MediaType;
use Amulen\ShopBundle\Entity\Product;
use Amulen\ShopBundle\Entity\ProductOrder;
use Amulen\ShopBundle\Entity\ProductOrderItem;
use Flower\ModelBundle\Entity\Project\ProjectIteration;
use Flowr\AmulenSyncBundle\Entity\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * This command syncronize Flowr products with Amulen products.
 *
 * @author Juan Manuel Agüero <jaguero@flowcode.com.ar>
 */
class SyncOrdersCommand extends ContainerAwareCommand
{

    const FLOWR_URL_LOGIN = "/api/users/login";
    const FLOWR_URL_ORDER_POST = "/api/sales/salepoint/{salepoint}/create";
    const FLOWR_ACCOUNT_ALREADY_CREATED = "Account already synced";
    const COMMAND_NAME = "amulen:flowr:syncorders";

    private $entityManager;

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Syncronize orders with Flowr.');
    }

    /**
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getContainer()->get("logger")->info(date("Y-m-d H:i:s") . " - starting sync.");

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
        $settingSalePoint = $this->getEM()->getRepository('FlowrAmulenSyncBundle:Setting')->findOneBy(array(
            'name' => Setting::FLOWR_SALES_SALE_POINT
        ));
        $settingSaleCategory = $this->getEM()->getRepository('FlowrAmulenSyncBundle:Setting')->findOneBy(array(
            'name' => Setting::FLOWR_SALES_SALE_CATEGORY
        ));
        $settingTimeOut = $this->getEM()->getRepository('FlowrAmulenSyncBundle:Setting')->findOneBy(array(
            'name' => Setting::SERVICE_TIMEOUT
        ));

        $apiUrl = self::FLOWR_URL_ORDER_POST;
        $apiUrl = str_replace('{salepoint}', $settingSalePoint->getValue(), $apiUrl);

        $client = new Client([
            'base_uri' => $settingUrl->getValue(),
            'timeout' => $settingTimeOut ? $settingTimeOut->getValue() : "10.0",
        ]);


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

        if ($token && strlen($token) > 0) {

            $notSyncedOrders = $this->getEM()->getRepository(ProductOrder::class)->findSynchronizableOrders();

            $output->writeln("Not synced orders count: " . count($notSyncedOrders));

            /** @var ProductOrder $order */
            foreach ($notSyncedOrders as $order) {

                $output->writeln("About to sync order: " . $order->getId());

                if (!$order->getUser() || !$order->getUser()->getFlowrId()) {
                    $order->setFlowrSynced(true);
                    $order->setFlowrSyncStatus(ProductOrder::sync_status_not_valid);
                    $order->setFlowrSyncMessage("No tiene usuario valido.");
                    break;
                }

                $orderItemsArr = [];

                $allFlowrProducts = true;

                /* @var ProductOrderItem $orderItem */
                foreach ($order->getItems() as $orderItem) {
                    if ($orderItem->getProduct() && $orderItem->getProduct()->getFlowrId()) {
                        $newOrderItemArr = [
                            'units' => $orderItem->getQuantity(),
                            'unit_price' => $orderItem->getUnitPrice(),
                            'product' => ['id' => $orderItem->getProduct()->getFlowrId()],
                            'total' => $orderItem->getSubtotal(),
                        ];
                        array_push($orderItemsArr, $newOrderItemArr);

                    } else {
                        $allFlowrProducts = false;
                        break;
                    }
                }

                if (!$allFlowrProducts) {

                    $order->setFlowrSyncStatus(ProductOrder::sync_status_not_valid);
                    $order->setFlowrSyncMessage("Hay productos que no estan en Flowr.");

                    break;

                } else {

                    $formParams = [
                        'status' => $settingSaleCategory->getValue(),
                        'circuit' => 2,
                        'sale_items' => $orderItemsArr,
                        'payment_items' => [],
                        'total' => $order->getTotal(),
                        'sub_total' => $order->getTotal(),
                        'total_with_tax' => $order->getTotal(),
                        'account' => $order->getUser()->getFlowrId(),
                    ];

                    $res = null;

                    $res = $client->request('POST', $apiUrl, array(
                        'content_type' => "application/x-www-form-urlencoded",
                        'headers' => array(
                            'Authorization' => "Bearer $token",
                        ),
                        'form_params' => $formParams,
                    ));

                    $code = $res->getStatusCode();
                    if ($code == Response::HTTP_OK) {
                        $body = $res->getBody();
                        $responseArr = json_decode($body, true);

                        $syncedOrder = $responseArr['entity'];

                        $order->setFlowrSynced(true);
                        $order->setFlowrId($syncedOrder['id']);
                        $order->setFlowrSyncStatus(ProductOrder::sync_status_ok);
                    } else {
                        $order->setFlowrSynced(true);
                        $order->setFlowrSyncMessage("Server error.");
                        $order->setFlowrSyncStatus(ProductOrder::sync_status_fail);
                    }
                }
            }


            $this->getEM()->flush();

        }

        $output->writeln(date("Y-m-d H:i:s") . " - flowr synced.");
        $this->getContainer()->get("logger")->info(date("Y-m-d H:i:s") . " - flowr synced.");
    }

    /**
     *
     * @return \Doctrine\ORM\EntityManagerInterface em
     */
    protected
    function getEM()
    {
        return $this->entityManager;
    }

}
