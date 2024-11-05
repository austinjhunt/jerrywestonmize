<?php declare(strict_types = 1);
namespace MailPoet\EmailEditor\Engine\Renderer\ContentRenderer\Preprocessors;
if (!defined('ABSPATH')) exit;
class SpacingPreprocessor implements Preprocessor {
 public function preprocess(array $parsedBlocks, array $layout, array $styles): array {
 $parsedBlocks = $this->addBlockGaps($parsedBlocks, $styles['spacing']['blockGap'] ?? '', null);
 return $parsedBlocks;
 }
 private function addBlockGaps(array $parsedBlocks, string $gap = '', $parentBlock = null): array {
 foreach ($parsedBlocks as $key => $block) {
 $parentBlockName = $parentBlock['blockName'] ?? '';
 // Ensure that email_attrs are set
 $block['email_attrs'] = $block['email_attrs'] ?? [];
 if ($parentBlock && $key !== 0 && $gap && $parentBlockName !== 'core/buttons') {
 $block['email_attrs']['margin-top'] = $gap;
 }
 $block['innerBlocks'] = $this->addBlockGaps($block['innerBlocks'] ?? [], $gap, $block);
 $parsedBlocks[$key] = $block;
 }
 return $parsedBlocks;
 }
}
