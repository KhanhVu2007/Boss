<?php

declare(strict_types=1);

namespace Labality\Boss;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class BossTask extends Task
{

    private Boss $boss;
    private int $counter = 0;

    public function __construct(Boss $boss)
    {
        $this->boss = $boss;
    }

    /**
     * @inheritDoc
     */
    public function onRun(int $currentTick)
    {
        if($this->boss->isAlive()) return;
        if($this->counter >= $this->boss->getSpawnTime()){
            $this->boss->setAlive();
            $this->boss->spawn();
            $this->counter = 0;
            Server::getInstance()->broadcastMessage(TextFormat::colorize(str_replace("{name}", $this->boss->getName(),Main::getInstance()->getConfig()->get("bossSpawn"))));
        }else{
            $this->counter++;
        }
    }
}