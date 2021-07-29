<?php

declare(strict_types=1);

namespace Labality\Boss;

use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\level\Position;
use pocketmine\nbt\tag\ByteArrayTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class Boss
{


    private string $name;

    private int $health;

    private float $speed;

    private float $damage;

    private float $scale;

    private array $drops;

    private Position $position;

    private Skin $skin;

    public const ITEMS_REWARDS = "items";
    public const COMMANDS_REWARDS = "commands";

    private bool $alive = false;

    private int $spawnTime;

    private Task $task;
    private float $radius;

    private int $attackSpeed;

    private string $id;

    private string $nametag;

    public function __construct(string $id, string $name, string $nametag, int $health, float $speed, float $damage, float $scale, array $drops, Position $position, Skin $skin, int $spawnTime, float $radius, int $attackSpeed)
    {
        $this->id = $id;
        $this->name = $name;
        $this->health = $health;
        $this->speed = $speed;
        $this->damage = $damage;
        $this->scale = $scale;
        $this->position = $position;
        $this->drops = $drops;
        $this->skin = $skin;
        $this->spawnTime = $spawnTime;
        $this->radius = $radius;
        $this->attackSpeed = $attackSpeed;
        $this->nametag = $nametag;
        $this->task = new BossTask($this);
        Main::getInstance()->getScheduler()->scheduleRepeatingTask($this->task, 20);
        Server::getInstance()->getLogger()->alert(base64_decode("QuG6oW4gxJHDoyBi4buLIGzhu6thIG7hur91IHBo4bqjaSB0cuG6oyB0aeG7gW4gxJHhu4MgbXVhIHBsdWdpbiBuw6B5ISBMacOqbiBo4buHOiBodHRwczovL3d3dy5mYWNlYm9vay5jb20vbGFiYWxpdHkvIMSR4buDIHThu5EgY8Ohbw=="));
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getHealth(): int
    {
        return $this->health;
    }

    /**
     * @return float
     */
    public function getSpeed(): float
    {
        return $this->speed;
    }

    /**
     * @return float
     */
    public function getDamage(): float
    {
        return $this->damage;
    }

    /**
     * @return float
     */
    public function getScale(): float
    {
        return $this->scale;
    }

    /**
     * @return array
     */
    public function getDrops(): array
    {
        return $this->drops;
    }

    /**
     * @return Skin
     */
    public function getSkin(): Skin
    {
        return $this->skin;
    }

    /**
     * @return Position
     */
    public function getPosition(): Position
    {
        return $this->position;
    }

    /**
     * @return bool
     */
    public function isAlive(): bool
    {
        return $this->alive;
    }

    /**
     * @param bool $alive
     */
    public function setAlive(bool $alive = true): void
    {
        $this->alive = $alive;
    }

    /**
     * @return BossTask|Task
     */
    public function getTask()
    {
        return $this->task;
    }

    /**
     * @return int
     */
    public function getSpawnTime(): int
    {
        return $this->spawnTime;
    }

    /**
     * @return float
     */
    public function getRadius(): float
    {
        return $this->radius;
    }

    public function spawn(){
        $nbt = Entity::createBaseNBT($this->getPosition(), null, 0, 0);
        $skinNamedTag = new CompoundTag("Skin", [
            new StringTag("Name", $this->getSkin()->getSkinId()),
            new ByteArrayTag("Data", $this->getSkin()->getSkinData()),
            new ByteArrayTag("CapeData", $this->getSkin()->getCapeData()),
            new StringTag("GeometryName", $this->getSkin()->getGeometryName()),
            new ByteArrayTag("GeometryData", $this->getSkin()->getGeometryData())
        ]);
        $nbt->setString("bossId", $this->getId());
        $nbt->setTag($skinNamedTag);
        (new Model($this->getPosition()->getLevel(), $nbt))->spawnToAll();
        $pk = new PlaySoundPacket();
        $pk->soundName = "mob.enderdragon.growl";
        $pk->x = $this->getPosition()->x;
        $pk->y = $this->getPosition()->y;
        $pk->z = $this->getPosition()->z;
        $pk->volume = 8;
        $pk->pitch = 1;
        Server::getInstance()->broadcastPacket(Server::getInstance()->getOnlinePlayers(), $pk);
    }

    /**
     * @return int
     */
    public function getAttackSpeed(): int
    {
        return $this->attackSpeed;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getNametag(): string
    {
        return $this->nametag;
    }
}
