<?php

namespace Flowr\AmulenSyncBundle\Command;

use Amulen\MediaBundle\Entity\Gallery;
use Amulen\MediaBundle\Entity\GalleryItem;
use Amulen\MediaBundle\Entity\Media;
use Amulen\MediaBundle\Entity\MediaType;
use Amulen\ShopBundle\Entity\Product;
use Flower\ModelBundle\Entity\Project\ProjectIteration;
use Flowr\AmulenSyncBundle\Entity\Setting;
use GuzzleHttp\Client;
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
class SyncProductsCommand extends ContainerAwareCommand
{

    const FLOWR_URL_LOGIN = "/api/users/login";
    const FLOWR_URL_PRODUCT_GET = "/api/stock/products/forsale";
    const FLOWR_ACCOUNT_ALREADY_CREATED = "Account already synced";
    const COMMAND_NAME = "amulen:flowr:syncproducts";

    private $entityManager;

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Syncronize products with Flowr.');
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
            $res = $client->request('GET', self::FLOWR_URL_PRODUCT_GET, array(
                'content_type' => "application/x-www-form-urlencoded",
                'headers' => array(
                    'Authorization' => "Bearer $token",
                ),
            ));

            $code = $res->getStatusCode();
            if ($code == Response::HTTP_OK) {
                $body = $res->getBody();
                $responseArr = json_decode($body, true);

                $productRepo = $this->getEM()->getRepository(Product::class);
                $mediaTypeRepo = $this->getEM()->getRepository(MediaType::class);
                foreach ($responseArr as $productArr) {

                    $product = $productRepo->findOneBy([
                        'flowrId' => $productArr['id']
                    ]);

                    if (!$product) {
                        $product = new Product();
                        $product->setFlowrId($productArr['id']);
                        $this->getEM()->persist($product);
                    }

                    // Update fields
                    if (isset($productArr['code'])) {
                        $product->setFlowrCode($productArr['code']);
                    }

                    $product->setFlowrSynced(true);
                    $product->setFlowrSyncEnabled(true);
                    $product->setName($productArr['name']);

                    $mediaGallery = $product->getMediaGallery();
                    if (!$mediaGallery) {
                        $mediaGallery = new Gallery();
                        $mediaGallery->setName($product->getName());
                        $product->setMediaGallery($mediaGallery);
                    }

                    $imageMediaType = $mediaTypeRepo->findOneBy([
                        'name' => 'image'
                    ]);

                    if (isset($productArr['raw_materials'])) {

                        $rawMaterials = [];
                        foreach ($productArr['raw_materials'] as $rawMaterialArr) {
                            $rawMaterialItem = [
                                'id' => $rawMaterialArr['raw_material']['id'],
                                'quantity' => $rawMaterialArr['quantity'],
                            ];
                            array_push($rawMaterials, $rawMaterialItem);
                        }

                        $product->setFlowrRawMaterials(json_encode($rawMaterials));

                    } else {
                        $product->setFlowrRawMaterials(null);
                    }

                    if (isset($productArr['image'])) {

                        if (!$product->getMediaGallery()) {
                            $media = new Media();
                            $media->setName($productArr['image']);
                            $media->setMediaType($imageMediaType);

                            $basePath = $settingUrl->getValue();
                            $completePath = $basePath . $productArr['image'];
                            $media->setPath($completePath);

                            $galleryItem = new GalleryItem();
                            $galleryItem->setMedia($media);
                            $galleryItem->setDescription($media->getName());

                            $mediaGallery->addGalleryItem($galleryItem);

                            $product->setMediaGallery($mediaGallery);
                            $this->getEM()->persist($media);
                            $this->getEM()->persist($galleryItem);
                            $this->getEM()->persist($mediaGallery);

                        } else {

                            $gallery = $product->getMediaGallery();

                            $basePath = $settingUrl->getValue();
                            $completePath = $basePath . $productArr['image'];

                            foreach ($gallery->getGalleryItems() as $galleryItem) {
                                $galleryItem->getMedia()->setPath($completePath);
                            }

                        }


                    }

                    if (isset($productArr['sale_price'])) {
                        $product->setPrice($productArr['sale_price']);
                    }

                }

                $this->getEM()->flush();
            }

        }

        $this->getContainer()->get("logger")->info(date("Y-m-d H:i:s") . " - flowr synced.");
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
