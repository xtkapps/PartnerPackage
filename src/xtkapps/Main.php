<?php
/*
 * ┏━━━━━━━━━━━━━━━━━━━━━━━━━━┓
 * ┃ Plugin created by xtkapps ┃
 * ┃ YouTube: @FastCr4cked       ┃
 * ┃ Date: 16/5/25 11:14 p.m.    ┃
 * ┗━━━━━━━━━━━━━━━━━━━━━━━━━━┛
 */

namespace xtkapps;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\TextFormat;
use pocketmine\world\sound\XpCollectSound;

class Main extends PluginBase implements Listener {

    private Config $rewards;

    public function onEnable(): void {
        $this->saveDefaultConfig();
        @mkdir($this->getDataFolder());
        $this->rewards = new Config($this->getDataFolder() . "rewards.yml", Config::YAML);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("Any errors please report them to my discord: xtkapps");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "The plugin only works in-game, so don't be lazy when you get in");
            return true;
        }

        if (empty($args)) {
            $sender->sendMessage(TextFormat::RED . "Usage: /pp <setcontent|get|all>");
            return true;
        }

        switch (strtolower($args[0])) {
            case "setcontent":
                $items = [];
                foreach ($sender->getInventory()->getContents() as $item) {
                    $items[] = base64_encode(serialize($item->nbtSerialize()));
                }
                $this->rewards->set("items", $items);
                $this->rewards->save();
                $sender->sendMessage(TextFormat::GREEN . "Rewards have been saved successfully");
                break;

            case "get":
                $amount = isset($args[1]) && is_numeric($args[1]) ? (int)$args[1] : 1;
                $package = VanillaBlocks::ENDER_CHEST()->asItem();
                $package->setCustomName(TextFormat::DARK_PURPLE . "Partner Package");
                $package->setCount($amount);
                $package->getNamedTag()->setByte("ppackage", 1);
                if (!$sender->getInventory()->canAddItem($package)) {
                    $sender->sendMessage(TextFormat::RED . "You don't have enough space in your inventory");
                    return true;
                }
                $sender->getInventory()->addItem($package);
                $sender->sendMessage(TextFormat::GREEN . "You received $amount Partner Package");
                break;

            case "all":
                if (!isset($args[1]) || !is_numeric($args[1])) {
                    $sender->sendMessage(TextFormat::RED . "Usage: /pp all <amount>");
                    return true;
                }
                $amount = (int)$args[1];
                $package = VanillaBlocks::ENDER_CHEST()->asItem();
                $package->setCustomName(TextFormat::DARK_PURPLE . "Partner Package");
                $package->setCount($amount);
                $package->getNamedTag()->setByte("ppackage", 1);

                foreach ($this->getServer()->getOnlinePlayers() as $player) {
                    if ($player->getInventory()->canAddItem($package)) {
                        $player->getInventory()->addItem(clone $package);
                        $player->sendMessage(TextFormat::GREEN . "You received $amount Partner Package");
                    } else {
                        $player->sendMessage(TextFormat::RED . "You have no space for the Partner Package");
                    }
                }
                $sender->sendMessage(TextFormat::GREEN . "All online players received $amount Partner Package");
                break;

            default:
                $sender->sendMessage(TextFormat::RED . "Unknown subcommand! Use: /pp <setcontent|get|all>");
                break;
        }

        return true;
    }

    public function onInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();

        if (!$item->getNamedTag()->getTag("ppackage")) {
            return;
        }

        $event->cancel();

        $storedItems = $this->rewards->get("items", []);
        if (empty($storedItems)) {
            $player->sendMessage(TextFormat::RED . "No rewards have been set yet");
            return;
        }

        $randomEncodedItem = $storedItems[array_rand($storedItems)];
        $reward = Item::nbtDeserialize(unserialize(base64_decode($randomEncodedItem)));

        if (!$player->getInventory()->canAddItem($reward)) {
            $player->sendMessage(TextFormat::RED . "Your inventory is full!");
            return;
        }

        $inventory = $player->getInventory();
        $slot = $inventory->getHeldItemIndex();
        $current = $inventory->getItem($slot);

        if ($current->getCount() > 1) {
            $current->setCount($current->getCount() - 1);
            $inventory->setItem($slot, $current);
        } else {
            $inventory->setItem($slot, VanillaItems::AIR());
        }

        $pos = $player->getPosition();
        $player->getWorld()->addSound($pos, new XpCollectSound());

        $inventory->addItem($reward);

        $rewardName = $reward->hasCustomName() ? $reward->getCustomName() : $reward->getName();
        $player->sendMessage(TextFormat::GRAY . "You received a " . TextFormat::YELLOW . $rewardName);
    }
}
