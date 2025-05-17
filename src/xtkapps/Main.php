<?php
/**
 * Plugin created by xtkapps.
 * Using the PHP programming language
 * YouTube: @FastCr4cked
 * Date: 16/5/25 11:14 p. m.
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
use pocketmine\block\VanillaBlocks;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat;
use pocketmine\world\sound\ExplodeSound;
use pocketmine\world\particle\HugeExplodeSeedParticle;

class Main extends PluginBase implements Listener {

    private Config $rewards;

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
        $this->rewards = new Config($this->getDataFolder() . "rewards.yml", Config::YAML);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("[Warning] This command can only be used within the game");
            return true;
        }

        if (!isset($args[0])) {
            $sender->sendMessage("§l§dPP §r§7» §cThe commands are /pp <setcontent|get|all>");
            return true;
        }

        switch ($args[0]) {
            case "setcontent":
                $items = array_map(function(Item $item) {
                    return base64_encode(serialize($item->nbtSerialize()));
                }, $sender->getInventory()->getContents());
                $this->rewards->set("items", $items);
                $this->rewards->save();
                $sender->sendMessage("§l§dPP §r§7» §aRewards saved.");
                break;

            case "get":
                $amount = isset($args[1]) ? (int)$args[1] : 1;
                $item = VanillaBlocks::ENDER_CHEST()->asItem();
                $item->setCustomName("§r§k§r§dPartner Package§r§k§d§r");
                $item->setCount($amount);
                $item->getNamedTag()->setByte("ppackage", 1);
                $sender->getInventory()->addItem($item);
                $sender->sendMessage("§l§dPP §r§7» §aYou just received $amount Partner Package(s).");
                break;

            case "all":
                if (!isset($args[1]) || !is_numeric($args[1])) {
                    $sender->sendMessage("§l§dPP §r§7»§c It's done like this: /pp all <cantidad>");
                    return true;
                }

                $count = (int)$args[1];
                $item = VanillaBlocks::ENDER_CHEST()->asItem();
                $item->setCustomName("§r§k§r§dPartner Package§r§k§d§r");
                $item->setCount($count);
                $item->getNamedTag()->setByte("ppackage", 1);

                foreach ($this->getServer()->getOnlinePlayers() as $player) {
                    $player->getInventory()->addItem(clone $item);
                }

                $sender->sendMessage("§l§dPP §r§7» §aYou gave it to everyone $count Partner Package.");
                break;

            default:
                $sender->sendMessage("§l§dPP §r§7» §cunknown command");
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

        $stored = $this->rewards->get("items", []);
        if (empty($stored)) {
            $player->sendMessage("§cThere are no rewards set.");
            return;
        }

        $reward = Item::nbtDeserialize(unserialize(base64_decode($stored[array_rand($stored)])));

        if (!$player->getInventory()->canAddItem($reward)) {
            $player->sendMessage(TextFormat::colorize("&cYOU HAVE A FULL INVENTORY!"));
            return;
        }

        $item->pop();
        $player->getInventory()->setItemInHand($item);
        $player->getWorld()->addSound($player->getPosition(), new ExplodeSound());
        $player->getWorld()->addParticle($player->getPosition(), new HugeExplodeSeedParticle());
        $player->getInventory()->addItem($reward);
        $player->sendMessage(TextFormat::colorize("    §l§dPartnerPackage"));
        $player->sendMessage(TextFormat::colorize("&7-» &fyou have won &e" . ($reward->hasCustomName() ? $reward->getCustomName() : $reward->getName())));
    }
}
