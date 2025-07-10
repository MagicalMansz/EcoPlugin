<?php

/**
 * Plugin: EcoBoost
 * Author: MagicalMansz
 * Description: Temporarily boosts ore drops with /eco, stackable and stoppable.
 */

namespace EcoBoost;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\ClosureTask;
use pocketmine\player\Player;

class Main extends PluginBase implements Listener {

    private int $boostPercent = 0;
    private int $endTime = 0;
    private string $booster = "Unknown";

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("EcoBoost by MagicalMansz enabled.");
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            $this->sendBoostInfo();
        }), 20); // Every 1 second
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (strtolower($command->getName()) === "eco") {
            if (!isset($args[0])) {
                $sender->sendMessage("§cUsage: /eco <percent> [seconds] | stack <percent> | stop");
                return true;
            }

            switch (strtolower($args[0])) {
                case "stop":
                    $this->boostPercent = 0;
                    $this->endTime = 0;
                    $this->booster = "Unknown";
                    $this->getServer()->broadcastMessage("§c[EcoBoost] All eco boosts have been stopped.");
                    return true;

                case "stack":
                    if (!isset($args[1]) || !is_numeric($args[1])) {
                        $sender->sendMessage("§cUsage: /eco stack <percent>");
                        return true;
                    }
                    $amount = (int)$args[1];
                    $this->boostPercent += $amount;
                    $this->endTime = time() + 300;
                    $this->booster = $sender instanceof Player ? $sender->getName() : "Console";
                    $this->getServer()->broadcastMessage("§a[EcoBoost] §b{$this->booster} §astacked §e{$amount}%§a. Total Boost: §e{$this->boostPercent}%");
                    return true;

                default:
                    if (!is_numeric($args[0])) {
                        $sender->sendMessage("§cUsage: /eco <percent> [seconds]");
                        return true;
                    }
                    $percent = (int)$args[0];
                    $duration = isset($args[1]) && is_numeric($args[1]) ? (int)$args[1] : 300;

                    $this->boostPercent = $percent;
                    $this->endTime = time() + $duration;
                    $this->booster = $sender instanceof Player ? $sender->getName() : "Console";

                    $this->getServer()->broadcastMessage("§a[EcoBoost] §e{$percent}% §aboost activated by §b{$this->booster} §afor §e{$duration} §aseconds!");
                    return true;
            }
        }
        return false;
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        if ($this->boostPercent <= 0 || time() > $this->endTime) return;

        $block = $event->getBlock();
        $ores = [
            VanillaBlocks::COAL_ORE(),
            VanillaBlocks::IRON_ORE(),
            VanillaBlocks::GOLD_ORE(),
            VanillaBlocks::DIAMOND_ORE(),
            VanillaBlocks::EMERALD_ORE(),
            VanillaBlocks::REDSTONE_ORE(),
            VanillaBlocks::LAPIS_LAZULI_ORE(),
        ];

        foreach ($ores as $ore) {
            if ($block->getTypeId() === $ore->getTypeId()) {
                $drops = $block->getDrops($event->getItem());
                $event->setDrops([]);
                foreach ($drops as $drop) {
                    $base = $drop->getCount();
                    $boost = (int)ceil($base * (1 + $this->boostPercent / 100));
                    $drop->setCount($boost);
                    $block->getPosition()->getWorld()->dropItem($block->getPosition()->add(0.5, 0.5, 0.5), $drop);
                }
                break;
            }
        }
    }

    private function sendBoostInfo(): void {
        if ($this->boostPercent <= 0 || time() > $this->endTime) return;

        $timeLeft = $this->endTime - time();
        $minutes = intdiv($timeLeft, 60);
        $seconds = $timeLeft % 60;
        $formatted = sprintf("%02d:%02d", $minutes, $seconds);

        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $player->sendPopup("§a[EcoBoost] §e{$this->boostPercent}% by §b{$this->booster} §7(Ends in §f{$formatted}§7)");
        }
    }
}
