# AmulenSync
Amulen plugin for syncronize data with Flowr.


## Install

Require with composer:
```
  composer require flowr/amulen-sync
```

Register the plugin with command:
```
  php app/console amulen:plugin:register "Flowr\AmulenSyncBundle\FlowrAmulenSyncBundle"
```

Update DB schema:
```
  php app/console doctrine:schema:update --force
```

## Setup

Add fields to the user models:
```

  // src/Amulen/UserBundle/Entity/User.php

  /**
   * @var string
   *
   * @ORM\Column(name="flowr_id", type="string", length=255, nullable=true)
   */
  protected $flowrId;

  /**
   * @var string
   *
   * @ORM\Column(name="flowr_code", type="string", length=255, nullable=true)
   */
  protected $flowrCode;

  /**
   * @var string
   *
   * @ORM\Column(name="flowr_synced", type="boolean")
   */
  protected $flowrSynced;
  
  /**
   * @var boolean
   *
   * @ORM\Column(name="flowr_sync_enabled", type="boolean")
   */
  protected $flowrSyncEnabled;
  
  
  public function __construct()
  {
      parent::__construct();
      $this->flowrSynced = false;
      $this->flowrSyncEnabled = false;
  }
  
      
```

Don`t forget to add getters and setters.

Then setup your Flowr settings in the admin section:
* flowr_url (required): http://demo2.myflowr.com
* flowr_username (required):	jaguero
* flowr_password (required):	123456
* flowr_contact_source (prefered but optional):	Test Kaka Hanga
* flowr_fields:	firstname,lastname,email
* service_timeout:	10.0

