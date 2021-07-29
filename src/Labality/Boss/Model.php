<?php

declare(strict_types=1);

namespace Labality\Boss;

use InvalidArgumentException;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class Model extends Human
{

    private ?Boss $boss = null;

    private int $knockbackTimer = 0;

    private int $attackDelay = 0;

    private array $damageLogging = [];


    public function __construct(Level $level, CompoundTag $nbt)
    {
        parent::__construct($level, $nbt);
    }

    /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
    public function initEntity(): void
    {
        parent::initEntity();
        if(!$this->namedtag->hasTag("bossId", StringTag::class)){
            throw new InvalidArgumentException("This entity should not be summoned directly.");
        }

        $this->boss = Main::getInstance()->getBoss($this->namedtag->getString("bossId"));

        if($this->boss === null){
            $this->kill();
            return;
        }

        $this->setNameTagAlwaysVisible();

        $this->setMaxHealth($this->boss->getHealth());
        $this->setHealth($this->boss->getHealth());

        $this->setScale($this->boss->getScale());

        $this->setSkin($this->boss->getSkin());

        $this->setNameTag(TextFormat::colorize(str_replace(["{name}", "{health}", "{maxhealth}"], [$this->boss->getName(), $this->getHealth(), $this->getMaxHealth()], $this->boss->getNametag())));
    }

    //Copy pasta

    public function getNearestEntity(Entity $target, float $maxDistance, string $entityType = Entity::class, bool $includeDead = false) : ?Entity{
        assert(is_a($entityType, Entity::class, true));

        $minX = ((int) floor($target->x - $maxDistance)) >> 4;
        $maxX = ((int) floor($target->x + $maxDistance)) >> 4;
        $minZ = ((int) floor($target->z - $maxDistance)) >> 4;
        $maxZ = ((int) floor($target->z + $maxDistance)) >> 4;

        $currentTargetDistSq = $maxDistance ** 2;

        $currentTarget = null;

        for($x = $minX; $x <= $maxX; ++$x){
            for($z = $minZ; $z <= $maxZ; ++$z){
                foreach((($______chunk = $this->getLevel()->getChunk($x,  $z)) !== null ? $______chunk->getEntities() : []) as $entity){
                    if(!($entity instanceof $entityType) or $entity->isClosed() or $entity->isFlaggedForDespawn() or (!$includeDead and !$entity->isAlive()) or $entity === $target){
                        continue;
                    }
                    $distSq = $entity->distanceSquared($target);
                    if($distSq < $currentTargetDistSq){
                        $currentTargetDistSq = $distSq;
                        $currentTarget = $entity;
                    }
                }
            }
        }
        return $currentTarget;
    }

    public function entityBaseTick(int $tickDiff = 1): bool
    {
        if(!Server::getInstance()->isRunning()) $this->close();
        $hasUpdated = parent::entityBaseTick($tickDiff);

        $radius = $this->boss->getRadius();

        //Check if boss still in radius of the spawn

        if($this->distance($this->boss->getPosition()) > $radius){
            $this->teleport($this->boss->getPosition());
        }

        //Check if target still in the game or in boss radius
        $target = $this->getTargetEntity();
        if($target == null || $target->isClosed() || $this->distanceSquared($target) > $radius) {
            //If not, fletching the new target
            $target = $this->getNearestEntity($this, $this->boss->getRadius(), Human::class, false);
            if($target == null) return $hasUpdated;
            $this->setTargetEntity($target);
        }

        if($this->knockbackTimer !== 0){
            $this->knockbackTimer--;
        }else{
            $distanced = $target->subtract($this);
            if($distanced->getX() ** 2 + $distanced->getZ() ** 2 < 0.7) {
                $this->motion->x = 0;
                $this->motion->z = 0;
            }else {
                $diff = abs($distanced->getX()) + abs($distanced->getZ());
                $this->motion->x = $this->boss->getSpeed() * 0.15 * ($distanced->getX() / $diff);
                $this->motion->z = $this->boss->getSpeed() * 0.15 * ($distanced->getZ() / $diff);
            }
            $this->lookAt($target->add(0, $target->getEyeHeight(), 0));
            $this->move($this->motion->x, $this->motion->y, $this->motion->z);
            if($this->distance($target) < $this->boss->getScale() and $this->attackDelay > $this->boss->getAttackSpeed()) {
                $this->attackDelay = 0;
                $target->attack(new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->boss->getDamage()));
            }else{
                $this->attackDelay++;
            }
        }
        return $hasUpdated;
    }

    public function attack(EntityDamageEvent $source): void{
        parent::attack($source);
        if((int)$source->getFinalDamage() === 0){
            echo "a";
            return;
        }
        $this->setNameTag(TextFormat::colorize(str_replace(["{name}", "{health}", "{maxhealth}"], [$this->boss->getName(), $this->getHealth(), $this->getMaxHealth()], $this->boss->getNametag())));
        if($source instanceof EntityDamageByEntityEvent || $source instanceof EntityDamageByChildEntityEvent){
            if(!$source->getDamager() instanceof Player){
                return;
            }
            $this->knockbackTimer = 10;
            if(isset($this->damageLogging[$source->getDamager()->getName()])){
                $this->damageLogging[$source->getDamager()->getName()] += $source->getFinalDamage();
            }else{
                $this->damageLogging[$source->getDamager()->getName()] = $source->getFinalDamage();
            }
            $source->getDamager()->sendMessage(TextFormat::colorize(str_replace(["{name}", "{damage}"], [$this->boss->getName(), $source->getFinalDamage()], Main::getInstance()->getConfig()->get("dealDamageToBoss"))));
        }
    }

    public function onDeath(): void
    {
        if($this->boss === null){
            return;
        }
        parent::onDeath();
        /** @var Player $player */
        foreach ($this->damageLogging as $name => $damage){
            $player = Server::getInstance()->getPlayerExact($name);
            if($player == null || $player->isClosed() || !$player->isConnected()){
                //Remove the player from array
                unset($this->damageLogging[$name]);
            }
        }

        arsort($this->damageLogging);
        $sliced = array_slice($this->damageLogging, 0, count($this->boss->getDrops()));
        $result = [];
        foreach ($sliced as $name => $damage){
            $player = Server::getInstance()->getPlayerExact($name);
            $result[] = [$player, $damage];
        }

        $message = str_replace("{boss_name}", $this->boss->getName(), Main::getInstance()->getConfig()->get("bestDamagerMessageHeader")) . "\n";
        foreach ($result as $i => $data){
            //oops
            $i++;
            $rewards = $this->boss->getDrops()[$i];
            $player = $data[0];
            $message .= str_replace(["{index}", "{name}", "{damage}"], [$i, $player->getName(), $data[1]], Main::getInstance()->getConfig()->get("bestDamagerMessageList")) . "\n";

            $itemsList = [];
            foreach ($rewards[Boss::ITEMS_REWARDS] as $index => $items){
                $itemsList[$index] = $items[1];
            }

            var_dump($itemsList);

            $randomizedItem = $rewards[Boss::ITEMS_REWARDS][ChanceLibrary::getRandomWeightedElement($itemsList)][0];

            var_dump($randomizedItem);

            if(!$player->getInventory()->canAddItem($randomizedItem)){
                $player->sendMessage(TextFormat::colorize(Main::getInstance()->getConfig()->get("inventoryFull")));
            }
            $player->getInventory()->addItem($randomizedItem);

            foreach($rewards[Boss::COMMANDS_REWARDS] as $command){
                Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), str_replace("{player}", $player->getName(), $command));
            }

            $pk = new PlaySoundPacket();
            $pk->soundName = "random.levelup";
            $pk->x = $player->x;
            $pk->y = $player->y;
            $pk->z = $player->z;
            $pk->volume = 1;
            $pk->pitch = 1;
            Server::getInstance()->broadcastPacket([$player], $pk);
        }
        Server::getInstance()->broadcastMessage(TextFormat::colorize($message));
        $this->boss->setAlive(false);
        $pk = new PlaySoundPacket();
        $pk->soundName = "mob.enderdragon.death";
        $pk->x = $this->getPosition()->x;
        $pk->y = $this->getPosition()->y;
        $pk->z = $this->getPosition()->z;
        $pk->volume = 8;
        $pk->pitch = 1;
        Server::getInstance()->broadcastPacket(Server::getInstance()->getOnlinePlayers(), $pk);
    }
}