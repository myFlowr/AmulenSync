<?php

namespace Flowr\AmulenSyncBundle\Command;

use Amulen\ClassificationBundle\Entity\Category;
use Amulen\MediaBundle\Entity\Gallery;
use Amulen\MediaBundle\Entity\GalleryItem;
use Amulen\MediaBundle\Entity\Media;
use Amulen\MediaBundle\Entity\MediaType;
use Amulen\SettingsBundle\Model\SettingRepository;
use Amulen\ShopBundle\Entity\Service;
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
 * This command syncronize Flowr services with Amulen services.
 *
 * @author Juan Manuel Agüero <jaguero@flowcode.com.ar>
 */
class SyncServicesCommand extends AmulenCommand
{

    const FLOWR_URL_LOGIN = "/api/users/login";
    const FLOWR_URL_SERVICE_GET = "/api/stock/services/pricelist";
    const FLOWR_ACCOUNT_ALREADY_CREATED = "Account already synced";
    const COMMAND_NAME = "amulen:flowr:syncservices";


    private $mediaTypeRepo;
    private $serviceCategoryRepo;

    private $syncedServices;


    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Syncronize services with Flowr');
    }


    /**
     * @param Service $service
     * @param $serviceArr
     * @return Service
     */
    private function processCategories(Service $service, $serviceArr)
    {
        if (isset($serviceArr['categories']) && count($serviceArr['categories']) > 0) {

            $categoryArr = $serviceArr['categories'][0];
            $serviceCategory = $this->serviceCategoryRepo->findOneBy([
                'name' => $categoryArr['name'],
            ]);
            if ($serviceCategory) {
                $service->setCategory($serviceCategory);
            } else {
                $serviceCategory = new Category();
                $serviceCategory->setName($categoryArr['name']);
                $serviceCateogories = $this->serviceCategoryRepo->findBy([], ['position' => 'desc']);
                $position = 0;
                if (sizeof($serviceCateogories) > 0) {
                    $serviceCategory->setPosition($serviceCateogories[0]->getPosition() + 1);
                }
                $parent = $this->serviceCategoryRepo->findOneBy(['position' => 0, 'slug' => 'service']);
                if (!$parent) {
                    $parent = new Category();
                    $parent->setName('service');
                    $parent->setPosition(0);
                    $this->getEm()->persist($parent);
                }
                $serviceCategory->setParent($parent);
                $serviceCategory->setPosition($position);
                $this->getEm()->persist($serviceCategory);
                $this->getEm()->flush();
                $service->setCategory($serviceCategory);
            }
        }

        return $service;

    }

    /**
     * @param Service $service
     * @param $serviceArr
     * @return Service
     */
    private function processImages(Service $service, $serviceArr)
    {

        $mediaGallery = $service->getMediaGallery();
        if (!$mediaGallery) {
            $mediaGallery = new Gallery();
            $mediaGallery->setName($service->getName());
            $service->setMediaGallery($mediaGallery);
            $this->getEM()->persist($mediaGallery);
        }

        $imageMediaType = $this->mediaTypeRepo->findOneBy([
            'name' => 'image'
        ]);


        if (isset($serviceArr['image'])) {

            if (!$service->getMediaGallery()) {
                $media = new Media();
                $media->setName($serviceArr['image']);
                $media->setMediaType($imageMediaType);

                $basePath = $this->settings['url'];
                $completePath = $basePath . $serviceArr['image'];
                $media->setPath($completePath);

                $galleryItem = new GalleryItem();
                $galleryItem->setMedia($media);
                $galleryItem->setDescription($media->getName());

                $mediaGallery->addGalleryItem($galleryItem);

                $service->setMediaGallery($mediaGallery);
                $this->getEM()->persist($media);
                $this->getEM()->persist($galleryItem);


            } else {

                $gallery = $service->getMediaGallery();

                $basePath = $this->settings['url'];
                $completePath = $basePath . $serviceArr['image'];

                $galleryItems = $gallery->getGalleryItems();

                if ($galleryItems->count() > 0) {

                    $galleryItem = $galleryItems->first();
                    $galleryItem->getMedia()->setPath($completePath);

                } else {

                    $media = new Media();
                    $media->setName($serviceArr['image']);
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

        return $service;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed
     */
    function task(InputInterface $input, OutputInterface $output)
    {
        $syncedServices = [];

        $this->mediaTypeRepo = $this->getEM()->getRepository(MediaType::class);
        $this->serviceCategoryRepo = $this->getEM()->getRepository(Category::class);

        $token = null;

        $this->settings = [
            'url' => $this->getSettings()->get(Setting::FLOWR_URL),
            'username' => $this->getSettings()->get(Setting::FLOWR_USERNAME),
            'password' => $this->getSettings()->get(Setting::FLOWR_PASSWORD),
            'contactSource' => $this->getSettings()->get(Setting::FLOWR_CONTACT_SOURCE),
            'timeOut' => $this->getSettings()->get(Setting::SERVICE_TIMEOUT),
            'priceListId' => $this->getSettings()->get(Setting::FLOWR_SERVICES_PRICE_LIST),
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
            $res = $client->request('GET', self::FLOWR_URL_SERVICE_GET, array(
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

                $serviceRepo = $this->getEM()->getRepository(Service::class);

                foreach ($responseArr as $serviceArr) {

                    $output->write("Service id:");
                    $output->writeln($serviceArr['id']);

                    $service = $serviceRepo->findOneBy([
                        'flowrId' => $serviceArr['id']
                    ]);

                    if (!$service) {
                        $service = new Service();
                        $service->setFlowrId($serviceArr['id']);
                        $this->getEM()->persist($service);
                    }

                    // Update fields
                    if (isset($serviceArr['code'])) {
                        $service->setFlowrCode($serviceArr['code']);
                    }

                    $service->setFlowrSynced(true);
                    $service->setFlowrSyncEnabled(true);

                    $service->setName($serviceArr['name']);

                    if (isset($serviceArr['description'])) {
                        $service->setDescription($serviceArr['description']);
                    }

                    if (isset($serviceArr['detail'])) {
                        $service->setDetail($serviceArr['detail']);
                    }

                    $output->writeln("Processing images...");
                    $service = $this->processImages($service, $serviceArr);

                    $output->writeln("Processing categories...");
                    $service = $this->processCategories($service, $serviceArr);

                    if (isset($serviceArr['price'])) {
                        $service->setPrice($serviceArr['price']);
                    }

                    /* Track what was synced */
                    array_push($syncedServices, $service->getId());

                }

                $this->getEM()->flush();
            } else {
                throw new \Exception($res->getBody()->getContents());
            }
        } else {
            throw new \Exception('Fail login');
        }
    }
}
