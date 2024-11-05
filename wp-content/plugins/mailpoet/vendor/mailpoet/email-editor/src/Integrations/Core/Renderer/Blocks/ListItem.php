<?php declare(strict_types = 1);
namespace MailPoet\EmailEditor\Integrations\Core\Renderer\Blocks;
if (!defined('ABSPATH')) exit;
use MailPoet\EmailEditor\Engine\SettingsController;
class ListItem extends AbstractBlockRenderer {
 protected function addSpacer($content, $emailAttrs): string {
 return $content;
 }
 protected function renderContent($blockContent, array $parsedBlock, SettingsController $settingsController): string {
 return $blockContent;
 }
}
