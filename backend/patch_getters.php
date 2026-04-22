<?php
$file = 'backend/src/Entity/ConfigAtelier.php';
$content = file_get_contents($file);

$insert = <<<PHP

    public function getDureeDefautMandatJours(): ?int
    {
        return \$this->dureeDefautMandatJours;
    }

    public function setDureeDefautMandatJours(int \$dureeDefautMandatJours): static
    {
        \$this->dureeDefautMandatJours = \$dureeDefautMandatJours;
        return \$this;
    }

    public function getTypeCommissionDepotVente(): ?string
    {
        return \$this->typeCommissionDepotVente;
    }

    public function setTypeCommissionDepotVente(string \$typeCommissionDepotVente): static
    {
        \$this->typeCommissionDepotVente = \$typeCommissionDepotVente;
        return \$this;
    }
PHP;

$content = preg_replace(
    '/(}\s*)$/',
    $insert . "\n$1",
    $content
);

file_put_contents($file, $content);
