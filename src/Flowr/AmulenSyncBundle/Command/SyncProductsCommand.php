<?php

namespace Flowr\AmulenSyncBundle\Command;

use Amulen\ClassificationBundle\Entity\Category;
use Amulen\MediaBundle\Entity\Gallery;
use Amulen\MediaBundle\Entity\GalleryItem;
use Amulen\MediaBundle\Entity\Media;
use Amulen\MediaBundle\Entity\MediaType;
use Amulen\SettingsBundle\Model\SettingRepository;
use Amulen\ShopBundle\Entity\Product;
use Amulen\ShopBundle\Entity\ProductItemField;
use Amulen\ShopBundle\Entity\ProductItemFieldData;
use Flowcode\DashboardBundle\Command\AmulenCommand;
use Flowcode\DashboardBundle\Entity\Job;
use Flower\ModelBundle\Entity\Project\ProjectIteration;
use Flowr\AmulenSyncBundle\Entity\Setting;
use Flowr\AmulenSyncBundle\Service\SettingService;
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
class SyncProductsCommand extends AmulenCommand
{

    const FLOWR_URL_LOGIN = "/api/users/login";
    const FLOWR_URL_PRODUCT_GET = "/api/stock/products/forsale/pricelist";
    const FLOWR_ACCOUNT_ALREADY_CREATED = "Account already synced";
    const COMMAND_NAME = "amulen:flowr:syncproducts";


    private $mediaTypeRepo;
    private $productCategoryRepo;

