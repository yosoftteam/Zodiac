<?php

/*
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

declare(strict_types=1);

namespace pocketmine\block\tile;

use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use pocketmine\world\World;
use function assert;
use function in_array;
use function is_a;
use function reset;

final class TileFactory{
	use SingletonTrait;

	/**
	 * @var string[] classes that extend Tile
	 * @phpstan-var array<string, class-string<Tile>>
	 */
	private $knownTiles = [];
	/**
	 * @var string[][]
	 * @phpstan-var array<class-string<Tile>, list<string>>
	 */
	private $saveNames = [];
	/**
	 * @var string[] base class => overridden class
	 * @phpstan-var array<class-string<Tile>, class-string<Tile>>
	 */
	private $classMapping = [];

	public function __construct(){
		$this->register(Banner::class, ["Banner", "minecraft:banner"]);
		$this->register(Bed::class, ["Bed", "minecraft:bed"]);
		$this->register(BrewingStand::class, ["BrewingStand", "minecraft:brewing_stand"]);
		$this->register(Chest::class, ["Chest", "minecraft:chest"]);
		$this->register(Comparator::class, ["Comparator", "minecraft:comparator"]);
		$this->register(DaylightSensor::class, ["DaylightDetector", "minecraft:daylight_detector"]);
		$this->register(EnchantTable::class, ["EnchantTable", "minecraft:enchanting_table"]);
		$this->register(EnderChest::class, ["EnderChest", "minecraft:ender_chest"]);
		$this->register(FlowerPot::class, ["FlowerPot", "minecraft:flower_pot"]);
		$this->register(Furnace::class, ["Furnace", "minecraft:furnace"]);
		$this->register(Hopper::class, ["Hopper", "minecraft:hopper"]);
		$this->register(ItemFrame::class, ["ItemFrame"]); //this is an entity in PC
		$this->register(MonsterSpawner::class, ["MobSpawner", "minecraft:mob_spawner"]);
		$this->register(ShulkerBox::class, ["ShulkerBox", "minecraft:shulker_box"]);
		$this->register(Note::class, ["Music", "minecraft:noteblock"]);
		$this->register(Sign::class, ["Sign", "minecraft:sign"]);
		$this->register(Skull::class, ["Skull", "minecraft:skull"]);

		//TODO: Barrel
		//TODO: Beacon
		//TODO: Bell
		//TODO: BlastFurnace
		//TODO: Campfire
		//TODO: Cauldron
		//TODO: ChalkboardBlock
		//TODO: ChemistryTable
		//TODO: CommandBlock
		//TODO: Conduit
		//TODO: Dispenser
		//TODO: Dropper
		//TODO: EndGateway
		//TODO: EndPortal
		//TODO: JigsawBlock
		//TODO: Jukebox
		//TODO: Lectern
		//TODO: MovingBlock
		//TODO: NetherReactor
		//TODO: PistonArm
		//TODO: Smoker
		//TODO: StructureBlock
	}

	/**
	 * @param string[] $saveNames
	 * @phpstan-param class-string<Tile> $className
	 */
	public function register(string $className, array $saveNames = []) : void{
		Utils::testValidInstance($className, Tile::class);

		$this->classMapping[$className] = $className;

		$shortName = (new \ReflectionClass($className))->getShortName();
		if(!in_array($shortName, $saveNames, true)){
			$saveNames[] = $shortName;
		}

		foreach($saveNames as $name){
			$this->knownTiles[$name] = $className;
		}

		$this->saveNames[$className] = $saveNames;
	}

	/**
	 * @param string $baseClass Already-registered tile class to override
	 * @param string $newClass Class which extends the base class
	 *
	 * TODO: use an explicit template for param1
	 * @phpstan-param class-string<Tile> $baseClass
	 * @phpstan-param class-string<Tile> $newClass
	 *
	 * @throws \InvalidArgumentException if the base class is not a registered tile
	 */
	public function override(string $baseClass, string $newClass) : void{
		if(!isset($this->classMapping[$baseClass])){
			throw new \InvalidArgumentException("Class $baseClass is not a registered tile");
		}

		Utils::testValidInstance($newClass, $baseClass);
		$this->classMapping[$baseClass] = $newClass;
	}

	/**
	 * @phpstan-template TTile of Tile
	 * @phpstan-param class-string<TTile> $baseClass
	 *
	 * @return Tile (will be an instanceof $baseClass)
	 * @phpstan-return TTile
	 *
	 * @throws \InvalidArgumentException if the specified class is not a registered tile
	 */
	public function create(string $baseClass, World $world, Vector3 $pos) : Tile{
		if(isset($this->classMapping[$baseClass])){
			$class = $this->classMapping[$baseClass];
			assert(is_a($class, $baseClass, true));
			/**
			 * @var Tile $tile
			 * @phpstan-var TTile $tile
			 * @see Tile::__construct()
			 */
			$tile = new $class($world, $pos);

			return $tile;
		}

		throw new \InvalidArgumentException("Class $baseClass is not a registered tile");
	}

	/**
	 * @internal
	 */
	public function createFromData(World $world, CompoundTag $nbt) : ?Tile{
		$type = $nbt->getString(Tile::TAG_ID, "");
		if(!isset($this->knownTiles[$type])){
			return null;
		}
		$class = $this->knownTiles[$type];
		assert(is_a($class, Tile::class, true));
		/**
		 * @var Tile $tile
		 * @see Tile::__construct()
		 */
		$tile = new $class($world, new Vector3($nbt->getInt(Tile::TAG_X), $nbt->getInt(Tile::TAG_Y), $nbt->getInt(Tile::TAG_Z)));
		$tile->readSaveData($nbt);

		return $tile;
	}

	/**
	 * @phpstan-param class-string<Tile> $class
	 */
	public function getSaveId(string $class) : string{
		if(isset($this->saveNames[$class])){
			return reset($this->saveNames[$class]);
		}
		throw new \InvalidArgumentException("Tile $class is not registered");
	}
}
