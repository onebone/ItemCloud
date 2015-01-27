<?php

namespace onebone\itemcloud;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\CallbackTask;

class MainClass extends PluginBase implements Listener{
	/**
	 * @var MainClass
	 */
	private static $instance;

	/**
	 * @var ItemCloud[]
	 */
	private $clouds;

	/**
	 * @return MainClass
	 */
	public static function getInstance(){
		return self::$instance;
	}

	/**
	 * @param Player|string $player
	 *
	 * @return ItemCloud|bool
	 */
	public function getCloudForPlayer($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);

		if(isset($this->clouds[$player])){
			return $this->clouds[$player];
		}
		return false;
	}

	/**************************   Below part is a non-API part   ***********************************/

	public function onEnable(){
		if(!self::$instance instanceof MainClass){
			self::$instance = $this;
		}
		@mkdir($this->getDataFolder());
		if(!is_file($this->getDataFolder()."ItemCloud.dat")){
			file_put_contents($this->getDataFolder()."ItemCloud.dat", serialize([]));
		}
		$data = unserialize(file_get_contents($this->getDataFolder()."ItemCloud.dat"));

		$this->saveDefaultConfig();
		if(is_numeric($interval = $this->getConfig()->get("auto-save-interval"))){
			$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new CallbackTask([$this, "save"], []), $interval * 1200, 1);
		}
		
		$this->clouds = [];
		foreach($data as $datam){
			$this->clouds[$datam[1]] = new ItemCloud($datam[0], $datam[1]);
		}
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $params){
		switch($command->getName()){
			case "itemcloud":
				if(!$sender instanceof Player){
					$sender->sendMessage("Please run this command in-game");
					return true;
				}
				$sub = array_shift($params);
				switch($sub){
					case "register":
						if(isset($this->clouds[strtolower($sender->getName())])){
							$sender->sendMessage("[ItemCloud] You already have your ItemCloud account");
							break;
						}
						$this->clouds[strtolower($sender->getName())] = new ItemCloud([], $sender->getName());
						$sender->sendMessage("[ItemCloud] Registered to the ItemCloud account");
						break;
					case "upload":
						if(!isset($this->clouds[strtolower($sender->getName())])){
							$sender->sendMessage("[ItemCloud] Please register to the ItemCloud service first.");
							break;
						}
						$id = array_shift($params);
						$amount = array_shift($params);
						if(trim($id) === "" or !is_numeric($amount)){
							usage:
							$sender->sendMessage("Usage: /itemcloud upload <item ID[:item damage]> <count>");
							break;
						}
						$amount = (int) $amount;
						$e = explode(":", $id);
						if(!isset($e[1])){
							$e[1] = 0;
						}
						if(!is_numeric($e[0]) or !is_numeric($e[1])){
							goto usage;
						}

						$count = 0;
						foreach($sender->getInventory()->getContents() as $item){
							if($item->getID() == $e[0] and $item->getDamage() == $e[1]){
								$count += $item->getCount();
							}
						}
						if($amount <= $count){
							$this->clouds[strtolower($sender->getName())]->addItem($e[0], $e[1], $amount, true);
							$sender->sendMessage("[ItemCloud] Uploaded your item to ItemCloud account.");
						}else{
							$sender->sendMessage("[ItemCloud] You don't have enough item to upload.");
						}
						break;
					case "download":
						$name = strtolower($sender->getName());
						if(!isset($this->clouds[$name])){
							$sender->sendMessage("[ItemCloud] Please register to the ItemCloud first.");
							break;
						}
						$id = array_shift($params);
						$amount = array_shift($params);
						if(trim($id) === "" or !is_numeric($amount)){
							usage2:
							$sender->sendMessage("Usage: /itemcloud download <item ID[:item damage]> <count>");
							break;
						}
						$amount = (int)$amount;
						$e = explode(":", $id);
						if(!isset($e[1])){
							$e[1] = 0;
						}
						if(!is_numeric($e[0]) or !is_numeric($e[1])){
							goto usage2;
						}
						
						if(!$this->clouds[$name]->itemExists($e[0], $e[1], $amount)){
							$sender->sendMessage("[ItemCloud] You don't have enough item in your account.");
							break;
						}
						$item = Item::get((int)$e[0], (int)$e[1], $amount);
						if($sender->getInventory()->canAddItem($item)){
							$this->clouds[$name]->removeItem($e[0], $e[1], $amount);
							$sender->getInventory()->addItem($item);
							$sender->sendMessage("[ItemCloud] You have downloaded items from the ItemCloud account.");
						}else{
							$sender->sendMessage("[ItemCloud] You have no space to download items.");
						}
						break;
					case "list":
						$name = strtolower($sender->getName());
						if(!isset($this->clouds[$name])){
							$sender->sendMessage("[ItemCloud] Please register to the ItemCloud first.");
							break;
						}
						$output = "[ItemCloud] Item list : \n";
						foreach($this->clouds[$name]->getItems() as $item => $count){
							$output .= "$item : $count\n";
						}
						$sender->sendMessage($output);
						break;
					case "count":
						$name = strtolower($sender->getName());
						if(!isset($this->clouds[$name])){
							$sender->sendMessage("[ItemCloud] Please register to the ItemCloud first.");
							break;
						}
						$id = array_shift($params);
						$e = explode(":", $id);
						if(!isset($e[1])){
							$e[1] = 0;
						}

						if(($count = $this->clouds[$name]->getCount($e[0], $e[1])) === false){
							$sender->sendMessage("[ItemCloud] There are no ".$e[0].":".$e[1]." in your account.");
							break;
						}else{
							$sender->sendMessage("[ItemCloud] Count of ".$e[0].":".$e[1]." = ".$count);
						}
						break;
					default:
						$sender->sendMessage("[ItemCloud] Usage: ".$command->getUsage());
				}
				return true;
		}
		return false;
	}

	public function save(){
		$save = [];
		foreach($this->clouds as $cloud){
			$save[] = $cloud->getAll();
		}
		file_put_contents($this->getDataFolder()."ItemCloud.dat", serialize($save));
	}

	public function onDisable(){
		$this->save();
		$this->clouds = [];
	}
}