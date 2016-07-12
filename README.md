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
      
```

Don`t forget to add getters and setters.


