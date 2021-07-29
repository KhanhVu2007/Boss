<?php

declare(strict_types=1);

namespace Labality\Boss;

use DirectoryIterator;
use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\ItemFactory;
use pocketmine\level\Position;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Main extends PluginBase{

    private array $bosses = [];

    private static self $instance;

    /**
     * @return Main
     */
    public static function getInstance(): Main
    {
        return self::$instance;
    }

    public function getBosses(): array{
        return $this->bosses;
    }

    public function getBoss(string $id): ?Boss{
        return $this->bosses[$id] ?? null;
    }

    public function onEnable(): void{
        self::$instance = $this;

        Entity::registerEntity(Model::class, true, ["bossModel"]);
        $this->saveDefaultConfig();
        $this->resourceFletch();
    }

    public function resourceFletch(): void{
        foreach (new DirectoryIterator($this->getDataFolder() . "boss") as $file) {
            if($file->isDot()) continue;
            $forceSkip = false;
            $config = new Config($file->getPathname());
            Server::getInstance()->getLogger()->info(TextFormat::colorize("&l&cBoss&8 >&6 Đang hình thành boss với mã:&a " . $config->get("id")));

            //Initialize position


            $rawPosition = $config->get("spawnPos");

            //Loading world
            if(!$this->getServer()->isLevelLoaded($rawPosition[3])){
                Server::getInstance()->getLogger()->info(TextFormat::colorize("&l&cBoss&8 >&6 Chưa load world với tên&a {$rawPosition[3]}&6. Tự động load world.."));
                if(!$this->getServer()->loadLevel($rawPosition[3])){
                    Server::getInstance()->getLogger()->info(TextFormat::colorize("&l&cBoss&8 >&6 Có vấn đề trong việc load &a {$rawPosition[3]}&6. Tự động skip.."));
                    continue;
                }
            }

            $position = new Position($rawPosition[0], $rawPosition[1], $rawPosition[2], $this->getServer()->getLevelByName($rawPosition[3]));

            //Load chunk

            $chunk = $position->getLevel()->getChunkAtPosition($position, true);

            $position->getLevel()->loadChunk($chunk->getX(), $chunk->getZ());

            Server::getInstance()->getLogger()->info(TextFormat::colorize("&l&cBoss&8 >&6 Load spawn của boss&a {$config->get("id")}&6 thành công! Spawn là:&a " . (string) $position));

            //Model resolver
            $modelId = $config->get("model");
            $skinPath = $this->getDataFolder() . "models/" . $modelId . "_skin.png";
            $geometryPath = $this->getDataFolder() . "models/" . $modelId . "_geometry.geometry";

            if(!file_exists($skinPath)){
                Server::getInstance()->getLogger()->info(TextFormat::colorize("&l&cBoss&8 >&6 Model &a{$modelId}&6 không tồn tại skin. Bỏ qua boss."));
                continue;
            }

            $skinData = Utils::getImageData($skinPath);
            $geometryData = "";

            if(!file_exists($geometryPath)){
                Server::getInstance()->getLogger()->info(TextFormat::colorize("&l&cBoss&8 >&6 Model &a{$modelId}&6 không tồn tại geometry. Dùng geometry default"));
            }else {
                $geometryData = file_get_contents($geometryPath);
            }

            $skin = new Skin($modelId, $skinData, "", "", $geometryData);

            Server::getInstance()->getLogger()->info(TextFormat::colorize("&l&cBoss&8 >&6 Load skin của boss&a {$config->get("id")}&6 thành công!"));

            //Rewards resolver

            $drops = [];

            foreach ($config->get("drops") as $top => $rewards){
                $drops[$top][Boss::COMMANDS_REWARDS] = $rewards["commands"];
                $chance = 0;
                foreach ($rewards["items"] as $itemData){
                    $item = ItemFactory::get($itemData[0], $itemData[1], $itemData[2]);
                    $item->setCustomName(TextFormat::colorize("&r{$itemData[3]}"));
                    $lores = explode("\n", $itemData[4]);
                    foreach ($lores as $id => $lore){
                        $lores[$id] = TextFormat::colorize($lore);
                    }
                    $item->setLore($lores);
                    foreach($itemData[5] as $id => $level){
                        $enchantment = new EnchantmentInstance(Enchantment::getEnchantment($id), $level);
                        $item->addEnchantment($enchantment);
                    }
                    $drops[$top][Boss::ITEMS_REWARDS][] = [$item, $itemData[6]];
                    $chance += $itemData[6];
                }
                if($chance !== 100){
                    $forceSkip = true;
                    Server::getInstance()->getLogger()->info(TextFormat::colorize("&l&cBoss&8 >&6 Tổng tỉ lệ của drops chưa bằng 100. Tự động skip boss"));
                }
            }
            if($forceSkip) {
                continue;
            }

            Server::getInstance()->getLogger()->info(TextFormat::colorize("&l&cBoss&8 >&6 Load drops của boss&a {$config->get("id")}&6 thành công!"));

            $this->bosses[$config->get("id")] = new Boss(
                $config->get("id"),
                $config->get("name"),
                $config->get("bossnametag"),
                $config->get("health"),
                $config->get("speed"),
                $config->get("damage"),
                $config->get("scale"),
                $drops,
                $position,
                $skin,
                $config->get("time"),
                $config->get("radius"),
                $config->get("attackSpeed"),
            );
        }
    }

    public function onDisable()
    {
        foreach($this->getServer()->getLevels() as $level){
            foreach ($level->getEntities() as $entity){
                if($entity instanceof Model){
                    $entity->kill(); //Bad
                }
            }
        }
    }
}