    private $syncedProducts;


    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Syncronize products with Flowr');
    }


    /**
     * @param Product $product
     * @param $productArr
     * @return Product
     */
    private function processCategories(Product $product, $productArr)
    {
        if (isset($productArr['categories']) && count($productArr['categories']) > 0) {

            $categoryArr = $productArr['categories'][0];
            $productCateogory = $this->productCategoryRepo->findOneBy([
                'name' => $categoryArr['name'],
            ]);
            if ($productCateogory) {
                $product->setCategory($productCateogory);
            }
        }

        return $product;

    }

    /**
     * @param Product $product
     * @param $productArr
     * @return Product
     */
    private function processCustomFields(Product $product, $productArr)
    {
        if (isset($productArr['custom_fields']) && count($productArr['custom_fields']) > 0) {

            $product = $this->addProductItemFieldData($product);

            foreach ($productArr['custom_fields'] as $customFieldArr) {

                $settingFieldArr = $customFieldArr['setting_field'];

                $productItemFieldData = $product->getProductItemFieldDataByName($settingFieldArr['name']);
                if (!$productItemFieldData) {

                    $productItemField = new ProductItemField();
                    $productItemField->setName($settingFieldArr['name']);
                    $productItemField->setFieldLabel($settingFieldArr['field_label']);
                    $productItemField->setType($settingFieldArr['type']);
                    $this->getEM()->persist($productItemField);

                    $productItemFieldData = new ProductItemFieldData();
                    $productItemFieldData->setProduct($product);
                    $productItemFieldData->setProductItemField($productItemField);
                    $this->getEM()->persist($productItemFieldData);
                }
                if (isset($customFieldArr['value'])) {
                    $productItemFieldData->setData($customFieldArr['value']);
                }

            }
        }

        return $product;

    }

    /**
     * @param Product $product
     * @param $productArr
     * @return Product
     */
    private function processRawMaterials(Product $product, $productArr)
    {
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
        return $product;
    }

    /**
     * @param Product $product
     * @param $productArr
     * @return Product
     */
    private function processImages(Product $product, $productArr)
    {

        $mediaGallery = $product->getMediaGallery();
        if (!$mediaGallery) {
            $mediaGallery = new Gallery();
            $mediaGallery->setName($product->getName());
            $product->setMediaGallery($mediaGallery);
            $this->getEM()->persist($mediaGallery);
        }

        $imageMediaType = $this->mediaTypeRepo->findOneBy([
            'name' => 'image'
        ]);


        if (isset($productArr['image'])) {

            if (!$product->getMediaGallery()) {
                $media = new Media();
                $media->setName($productArr['image']);
                $media->setMediaType($imageMediaType);

                $basePath = $this->settings['url'];
                $completePath = $basePath . $productArr['image'];
                $media->setPath($completePath);

                $galleryItem = new GalleryItem();
                $galleryItem->setMedia($media);
                $galleryItem->setDescription($media->getName());

                $mediaGallery->addGalleryItem($galleryItem);

                $product->setMediaGallery($mediaGallery);
                $this->getEM()->persist($media);
                $this->getEM()->persist($galleryItem);


            } else {

                $gallery = $product->getMediaGallery();

                $basePath = $this->settings['url'];
                $completePath = $basePath . $productArr['image'];

                $galleryItems = $gallery->getGalleryItems();

                if ($galleryItems->count() > 0) {

                    $galleryItem = $galleryItems->first();
                    $galleryItem->getMedia()->setPath($completePath);

                } else {

                    $media = new Media();
                    $media->setName($productArr['image']);
                    $media->setMediaType($imageMediaType);
                    $media->setPath($completePath);
                    $this->getEM()->persist($media);


                    $galleryItem = new GalleryItem();
                    $galleryItem->setMedia($media);
                    $galleryItem->setDescription($media->getName());
                    $this->getEM()->persist($galleryItem);

                    $gallery->addGalleryItem($galleryItem);
                }
            }


        }

        return $product;
    }

    private function addProductItemFieldData(Product $product)
    {
        $em = $this->getEM();
        $itemFields = $em->getRepository('AmulenShopBundle:ProductItemField')->findAll();

        /* @var ProductItemField $itemField */
        foreach ($itemFields as $itemField) {
            if (!$product->getProductItemFieldData($itemField->getId())) {

                $fieldData = new ProductItemFieldData();
                $fieldData->setProductItemField($itemField);

                $product->addProductItemFieldData($fieldData);
            }
        }

        return $product;
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed
     */
    function task(InputInterface $input, OutputInterface $output)
    {
        $syncedProducts = [];

        $this->mediaTypeRepo = $this->getEM()->getRepository(MediaType::class);
        $this->productCategoryRepo = $this->getEM()->getRepository(Category::class);

        $token = null;

        $this->settings = [
            'url' => $this->getSettings()->get(Setting::FLOWR_URL),
            'username' => $this->getSettings()->get(Setting::FLOWR_USERNAME),
            'password' => $this->getSettings()->get(Setting::FLOWR_PASSWORD),
            'contactSource' => $this->getSettings()->get(Setting::FLOWR_CONTACT_SOURCE),
            'timeOut' => $this->getSettings()->get(Setting::SERVICE_TIMEOUT),
            'priceListId' => $this->getSettings()->get(Setting::FLOWR_PRODUCTS_PRICE_LIST),
        ];


        $client = new Client([
            'base_uri' => $this->settings['url'],
            'timeout' => $this->settings['timeOut'] ? $this->settings['timeOut'] : "10.0",
        ]);


        /* login */
        $resLogin = $client->request('POST', self::FLOWR_URL_LOGIN, array(
            'content_type' => "application/x-www-form-urlencoded",
            'form_params' => array(
                'username' => $this->settings['username'],
                'password' => $this->settings['password'],
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
                'query' => "pricelist=" . $this->settings['priceListId'],
            ));

            $code = $res->getStatusCode();
            if ($code == Response::HTTP_OK) {
                $body = $res->getBody();
                $responseArr = json_decode($body, true);

                $productRepo = $this->getEM()->getRepository(Product::class);

                foreach ($responseArr as $productArr) {

                    $output->write("Product id:");
                    $output->writeln($productArr['id']);

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

                    if (isset($productArr['description'])) {
                        $product->setDescription($productArr['description']);
                    }


                    $product = $this->processImages($product, $productArr);

                    $product = $this->processRawMaterials($product, $productArr);

                    $product = $this->processCustomFields($product, $productArr);

                    $product = $this->processCategories($product, $productArr);


                    if (isset($productArr['sale_price'])) {
                        $product->setPrice($productArr['sale_price']);
                    }


                    /* Track what was synced */
                    array_push($syncedProducts, $product->getId());

                }

                $this->getEM()->flush();
            }

        }

    }
}
