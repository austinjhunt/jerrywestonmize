<?php declare(strict_types = 1);
namespace MailPoet\EmailEditor\Integrations\Core\Renderer\Blocks;
if (!defined('ABSPATH')) exit;
use MailPoet\EmailEditor\Engine\SettingsController;
class Fallback extends AbstractBlockRenderer {
 protected function renderContent($blockContent, array $parsedBlock, SettingsController $settingsController): string {
 return $blockContent;
 }
}
