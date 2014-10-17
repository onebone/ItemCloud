<?php

namespace onebone\itemcloud;

use pocketmine\Player;
use pocketmine\Server;

class ItemCloud{
	/**
	 * @var Server
	 */
	private $server;

	private $items, $player;

	public function __construct($items, $player){
		$this->server = Server::getInstance();
		if($player instanceof Player){
			$this->player = $player->getName();
		}else{
			$this->player = $player;
		}
		$this->player = strtolower($this->player);
		$this->items = $items;
	}

	/**
	 * @param int $id
	 * @param int $damage
	 * @param int $count
	 * @param bool $removeInv
	 *
	 * @return bool
	 */
	public function addItem($id, $damage, $count, $removeInv = true){
		if($removeInv === true){
			$p = $this->server->getPlayerExact($this->player);
			if(!$p instanceof Player){
				return false;
			}
			$tmp = $count;
			foreach($p->getInventory()->getContents() as $slot => $content){
				if($content->getID() == $id and $content->getDamage() == $damage){
					if($tmp <= 0) break;
					$take = min($content->getCount(), $tmp);
					$tmp -= $take;
					$content->setCount($content->getCount() - $take);
					$p->getInventory()->setItem($slot, $content);
				}
			}
		}

		if(isset($this->items[$id.":".$damage])){
			$this->items[$id.":".$damage] += $count;
		}else{
			$this->items[$id.":".$damage] = $count;
		}
		return true;
	}

	public function itemExists($item, $damage, $amount){
		$cnt = 0;
		foreach($this->items as $i => $a){
			if($i === $item.":".$damage){
				$cnt += $a;
				if($amount <= $cnt){
					return true;
				}
			}
		}
		return false;
	}

	public function removeItem($item, $damage = 0, $amount = 64){
		$cnt = 0;
		foreach($this->items as $s => $i){
			if($s === $item.":".$damage){
				$cnt += $i;
			}
		}
		if((int) $cnt < (int) $amount){
			return false;
		}
		$this->items[$item.":".$damage] -= $amount;
		if($this->items[$item.":".$damage] <= 0){
			unset($this->items[$item.":".$damage]);
		}
		return true;
	}

	public function getCount($id, $damage = 0){
		return isset($this->items[$id.":".$damage]) ? $this->items[$id.":".$damage] : false;
	}

	public function getAll(){
		return [
			$this->items,
			$this->player
		];
	}

	public function getPlayer(){
		return $this->player;
	}

	public function getItems(){
		return $this->items;
	}
}