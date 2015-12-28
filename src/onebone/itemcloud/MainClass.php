<?php

namespace onebone\itemcloud;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

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

	/**************************   Non-API part   ***********************************/

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
			$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new SaveTask($this), $interval * 1200, $interval * 1200);
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
					case "reg":
						if(!$sender->hasPermission("itemcloud.command.register")){
							$sender->sendMessage(TextFormat::RED."You don't have permission to use this command.");
							return true;
						}
						if(isset($this->clouds[strtolower($sender->getName())])){
							$sender->sendMessage("[ItemCloud] You already have your ItemCloud account");
							break;
						}
						$this->clouds[strtolower($sender->getName())] = new ItemCloud([], $sender->getName());
						$sender->sendMessage("[ItemCloud] Registered to the ItemCloud account");
						break;
					case "upload":
					case "up":
						if(!$sender->hasPermission("itemcloud.command.upload")){
							$sender->sendMessage(TextFormat::RED."You don't have permission to use this command.");
							return true;
						}
						if(!isset($this->clouds[strtolower($sender->getName())])){
							$sender->sendMessage("[ItemCloud] Please register to the ItemCloud service first.");
							break;
						}
						$item = array_shift($params);
						$amount = array_shift($params);
						if(trim($item) === "" or !is_numeric($amount)){
							usage:
							$sender->sendMessage("Usage: /itemcloud upload <item ID[:item damage]> <count>");
							break;
						}
						$amount = (int) $amount;
						if($amount < 1){
							usage:
							$sender->sendMessage("Wrong amount");
							break;
						}
						$item = Item::fromString($item);
						$item->setCount($amount);

						$count = 0;
						foreach($sender->getInventory()->getContents() as $i){
							if($i->getID() == $item->getID() and $i->getDamage() == $item->getDamage()){
								$count += $i->getCount();
							}
						}
						if($amount <= $count){
							$this->clouds[strtolower($sender->getName())]->addItem($item->getID(), $item->getDamage(), $amount, true);
							$sender->sendMessage("[ItemCloud] Uploaded your item to ItemCloud account.");
						}else{
							$sender->sendMessage("[ItemCloud] You don't have enough item to upload.");
						}
						break;
					case "download":
					case "down":
						if(!$sender->hasPermission("itemcloud.command.download")){
							$sender->sendMessage(TextFormat::RED."You don't have permission to use this command.");
							return true;
						}
						$name = strtolower($sender->getName());
						if(!isset($this->clouds[$name])){
							$sender->sendMessage("[ItemCloud] Please register to the ItemCloud first.");
							break;
						}
						$item = array_shift($params);
						$amount = array_shift($params);
						if(trim($item) === "" or !is_numeric($amount)){
							usage2:
							$sender->sendMessage("Usage: /itemcloud download <item ID[:item damage]> <count>");
							break;
						}
						$amount = (int)$amount;
						if($amount < 1){
							usage:
							$sender->sendMessage("Wrong amount");
							break;
						}
						$item = Item::fromString($item);
						$item->setCount($amount);

						if(!$this->clouds[$name]->itemExists($item->getID(), $item->getDamage(), $amount)){
							$sender->sendMessage("[ItemCloud] You don't have enough item in your account.");
							break;
						}

						if($sender->getInventory()->canAddItem($item)){
							$this->clouds[$name]->removeItem($item->getID(), $item->getDamage(), $amount);
							$sender->getInventory()->addItem($item);
							$sender->sendMessage("[ItemCloud] You have downloaded items from the ItemCloud account.");
						}else{
							$sender->sendMessage("[ItemCloud] You have no space to download items.");
						}
						break;
					case "list":
						if(!$sender->hasPermission("itemcloud.command.list")){
							$sender->sendMessage(TextFormat::RED."You don't have permission to use this command.");
							return true;
						}
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
						if(!$sender->hasPermission("itemcloud.command.count")){
							$sender->sendMessage(TextFormat::RED."You don't have permission to use this command.");
							return true;
						}
						$name = strtolower($sender->getName());
						if(!isset($this->clouds[$name])){
							$sender->sendMessage("[ItemCloud] Please register to the ItemCloud first.");
							return true;
						}
						$item = array_shift($params);
						if(trim($item) === ""){
							$sender->sendMessage("Usage: /itemcloud count <item>");
							return true;
						}

						$item = Item::fromString($item);

						if(($count = $this->clouds[$name]->getCount($item->getID(), $item->getDamage())) === false){
							$sender->sendMessage("[ItemCloud] There are no ".$item->getName()." in your account.");
							break;
						}else{
							$sender->sendMessage("[ItemCloud] Count of ".$item->getName()." = ".$count);
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
