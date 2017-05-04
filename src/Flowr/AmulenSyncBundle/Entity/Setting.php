<?php

namespace Flowr\AmulenSyncBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Setting
 *
 * @ORM\Table(name="flowr_sync_settings")
 * @ORM\Entity
 */
class Setting
{

    const FLOWR_URL = "flowr_url";
    const FLOWR_USERNAME = "flowr_username";
    const FLOWR_PASSWORD = "flowr_password";
    const FLOWR_CONTACT_SOURCE = "flowr_contact_source";
    const FLOWR_SALES_SALE_POINT = "flowr_sales_sale_point";
    const FLOWR_SALES_SALE_CATEGORY = "flowr_sales_sale_category";
    const FLOWR_PRODUCTS_PRICE_LIST = "flowr_products_price_list";
    const FLOWR_SERVICES_PRICE_LIST = "flowr_services_price_list";
    const FLOWR_FIELDS = "flowr_fields";
    const SERVICE_TIMEOUT = "service_timeout";

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="value", type="string", length=255)
     */
    private $value;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Setting
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set value
     *
     * @param string $value
     *
     * @return Setting
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}

