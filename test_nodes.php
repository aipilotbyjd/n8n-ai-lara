<?php

require_once 'vendor/autoload.php';

try {
    echo "Testing ScheduleTriggerNode...\n";
    $node = new App\Nodes\Core\ScheduleTriggerNode();
    echo "✓ ScheduleTriggerNode: " . $node->getName() . "\n";

    echo "Testing DataTransformationNode...\n";
    $node = new App\Nodes\Core\DataTransformationNode();
    echo "✓ DataTransformationNode: " . $node->getName() . "\n";

    echo "Testing SwitchNode...\n";
    $node = new App\Nodes\Core\SwitchNode();
    echo "✓ SwitchNode: " . $node->getName() . "\n";

    echo "Testing LoopNode...\n";
    $node = new App\Nodes\Core\LoopNode();
    echo "✓ LoopNode: " . $node->getName() . "\n";

    echo "\nAll nodes loaded successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
