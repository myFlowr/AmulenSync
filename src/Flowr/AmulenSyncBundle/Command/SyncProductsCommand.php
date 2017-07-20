<?php

namespace Flowr\AmulenSyncBundle\Command;

use Amulen\ClassificationBundle\Entity\Category;
use Amulen\ClassificationBundle\Entity\Tag;
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
use Flowcode\ShopBundle\Entity\Warehouse;
use Flowcode\ShopBundle\Entity\WarehouseProduct;
use Flower\ModelBundle\Entity\Project\ProjectIteration;
use Flowr\AmulenSyncBundle\Entity\Setting;
use Flowr\AmulenSyncBundle\Service\SettingService;
use Gedmo\Sluggable\Util\Urlizer;
use GuzzleHttp\Client;
use Intervention\Image\Image;
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
    const FLOWR_URL_PRODUCT_GET = "/api/stock/products/forsale";
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

    private function processStandardFields(Product $product, $productArr)
    {

        $product->setName($productArr['name']);

        if (isset($productArr['description'])) {
            $product->setDescription($productArr['description']);
        }

        if (isset($productArr['sale_price'])) {
            $product->setPrice($productArr['sale_price']);
        }

        if (isset($productArr['manual_pack_pricing'])) {
            if ($productArr['manual_pack_pricing']) {
                $product->setManualPackPricing(true);
            } else {
                $product->setManualPackPricing(false);
            }
        }

        if (isset($productArr['featured'])) {
            if ($productArr['featured']) {
                $product->setFeatured(true);
            } else {
                $product->setFeatured(false);
            }
        }

        if (isset($productArr['enabled'])) {
            if ($productArr['enabled']) {
                $product->setEnabled(true);
            } else {
                $product->setEnabled(false);
            }
        }

        return $product;

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
            } else {
                $productCateogory = new Category();
                $productCateogory->setName($categoryArr['name']);
                $productCateogories = $this->productCategoryRepo->findBy([], ['position' => 'desc']);
                $position = 0;
                if (sizeof($productCateogories) > 0) {
                    $productCateogory->setPosition($productCateogories[0]->getPosition() + 1);
                }
                $parent = $this->productCategoryRepo->findOneBy(['position' => 0]);
                $productCateogory->setParent($parent);
                $productCateogory->setPosition($position);
                $this->getEm()->persist($productCateogory);
                $this->getEm()->flush();
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
    private function processTags(Product $product, $productArr)
    {
        if (isset($productArr['tags'])) {

            foreach ($productArr['tags'] as $tagArr) {
                if (isset($tagArr['name'])) {
                    if (!$this->hasTag($product, $tagArr['name'])) {

                        $tag = $this->getEM()->getRepository(Tag::class)->findOneBy([
                            'name' => $tagArr['name']
                        ]);

                        if (!$tag) {
                            $tag = new Tag();
                            $tag->setName($tagArr['name']);
                            $this->getEM()->persist($tag);
                        }

                        $product->addTag($tag);
                    }
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
    private function processWarehouses(Product $product, $productArr)
    {
        if (isset($productArr['warehouses_stock'])) {

            foreach ($productArr['warehouses_stock'] as $warehouseStockArr) {

                $warehouseArr = $warehouseStockArr['warehouse'];
                $wareHouse = $this->getEM()->getRepository(Warehouse::class)->findOneBy([
                    'name' => $warehouseArr['name'],
                ]);

                $wareHouseProduct = null;
                if (!$wareHouse) {

                    $wareHouse = new Warehouse();
                    $wareHouse->setName($warehouseArr['name']);
                    if (isset($warehouseArr['address'])) {
                        $wareHouse->setAddress($warehouseArr['address']);
                    }
                    if (isset($warehouseArr['lat'])) {
                        $wareHouse->setLat($warehouseArr['lat']);
                    }
                    if (isset($warehouseArr['lng'])) {
                        $wareHouse->setLng($warehouseArr['lng']);
                    }
                    if (isset($warehouseArr['phone'])) {
                        $wareHouse->setPhone($warehouseArr['phone']);
                    }

                    $this->getEM()->persist($wareHouse);
                    $this->getEM()->flush();

                }

                $wareHouseProduct = $this->getEM()->getRepository(WarehouseProduct::class)->findOneBy([
                    'product' => $product->getId(),
                    'warehouse' => $warehouseStockArr['warehouse']['id'],
                ]);

                if (!$wareHouseProduct) {
                    $wareHouseProduct = new WarehouseProduct();
                    $wareHouseProduct->setProduct($product);
                    $wareHouseProduct->setWarehouse($wareHouse);
                    $this->getEM()->persist($wareHouseProduct);
                }

                $wareHouseProduct->setStock($warehouseStockArr['stock']);
            }

        }

        // Default warehouse.
        if (isset($productArr['warehouse'])) {
            $warehouseArr = $productArr['warehouse'];
            $wareHouse = $this->getEM()->getRepository(Warehouse::class)->findOneBy([
                'name' => $warehouseArr['name'],
            ]);
            if (!$wareHouse) {

                $wareHouse = new Warehouse();
                $wareHouse->setName($warehouseArr['name']);
                if (isset($warehouseArr['address'])) {
                    $wareHouse->setAddress($warehouseArr['address']);
                }
                if (isset($warehouseArr['lat'])) {
                    $wareHouse->setLat($warehouseArr['lat']);
                }
                if (isset($warehouseArr['lng'])) {
                    $wareHouse->setLng($warehouseArr['lng']);
                }
                if (isset($warehouseArr['phone'])) {
                    $wareHouse->setPhone($warehouseArr['phone']);
                }

                $this->getEM()->persist($wareHouse);
                $this->getEM()->flush();
            }
            $product->setWarehouse($wareHouse);

        }

        return $product;
    }

    private function hasTag(Product $produdct, $name)
    {
        foreach ($produdct->getTags() as $tag) {
            if (strtolower($tag->getName()) == strtolower($name)) {
                return true;
            }
        }
        return false;
    }

    private function getImageName($imageName, $imageUrl)
    {
        $basePath = $this->settings['url'];
        $completePath = $basePath . $imageUrl;

        $content = file_get_contents($completePath);

        $baseDir = $this->getContainer()->getParameter('uploads_base_dir');
        $productsDir = "/uploads" . $this->getContainer()->getParameter('uploads_product_dir');

        $imageFileName = $productsDir . Urlizer::transliterate($imageName) . ".jpg";
        $imageFilePath = $baseDir . $imageFileName;

        file_put_contents($imageFilePath, $content);
        return $imageFileName;
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

                $imageFileName = $this->getImageName($product->getName(), $productArr['image']);
                $media->setPath($imageFileName);

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

                    $imageFileName = $this->getImageName($product->getName(), $productArr['image']);
                    $media->setPath($imageFileName);
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
     * @throws \Exception
     */
    function task(InputInterface $input, OutputInterface $output)
    {
        $syncedProducts = [];

        $this->mediaTypeRepo = $this->getEM()->getRepository(MediaType::class);
        $this->productCategoryRepo = $this->getEM()->getRepository(Category::class);

        $token = null;

        /* @var \DateTime $lastRun */
        $lastRun = $this->getJob()->getLastSuccessfulRun();

        $output->writeln(" - Last run was on " . ($lastRun ? $lastRun->format('Y-m-d H:i:s') : 'never'));


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

            $queryParams = [];
            $queryParams['includeDisabled'] = 1;
            $queryParams['pricelist'] = $this->settings['priceListId'];

            if ($lastRun) {
                //$queryParams['since'] = $lastRun->format('Y-m-d H:i:s');
            }

            $res = $client->request('GET', self::FLOWR_URL_PRODUCT_GET, array(
                'content_type' => "application/x-www-form-urlencoded",
                'headers' => array(
                    'Authorization' => "Bearer $token",
                ),
                'query' => $queryParams,
            ));

            $code = $res->getStatusCode();
            if ($code == Response::HTTP_OK) {
                $body = $res->getBody();
                $responseArr = json_decode($body, true);

                $productRepo = $this->getEM()->getRepository(Product::class);

                $output->writeln(" - About to sync " . count($responseArr) . " entities.");
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



                    $output->writeln("Processing standard fields . . .");
                    $product = $this->processStandardFields($product, $productArr);

                    $output->writeln("Processing images . . .");
                    $product = $this->processImages($product, $productArr);

                    $output->writeln("Processing raw materials . . .");
                    $product = $this->processRawMaterials($product, $productArr);

                    $output->writeln("Processing custom fields . . .");
                    $product = $this->processCustomFields($product, $productArr);

                    $output->writeln("Processing categories . . .");
                    $product = $this->processCategories($product, $productArr);

                    $output->writeln("Processing tags . . .");
                    $product = $this->processTags($product, $productArr);

                    $output->writeln("Processing warehouses . . .");
                    $product = $this->processWarehouses($product, $productArr);


                    /* Track what was synced */
                    $output->writeln("Tracking product...");
                    array_push($syncedProducts, $product->getId());

                }

                $this->getEM()->flush();

            } else {
                throw new \Exception('Not not not');
            }

        }

    }
}
