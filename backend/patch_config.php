<?php
$file = 'backend/src/Entity/ConfigAtelier.php';
$content = file_get_contents($file);

$insert = <<<PHP
    #[ORM\Column(options: ['default' => 90])] #[Groups(['config:read', 'config:write'])] private int \$dureeDefautMandatJours = 90;
    #[ORM\Column(length: 20, options: ['default' => 'pourcentage'])] #[Groups(['config:read', 'config:write'])] private string \$typeCommissionDepotVente = 'pourcentage';

PHP;

$content = preg_replace(
    '/(private array \$datesFermetureExceptionnelles = \[\];)/',
    "$1\n$insert",
    $content
);

file_put_contents($file, $content);
