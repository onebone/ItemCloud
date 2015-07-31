<?php

namespace onebone\itemcloud;

use pocketmine\scheduler\PluginTask;

class SaveTask extends PluginTask{
  public function __construct(MainClass $plugin){
    parent::__construct($plugin);
  }

  public function onRun($currentTick){
    $this->getOwner()->save();
  }
}
