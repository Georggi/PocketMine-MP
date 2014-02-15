<?php

/**
 *
 *  ____            _        _   __  __ _                  __  __ ____  
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \ 
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/ 
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_| 
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 * 
 *
*/

class FlatGenerator implements LevelGenerator{
	private $level, $random, $structure, $chunks, $options, $floorLevel, $populators = array();
	
	public function getSettings(){
		return $this->options;
	}
	
	public function __construct(array $options = array()){
		//$this->preset = "2;7,59x1,3x3,2;1;decoration";
		$this->preset = "2;7,59x1,3x3,2;1;";
		$this->options = $options;
		if(isset($options["preset"])){
			$this->parsePreset($options["preset"]);
		}else{
			$this->parsePreset($this->preset);
		}
		if(isset($this->options["decoration"])){
			$ores = new OrePopulator();
			$ores->setOreTypes(array(
				new OreType(new CoalOreBlock(), 20, 16, 0, 128),
				new OreType(New IronOreBlock(), 20, 8, 0, 64),
				new OreType(new RedstoneOreBlock(), 8, 7, 0, 16),
				new OreType(new LapisOreBlock(), 1, 6, 0, 32),
				new OreType(new GoldOreBlock(), 2, 8, 0, 32),
				new OreType(new DiamondOreBlock(), 1, 7, 0, 16),
				new OreType(new DirtBlock(), 20, 32, 0, 128),
				new OreType(new GravelBlock(), 10, 16, 0, 128),
			));
			$this->populators[] = $ores;
			
			$trees = new TreePopulator();
			$trees->setBaseAmount(1);
			$trees->setRandomAmount(0);
			$this->populators[] = $trees;
			
			$tallGrass = new TallGrassPopulator();
			$tallGrass->setBaseAmount(3);
			$tallGrass->setRandomAmount(0);
			$this->populators[] = $tallGrass;
		}
		
		/*if(isset($this->options["mineshaft"])){
			$this->populators[] = new MineshaftPopulator(isset($this->options["mineshaft"]["chance"]) ? floatval($this->options["mineshaft"]["chance"]) : 0.01);
		}*/
	}
	
	public function parsePreset($preset){
		$this->preset = $preset;
		$preset = explode(";", $preset);
		$version = (int) $preset[0];
		$blocks = @$preset[1];
		$biome = isset($preset[2]) ? $preset[2]:1;
		$options = isset($preset[3]) ? $preset[3]:"";
		preg_match_all('#(([0-9]{0,})x?([0-9]{1,3}:?[0-9]{0,2})),?#', $blocks, $matches);
		$y = 0;
		$this->structure = array();
		$this->chunks = array();
		foreach($matches[3] as $i => $b){
			$b = BlockAPI::fromString($b);
			$cnt = $matches[2][$i] === "" ? 1:intval($matches[2][$i]);
			for($cY = $y, $y += $cnt; $cY < $y; ++$cY){
				$this->structure[$cY] = $b;
			}
		}
		
		$this->floorLevel = $y;
		
		for(;$y < 0xFF; ++$y){
			$this->structure[$y] = new AirBlock();
		}
		
		
		for($Y = 0; $Y < 8; ++$Y){
			$this->chunks[$Y] = "";
			$startY = $Y << 4;
			$endY = $startY + 16;
			for($Z = 0; $Z < 16; ++$Z){
				for($X = 0; $X < 16; ++$X){
					$blocks = "";
					$metas = "";
					for($y = $startY; $y < $endY; ++$y){
						$blocks .= chr($this->structure[$y]->getID());
						$metas .= substr(dechex($this->structure[$y]->getMetadata()), -1);
					}
					$this->chunks[$Y] .= $blocks.hex2bin($metas)."\x00\x00\x00\x00\x00\x00\x00\x00";
				}
			}
		}
		
		preg_match_all('#(([0-9a-z_]{1,})\(?([0-9a-z_ =:]{0,})\)?),?#', $options, $matches);
		foreach($matches[2] as $i => $option){
			$params = true;
			if($matches[3][$i] !== ""){
				$params = array();
				$p = explode(" ", $matches[3][$i]);
				foreach($p as $k){
					$k = explode("=", $k);
					if(isset($k[1])){
						$params[$k[0]] = $k[1];
					}
				}
			}
			$this->options[$option] = $params;
		}
	}
	
	public function init(Level $level, Random $random){
		$this->level = $level;
		$this->random = $random;
	}
		
	public function generateChunk($chunkX, $chunkZ){
		for($Y = 0; $Y < 8; ++$Y){
			$this->level->setMiniChunk($chunkX, $chunkZ, $Y, $this->chunks[$Y]);
		}
	}
	
	public function populateChunk($chunkX, $chunkZ){
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());
		foreach($this->populators as $populator){
			$populator->populate($this->level, $chunkX, $chunkZ, $this->random);
		}
	}
	
	public function getSpawn(){
		return new Vector3(128, $this->floorLevel, 128);
	}
}